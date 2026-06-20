<?php

namespace App\Domain\Planilla;

use App\Domain\Tributario\Renta5taService;
use App\Models\Attendance;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\ParametroPeriodo;
use App\Models\Payroll;
use App\Models\Periodo;
use App\Models\PolizaSctr;
use App\Models\PolizaVidaLey;
use Illuminate\Support\Facades\DB;

/**
 * Genera la planilla de un periodo: agrega la asistencia, ejecuta el motor de
 * cálculo por cada empleado y persiste payroll + payroll_details.
 * Cada empresa es independiente (SUNAT/SUNAFIL audita por separado).
 */
class PlanillaService
{
    public function __construct(
        private CalculadoraPlanilla $motor,
        private Renta5taService $renta5ta,
    ) {}

    public function generar(Periodo $periodo, ?int $generadoPor = null): Payroll
    {
        $param = ParametroPeriodo::vigenteEn($periodo->fecha_inicio)
            ?? ParametroPeriodo::orderByDesc('vigente_desde')->first();

        $diasBase = $param?->dias_base ?? 30;
        $diasPeriodo = $periodo->fecha_inicio->diffInDays($periodo->fecha_fin) + 1;

        $polizaSctr = PolizaSctr::where('vigente_desde', '<=', $periodo->fecha_inicio)
            ->orderByDesc('vigente_desde')->first();
        $polizaVida = PolizaVidaLey::where('vigente_desde', '<=', $periodo->fecha_inicio)
            ->orderByDesc('vigente_desde')->first();

        return DB::transaction(function () use ($periodo, $param, $diasBase, $diasPeriodo, $polizaSctr, $polizaVida, $generadoPor) {
            $payroll = Payroll::updateOrCreate(
                ['empresa_id' => $periodo->empresa_id, 'periodo_id' => $periodo->id],
                ['estado' => 'calculado', 'generado_por' => $generadoPor]
            );
            $payroll->detalles()->delete();

            $empleados = Employee::with('contratoVigente')
                ->where('empresa_id', $periodo->empresa_id)
                ->where('activo', true)
                ->get();

            $tot = ['ing' => 0, 'desc' => 0, 'neto' => 0, 'apo' => 0, 'n' => 0];

            foreach ($empleados as $emp) {
                $contrato = $emp->contratoVigente->first();
                if (! $contrato) {
                    continue;
                }

                $ag = $this->agregarAsistencia($emp->id, $periodo, $diasPeriodo);

                $asigFam = $contrato->percibe_asignacion_familiar ? (float) ($param?->asignacion_familiar ?? 0) : 0;

                $entrada = [
                    'sueldo_basico' => (float) $contrato->sueldo_basico,
                    'asignacion_familiar' => $asigFam,
                    'movilidad' => (float) $contrato->movilidad,
                    'dias_base' => $diasBase,
                    'dias_trabajados' => $ag['dias_trabajados'],
                    'minutos_tarde' => $ag['minutos_tarde'],
                    'horas_extra_25' => $ag['horas_extra_25'],
                    'horas_extra_35' => $ag['horas_extra_35'],
                    'sistema_pensiones' => $contrato->sistema_pensiones ?? 'ONP',
                    'afp' => $contrato->afp,
                    'tipo_afp' => $contrato->tipo_afp,
                    'aporta_sctr' => $contrato->aporta_sctr,
                    'aporta_senati' => $contrato->aporta_senati,
                    'aporta_essalud' => true,
                    'essalud_tasa' => 0.09,
                    'sctr_tasa_pension' => (float) ($polizaSctr->tasa_pension ?? 0),
                    'sctr_tasa_salud' => (float) ($polizaSctr->tasa_salud ?? 0),
                    'vida_ley_tasa' => (float) ($polizaVida->tasa ?? 0),
                    'senati_tasa' => 0,
                    'renta_5ta' => 0, // 5ta acumulada se aplica aparte cuando haya UIT confirmada
                    'fecha_periodo' => $periodo->fecha_inicio,
                ];

                $r = $this->motor->calcular($entrada);

                $payroll->detalles()->create([
                    'employee_id' => $emp->id,
                    'base_afecta' => $r['base_afecta'],
                    'total_ingresos' => $r['total_ingresos'],
                    'pension_total' => $r['descuentos']['pension']['total'],
                    'renta_5ta' => $r['descuentos']['renta_5ta'],
                    'total_descuentos' => $r['total_descuentos'],
                    'neto' => $r['neto'],
                    'essalud' => $r['aportes_empleador']['essalud'],
                    'sctr_pension' => $r['aportes_empleador']['sctr_pension'],
                    'sctr_salud' => $r['aportes_empleador']['sctr_salud'],
                    'vida_ley' => $r['aportes_empleador']['vida_ley'],
                    'senati' => $r['aportes_empleador']['senati'],
                    'desglose' => $r,
                ]);

                $aportes = array_sum($r['aportes_empleador']);
                $tot['ing'] += $r['total_ingresos'];
                $tot['desc'] += $r['total_descuentos'];
                $tot['neto'] += $r['neto'];
                $tot['apo'] += $aportes;
                $tot['n']++;
            }

            $payroll->update([
                'total_ingresos' => round($tot['ing'], 2),
                'total_descuentos' => round($tot['desc'], 2),
                'total_neto' => round($tot['neto'], 2),
                'total_aportes_empleador' => round($tot['apo'], 2),
                'cantidad_empleados' => $tot['n'],
            ]);

            return $payroll->fresh('detalles');
        });
    }

    /** Agrega la asistencia del empleado en el periodo. */
    private function agregarAsistencia(int $employeeId, Periodo $periodo, int $diasPeriodo): array
    {
        $registros = Attendance::where('employee_id', $employeeId)
            ->whereBetween('fecha', [$periodo->fecha_inicio->toDateString(), $periodo->fecha_fin->toDateString()])
            ->get();

        $minutosTarde = (int) $registros->sum('minutos_tarde');

        // No pagados: falta injustificada y licencia sin goce
        $noPagados = $registros->whereIn('estado', ['FALTA', 'LICENCIA_SIN_GOCE'])->count();

        // Días trabajados: si no hay registros, se asume periodo completo;
        // si hay, se descuentan los no pagados.
        $diasTrabajados = $registros->isEmpty()
            ? $diasPeriodo
            : max($diasPeriodo - $noPagados, 0);

        // Horas extra por tramo (25% primeras 2h/día, 35% el resto). v1: cuenta todas.
        $he25 = 0.0;
        $he35 = 0.0;
        foreach ($registros as $r) {
            $he = (float) $r->horas_extra;
            if ($he <= 0) {
                continue;
            }
            $he25 += min($he, 2);
            $he35 += max($he - 2, 0);
        }

        return [
            'dias_trabajados' => $diasTrabajados,
            'minutos_tarde' => $minutosTarde,
            'horas_extra_25' => $he25,
            'horas_extra_35' => $he35,
        ];
    }
}
