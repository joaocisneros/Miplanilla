<?php

namespace App\Domain\Asistencia;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee la hoja "Asistencia" del Excel del cliente, que trae el detalle dia por dia.
 *
 * Formato: un bloque por quincena. Cada bloque tiene una fila de fechas y debajo
 * una fila por trabajador. Cada dia ocupa 8 columnas a partir de la D:
 *
 *     D=entrada   F=salida   H=horas lab.   J=min tardanza    (dia 1)
 *     L=entrada   N=salida   P=horas lab.   R=min tardanza    (dia 2)  ...
 *
 * Las filas de trabajador no traen el DNI: la columna B es una formula que apunta
 * a la hoja de planilla. Por eso se cruza por posicion contra esa hoja, que si lo
 * tiene, en vez de emparejar por nombre (vienen con coma y sin acentos uniformes).
 *
 * Esta clase solo lee y devuelve datos: no toca la base.
 */
class LectorAsistenciaExcel
{
    /** Codigos que el cliente escribe en la celda de entrada cuando no hubo jornada. */
    private const CODIGOS = [
        'FAL' => 'FALTA',
        'FALTA' => 'FALTA',
        'FE' => 'FERIADO',
        'FER' => 'FERIADO',
        'VACA' => 'VACACIONES',
        'VAC' => 'VACACIONES',
        'LIC' => 'LICENCIA',
        'DM' => 'DESCANSO_MEDICO',
        'SUB' => 'SUBSIDIO',
        'DESC' => 'DESCANSO',
    ];

    private const PRIMERA_COLUMNA = 4;   // D
    private const ANCHO_DIA = 8;         // cada dia ocupa 8 columnas

    /** Avisos de lo que no se pudo interpretar, para mostrarlos al importar. */
    public array $avisos = [];

    /**
     * @return array<int,array{dni:string,nombre:string,fecha:string,estado:string,
     *                          entrada:?string,salida:?string,minutos_tarde:int}>
     */
    public function leer(string $ruta): array
    {
        $lector = IOFactory::createReaderForFile($ruta);
        $libro = $lector->load($ruta);

        $hoja = $libro->getSheetByName('Asistencia');
        if (! $hoja) {
            $this->avisos[] = 'El archivo no tiene una hoja llamada "Asistencia".';

            return [];
        }

        $dniPorOrden = $this->dnisDeLaPlanilla($libro);
        if (! $dniPorOrden) {
            $this->avisos[] = 'No se encontro la hoja de planilla para obtener los DNI.';

            return [];
        }

        $filas = [];
        foreach ($this->bloques($hoja) as $filaFechas) {
            $dias = $this->diasDelBloque($hoja, $filaFechas);
            if (! $dias) {
                continue;
            }

            // Los trabajadores empiezan dos filas debajo: fechas, subtitulos, datos.
            $orden = 0;
            for ($fila = $filaFechas + 2; $fila <= $hoja->getHighestRow(); $fila++) {
                $nombre = trim((string) $this->valor($hoja, 'B', $fila));

                if ($nombre === '' || str_contains($nombre, '#')) {
                    if ($orden > 0) {
                        break; // se acabo el bloque
                    }

                    continue; // todavia no empieza
                }

                $orden++;
                $dni = $dniPorOrden[$orden] ?? null;
                if (! $dni) {
                    $this->avisos[] = "Fila {$fila}: no se pudo determinar el DNI de {$nombre}.";

                    continue;
                }

                foreach ($dias as $columna => $fecha) {
                    $dia = $this->leerDia($hoja, $columna, $fila);
                    if ($dia === null) {
                        continue;
                    }
                    $filas[] = ['dni' => $dni, 'nombre' => $nombre, 'fecha' => $fecha] + $dia;
                }
            }
        }

        return $filas;
    }

    /** Filas que arrancan un bloque: las que tienen una fecha en la primera columna de dia. */
    private function bloques(Worksheet $hoja): array
    {
        $filas = [];
        $columna = Coordinate::stringFromColumnIndex(self::PRIMERA_COLUMNA);
        for ($fila = 1; $fila <= $hoja->getHighestRow(); $fila++) {
            if ($this->aFecha($this->valor($hoja, $columna, $fila))) {
                $filas[] = $fila;
            }
        }

        return $filas;
    }

