<?php

namespace App\Domain\Planilla;

use App\Domain\Pensiones\CalculadoraPension;
use Carbon\Carbon;

/**
 * Motor de cálculo de planilla (ESPECIFICACION_PLANILLA.md §6–§9).
 *
 * Recibe insumos YA agregados (días, tardanzas, horas extra por tramo) y los
 * parámetros/tasas del periodo, y devuelve el desglose completo. Es una función
 * pura y determinista -> testeable contra la matriz de casos validada.
 *
 * Reglas clave implementadas:
 *  - Base afecta = remuneración devengada - desc. tardanza + horas extra (afectas).
 *    NO incluye movilidad (condición de trabajo) ni subsidios.
 *  - Horas extra con recargo legal: +25% (primeras 2h/día) y +35% (resto).
 *  - Prorrateo siempre /30 (dias_base).
 *  - Aportes empleador: EsSalud 9% (base afecta), SCTR por póliza, Vida Ley sobre básico.
 */
class CalculadoraPlanilla
{
    public function __construct(private CalculadoraPension $pension) {}

    /**
     * @param array $in {
     *   sueldo_basico, asignacion_familiar, movilidad, dias_base,
     *   dias_trabajados, minutos_tarde, horas_extra_25, horas_extra_35,
     *   subsidio_monto, otros_ingresos_afectos, incentivos_afectos,
     *   sistema_pensiones, afp, tipo_afp,
     *   aporta_sctr, aporta_senati, aporta_essalud,
     *   essalud_tasa, sctr_tasa_pension, sctr_tasa_salud, vida_ley_tasa, senati_tasa,
     *   renta_5ta, adelantos, reintegros, fecha_periodo
     * }
     */
    public function calcular(array $in): array
    {
        $diasBase = $in['dias_base'] ?? 30;
        $fecha = $in['fecha_periodo'] instanceof Carbon ? $in['fecha_periodo'] : Carbon::parse($in['fecha_periodo']);

        $basico = (float) ($in['sueldo_basico'] ?? 0);
        $asigFam = (float) ($in['asignacion_familiar'] ?? 0);
        $movilidad = (float) ($in['movilidad'] ?? 0);

        // Remuneración base prorrateable y afecta (básico + asignación familiar)
        $remBaseMensual = $basico + $asigFam;
        $jornal = $remBaseMensual / $diasBase;

        $diasTrab = (float) ($in['dias_trabajados'] ?? 0);
        $remDevengada = round($jornal * $diasTrab, 2);

        // Descuento por tardanza (sobre la remuneración afecta, no la movilidad)
        $valorMinuto = $remBaseMensual / $diasBase / 8 / 60;
        $descTardanza = round($valorMinuto * (float) ($in['minutos_tarde'] ?? 0), 2);

        // Horas extra con recargo legal
        $valorHora = $remBaseMensual / $diasBase / 8;
        $he25 = round($valorHora * 1.25 * (float) ($in['horas_extra_25'] ?? 0), 2);
        $he35 = round($valorHora * 1.35 * (float) ($in['horas_extra_35'] ?? 0), 2);
        $horasExtra = round($he25 + $he35, 2);

        $otrosAfectos = (float) ($in['otros_ingresos_afectos'] ?? 0) + (float) ($in['incentivos_afectos'] ?? 0);

        // BASE AFECTA a aportes/retenciones
        $baseAfecta = round($remDevengada - $descTardanza + $horasExtra + $otrosAfectos, 2);

        // Conceptos no afectos (al neto, fuera de bases)
        $subsidio = (float) ($in['subsidio_monto'] ?? 0);
        $movilidadProrrateada = round(($movilidad / $diasBase) * $diasTrab, 2);

        // Pensión (AFP / ONP)
        $pension = $this->pension->calcular(
            $in['sistema_pensiones'] ?? 'ONP',
            $in['afp'] ?? null,
            $in['tipo_afp'] ?? null,
            $baseAfecta,
            $fecha
        );

        $renta5ta = round((float) ($in['renta_5ta'] ?? 0), 2);

        // Total ingresos al trabajador
        $totalIngresos = round($baseAfecta + $movilidadProrrateada + $subsidio, 2);

        // Descuentos al trabajador
        $totalDescuentos = round($pension['total'] + $renta5ta, 2);

        $adelantos = (float) ($in['adelantos'] ?? 0);
        $reintegros = (float) ($in['reintegros'] ?? 0);

        $neto = round($totalIngresos - $totalDescuentos - $adelantos + $reintegros, 2);

        // Aportes del empleador
        $essaludTasa = (float) ($in['essalud_tasa'] ?? 0.09);
        $essalud = ($in['aporta_essalud'] ?? true) ? round($baseAfecta * $essaludTasa, 2) : 0.0;
        $sctrPension = ! empty($in['aporta_sctr']) ? round($baseAfecta * (float) ($in['sctr_tasa_pension'] ?? 0), 2) : 0.0;
        $sctrSalud = ! empty($in['aporta_sctr']) ? round($baseAfecta * (float) ($in['sctr_tasa_salud'] ?? 0), 2) : 0.0;
        $vidaLey = round($basico * (float) ($in['vida_ley_tasa'] ?? 0), 2);
        $senati = ! empty($in['aporta_senati']) ? round($baseAfecta * (float) ($in['senati_tasa'] ?? 0), 2) : 0.0;

        return [
            'ingresos' => [
                'remuneracion_devengada' => $remDevengada,
                'horas_extra' => $horasExtra,
                'horas_extra_25' => $he25,
                'horas_extra_35' => $he35,
                'otros_afectos' => round($otrosAfectos, 2),
                'movilidad' => $movilidadProrrateada,
                'subsidio' => $subsidio,
            ],
            'descuentos' => [
                'tardanza' => $descTardanza,
                'pension' => $pension,
                'renta_5ta' => $renta5ta,
                'adelantos' => $adelantos,
            ],
            'aportes_empleador' => [
                'essalud' => $essalud,
                'sctr_pension' => $sctrPension,
                'sctr_salud' => $sctrSalud,
                'vida_ley' => $vidaLey,
                'senati' => $senati,
            ],
            'base_afecta' => $baseAfecta,
            'total_ingresos' => $totalIngresos,
            'total_descuentos' => $totalDescuentos,
            'reintegros' => $reintegros,
            'neto' => $neto,
        ];
    }
}
