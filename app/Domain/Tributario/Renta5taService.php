<?php

namespace App\Domain\Tributario;

use App\Models\TramoRenta5ta;
use Carbon\Carbon;

/**
 * Renta de 5ta categoría — procedimiento SUNAT (ESPECIFICACION_PLANILLA.md §5.1).
 *
 * 1. Proyección anual de la remuneración.
 * 2. Deducción de 7 UIT -> renta neta proyectada.
 * 3. Escala progresiva (tramos en UIT) -> impuesto anual proyectado.
 * 4. Retención del mes = impuesto / divisor del mes, menos lo ya retenido.
 *
 * NOTA: requiere UIT del periodo y retenciones previas para el cálculo acumulado.
 */
class Renta5taService
{
    /** Divisores SUNAT por mes (art. 40 Reglamento LIR). */
    private const DIVISOR_MES = [
        1 => 12, 2 => 12, 3 => 12, 4 => 9, 5 => 9, 6 => 9,
        7 => 8, 8 => 8, 9 => 5, 10 => 4, 11 => 4, 12 => 0,
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
     * Retención mensual estimada (método simple por proyección).
     *
     * @param float $remuneracionMensual remuneración mensual ordinaria
     * @param float $otrosIngresosAnuales gratificaciones u otros ya conocidos del año
     * @param float $retenidoPrevio       retenciones ya hechas en el año
     */
    public function retencionMensual(
        float $remuneracionMensual,
        float $uit,
        int $mes,
        Carbon $fechaPeriodo,
        float $otrosIngresosAnuales = 0.0,
        float $retenidoPrevio = 0.0,
        int $mesesRestantes = null
    ): float {
        $mesesRestantes ??= 12 - $mes + 1;

        // 1. Proyección anual
        $proyeccion = $remuneracionMensual * $mesesRestantes + $otrosIngresosAnuales;

        // 2. Deducción 7 UIT
        $rentaNeta = max($proyeccion - 7 * $uit, 0);

        // 3. Impuesto anual proyectado
        $impuestoAnual = $this->impuestoAnual($rentaNeta, $uit, $fechaPeriodo);

        // 4. Fracción del mes (menos lo retenido)
        $divisor = self::DIVISOR_MES[$mes] ?? 12;
        if ($divisor === 0) { // diciembre: el saldo
            return round(max($impuestoAnual - $retenidoPrevio, 0), 2);
        }

        $retencion = ($impuestoAnual / $divisor) - $retenidoPrevio;

        return round(max($retencion, 0), 2);
    }
}
