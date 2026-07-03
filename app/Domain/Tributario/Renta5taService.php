<?php

namespace App\Domain\Tributario;

use App\Models\TramoRenta5ta;
use Carbon\Carbon;

/**
 * Renta de 5ta categoría — procedimiento SUNAT (ESPECIFICACION_PLANILLA.md §5.1).
 *
 * 1. Proyección anual de la remuneración (12 sueldos + gratificaciones + otros).
 * 2. Deducción de 7 UIT -> renta neta proyectada.
 * 3. Escala progresiva (tramos en UIT) -> impuesto anual proyectado.
 * 4. Retención del mes = impuesto anual ÷ divisor del mes, restando (desde abril) lo ya retenido.
 *
 * Usa el procedimiento SUNAT por divisores (art. 40 Reglamento LIR), acumulando las
 * retenciones previas del año a partir de abril. Requiere UIT del periodo.
 */
class Renta5taService
{
    /**
     * Divisores SUNAT por mes (art. 40 Reglamento LIR). Referencia para la variante
     * estricta por divisor fijo; el método vigente usa reparto por meses restantes.
     */
    private const DIVISOR_MES = [
        1 => 12, 2 => 12, 3 => 12, 4 => 9, 5 => 8, 6 => 8,
        7 => 8, 8 => 5, 9 => 4, 10 => 4, 11 => 4, 12 => 0,
    ];

    /**
     * Impuesto anual sobre la renta neta gravable, aplicando la escala progresiva.
     */
    public function impuestoAnual(float $rentaNetaGravable, float $uit, Carbon $fechaPeriodo): float
    {
        if ($rentaNetaGravable <= 0) {
            return 0.0;
        }

        $tramos = TramoRenta5ta::where('vigente_desde', '<=', $fechaPeriodo)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fechaPeriodo))
            ->orderBy('orden')
            ->get();

        $impuesto = 0.0;
        foreach ($tramos as $t) {
            $desde = (float) $t->desde_uit * $uit;
            $hasta = $t->hasta_uit !== null ? (float) $t->hasta_uit * $uit : INF;

            if ($rentaNetaGravable > $desde) {
                $montoEnTramo = min($rentaNetaGravable, $hasta) - $desde;
                $impuesto += $montoEnTramo * (float) $t->tasa;
            }
        }

        return round($impuesto, 2);
    }

    /**
     * Retención MENSUAL — procedimiento SUNAT por divisores (art. 40 Reglamento LIR):
     *
     *   Ene–Mar:  impuesto anual ÷ 12
     *   Abr:      (impuesto anual − retenido en ene-mar) ÷ 9
     *   May–Jul:  (impuesto anual − retenido previo) ÷ 8
     *   Ago:      (impuesto anual − retenido previo) ÷ 5
     *   Set–Nov:  (impuesto anual − retenido previo) ÷ 4
     *   Dic:      impuesto anual − retenido previo (regularización)
     *
     * El descuento del retenido previo recién opera desde ABRIL (así lo dispone la norma);
     * de enero a marzo se reparte siempre entre 12. Esto hace que el cálculo sea correcto
     * aunque el sistema empiece a media campaña y no tenga el historial completo del año.
     *
     * @param float    $remuneracionMensual  remuneración mensual ordinaria (incluye asig. familiar)
     * @param float    $otrosIngresosAnuales gratificaciones, bonificaciones u otros del año
     * @param float    $retenidoPrevio       retenciones ya efectuadas en meses anteriores del año
     * @param int|null $mesesRestantes       (no usado; se conserva por compatibilidad)
     */
    public function retencionMensual(
        float $remuneracionMensual,
        float $uit,
        int $mes,
        Carbon $fechaPeriodo,
        float $otrosIngresosAnuales = 0.0,
        float $retenidoPrevio = 0.0,
        ?int $mesesRestantes = null
    ): float {
        // 1. Proyección anual (12 remuneraciones + otros ingresos conocidos del año)
        $proyeccion = $remuneracionMensual * 12 + $otrosIngresosAnuales;

        // 2. Deducción 7 UIT -> renta neta gravable
        $rentaNeta = max($proyeccion - 7 * $uit, 0);

        // 3. Impuesto anual proyectado (escala progresiva)
        $impuestoAnual = $this->impuestoAnual($rentaNeta, $uit, $fechaPeriodo);
        if ($impuestoAnual <= 0) {
            return 0.0;
        }

        // 4. Reparto según el mes. El retenido previo solo se resta desde abril.
        $divisor = self::DIVISOR_MES[$mes] ?? 12;
        $base = $mes >= 4 ? max($impuestoAnual - $retenidoPrevio, 0) : $impuestoAnual;

        if ($divisor === 0) { // diciembre: regularización del saldo
            return round($base, 2);
        }

        return round($base / $divisor, 2);
    }
}
