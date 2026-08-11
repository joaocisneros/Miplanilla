<?php

namespace App\Exports;

use Maatwebsite\Excel\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Formato detallado solicitado por el cliente (dos niveles de encabezado). */
class PlanillaClienteExport extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents
{
    public function __construct(private array $rows, private string $gratificacionTitulo) {}

    public function array(): array
    {
        $superior = [
            'ITEM', 'APELLIDO Y NOMBRES', "FECHA DE\nNACIM.", 'DNI', "FECHA\nINGRESO", "FECHA\nDE CESE",
            'GÉNERO', 'TIPO DE CONTRATO', 'CATEGORÍA OCUPACIONAL', 'ONP', 'SCTR', 'SENATI', 'COD AFP',
            'ÁREA', 'CARGO', "SUELDO\nBÁSICO", 'ASIG. FAM.', 'MOV.', 'POR FUERA', 'TOTAL MENSUAL', 'DÍAS TRABAJADOS',
            'DESCANSO MÉDICO', '', 'SUBSIDIO', '', 'ADELANTOS', '', '', '', 'VACACIONES', '', 'LICENCIA',
            'LIQ. VACACIONES', '', 'CTS', '', '', 'LICENCIA HIJO ENFERMO', '', "TOTAL\nREM. NETA", "TOTAL\nMOVIL",
            'DÍAS FALTOS', '', 'TARDANZAS', '', 'REINTEGRO', "SISTEMA DE\nPENSIONES", 'COMISIÓN AFP', '',
            'ONP', 'AFP PRIMA', '', '', "DSCTO\nAFP", "RTA. 5TA\nCATEG.", $this->gratificacionTitulo,
            'TOTAL A PAGAR', 'ESSALUD', 'SCTR PENSIÓN', 'SCTR SALUD', 'SVL', 'ADELANTOS', '',
        ];
        $inferior = array_fill(0, 63, '');
        foreach ([21=>'DÍAS',22=>'MONTO',23=>'DÍAS',24=>'MONTO',25=>'GRAT.',26=>"BONIF.\nGRAT.",27=>'VACACIONES',28=>'TOTAL',
            29=>'DÍAS',30=>'MONTO',32=>'GRAT.',33=>'VAC.',34=>'MONTO',35=>'DÍAS',36=>'MONTO',37=>'DÍAS',38=>'MONTO',
            41=>'CANT.',42=>'DESCUENTO',43=>'CANT.',44=>'DESCUENTO',47=>'TIPO',48=>'TASA',49=>'13%',50=>'10%',
            51=>'COMISIÓN',52=>'PRIMA',57=>'9%',58=>'2.14%',60=>'DL 688',61=>'DESCONTADO',62=>'PAGADO'] as $index=>$label) {
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
            $lastCol = 'BK';

            foreach (['A:U','AF:AF','AN:AO','AT:AU','BB:BE'] as $range) {
                [$from,$to] = explode(':', $range);
                for ($column=Coordinate::columnIndexFromString($from); $column<=Coordinate::columnIndexFromString($to); $column++) {
                    $letter = Coordinate::stringFromColumnIndex($column);
                    $sheet->mergeCells("{$letter}1:{$letter}2");
                }
            }
            foreach (['V1:W1','X1:Y1','Z1:AC1','AD1:AE1','AG1:AH1','AI1:AK1','AL1:AM1','AP1:AQ1','AR1:AS1','AV1:AW1','AY1:BA1','BJ1:BK1'] as $range) {
                $sheet->mergeCells($range);
            }

            $sheet->freezePane('F3');
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
            foreach (['P','Q','R','S','T','W','Y','Z','AA','AB','AC','AE','AF','AG','AH','AI','AK','AM','AN','AO','AQ','AS','AT','AX','AY','AZ','BA','BB','BC','BD','BE','BF','BG','BH','BI','BJ','BK'] as $column) {
                $sheet->getStyle("{$column}3:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
            foreach (['C','E','F'] as $column) {
                $sheet->getStyle("{$column}3:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            }
            $sheet->getStyle("BE3:BE{$lastRow}")->applyFromArray([
                'font'=>['bold'=>true,'color'=>['rgb'=>'1E6B33']],
                'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'E2F0D9']],
            ]);
            $sheet->getStyle("D3:D{$lastRow}")->getNumberFormat()->setFormatCode('@');
            $sheet->getColumnDimension('B')->setWidth(36);
            foreach (['H','I','N','O','AU'] as $column) $sheet->getColumnDimension($column)->setWidth(20);
        }];
    }
}
