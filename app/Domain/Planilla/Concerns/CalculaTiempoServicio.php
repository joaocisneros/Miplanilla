<?php

namespace App\Domain\Planilla\Concerns;

use Carbon\Carbon;

trait CalculaTiempoServicio
{
    /**
     * Meses completos y días extra trabajados dentro de un rango [inicio, fin].
     * Si ingresó antes del rango, cuenta desde el inicio del rango.
     * Un mes completo cubre [cursor, cursor+1mes-1día].
     *
     * @return array{0:int,1:int} [meses, dias] (días tope 30)
     */
    protected function mesesYDias(Carbon $ingreso, Carbon $inicio, Carbon $fin, ?Carbon $cese = null): array
    {
        $desde = $ingreso->lt($inicio) ? $inicio->copy() : $ingreso->copy();
        $hasta = ($cese && $cese->lt($fin)) ? $cese->copy() : $fin->copy();

        if ($desde->gt($hasta)) {
            return [0, 0];
        }

        $meses = 0;
        $cursor = $desde->copy();
        while ($cursor->copy()->addMonthNoOverflow()->lte($hasta->copy()->addDay())) {
            $meses++;
            $cursor->addMonthNoOverflow();
        }

        $dias = 0;
        if ($cursor->lte($hasta)) {
            $dias = min($cursor->diffInDays($hasta) + 1, 30);
        }

        return [$meses, $dias];
    }
}
