<?php

namespace App\Domain\Asistencia;

use App\Models\AsistenciaResumen;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga en el sistema el detalle dia por dia de la hoja "Asistencia" del cliente
 * y deja el cuadro resumen recalculado a partir de ese detalle.
 *
 * Hasta ahora solo se importaba el resumen (los totales). Como la planilla lee ese
 * resumen y nada lo actualizaba desde el registro diario, cualquier correccion que
 * se hiciera en pantalla no llegaba al calculo. Al cargar el detalle completo, el
 * resumen se puede recalcular sin inventar nada y el registro diario pasa a ser la
 * fuente real.
 */
class ImportadorAsistenciaCliente
{
    /** Estados que NO pagan el dia, igual que en el motor de planilla. */
    private const NO_PAGADOS = ['FALTA', 'LICENCIA_SIN_GOCE'];

    public function __construct(private LectorAsistenciaExcel $lector) {}

    /**
     * @return array{importados:int,dias:int,trabajadores:int,resumenes:int,
     *               sin_empleado:array<string>,avisos:array<string>}
     */
    public function importar(string $ruta, int $empresaId): array
    {
        $filas = $this->lector->leer($ruta);
        if (! $filas) {
            return $this->vacio($this->lector->avisos);
        }

        // Un solo golpe a la base para resolver los DNI de esta empresa.
        $empleados = Employee::where('empresa_id', $empresaId)
            ->pluck('id', 'numero_documento');

        $sinEmpleado = [];
        $periodos = [];
        $importados = 0;
        $conservados = 0;

        DB::transaction(function () use ($filas, $empresaId, $empleados, &$sinEmpleado, &$periodos, &$importados, &$conservados) {
            foreach ($filas as $f) {
                $employeeId = $empleados[$f['dni']] ?? null;
                if (! $employeeId) {
                    $sinEmpleado[$f['dni']] = $f['nombre'];

                    continue;
                }

                // Lo corregido a mano gana sobre el Excel: si no, reimportar el
                // mismo archivo borraria en silencio el trabajo de quien reviso.
                $existente = Attendance::where('employee_id', $employeeId)
                    ->whereDate('fecha', $f['fecha'])->first();
                if ($existente && $existente->origen === 'manual') {
                    $conservados++;

                    continue;
                }

                Attendance::updateOrCreate(
                    ['employee_id' => $employeeId, 'fecha' => $f['fecha']],
                    [
                        'empresa_id' => $empresaId,
                        'estado' => $f['estado'],
                        'hora_entrada_real' => $f['entrada'],
                        'hora_salida_real' => $f['salida'],
                        'minutos_tarde' => $f['minutos_tarde'],
                        'origen' => 'excel',
                    ]
                );
                $importados++;

                // Se anota el periodo para recalcular su resumen al final.
                $fecha = Carbon::parse($f['fecha']);
                $clave = $employeeId.'|'.$fecha->year.'|'.$fecha->month.'|'.($fecha->day <= 15 ? 1 : 2);
                $periodos[$clave] = true;
            }
        });

        $resumenes = 0;
        foreach (array_keys($periodos) as $clave) {
            [$employeeId, $anio, $mes, $quincena] = explode('|', $clave);
            $this->recalcularResumen($empresaId, (int) $employeeId, (int) $anio, (int) $mes, (int) $quincena, datosCompletos: true);
            $resumenes++;
        }

        return [
            'importados' => $importados,
            'conservados' => $conservados,
            'dias' => count(array_unique(array_column($filas, 'fecha'))),
            'trabajadores' => count(array_unique(array_column($filas, 'dni'))),
            'resumenes' => $resumenes,
            'sin_empleado' => $sinEmpleado,
            'avisos' => $this->lector->avisos,
        ];
    }

    /**
     * Rehace el cuadro resumen de un trabajador y quincena desde su registro diario.
     *
     * Los dias trabajados se cuentan igual que el motor: el periodo completo menos
     * los dias que no se pagan. No se cuentan los registros existentes, porque el
     * cuadro solo trae los dias habiles y los domingos igual se pagan.
     */
    public function recalcularResumen(int $empresaId, int $employeeId, int $anio, int $mes, int $quincena, bool $datosCompletos = false): void
    {
        $base = Carbon::create($anio, $mes, 1);
        $inicio = $quincena === 1 ? $base->copy()->startOfMonth() : $base->copy()->day(16);
        $fin = $quincena === 1 ? $base->copy()->day(15) : $base->copy()->endOfMonth();
        $diasPeriodo = $inicio->diffInDays($fin) + 1;

        $registros = Attendance::where('employee_id', $employeeId)
            ->whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])
            ->get();

        $resumen = AsistenciaResumen::where('empresa_id', $empresaId)->where('employee_id', $employeeId)
            ->where('anio', $anio)->where('mes', $mes)->where('quincena', $quincena)->first();

        // Al importar el detalle sabemos que los dias vienen completos, asi que se
        // rehace siempre. Al corregir a mano hay que ser prudente: en un periodo
        // cargado solo con totales el diario esta casi vacio, y recalcular pondria
        // los dias al maximo borrando las faltas y tardanzas que vinieron del Excel.
        if (! $datosCompletos) {
            if ($registros->isEmpty()) {
                return;
            }

            // Sin resumen previo no hace falta crear uno: el motor lee el diario.
            if (! $resumen) {
                return;
            }

            if ($registros->count() < (int) ceil($diasPeriodo / 2)) {
                return;
            }
        } elseif ($registros->isEmpty()) {
            return;
        }

        $contar = fn (array $estados) => $registros->whereIn('estado', $estados)->count();
        $faltas = $contar(self::NO_PAGADOS);

        AsistenciaResumen::updateOrCreate(
            ['empresa_id' => $empresaId, 'employee_id' => $employeeId,
                'anio' => $anio, 'mes' => $mes, 'quincena' => $quincena],
            [
                'dias_trabajados' => max($diasPeriodo - $faltas, 0),
                'faltas' => $faltas,
                'tardanza_min' => (int) $registros->sum('minutos_tarde'),
                // Las horas extra no salen de aqui: se cargan y aprueban en Pagos
                // adicionales, para no contarlas dos veces.
                'horas_extra' => 0,
                'sabado' => $contar(['TRABAJO_SABADO']),
                'feriados_domingos' => $contar(['TRABAJO_DOMINGO', 'TRABAJO_FERIADO']),
                'vacaciones' => $contar(['VACACIONES']),
                'licencia' => $contar(['LICENCIA', 'LICENCIA_SIN_GOCE', 'LICENCIA_HIJO_ENFERMO']),
            ]
        );
    }

    private function vacio(array $avisos): array
    {
        return ['importados' => 0, 'conservados' => 0, 'dias' => 0, 'trabajadores' => 0, 'resumenes' => 0,
            'sin_empleado' => [], 'avisos' => $avisos];
    }
}
