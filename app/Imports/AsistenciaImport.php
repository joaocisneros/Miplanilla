<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importa asistencia desde un Excel/CSV con columnas (encabezados):
 *   dni | fecha | estado | minutos_tarde | horas_extra | hora_entrada | hora_salida
 * Solo `dni` y `fecha` son obligatorios. Empareja por DNI dentro de la empresa activa.
 * Upsert por (employee_id, fecha). Reporta filas con error sin abortar todo.
 */
class AsistenciaImport implements ToCollection, WithHeadingRow
{
    public int $importadas = 0;
    public array $errores = [];

    private array $empleadosPorDni;

    public function __construct(private int $empresaId)
    {
        $this->empleadosPorDni = Employee::where('empresa_id', $empresaId)
            ->pluck('id', 'numero_documento')->all();
    }

    public function collection($filas): void
    {
        foreach ($filas as $i => $fila) {
            $linea = $i + 2; // +1 encabezado, +1 base 1
            $dni = trim((string) ($fila['dni'] ?? ''));
            if ($dni === '') {
                continue; // fila vacía
            }

            $employeeId = $this->empleadosPorDni[$dni] ?? null;
            if (! $employeeId) {
                $this->errores[] = "Fila {$linea}: DNI {$dni} no existe en esta empresa.";
                continue;
            }

            $fecha = $this->parseFecha($fila['fecha'] ?? null);
            if (! $fecha) {
                $this->errores[] = "Fila {$linea}: fecha inválida.";
                continue;
            }

            $estado = strtoupper(trim((string) ($fila['estado'] ?? 'NORMAL'))) ?: 'NORMAL';

            Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'fecha' => $fecha->toDateString()],
                [
                    'empresa_id' => $this->empresaId,
                    'estado' => $estado,
                    'minutos_tarde' => (int) ($fila['minutos_tarde'] ?? 0),
                    'horas_extra' => (float) ($fila['horas_extra'] ?? 0),
                    'hora_entrada_real' => $this->parseHora($fila['hora_entrada'] ?? null),
                    'hora_salida_real' => $this->parseHora($fila['hora_salida'] ?? null),
                    'origen' => 'excel',
                ]
            );
            $this->importadas++;
        }
    }

    private function parseFecha($valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            if (is_numeric($valor)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valor));
            }
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseHora($valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            if (is_numeric($valor)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $valor))->format('H:i:s');
            }
            return Carbon::parse($valor)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
