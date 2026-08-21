<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XDate;

/**
 * Rellena la fecha de nacimiento de los trabajadores desde los Excel del cliente.
 *
 * El campo existe desde siempre pero llego vacio en casi todos, y hace falta para
 * la planilla detallada, que lo pide como tercera columna. RENIEC no sirve aqui:
 * la consulta por DNI devuelve nombres y direccion, no la fecha.
 *
 * En las hojas de planilla la fecha va en la columna C, al lado del DNI.
 *
 *   php artisan empleados:fechas-nacimiento "C:/ruta/archivo.xlsx"
 *   php artisan empleados:fechas-nacimiento "C:/Users/HP/Downloads/*.xlsx"
 */
class ImportarFechasNacimiento extends Command
{
    protected $signature = 'empleados:fechas-nacimiento
                            {archivos* : Uno o varios .xlsx (admite comodines)}
                            {--pisar : Reemplazar tambien las fechas que ya estan cargadas}';

    protected $description = 'Carga las fechas de nacimiento desde los Excel de planilla del cliente';

    public function handle(): int
    {
        $rutas = $this->rutas($this->argument('archivos'));
        if (! $rutas) {
            $this->error('No se encontro ningun archivo con esa ruta.');

            return self::FAILURE;
        }

        $this->info('Archivos a revisar: '.count($rutas));

        $fechas = [];
        foreach ($rutas as $ruta) {
            $encontradas = $this->leerArchivo($ruta);
            $this->line('  '.basename($ruta).': '.count($encontradas).' fechas');
            $fechas += $encontradas;
        }

        if (! $fechas) {
            $this->warn('No se encontraron fechas de nacimiento en esos archivos.');

            return self::SUCCESS;
        }

        $puestas = 0;
        $yaTenian = 0;
        $sinEmpleado = 0;

        foreach ($fechas as $dni => $fecha) {
            $empleado = Employee::where('numero_documento', $dni)->first();
            if (! $empleado) {
                $sinEmpleado++;

                continue;
            }

            // Por defecto no se pisa lo ya cargado: el dato del sistema pudo
            // corregirse a mano y el Excel arrastra errores viejos.
            if ($empleado->fecha_nacimiento && ! $this->option('pisar')) {
                $yaTenian++;

                continue;
            }

            $empleado->update(['fecha_nacimiento' => $fecha]);
            $puestas++;
        }

        $this->newLine();
        $this->info("Fechas cargadas:      {$puestas}");
        $this->line("Ya tenian fecha:      {$yaTenian}");
        $this->line("DNI sin empleado:     {$sinEmpleado}");

        $faltan = Employee::whereNull('fecha_nacimiento')->count();
        if ($faltan > 0) {
            $this->newLine();
            $this->warn("Siguen sin fecha: {$faltan} trabajadores.");
            $this->line('Esos no estan en los archivos revisados: hacen falta mas Excel o cargarlos a mano.');
        }

        return self::SUCCESS;
    }

    /** Expande comodines y descarta lo que no exista. */
    private function rutas(array $argumentos): array
    {
        $rutas = [];
        foreach ($argumentos as $argumento) {
            foreach (glob($argumento) ?: [] as $ruta) {
                if (is_file($ruta) && str_ends_with(strtolower($ruta), '.xlsx')) {
                    $rutas[] = $ruta;
                }
            }
        }

        return array_values(array_unique($rutas));
    }

    /** @return array<string,string> DNI => fecha (Y-m-d) */
    private function leerArchivo(string $ruta): array
    {
        try {
            $lector = IOFactory::createReaderForFile($ruta);
            $lector->setReadDataOnly(true);
            $libro = $lector->load($ruta);
        } catch (\Throwable $e) {
            $this->warn('  No se pudo abrir '.basename($ruta).': '.$e->getMessage());

            return [];
        }

        $fechas = [];
        foreach ($libro->getSheetNames() as $nombre) {
            $hoja = $libro->getSheetByName($nombre);

            // Cada archivo ordena distinto: en las planillas el DNI va en la D y la
            // fecha en la C; en el padron de personal es al reves. Por eso las
            // columnas se ubican por su encabezado en vez de asumir posiciones.
            [$colDni, $colFecha, $filaEncabezado] = $this->ubicarColumnas($hoja);
            if (! $colDni || ! $colFecha) {
                continue;
            }

            for ($fila = $filaEncabezado + 1; $fila <= min($hoja->getHighestRow(), 300); $fila++) {
                $dni = $hoja->getCell($colDni.$fila)->getValue();
                $fecha = $hoja->getCell($colFecha.$fila)->getValue();

                if (! is_numeric($dni) || $dni < 1000000 || $dni > 99999999) {
                    continue;
                }
                // Fecha de Excel entre 1927 y 2009, para no tomar por fecha
                // cualquier numero que ande en esa columna.
                if (! is_numeric($fecha) || $fecha < 10000 || $fecha > 40000) {
                    continue;
                }

                $dni = str_pad((string) (int) $dni, 8, '0', STR_PAD_LEFT);
                $fechas[$dni] = XDate::excelToDateTimeObject($fecha)->format('Y-m-d');
            }
        }

        return $fechas;
    }

    /**
     * Ubica las columnas de DNI y fecha de nacimiento por su encabezado.
     *
     * @return array{0:?string,1:?string,2:int}
     */
    private function ubicarColumnas($hoja): array
    {
        $ultima = min(Coordinate::columnIndexFromString($hoja->getHighestColumn()), 30);

        for ($fila = 1; $fila <= min($hoja->getHighestRow(), 30); $fila++) {
            $dni = null;
            $fecha = null;

            for ($i = 1; $i <= $ultima; $i++) {
                $columna = Coordinate::stringFromColumnIndex($i);
                $texto = mb_strtoupper(preg_replace('/\s+/', ' ', trim((string) $hoja->getCell($columna.$fila)->getValue())));

                if ($texto === 'DNI' || str_starts_with($texto, 'DNI/') || str_starts_with($texto, 'DNI ')) {
                    $dni = $columna;
                } elseif (str_contains($texto, 'NACIM')) {
                    $fecha = $columna;
                }
            }

            if ($dni && $fecha) {
                return [$dni, $fecha, $fila];
            }
        }

        return [null, null, 0];
    }
}
