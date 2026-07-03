<?php

namespace App\Imports;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Convertidor genérico de MARCACIONES de reloj biométrico a asistencia.
 *
 * Acepta un Excel/CSV con encabezados flexibles:
 *   - Identificador: `dni` o `codigo` (código biométrico del trabajador)
 *   - `fecha`
 *   - Horas: `entrada` y `salida`  (una fila por día)  Ó  `hora` (una fila por marcación)
 *
 * Agrupa todas las marcaciones por (trabajador, fecha): la más temprana es la
 * entrada y la más tardía la salida. Cruza con el TURNO del trabajador para
 * calcular minutos de tardanza y horas extra. Las HE quedan SIN aprobar
 * (origen biométrico) hasta que el jefe las apruebe.
 */
class MarcacionesImport implements ToCollection, WithHeadingRow
{
    public int $importadas = 0;
    public array $errores = [];

    /** @var array<string,array{id:int,turno:?object}> */
    private array $porDni = [];
    private array $porCodigo = [];

    public function __construct(private int $empresaId)
    {
        $empleados = Employee::with('contratoVigente.turno')
            ->where('empresa_id', $empresaId)->get();

        foreach ($empleados as $e) {
            $turno = $e->contratoVigente->first()?->turno;
            $ref = ['id' => $e->id, 'turno' => $turno];
            if ($e->numero_documento) {
                $this->porDni[trim((string) $e->numero_documento)] = $ref;
            }
            if ($e->codigo_biometrico) {
                $this->porCodigo[trim((string) $e->codigo_biometrico)] = $ref;
            }
        }
    }

    public function collection($filas): void
    {
        // 1) Acumular marcaciones por trabajador y fecha.
        $acum = []; // [empId][fecha] => ['ref'=>..., 'horas'=>[minutos,...]]

        foreach ($filas as $i => $fila) {
            $linea = $i + 2;
            $idTxt = trim((string) ($fila['dni'] ?? $fila['codigo'] ?? $fila['documento'] ?? ''));
            if ($idTxt === '') {
                continue;
            }

            $ref = $this->porDni[$idTxt] ?? $this->porCodigo[$idTxt] ?? null;
            if (! $ref) {
                $this->errores[] = "Fila {$linea}: trabajador '{$idTxt}' no existe en esta empresa (DNI o código).";
                continue;
            }

            $fecha = $this->parseFecha($fila['fecha'] ?? null);
            if (! $fecha) {
                $this->errores[] = "Fila {$linea}: fecha inválida.";
                continue;
            }
            $key = $fecha->toDateString();

            $acum[$ref['id']][$key]['ref'] ??= $ref;
            foreach (['entrada', 'salida', 'hora'] as $col) {
                $min = $this->horaAMinutos($fila[$col] ?? null);
                if ($min !== null) {
                    $acum[$ref['id']][$key]['horas'][] = $min;
                }
            }
        }

        // 2) Calcular asistencia por (trabajador, fecha).
        foreach ($acum as $empId => $dias) {
            foreach ($dias as $fecha => $data) {
                $horas = $data['horas'] ?? [];
                if (empty($horas)) {
                    continue;
                }
                $entrada = min($horas);
                $salida = max($horas);
                $turno = $data['ref']['turno'];

                [$tardanza, $he] = $this->calcular($entrada, $salida, $turno);

                Attendance::updateOrCreate(
                    ['employee_id' => $empId, 'fecha' => $fecha],
                    [
                        'empresa_id' => $this->empresaId,
                        'estado' => 'NORMAL',
                        'hora_entrada_real' => $this->minutosAHora($entrada),
                        'hora_salida_real' => $this->minutosAHora($salida),
                        'minutos_tarde' => $tardanza,
                        'horas_extra' => $he,
                        'horas_extra_aprobadas' => false, // pendiente de aprobación del jefe
                        'origen' => 'biometrico',
                    ]
                );
                $this->importadas++;
            }
        }
    }

    /** @return array{0:int,1:float} [minutos_tarde, horas_extra] */
    private function calcular(int $entrada, int $salida, ?object $turno): array
    {
        if (! $turno) {
            return [0, 0.0];
        }
        $tEntrada = $this->horaAMinutos($turno->hora_entrada) ?? 0;
        $tSalida = $this->horaAMinutos($turno->hora_salida) ?? 0;
        $tolerancia = (int) ($turno->tolerancia_min ?? 0);

        $tardanza = max(0, $entrada - ($tEntrada + $tolerancia));
        $he = max(0, $salida - $tSalida);

        return [$tardanza, round($he / 60, 2)];
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

    /** Convierte una hora (string "HH:MM", datetime o fracción Excel) a minutos del día. */
    private function horaAMinutos($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            if (is_numeric($valor)) {
                $frac = (float) $valor;
                if ($frac > 1) { // serial de fecha+hora de Excel
                    $dt = Carbon::instance(ExcelDate::excelToDateTimeObject($frac));

                    return $dt->hour * 60 + $dt->minute;
                }

                return (int) round($frac * 24 * 60); // fracción de día
            }
            $s = trim((string) $valor);
            // Si trae fecha y hora, quédate con la hora.
            $dt = Carbon::parse($s);

            return $dt->hour * 60 + $dt->minute;
        } catch (\Throwable) {
            return null;
        }
    }

    private function minutosAHora(int $min): string
    {
        return sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60);
    }
}
