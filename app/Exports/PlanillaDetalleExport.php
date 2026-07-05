<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel de la planilla detallada con formato: cabecera con color, filtros,
 * columnas de dinero, columna NETO resaltada, bordes e inmovilización.
 *
 * @param  array<int>  $moneyCols  Columnas (1-based) a formatear como moneda.
 * @param  int  $netoCol  Columna (1-based) del NETO a resaltar.
 */
class PlanillaDetalleExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
{
    public function __construct(
        private array $headings,
        private array $rows,
        private array $moneyCols = [],
        private int $netoCol = 0,
        private string $freezeCol = 'E2', // celda donde inmovilizar (columnas de identificación a la izquierda)
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastCol = Coordinate::stringFromColumnIndex(count($this->headings));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $nCols = count($this->headings);
                $nRows = count($this->rows) + 1; // +cabecera
                $lastCol = Coordinate::stringFromColumnIndex($nCols);

                // Filtros en la cabecera + inmovilizar cabecera y las primeras columnas
                $sheet->setAutoFilter("A1:{$lastCol}1");
                $sheet->freezePane($this->freezeCol);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // Bordes finos a toda la tabla
                $sheet->getStyle("A1:{$lastCol}{$nRows}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9D9D9');

                if ($nRows > 1) {
                    // Formato de moneda
                    foreach ($this->moneyCols as $c) {
                        $L = Coordinate::stringFromColumnIndex($c);
                        $sheet->getStyle("{$L}2:{$L}{$nRows}")->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                    // Filas alternas (zebra) suave
                    for ($r = 2; $r <= $nRows; $r++) {
                        if ($r % 2 === 0) {
                            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F6FA');
                        }
                    }
                    // Resaltar columna NETO
                    if ($this->netoCol) {
                        $L = Coordinate::stringFromColumnIndex($this->netoCol);
                        $sheet->getStyle("{$L}2:{$L}{$nRows}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => '1E6B33']],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2EFDA']],
                        ]);
                    }
                }
            },
        ];
    }
}
