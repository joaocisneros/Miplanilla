<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Listado con formato profesional: fila de título, encabezados resaltados,
 * filas cebra, bordes, autofiltro y panel congelado. Para reportes que ve
 * el cliente (a diferencia de PlantillaExport, que es plano para importar).
 */
class ListadoExport implements FromArray, WithEvents, WithTitle
{
    public function __construct(
        private string $titulo,
        private array $headings,
        private array $rows = [],
        private array $moneyCols = [], // indices 1-based de columnas de soles
    ) {}

    public function title(): string
    {
        return mb_substr($this->titulo, 0, 31);
    }

    public function array(): array
    {
        return array_merge([[$this->titulo], $this->headings], $this->rows);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $nCols = count($this->headings);
                $ult = Coordinate::stringFromColumnIndex($nCols);
                $finData = count($this->rows) + 2;

                // Título
                $sheet->mergeCells("A1:{$ult}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
                $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
                $sheet->getRowDimension(1)->setRowHeight(26);

                // Encabezados
                $sheet->getStyle("A2:{$ult}2")->getFont()->setBold(true)->getColor()->setARGB('FF1E3A5F');
                $sheet->getStyle("A2:{$ult}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E2F3');
                $sheet->getStyle("A2:{$ult}2")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getRowDimension(2)->setRowHeight(20);

                if ($finData >= 3) {
                    // Cebra
                    for ($r = 3; $r <= $finData; $r++) {
                        if ($r % 2 === 1) {
                            $sheet->getStyle("A{$r}:{$ult}{$r}")->getFill()
                                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF4F6FA');
                        }
                    }
                    // Soles
                    foreach ($this->moneyCols as $i) {
                        $col = Coordinate::stringFromColumnIndex($i);
                        $sheet->getStyle("{$col}3:{$col}{$finData}")
                            ->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                $sheet->getStyle("A2:{$ult}{$finData}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFB7C3D7');
                $sheet->setAutoFilter("A2:{$ult}{$finData}");
                $sheet->freezePane('A3');
                foreach (range(1, $nCols) as $i) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
                }
            },
        ];
    }
}
