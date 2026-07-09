<?php

namespace App\Domain\Planilla;

use App\Domain\Tributario\Renta5taService;
use App\Models\Adelanto;
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

            // Modo de cálculo de la empresa: 'excel' (movilidad/adicionales NO afectos) o 'legal'.
            $modoCalculo = \App\Models\Empresa::find($periodo->empresa_id)?->modo_calculo ?? 'excel';

            $tot = ['ing' => 0, 'desc' => 0, 'neto' => 0, 'apo' => 0, 'n' => 0];

            foreach ($empleados as $emp) {
                $contrato = $emp->contratoVigente->first();
                if (! $contrato) {
                    continue;
                }

                $ag = $this->agregarAsistencia($emp->id, $periodo, $diasPeriodo);

                // Modalidad: 'honorarios' (RxH, renta 4ta) o 'planilla' (empleado, renta 5ta).
                $esHonorarios = ($emp->modalidad ?? 'planilla') === 'honorarios';

                // Los honorarios NO perciben asignación familiar (no son planilla).
                $asigFam = (! $esHonorarios && $contrato->percibe_asignacion_familiar)
                    ? (float) ($param?->asignacion_familiar ?? 0) : 0;

                $uit = (float) ($param?->uit ?? 0);
                $renta5ta = 0.0;

                if ($esHonorarios) {
                    // RRxHH = sueldo NETO: sin descuentos (no 8%, no AFP) ni aportes ni beneficios.
                    // SÍ aplican tardanzas, faltas, sábados y domingos/feriados (los maneja el
                    // motor por asistencia + adicionales). No hay retención.
                    $renta5ta = 0.0;
                } else {
                // Retención de Renta 5ta — método acumulado (igual que el Excel del contador):
                // proyección anual (12 sueldos + 2 gratificaciones + bonif. de ley 9%),
                // menos lo ya retenido en meses previos, repartido en los meses que faltan.
                // En periodos quincenales se retiene la mitad del mensual.
                // Si el cliente cargó la Renta 5ta calculada aparte, se respeta ese valor.
                $rentaManual = \App\Models\IngresoAdicional::where('empresa_id', $periodo->empresa_id)
                    ->where('employee_id', $emp->id)
                    ->where('anio', $periodo->anio)
                    ->where('mes', $periodo->mes)
                    ->where('quincena', $periodo->quincena)
                    ->value('renta_5ta_manual');

                if ($rentaManual !== null) {
                    $renta5ta = (float) $rentaManual;
                } elseif ($uit > 0) {
                    $remMensual = (float) $contrato->sueldo_basico + $asigFam;
                    // Gratificaciones (2) + bonificación extraordinaria de ley 9% sobre ellas.
                    $otrosAnuales = 2 * $remMensual * 1.09;
                    $retenidoPrevio = $this->retenidoPrevioAnio($periodo, $emp->id);
                    $mesesRestantes = max(12 - $periodo->mes + 1, 1);

                    $renta5taMensual = $this->renta5ta->retencionMensual(
                        $remMensual, $uit, $periodo->mes, $periodo->fecha_inicio,
                        otrosIngresosAnuales: $otrosAnuales,
                        retenidoPrevio: $retenidoPrevio,
                        mesesRestantes: $mesesRestantes
                    );

                    // La Renta 5ta se descuenta SOLO en la 2da quincena (o en planilla mensual).
                    // En la 1ra quincena no se retiene nada.
                    $renta5ta = $periodo->quincena == 1 ? 0.0 : $renta5taMensual;
                }
                } // fin else (planilla)

                // Adelantos / cuotas de préstamo a descontar en este periodo (mes).
                $adelantos = (float) Adelanto::where('empresa_id', $periodo->empresa_id)
                    ->where('employee_id', $emp->id)
                    ->where('anio', $periodo->anio)
                    ->where('mes', $periodo->mes)
                    ->sum('monto');

                // Ingresos adicionales aprobados por el supervisor (horas extra + bonos).
                // Las horas extra solo se pagan si el supervisor las APROBÓ; el bono siempre.
                $adic = \App\Models\IngresoAdicional::where('empresa_id', $periodo->empresa_id)
                    ->where('employee_id', $emp->id)
                    ->where('anio', $periodo->anio)
                    ->where('mes', $periodo->mes)
                    ->where('quincena', $periodo->quincena)
                    ->first();
                $montoHorasAprob = ($adic && $adic->aprobado) ? (float) $adic->monto_horas : 0.0;
                $sabadoAdic = $adic ? (float) $adic->sabado : 0.0;
                $domingoAdic = $adic ? (float) $adic->domingo_feriado : 0.0;
                $bonoAdic = $adic ? (float) $adic->bono : 0.0;
                $otrosAfectosAdic = $adic ? (float) $adic->otros_afectos : 0.0;

                $entrada = [
                    'modo' => $modoCalculo,
                    'sueldo_basico' => (float) $contrato->sueldo_basico,
                    'asignacion_familiar' => $asigFam,
                    // Honorarios: NO tienen movilidad (solo su honorario). Planilla: normal.
                    'movilidad' => $esHonorarios ? 0 : (float) $contrato->movilidad,
                    'dias_base' => $diasBase,
                    'dias_trabajados' => $ag['dias_trabajados'],
                    'minutos_tarde' => $ag['minutos_tarde'],
                    // H.E. APROBADAS del registro diario (con recargo 25%/35%). En modo 'excel'
                    // el motor las suma a la bolsa de movilidad (no afectas). Si la fuente es
                    // "resumen" estos vienen en 0 y las H.E. llegan por Adicionales (monto).
                    'horas_extra_25' => $ag['horas_extra_25'] ?? 0,
                    'horas_extra_35' => $ag['horas_extra_35'] ?? 0,
                    // Bolsa de movilidad del cliente: sábados + domingos + horas extra + incentivo
                    'mov_horas_extra' => $montoHorasAprob, // solo si el supervisor aprobó
                    'mov_sabado' => $sabadoAdic,
                    'mov_domingo' => $domingoAdic,
                    'mov_incentivo' => $bonoAdic,
                    // Ingreso afecto adicional (col. AG "otros pensionables/incentivos" del cliente)
                    'otros_afectos' => $otrosAfectosAdic,
                    // Honorarios (RxH): sin pensión ni aportes de empleador. Planilla: normal.
                    // Si el contrato no define sistema, se asume ONP; 'NINGUNO' = exonerado.
                    'sistema_pensiones' => $esHonorarios ? 'NINGUNO' : ($contrato->sistema_pensiones ?: 'ONP'),
                    'afp' => $esHonorarios ? null : $contrato->afp,
                    'tipo_afp' => $esHonorarios ? null : $contrato->tipo_afp,
                    'aporta_sctr' => $esHonorarios ? false : $contrato->aporta_sctr,
                    'aporta_senati' => $esHonorarios ? false : $contrato->aporta_senati,
                    'aporta_essalud' => $esHonorarios ? false : true,
                    'essalud_tasa' => 0.09,
                    'sctr_tasa_pension' => $esHonorarios ? 0 : (float) ($polizaSctr->tasa_pension ?? 0),
                    'sctr_tasa_salud' => $esHonorarios ? 0 : (float) ($polizaSctr->tasa_salud ?? 0),
                    'vida_ley_tasa' => $esHonorarios ? 0 : (float) ($polizaVida->tasa ?? 0),
                    'senati_tasa' => 0,
                    'renta_5ta' => $renta5ta,
                    'adelantos' => $adelantos,
                    'fecha_periodo' => $periodo->fecha_inicio,
                ];

                $r = $this->motor->calcular($entrada);

                // Monto de las faltas (días no trabajados × valor día) para mostrarlo como descuento.
                $valorDia = ((float) $contrato->sueldo_basico + $asigFam) / $diasBase;
                $descFaltas = round($valorDia * ($ag['faltas'] ?? 0), 2);

                // Resumen de asistencia para mostrarlo en el detalle/boleta.
                $r['asistencia'] = [
                    'dias_trabajados' => $ag['dias_trabajados'],
                    'dias_periodo' => $diasPeriodo,
                    'faltas' => $ag['faltas'] ?? 0,
                    'minutos_tarde' => $ag['minutos_tarde'],
                    'horas_extra' => round(($ag['horas_extra_25'] ?? 0) + ($ag['horas_extra_35'] ?? 0), 2),
                    'descuento_faltas' => $descFaltas,
                    'remuneracion_periodo' => round($r['ingresos']['remuneracion_devengada'] + $descFaltas, 2),
                    'fuente' => $ag['fuente'] ?? 'diario',
                ];

                $payroll->detalles()->create([
                    'employee_id' => $emp->id,
                    // Se congela aquí: si el trabajador cambia de modalidad después,
                    // este periodo ya calculado no debe reclasificarse solo.
                    'modalidad' => $esHonorarios ? 'honorarios' : 'planilla',
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

    /**
     * Suma de la Renta 5ta ya retenida a un trabajador en MESES ANTERIORES del mismo año
     * (incluye ambas quincenas de cada mes previo). No cuenta el mes actual, para que en
     * planillas quincenales las dos quincenas del mes no se resten entre sí.
     */
    private function retenidoPrevioAnio(Periodo $periodo, int $employeeId): float
    {
        return (float) \App\Models\PayrollDetail::where('employee_id', $employeeId)
            ->whereHas('payroll.periodo', function ($q) use ($periodo) {
                $q->where('empresa_id', $periodo->empresa_id)
                    ->where('anio', $periodo->anio)
                    ->where('mes', '<', $periodo->mes);
                // Solo el mismo tipo de planilla, para no mezclar mensual con quincenal.
                $periodo->quincena ? $q->whereNotNull('quincena') : $q->whereNull('quincena');
            })
            ->sum('renta_5ta');
    }

    /** Agrega la asistencia del empleado en el periodo. */
    private function agregarAsistencia(int $employeeId, Periodo $periodo, int $diasPeriodo): array
    {
        // 1) Si existe un CUADRO RESUMEN importado para este periodo, es la fuente exacta.
        $resumen = \App\Models\AsistenciaResumen::where('empresa_id', $periodo->empresa_id)
            ->where('employee_id', $employeeId)
            ->where('anio', $periodo->anio)
            ->where('mes', $periodo->mes)
            ->where('quincena', $periodo->quincena)
            ->first();

        if ($resumen) {
            // Las horas extra NO se toman aquí: fluyen por IngresoAdicional (pantalla #7),
            // que ya las aprueba y valoriza. Así se evita contarlas dos veces.
            return [
                'dias_trabajados' => (float) $resumen->dias_trabajados,
                'minutos_tarde' => (int) $resumen->tardanza_min,
                'horas_extra_25' => 0.0,
                'horas_extra_35' => 0.0,
                'faltas' => (int) $resumen->faltas,
                'fuente' => 'resumen',
            ];
        }

        // 2) Si no hay resumen, se agrega desde el registro diario.
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

        // Horas extra por tramo (25% primeras 2h/día, 35% el resto).
        // Solo cuentan las H.E. APROBADAS por el supervisor (check en el registro diario).
        $he25 = 0.0;
        $he35 = 0.0;
        foreach ($registros as $r) {
            $he = (float) $r->horas_extra;
            if ($he <= 0 || ! $r->horas_extra_aprobadas) {
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
            'faltas' => $noPagados,
            'fuente' => 'diario',
        ];
    }
}
