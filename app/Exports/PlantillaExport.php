<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Exportación genérica de plantillas (.xlsx): encabezados + filas de ejemplo.
 * Se descarga como Excel real para que abra en columnas en cualquier idioma.
 */
class PlantillaExport implements FromArray, WithHeadings
{
    public function __construct(private array $headings, private array $rows = []) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
