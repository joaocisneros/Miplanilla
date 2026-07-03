<?php

namespace App\Domain\Planilla;

use App\Domain\Planilla\Concerns\CalculaTiempoServicio;
use App\Models\Employee;
use App\Models\Gratificacion;
use App\Models\Vacacion;
use Carbon\Carbon;

/**
 * Liquidación de beneficios sociales al cese del trabajador:
 *  - Gratificación trunca (del semestre en curso) + bonificación 9%.
 *  - CTS trunca (desde el inicio del semestre CTS en curso).
 *  - Vacaciones no gozadas (saldo pendiente).
 */
class LiquidacionService
{
    use CalculaTiempoServicio;

    public function calcular(Employee $emp, Carbon $cese): array
    {
        $contrato = $emp->contratoVigente->first();
        if (! $contrato) {
            return ['error' => 'El trabajador no tiene contrato vigente.'];
        }

        $ingreso = Carbon::parse($contrato->fecha_ingreso);
        $asigFam = $contrato->percibe_asignacion_familiar ? 113.0 : 0.0;
        $remMensual = (float) $contrato->sueldo_basico + $asigFam;

        // --- Gratificación trunca ---
        $gInicio = $cese->month <= 6 ? Carbon::create($cese->year, 1, 1) : Carbon::create($cese->year, 7, 1);
        [$gMeses, $gDias] = $this->mesesYDias($ingreso, $gInicio, $cese->copy());
        $gratTrunca = round($remMensual * ($gMeses / 6 + $gDias / 180), 2);
        $bonifGrat = round($gratTrunca * 0.09, 2);

        // --- CTS trunca ---
        // 1/6 de la última gratificación (si existe) para la remuneración computable.
        $gratPrevia = Gratificacion::where('employee_id', $emp->id)->orderByDesc('anio')->first();
        $sexto = round(($gratPrevia ? (float) $gratPrevia->monto : $remMensual) / 6, 2);
        $remComputableCts = round($remMensual + $sexto, 2);

        $cInicio = $this->inicioSemestreCts($cese);
        [$cMeses, $cDias] = $this->mesesYDias($ingreso, $cInicio, $cese->copy());
        $ctsTrunca = round($remComputableCts * ($cMeses / 12 + $cDias / 360), 2);

        // --- Vacaciones no gozadas (saldo) ---
        $mesesServicio = $ingreso->diffInMonths($cese);
        $diasGanados = (int) floor($mesesServicio * 2.5);
        $diasGozados = (int) Vacacion::where('employee_id', $emp->id)->sum('dias');
        $diasPendientes = max($diasGanados - $diasGozados, 0);
        $vacacionesTruncas = round($remMensual / 30 * $diasPendientes, 2);

        $total = round($gratTrunca + $bonifGrat + $ctsTrunca + $vacacionesTruncas, 2);

        return [
            'rem_mensual' => $remMensual,
            'fecha_ingreso' => $ingreso->toDateString(),
            'fecha_cese' => $cese->toDateString(),
            'gratificacion' => [
                'meses' => $gMeses, 'dias' => $gDias,
                'monto' => $gratTrunca, 'bonificacion' => $bonifGrat,
                'subtotal' => round($gratTrunca + $bonifGrat, 2),
            ],
            'cts' => [
                'meses' => $cMeses, 'dias' => $cDias,
                'rem_computable' => $remComputableCts, 'sexto' => $sexto,
                'monto' => $ctsTrunca,
            ],
            'vacaciones' => [
                'dias_ganados' => $diasGanados, 'dias_gozados' => $diasGozados,
                'dias_pendientes' => $diasPendientes, 'monto' => $vacacionesTruncas,
            ],
            'total' => $total,
        ];
    }

    /** Inicio del semestre CTS que contiene la fecha (may–oct ó nov–abr). */
    private function inicioSemestreCts(Carbon $fecha): Carbon
    {
        $m = $fecha->month;
        if ($m >= 5 && $m <= 10) {
            return Carbon::create($fecha->year, 5, 1);
        }
        // nov–dic: empieza nov de este año; ene–abr: empieza nov del año anterior
        return $m >= 11 ? Carbon::create($fecha->year, 11, 1) : Carbon::create($fecha->year - 1, 11, 1);
    }
}
