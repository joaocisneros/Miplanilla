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

        // Descuento por tardanza.
        // En el modelo del cliente ('excel') el valor-minuto se calcula sobre el TOTAL MENSUAL
        // (básico + asig. familiar + movilidad), igual que su hoja. En modo 'legal' se usa solo
        // la remuneración afecta (básico + asig. familiar).
        $modoTard = $in['modo'] ?? 'legal';
        $baseTardanza = $modoTard === 'excel' ? ($remBaseMensual + $movilidad) : $remBaseMensual;
        $valorMinuto = $baseTardanza / $diasBase / 8 / 60;
        $descTardanza = round($valorMinuto * (float) ($in['minutos_tarde'] ?? 0), 2);

        // Horas extra con recargo legal
        $valorHora = $remBaseMensual / $diasBase / 8;
        $he25 = round($valorHora * 1.25 * (float) ($in['horas_extra_25'] ?? 0), 2);
        $he35 = round($valorHora * 1.35 * (float) ($in['horas_extra_35'] ?? 0), 2);
        $horasExtra = round($he25 + $he35, 2);

        $modo = $in['modo'] ?? 'legal';

        // Otros ingresos AFECTOS del bloque 1 (gratificación, vacaciones, licencia, subsidio,
        // licencia por hijo enfermo). En la planilla normal suelen ser 0 (módulos aparte).
        $gratificacion = (float) ($in['gratificacion'] ?? 0);
        $vacaciones = (float) ($in['vacaciones_monto'] ?? 0);
        $licencia = (float) ($in['licencia_monto'] ?? 0);
        $subsidio = (float) ($in['subsidio_monto'] ?? 0);
        $hijoEnfermo = (float) ($in['hijo_enfermo_monto'] ?? 0);
        // "Otros pensionables / incentivos" afectos (col. AG del Excel del cliente).
        $otrosAfectos = (float) ($in['otros_afectos'] ?? 0);
        $otrosBloque1 = round($gratificacion + $vacaciones + $licencia + $subsidio + $hijoEnfermo + $otrosAfectos, 2);

        // Conceptos de la "bolsa de movilidad" del cliente (sábados, domingos/feriados,
        // horas extra e incentivos/bonos). Más las HE con recargo legacy si vinieran.
        $movHorasExtra = (float) ($in['mov_horas_extra'] ?? 0) + $horasExtra
            + (float) ($in['otros_ingresos_afectos'] ?? 0); // compat
        $movSabado = (float) ($in['mov_sabado'] ?? 0);
        $movDomingo = (float) ($in['mov_domingo'] ?? 0);
        $movIncentivo = (float) ($in['mov_incentivo'] ?? 0) + (float) ($in['incentivos_afectos'] ?? 0);
        $bolsaExtras = round($movHorasExtra + $movSabado + $movDomingo + $movIncentivo, 2);

        $movilidadProrrateada = round(($movilidad / $diasBase) * $diasTrab, 2);

        // BLOQUE 1 (afecto) y BLOQUE 2 (movilidad), según el modo:
        //  - 'excel': los extras (HE, sábado, domingo, incentivo) van en la movilidad (NO afectos).
        //  - 'legal': esos extras van afectos (suman a la base de aportes/renta).
        if ($modo === 'legal') {
            $baseAfecta = round($remDevengada - $descTardanza + $otrosBloque1 + $bolsaExtras, 2);
            $totalMovilidad = $movilidadProrrateada;
        } else { // excel
            $baseAfecta = round($remDevengada - $descTardanza + $otrosBloque1, 2);
            $totalMovilidad = round($movilidadProrrateada + $bolsaExtras, 2);
        }

        // Pensión (AFP / ONP) sobre el bloque 1 afecto
        $pension = $this->pension->calcular(
            $in['sistema_pensiones'] ?? 'ONP',
            $in['afp'] ?? null,
            $in['tipo_afp'] ?? null,
            $baseAfecta,
            $fecha
        );

        $renta5ta = round((float) ($in['renta_5ta'] ?? 0), 2);

        // BLOQUE 1: Remuneración neta quincenal = afecto − AFP/ONP
        $remNetaQuincenal = round($baseAfecta - $pension['total'], 2);
        // SUMA NETO = Rem. neta + Movilidad − Renta 5ta
        $sumaNeto = round($remNetaQuincenal + $totalMovilidad - $renta5ta, 2);

        $adelantos = (float) ($in['adelantos'] ?? 0);
        $reintegros = (float) ($in['reintegros'] ?? 0);

        // A PAGAR FINAL = Suma neto + Reintegro − Adelanto
        $neto = round($sumaNeto + $reintegros - $adelantos, 2);

        $totalIngresos = round($baseAfecta + $totalMovilidad, 2);
        $totalDescuentos = round($pension['total'] + $renta5ta, 2);

        // Aportes del empleador (sobre la base afecta del bloque 1)
        $essaludTasa = (float) ($in['essalud_tasa'] ?? 0.09);
        $essalud = ($in['aporta_essalud'] ?? true) ? round($baseAfecta * $essaludTasa, 2) : 0.0;
        $sctrPension = ! empty($in['aporta_sctr']) ? round($baseAfecta * (float) ($in['sctr_tasa_pension'] ?? 0), 2) : 0.0;
        $sctrSalud = ! empty($in['aporta_sctr']) ? round($baseAfecta * (float) ($in['sctr_tasa_salud'] ?? 0), 2) : 0.0;
        $vidaLey = round($basico * (float) ($in['vida_ley_tasa'] ?? 0), 2);
        $senati = ! empty($in['aporta_senati']) ? round($baseAfecta * (float) ($in['senati_tasa'] ?? 0), 2) : 0.0;

        return [
            'modo' => $modo,
            'ingresos' => [
                'remuneracion_devengada' => $remDevengada,
                'gratificacion' => $gratificacion,
                'vacaciones' => $vacaciones,
                'licencia' => $licencia,
                'subsidio' => $subsidio,
                'hijo_enfermo' => $hijoEnfermo,
                // bolsa de movilidad (desglosada)
                'movilidad' => $movilidadProrrateada,
                'sabado' => $movSabado,
                'domingo_feriado' => $movDomingo,
                'horas_extra' => round($movHorasExtra, 2),
                'incentivos' => $movIncentivo,
                'otros_afectos' => round($otrosBloque1, 2),
            ],
            'descuentos' => [
                'tardanza' => $descTardanza,
                'pension' => $pension,
                'renta_5ta' => $renta5ta,
                'adelantos' => $adelantos,
            ],
            'bloques' => [
                'remuneracion_neta_quincenal' => $remNetaQuincenal,
                'total_movilidad_quincenal' => $totalMovilidad,
                'suma_neto' => $sumaNeto,
            ],
            'aportes_empleador' => [
                'essalud' => $essalud,
                'sctr_pension' => $sctrPension,
                'sctr_salud' => $sctrSalud,
                'vida_ley' => $vidaLey,
                'senati' => $senati,
            ],
            'dias_trabajados' => $diasTrab,
            'dias_base' => $diasBase,
            'base_afecta' => $baseAfecta,
            'total_ingresos' => $totalIngresos,
            'total_descuentos' => $totalDescuentos,
            'reintegros' => $reintegros,
            'neto' => $neto,
        ];
    }
}
