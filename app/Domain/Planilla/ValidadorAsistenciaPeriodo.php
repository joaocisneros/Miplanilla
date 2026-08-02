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

        return "ASISTENCIA INCOMPLETA\n\n"
            .'No se puede crear, generar, recalcular ni cerrar este período porque falta registrar la asistencia. '
            .'Completa la asistencia o importa el Resumen Excel correspondiente y vuelve a intentarlo.';
    }
}
