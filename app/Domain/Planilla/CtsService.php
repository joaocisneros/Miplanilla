<?php

namespace App\Domain\Planilla;

use App\Domain\Planilla\Concerns\CalculaTiempoServicio;
use App\Models\Cts;
use App\Models\Employee;
use App\Models\Gratificacion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CTS — Compensación por Tiempo de Servicios (D.S. 001-97-TR).
 *
 *  - Dos depósitos al año: MAYO (semestre nov–abr) y NOVIEMBRE (semestre may–oct).
 *  - Remuneración computable = básico + asignación familiar + 1/6 de la
 *    gratificación percibida en el semestre.
 *  - CTS = rem. computable × (meses completos / 12 + días / 360).
 *  - No está afecta a aportes ni descuentos (es un depósito, no remuneración).
 */
class CtsService
{
    use CalculaTiempoServicio;

    public function generar(int $empresaId, int $anio, string $periodo, ?int $generadoPor = null): array
    {
        [$inicio, $fin, $gratifAnio, $gratifTipo] = $this->semestre($anio, $periodo);

        $empleados = Employee::with('contratoVigente')
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->get();

        $ids = [];

        DB::transaction(function () use ($empleados, $empresaId, $anio, $periodo, $inicio, $fin, $gratifAnio, $gratifTipo, $generadoPor, &$ids) {
            Cts::where('empresa_id', $empresaId)->where('anio', $anio)->where('periodo', $periodo)->delete();

            foreach ($empleados as $emp) {
                $contrato = $emp->contratoVigente->first();
                if (! $contrato) {
                    continue;
                }

                $ingreso = $contrato->fecha_ingreso instanceof Carbon
                    ? $contrato->fecha_ingreso : Carbon::parse($contrato->fecha_ingreso);

                [$meses, $dias] = $this->mesesYDias($ingreso, $inicio, $fin);
                if ($meses === 0 && $dias === 0) {
                    continue;
                }

                $asigFam = $contrato->percibe_asignacion_familiar ? 113.0 : 0.0;
                $remMensual = (float) $contrato->sueldo_basico + $asigFam;

                // 1/6 de la gratificación del semestre (si existe el cálculo; si no, se estima 1/6 del sueldo).
                $grat = Gratificacion::where('empresa_id', $empresaId)
                    ->where('employee_id', $emp->id)
                    ->where('anio', $gratifAnio)->where('tipo', $gratifTipo)->first();
                $sexto = round(($grat ? (float) $grat->monto : $remMensual) / 6, 2);

                $remComputable = round($remMensual + $sexto, 2);
                $monto = round($remComputable * ($meses / 12 + $dias / 360), 2);

                $c = Cts::create([
                    'empresa_id' => $empresaId,
                    'employee_id' => $emp->id,
                    'anio' => $anio,
                    'periodo' => $periodo,
                    'meses_computables' => $meses,
                    'dias_computables' => $dias,
                    'rem_computable' => $remComputable,
                    'sexto_gratificacion' => $sexto,
                    'monto' => $monto,
                    'generado_por' => $generadoPor,
                ]);
                $ids[] = $c->id;
            }
        });

        return $ids;
    }

    /**
     * Rango del semestre y la gratificación asociada (para el 1/6).
     *  - mayo: nov(año-1)–abr(año), gratificación de diciembre(año-1)
     *  - noviembre: may–oct(año), gratificación de julio(año)
     *
     * @return array{0:Carbon,1:Carbon,2:int,3:string}
     */
    private function semestre(int $anio, string $periodo): array
    {
        return $periodo === 'noviembre'
            ? [Carbon::create($anio, 5, 1), Carbon::create($anio, 10, 31), $anio, 'julio']
            : [Carbon::create($anio - 1, 11, 1), Carbon::create($anio, 4, 30), $anio - 1, 'diciembre'];
    }
}
