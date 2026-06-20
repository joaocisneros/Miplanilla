<?php

namespace App\Domain\Pensiones;

use App\Models\TasaAfp;
use Carbon\Carbon;

/**
 * Calcula el descuento de pensiones (AFP u ONP) sobre la base afecta.
 * Lee las tasas vigentes al periodo desde el maestro `tasas_afp`.
 *
 * Reglas (ESPECIFICACION_PLANILLA.md §4.5):
 *  - ONP: 13% de la base.
 *  - AFP: aporte obligatorio (10%) + comisión (flujo o mixta) + prima de seguro.
 *  - La prima de seguro tiene tope (remuneración máxima asegurable).
 */
class CalculadoraPension
{
    /**
     * @return array{aporte:float, comision:float, prima:float, total:float, detalle:array}
     */
    public function calcular(
        string $sistema,            // 'AFP' | 'ONP'
        ?string $afp,               // INTEGRA, PRIMA, ... (si AFP)
        ?string $tipoAfp,           // 'mixta' | 'sueldo' (si AFP)
        float $baseAfecta,
        Carbon $fechaPeriodo
    ): array {
        if (strtoupper($sistema) === 'ONP') {
            $tasa = $this->tasaVigente('ONP', 'onp', $fechaPeriodo);
            $aporte = round($baseAfecta * ($tasa?->aporte_obligatorio ?? 0.13), 2);

            return [
                'aporte' => $aporte, 'comision' => 0.0, 'prima' => 0.0,
                'total' => $aporte,
                'detalle' => ['sistema' => 'ONP', 'tasa_aporte' => $tasa?->aporte_obligatorio ?? 0.13],
            ];
        }

        // AFP
        $tasa = $this->tasaVigente($afp, $tipoAfp, $fechaPeriodo);
        if (! $tasa) {
            throw new \RuntimeException("No hay tasa AFP vigente para {$afp} ({$tipoAfp}) en {$fechaPeriodo->toDateString()}.");
        }

        $aporte = round($baseAfecta * (float) $tasa->aporte_obligatorio, 2);
        $comision = round($baseAfecta * (float) $tasa->comision_flujo, 2);

        // Prima de seguro: con tope de remuneración máxima asegurable
        $baseParaPrima = $tasa->rem_max_asegurable
            ? min($baseAfecta, (float) $tasa->rem_max_asegurable)
            : $baseAfecta;
        $prima = round($baseParaPrima * (float) $tasa->prima_seguro, 2);

        return [
            'aporte' => $aporte,
            'comision' => $comision,
            'prima' => $prima,
            'total' => round($aporte + $comision + $prima, 2),
            'detalle' => [
                'sistema' => 'AFP', 'afp' => $afp, 'tipo' => $tipoAfp,
                'tasa_aporte' => (float) $tasa->aporte_obligatorio,
                'tasa_comision' => (float) $tasa->comision_flujo,
                'tasa_prima' => (float) $tasa->prima_seguro,
                'base_prima' => $baseParaPrima,
            ],
        ];
    }

    private function tasaVigente(?string $afp, ?string $tipo, Carbon $fecha): ?TasaAfp
    {
        return TasaAfp::where('afp', $afp)
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->where('vigente_desde', '<=', $fecha)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha))
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
