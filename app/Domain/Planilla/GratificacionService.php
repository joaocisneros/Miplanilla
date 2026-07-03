<?php

namespace App\Domain\Planilla;

use App\Models\Employee;
use App\Models\Gratificacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Gratificaciones legales (Ley 27735 / Ley 30334).
 *
 *  - Julio (Fiestas Patrias): semestre enero–junio.
 *  - Diciembre (Navidad): semestre julio–diciembre.
 *  - Monto = remuneración computable × (meses completos / 6 + días / 180).
 *    Mes completo = 1/6; cada día del mes incompleto = 1/180.
 *  - Remuneración computable = básico + asignación familiar (v1; los
 *    promedios de conceptos variables regulares se pueden añadir luego).
 *  - NO está afecta a aportes pensionarios (AFP/ONP) ni descuentos.
 *  - Bonificación extraordinaria (Ley 30334) = 9% de la gratificación
 *    (lo que el empleador habría pagado a EsSalud), se entrega al trabajador.
 *  - La retención de Renta 5ta de la gratificación se maneja en la
 *    proyección anual de la planilla mensual (aquí no se descuenta para
 *    evitar doble retención).
 */
class GratificacionService
{
    public function generar(int $empresaId, int $anio, string $tipo, ?int $generadoPor = null): array
    {
        [$inicio, $fin] = $this->semestre($anio, $tipo);

        $empleados = Employee::with('contratoVigente')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->get();

        $filas = [];

        DB::transaction(function () use ($empleados, $empresaId, $anio, $tipo, $inicio, $fin, $generadoPor, &$filas) {
            // Limpia las gratificaciones previas de este periodo (recalculo idempotente).
            Gratificacion::where('empresa_id', $empresaId)->where('anio', $anio)->where('tipo', $tipo)->delete();

            foreach ($empleados as $emp) {
                $contrato = $emp->contratoVigente->first();
                if (! $contrato) {
                    continue;
                }

                $ingreso = $contrato->fecha_ingreso instanceof Carbon
                    ? $contrato->fecha_ingreso
                    : Carbon::parse($contrato->fecha_ingreso);

                [$meses, $dias] = $this->mesesYDias($ingreso, $inicio, $fin);
                if ($meses === 0 && $dias === 0) {
                    continue; // sin derecho en el semestre
                }

                $asigFam = $contrato->percibe_asignacion_familiar ? 113.0 : 0.0; // RMV-dependiente; v1 fijo
                $remComputable = (float) $contrato->sueldo_basico + $asigFam;

                $monto = round($remComputable * ($meses / 6 + $dias / 180), 2);
                $bonif = round($monto * 0.09, 2);
                $renta5ta = 0.0;
                $neto = round($monto + $bonif - $renta5ta, 2);

                $g = Gratificacion::create([
                    'empresa_id' => $empresaId,
                    'employee_id' => $emp->id,
                    'anio' => $anio,
                    'tipo' => $tipo,
                    'meses_computables' => $meses,
                    'dias_computables' => $dias,
                    'rem_computable' => $remComputable,
                    'monto' => $monto,
                    'bonificacion_extraordinaria' => $bonif,
                    'renta_5ta' => $renta5ta,
                    'neto' => $neto,
                    'generado_por' => $generadoPor,
                ]);
                $filas[] = $g->id;
            }
        });

        return $filas;
    }

    /** Rango del semestre según el tipo de gratificación. */
    private function semestre(int $anio, string $tipo): array
    {
        return $tipo === 'diciembre'
            ? [Carbon::create($anio, 7, 1), Carbon::create($anio, 12, 31)]
            : [Carbon::create($anio, 1, 1), Carbon::create($anio, 6, 30)];
    }

    /**
     * Meses completos y días extra trabajados dentro del semestre.
     * Si ingresó antes del semestre, le corresponde el semestre completo (6 meses).
     */
    private function mesesYDias(Carbon $ingreso, Carbon $inicio, Carbon $fin): array
    {
        $desde = $ingreso->lt($inicio) ? $inicio->copy() : $ingreso->copy();
        if ($desde->gt($fin)) {
            return [0, 0];
        }

        $meses = 0;
        $cursor = $desde->copy();
        // Un mes completo cubre [cursor, cursor+1mes-1día]; cabe si cursor+1mes <= fin+1día.
        while ($cursor->copy()->addMonthNoOverflow()->lte($fin->copy()->addDay())) {
            $meses++;
            $cursor->addMonthNoOverflow();
        }
        $meses = min($meses, 6);

        $dias = 0;
        if ($cursor->lte($fin)) {
            $dias = min($cursor->diffInDays($fin) + 1, 30);
        }

        return [$meses, $dias];
    }
}
