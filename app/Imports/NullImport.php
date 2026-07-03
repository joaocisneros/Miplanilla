<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Import mínimo: solo sirve para leer el Excel como matriz con Excel::toArray().
 * El procesamiento (mapear columnas, calcular, guardar) se hace en el controlador.
 */
class NullImport implements ToArray
{
    public function array(array $array): void
    {
        // No hace nada; el arreglo se obtiene por el valor de retorno de Excel::toArray().
    }
}
