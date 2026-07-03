<?php

namespace App\Imports;

use App\Models\AsistenciaResumen;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\IngresoAdicional;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Importa el CUADRO RESUMEN de asistencia (una fila por trabajador) que el
 * cliente ya tiene calculado en su Excel. Es la fuente exacta para la planilla.
 *
 * Encabezados esperados:
 *   dni | dias_trabajados | faltas | tardanza_min | horas_extra
 *   [opcionales: he_aprobada (SI/NO) | sabado | feriados_domingos | incentivo | vacaciones | licencia]
 *
 * - La asistencia (días/faltas/tardanza) va a AsistenciaResumen (base de la planilla).
 * - Las horas extra + incentivo van a IngresoAdicional (afectos). Las horas extra
 *   SOLO se pagan si el supervisor las aprobó (columna he_aprobada = SI).
 *
 * Se guarda por (empresa, trabajador, año, mes, quincena).
 */
class ResumenAsistenciaImport implements ToCollection, WithHeadingRow
{
    public int $importadas = 0;
    public array $errores = [];

    private array $porDni;
    private array $sueldoPorEmpleado;

    public function __construct(
        private int $empresaId,
        private int $anio,
        private int $mes,
        private ?int $quincena,
        private ?int $importadoPor = null
    ) {
        $this->porDni = Employee::where('empresa_id', $empresaId)
            ->pluck('id', 'numero_documento')->all();

        // Sueldo básico vigente por empleado, para valorizar las horas extra.
        $this->sueldoPorEmpleado = Contract::whereIn('employee_id', array_values($this->porDni))
            ->where('activo', true)
            ->pluck('sueldo_basico', 'employee_id')->all();
    }

    public function collection($filas): void
    {
        foreach ($filas as $i => $fila) {
            $linea = $i + 2;
            $dni = trim((string) ($fila['dni'] ?? ''));
            if ($dni === '') {
                continue;
            }

            $empId = $this->porDni[$dni] ?? null;
            if (! $empId) {
                $this->errores[] = "Fila {$linea}: DNI {$dni} no existe en esta empresa.";
                continue;
            }

            $horasExtra = (float) ($fila['horas_extra'] ?? 0);
            $minutosExtra = (int) ($fila['horas_extra_min'] ?? 0);
            $sabadoMonto = (float) ($fila['sabado_monto'] ?? 0);
            $domingoMonto = (float) ($fila['domingo_monto'] ?? 0);
            $incentivo = (float) ($fila['incentivo'] ?? 0);
            $aprobado = $this->aProbado($fila['he_aprobada'] ?? null);

            AsistenciaResumen::updateOrCreate(
                [
                    'empresa_id' => $this->empresaId, 'employee_id' => $empId,
                    'anio' => $this->anio, 'mes' => $this->mes, 'quincena' => $this->quincena,
                ],
                [
                    'dias_trabajados' => (float) ($fila['dias_trabajados'] ?? 0),
                    'faltas' => (int) ($fila['faltas'] ?? 0),
                    'tardanza_min' => (int) ($fila['tardanza_min'] ?? 0),
                    'horas_extra' => $horasExtra,
                    'sabado' => (int) ($fila['sabado'] ?? 0),
                    'feriados_domingos' => (int) ($fila['feriados_domingos'] ?? 0),
                    'vacaciones' => (int) ($fila['vacaciones'] ?? 0),
                    'licencia' => (int) ($fila['licencia'] ?? 0),
                    'importado_por' => $this->importadoPor,
                ]
            );

            // Horas extra + incentivo -> ingreso adicional (afecto). La hora se valoriza
            // como hora normal (sueldo / 30 / 8). Solo se paga si el supervisor aprobó.
            $clave = [
                'empresa_id' => $this->empresaId, 'employee_id' => $empId,
                'anio' => $this->anio, 'mes' => $this->mes, 'quincena' => $this->quincena,
            ];

            if ($horasExtra > 0 || $minutosExtra > 0 || $sabadoMonto > 0 || $domingoMonto > 0 || $incentivo > 0) {
                $sueldo = (float) ($this->sueldoPorEmpleado[$empId] ?? 0);
                $valorHora = $sueldo > 0 ? ($sueldo / 30 / 8) : 0;
                // Total de horas extra incluyendo los minutos (ej. 6h 30min = 6.5h).
                $totalHoras = $horasExtra + ($minutosExtra / 60);
                $montoHoras = round($valorHora * $totalHoras, 2);

                IngresoAdicional::updateOrCreate($clave, [
                    'horas' => $horasExtra,
                    'minutos' => $minutosExtra,
                    'aprobado' => $aprobado,
                    'monto_horas' => $montoHoras,
                    'sabado' => $sabadoMonto,
                    'domingo_feriado' => $domingoMonto,
                    'bono' => $incentivo,
                    'nota' => 'Importado del cuadro de asistencia',
                    'registrado_por' => $this->importadoPor,
                ]);
            } else {
                // Sin adicionales: limpiar cualquier adicional previo importado.
                IngresoAdicional::where($clave)->delete();
            }

            $this->importadas++;
        }
    }

    /** Interpreta SI/NO, SÍ, S, 1, true como aprobado. */
    private function aProbado($v): bool
    {
        $s = strtolower(trim((string) $v));

        return in_array($s, ['si', 'sí', 's', '1', 'true', 'x', 'aprobado'], true);
    }
}
