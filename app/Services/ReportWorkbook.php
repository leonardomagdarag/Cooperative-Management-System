<?php

namespace App\Services;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportWorkbook
{
    public function build(array $report): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator($report['appName'])->setTitle($report['title']);

        foreach ($report['sections'] as $index => $section) {
            $sheet = $index === 0 ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle(mb_substr(preg_replace('/[^\pL\pN _-]/u', '', $section['title']), 0, 31));
            $lastColumn = Coordinate::stringFromColumnIndex(count($section['columns']));
            foreach ([$report['appName'], $report['title'], $report['subtitle'], $report['filter_summary'], $report['note'] ?? '', 'Generated '.$report['generatedAt']->format('Y-m-d H:i').' by '.$report['generatedBy']] as $row => $text) {
                $sheet->setCellValueExplicit('A'.($row + 1), $text, DataType::TYPE_STRING);
                $sheet->mergeCells('A'.($row + 1).':'.$lastColumn.($row + 1));
            }
            $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle('A3:A6')->getAlignment()->setWrapText(true);
            $sheet->getRowDimension(4)->setRowHeight(30);
            $sheet->getRowDimension(5)->setRowHeight(empty($report['note']) ? 15 : 44);

            foreach ($section['columns'] as $columnIndex => $column) {
                $letter = Coordinate::stringFromColumnIndex($columnIndex + 1);
                $sheet->setCellValueExplicit($letter.'8', $column['label'], DataType::TYPE_STRING);
                $sheet->getColumnDimension($letter)->setWidth(($column['type'] ?? 'text') === 'text' ? 30 : 21);
                foreach ($section['rows'] as $rowIndex => $row) {
                    $cell = $letter.($rowIndex + 9);
                    $value = $row[$columnIndex] ?? null;
                    $type = $column['type'] ?? 'text';
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if ($type === 'date') {
                        $sheet->setCellValueExplicit($cell, Date::PHPToExcel(Carbon::parse($value)), DataType::TYPE_NUMERIC);
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('mmm d, yyyy');
                    } elseif (in_array($type, ['money', 'number', 'percent'], true)) {
                        $sheet->setCellValueExplicit($cell, (float) $value, DataType::TYPE_NUMERIC);
                        $format = match ($type) {
                            'money' => '"PHP "#,##0.00;[Red]-"PHP "#,##0.00',
                            'percent' => '0.00"%"',
                            default => '#,##0',
                        };
                        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($format);
                    } else {
                        $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
                    }
                }
            }
            $lastRow = max(8, count($section['rows']) + 8);
            $sheet->getStyle('A8:'.$lastColumn.'8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF065F46');
            $sheet->getStyle('A8:'.$lastColumn.'8')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getRowDimension(8)->setRowHeight(30);
            $sheet->getStyle('A8:'.$lastColumn.$lastRow)->getAlignment()->setWrapText(true);
            $sheet->freezePane('A9');
            $sheet->setAutoFilter('A8:'.$lastColumn.$lastRow);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(8, 8);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            if (isset($section['totals'])) {
                $totalRow = $lastRow + 2;
                $sheet->setCellValueExplicit('A'.$totalRow, 'Totals', DataType::TYPE_STRING);
                foreach ($section['totals'] as $columnIndex => $total) {
                    $cell = Coordinate::stringFromColumnIndex($columnIndex + 1).$totalRow;
                    $sheet->setCellValueExplicit($cell, $total, DataType::TYPE_NUMERIC);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"PHP "#,##0.00;[Red]-"PHP "#,##0.00');
                }
                $sheet->getStyle('A'.$totalRow.':'.$lastColumn.$totalRow)->getFont()->setBold(true);
            }
        }

        $book->setActiveSheetIndex(0);

        return $book;
    }
}