    /** Columna => fecha (Y-m-d) de cada dia del bloque. */
    private function diasDelBloque(Worksheet $hoja, int $filaFechas): array
    {
        $dias = [];
        $ultima = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        for ($i = self::PRIMERA_COLUMNA; $i <= $ultima; $i += self::ANCHO_DIA) {
            $columna = Coordinate::stringFromColumnIndex($i);
            $fecha = $this->aFecha($this->valor($hoja, $columna, $filaFechas));
            if ($fecha) {
                $dias[$columna] = $fecha;
            }
        }

        return $dias;
    }

    private function leerDia(Worksheet $hoja, string $columna, int $fila): ?array
    {
        $i = Coordinate::columnIndexFromString($columna);
        $entrada = $this->valor($hoja, $columna, $fila);
        $salida = $this->valor($hoja, Coordinate::stringFromColumnIndex($i + 2), $fila);
        $tardanza = $this->valor($hoja, Coordinate::stringFromColumnIndex($i + 6), $fila);

        // Codigo de texto: ese dia no hubo jornada.
        if (is_string($entrada) && trim($entrada) !== '') {
            $codigo = self::CODIGOS[mb_strtoupper(trim($entrada))] ?? null;
            if (! $codigo) {
                // Texto que no reconocemos: se avisa y no se inventa un estado.
                $this->avisos[] = "Celda {$columna}{$fila}: no se reconoce el codigo \"{$entrada}\".";

                return null;
            }

            return ['estado' => $codigo, 'entrada' => null, 'salida' => null, 'minutos_tarde' => 0];
        }

        $horaEntrada = $this->aHora($entrada);
        if ($horaEntrada === null) {
            return null; // celda vacia: ese dia no se registro
        }

        return [
            'estado' => 'NORMAL',
            'entrada' => $horaEntrada,
            'salida' => $this->aHora($salida),
            // El Excel llega a dar tardanzas negativas (entro antes de hora): se toma 0.
            'minutos_tarde' => max(0, (int) round(is_numeric($tardanza) ? (float) $tardanza : 0)),
        ];
    }

    /**
     * DNI de la hoja de planilla, indexados por su posicion en la lista.
     *
     * Se ubica la hoja buscando la celda de encabezado "DNI" en vez de asumir una
     * columna: otras hojas del libro (Tasas, Tabla) tambien tienen numeros en la D
     * y se colaban, devolviendo documentos en cero.
     */
    private function dnisDeLaPlanilla(Spreadsheet $libro): array
    {
        foreach ($libro->getSheetNames() as $nombre) {
            if ($nombre === 'Asistencia') {
                continue;
            }

            $hoja = $libro->getSheetByName($nombre);
            [$columna, $filaEncabezado] = $this->ubicarColumnaDni($hoja);
            if (! $columna) {
                continue;
            }

            $dnis = [];
            $orden = 0;
            for ($fila = $filaEncabezado + 1; $fila <= min($hoja->getHighestRow(), 200); $fila++) {
                $valor = $this->valor($hoja, $columna, $fila);
                if (! is_numeric($valor) || $valor <= 0 || $valor > 99999999) {
                    continue;
                }
                // El Excel guarda el DNI como numero y pierde el cero inicial.
                $dnis[++$orden] = str_pad((string) (int) $valor, 8, '0', STR_PAD_LEFT);
            }

            if (count($dnis) >= 3) {
                return $dnis;
            }
        }

        return [];
    }

    /** @return array{0:?string,1:int} columna y fila donde dice "DNI" */
    private function ubicarColumnaDni(Worksheet $hoja): array
    {
        $ultima = Coordinate::columnIndexFromString($hoja->getHighestColumn());
        for ($fila = 1; $fila <= min($hoja->getHighestRow(), 30); $fila++) {
            for ($i = 1; $i <= min($ultima, 20); $i++) {
                $columna = Coordinate::stringFromColumnIndex($i);
                if (mb_strtoupper(trim((string) $this->valor($hoja, $columna, $fila))) === 'DNI') {
                    return [$columna, $fila];
                }
            }
        }

        return [null, 0];
    }

    private function valor(Worksheet $hoja, string $columna, int $fila): mixed
    {
        try {
            return $hoja->getCell($columna.$fila)->getCalculatedValue();
        } catch (\Throwable) {
            return null;
        }
    }

    private function aFecha(mixed $valor): ?string
    {
        if (! is_numeric($valor) || $valor < 40000 || $valor > 60000) {
            return null;
        }

        return XDate::excelToDateTimeObject($valor)->format('Y-m-d');
    }

    private function aHora(mixed $valor): ?string
    {
        if (! is_numeric($valor) || $valor <= 0 || $valor >= 1) {
            return null;
        }

        $minutos = (int) round($valor * 24 * 60);

        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }
}
