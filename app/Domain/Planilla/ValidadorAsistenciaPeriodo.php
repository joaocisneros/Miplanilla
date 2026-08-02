<?php

namespace App\Domain\Planilla;

use App\Models\AsistenciaResumen;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Periodo;
use Carbon\CarbonPeriod;

class ValidadorAsistenciaPeriodo
{
    /** @return array<int, array{empleado:string, faltantes:int, fechas:array<int,string>}> */
    public function pendientes(Periodo $periodo): array
    {
        $fechas = collect(CarbonPeriod::create($periodo->fecha_inicio, $periodo->fecha_fin))
            ->map(fn ($fecha) => $fecha->format('Y-m-d'));

        $empleados = Employee::with('contratoVigente')
            ->where('empresa_id', $periodo->empresa_id)
            ->where('activo', true)
            ->get()
            ->filter(fn ($empleado) => $empleado->contratoVigente->isNotEmpty());

        $pendientes = [];
        foreach ($empleados as $empleado) {
            $tieneResumen = AsistenciaResumen::where('empresa_id', $periodo->empresa_id)
                ->where('employee_id', $empleado->id)
                ->where('anio', $periodo->anio)
                ->where('mes', $periodo->mes)
                ->where('quincena', $periodo->quincena)
                ->exists();

            if ($tieneResumen) {
                continue;
            }

            $registradas = Attendance::where('empresa_id', $periodo->empresa_id)
                ->where('employee_id', $empleado->id)
                ->whereBetween('fecha', [$periodo->fecha_inicio, $periodo->fecha_fin])
                ->pluck('fecha')
                ->map(fn ($fecha) => substr((string) $fecha, 0, 10));
            $faltantes = $fechas->diff($registradas)->values();

            if ($faltantes->isNotEmpty()) {
                $pendientes[] = [
                    'empleado' => $empleado->nombre_completo,
                    'faltantes' => $faltantes->count(),
                    'fechas' => $faltantes->take(5)->all(),
                ];
            }
        }

        return $pendientes;
    }

    public function mensaje(Periodo $periodo): ?string
    {
        $pendientes = $this->pendientes($periodo);
        if ($pendientes === []) {
            return null;
        }

        $detalle = collect($pendientes)->take(5)->map(
            fn ($item) => "• {$item['empleado']} — {$item['faltantes']} día(s) pendientes"
        )->implode("\n");

        $extra = count($pendientes) > 5
            ? "\n• Y ".(count($pendientes) - 5).' trabajador(es) más'
            : '';

        return "ASISTENCIA INCOMPLETA\n\n"
            ."No se creó ningún período ni planilla. Antes de continuar debes completar la asistencia de todo el personal.\n\n"
            ."Trabajadores pendientes: ".count($pendientes)."\n{$detalle}{$extra}\n\n"
            ."Ve a Asistencia diaria o importa el Resumen Excel correspondiente. Después vuelve a intentar.";
    }
}
