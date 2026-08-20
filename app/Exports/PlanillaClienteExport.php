<?php

namespace App\Exports;

use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Formato detallado solicitado por el cliente (dos niveles de encabezado). */
class PlanillaClienteExport extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithStrictNullComparison
{
    public function __construct(private array $rows, private string $anioCorto) {}

    /**
     * Las 53 columnas visibles de la hoja del cliente, en su orden exacto.
     * Los nombres se copian tal cual los escribe el (incluidas sus erratas:
     * "DIAS TRBAJADOS", "INSENTIVOS", "descuemto") para que al comparar su
     * Excel con el nuestro los encabezados coincidan.
     */
    public function array(): array
    {
        $superior = [
            'ITEM', 'APELLIDO Y NOMBRES', "FECHA DE\nNACIM.", 'DNI',
            "SUELDO\nBASICO", 'ASIG FAM', 'MOV', 'TOTAL MENSUAL', "DIAS\nTRBAJADOS",
            'DIAS FALTOS', '', 'SUBSIDIO', '', 'ADELANTOS', '', '',
            'DESCANSO', '', 'VACACIONES', '',
            'GRATIFICACIÓN JUL '.$this->anioCorto, 'GRATIFICACIÓN DIC '.$this->anioCorto, 'LICENCIA',
            'TARDANZAS', '', "TOTAL\nREM\nNETA BASICA",
            'HE', '', 'SABADOS', '', 'DOMINGO Y FERIADO', '', 'INSENTIVOS', '',
            "TOTAL\nREM NETA X\nMOVILTAL",
            "SISTEMA DE\nPENSIONES", '', '', 'ONP', 'AFP PRIMA', '', '',
            "DSCTO\nAFP", 'descuemto', "RTA.\n5TA\nCATEG", "TOTAL\nNETO A\nPAGAR",
            'ESSALUD', 'SCTR PENSIÓN', 'SCTR SALUD', 'SVL',
            'ADELANTOS', 'REINTEGRO', "NETO A\nPAGAR",
        ];

        $inferior = array_fill(0, 53, '');
        foreach ([
            9 => 'CANT', 10 => 'DESCUENTO',
            11 => 'DIAS', 12 => 'MONTO',
            13 => 'GRAT', 14 => "BONIFI\nGRAT", 15 => 'VACACIONES',
            16 => 'DIAS', 17 => 'MONTO',
            18 => 'DIAS', 19 => 'MONTO',
            23 => 'CANT', 24 => 'DESCUENTO',
            26 => 'HORAS', 27 => 'MONTO',
            28 => 'DIAS', 29 => 'MONTO',
            30 => 'DIAS', 31 => 'MONTO',
            32 => 'POR PROD.', 33 => 'OTROS',
            36 => 'COM',
            38 => '13%', 39 => '10%', 40 => 'COMISI', 41 => 'PRIMA',
            43 => "y AFP y\nONP",
            46 => '9%', 47 => '2.14%', 49 => 'DL 688',
        ] as $index => $label) {
            $inferior[$index] = $label;
        }

        return [$superior, $inferior, ...$this->rows];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'D' && $cell->getRow() >= 3) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastRow = count($this->rows) + 2;
            $lastCol = 'BA';  // 53 columnas: A..BA

            foreach (['A:I','U:W','Z:Z','AI:AI','AM:AM','AQ:AR','AS:AU','AV:AY','AZ:BA'] as $range) {
                [$from,$to] = explode(':', $range);
                for ($column=Coordinate::columnIndexFromString($from); $column<=Coordinate::columnIndexFromString($to); $column++) {
                    $letter = Coordinate::stringFromColumnIndex($column);
                    $sheet->mergeCells("{$letter}1:{$letter}2");
                }
            }
            foreach (['J1:K1','L1:M1','N1:P1','Q1:R1','S1:T1','X1:Y1','AA1:AB1','AC1:AD1','AE1:AF1','AG1:AH1','AJ1:AL1','AN1:AP1'] as $range) {
                $sheet->mergeCells($range);
            }

            $sheet->freezePane('E3');
            $sheet->setAutoFilter("A2:{$lastCol}{$lastRow}");
            $sheet->getRowDimension(1)->setRowHeight(38);
            $sheet->getRowDimension(2)->setRowHeight(32);
            $sheet->getStyle("A1:{$lastCol}2")->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>9],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1F4E78']],
                'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER,'wrapText'=>true],
                'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>'A9C4D8']]],
            ]);
            $sheet->getStyle("A1:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E1E8');
            $sheet->getStyle("A3:{$lastCol}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            for ($row=3; $row<=$lastRow; $row++) {
                if ($row % 2 === 0) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F4F8FB');
                }
            }
            foreach (['E','F','G','H','K','M','N','O','P','R','T','U','V','W','Y','Z','AB','AD','AF','AG','AH','AI','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV','AW','AX','AY','AZ','BA'] as $column) {
                $sheet->getStyle("{$column}3:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
            // Tasas de AFP: 4 decimales, si no 1.37% se veria como 1%.
            foreach (['AK', 'AL'] as $column) {
                $sheet->getStyle("{$column}3:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
            }
            foreach (['C'] as $column) {
                $sheet->getStyle("{$column}3:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            }
            $sheet->getStyle("BA3:BA{$lastRow}")->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'1E6B33']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E2F0D9']],
            ]);
            $sheet->getStyle("D3:D{$lastRow}")->getNumberFormat()->setFormatCode('@');
            $sheet->getColumnDimension('B')->setWidth(36);
            $sheet->getColumnDimension('AJ')->setWidth(18);
        }];
    }
}
