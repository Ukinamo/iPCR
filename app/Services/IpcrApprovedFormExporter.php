<?php

namespace App\Services;

use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds IPCR Form 1–style worksheets (titles, merged column headers, CORE/STRATEGIC blocks, totals).
 */
final class IpcrApprovedFormExporter
{
    private const LAST_COL_INDEX = 17;

    public static function exportToSpreadsheet(Collection $submissions, User $employee): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        if ($submissions->isEmpty()) {
            $sheet = new Worksheet($spreadsheet, 'IPCR');
            $spreadsheet->addSheet($sheet);
            $sheet->setCellValue('A1', 'No approved IPCR submissions to export.');
            $spreadsheet->setActiveSheetIndex(0);

            return $spreadsheet;
        }

        $index = 0;
        foreach ($submissions as $submission) {
            $title = self::safeSheetTitle($submission, $index);
            $sheet = new Worksheet($spreadsheet, $title);
            $spreadsheet->addSheet($sheet);
            self::renderSubmissionSheet($sheet, $submission, $employee);
            $index++;
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private static function safeSheetTitle(IpcrSubmission $submission, int $index): string
    {
        $base = 'Q'.$submission->evaluation_quarter.'_'.$submission->evaluation_year;
        $base = preg_replace('/[^A-Za-z0-9_]/', '_', $base) ?: 'Sheet';
        $base = substr($base, 0, 28);
        if ($index > 0) {
            $suffix = '_'.$index;
            $base = substr($base, 0, 31 - strlen($suffix)).$suffix;
        }

        return substr($base, 0, 31);
    }

    private static function lastColLetter(): string
    {
        return Coordinate::stringFromColumnIndex(self::LAST_COL_INDEX);
    }

    private static function cell(int $columnIndex1Based, int $row): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex1Based).$row;
    }

    private static function renderSubmissionSheet(Worksheet $sheet, IpcrSubmission $submission, User $employee): void
    {
        $L = self::lastColLetter();
        $periodLabel = 'Q'.$submission->evaluation_quarter.' '.$submission->evaluation_year;
        $supervisorName = $submission->supervisor?->name ?? '—';
        $institution = (string) config('app.name', 'I-PERFORM');

        $r = 1;
        $sheet->mergeCells("A{$r}:{$L}{$r}");
        $sheet->setCellValue("A{$r}", 'FORM 1');
        self::styleTitleRow($sheet, "A{$r}:{$L}{$r}", 11, false);
        $r++;

        $sheet->mergeCells("A{$r}:{$L}{$r}");
        $sheet->setCellValue("A{$r}", 'INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)');
        self::styleTitleRow($sheet, "A{$r}:{$L}{$r}", 14, true);
        $r++;

        $sheet->mergeCells("A{$r}:{$L}{$r}");
        $sheet->setCellValue("A{$r}", $institution);
        self::styleTitleRow($sheet, "A{$r}:{$L}{$r}", 11, false);
        $r++;

        $sheet->mergeCells("A{$r}:{$L}{$r}");
        $sheet->setCellValue(
            "A{$r}",
            'I, '.$employee->name.', commit to deliver the following outputs for '.$periodLabel
            .' under the supervision of '.$supervisorName.'.'
        );
        $sheet->getStyle("A{$r}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension($r)->setRowHeight(36);
        $r++;

        $r++;

        $headerTop = $r;
        $sheet->mergeCells("A{$headerTop}:A".($headerTop + 1));
        $sheet->setCellValue("A{$headerTop}", 'Major Final Output / MFO / PAP');
        $sheet->mergeCells('B'.$headerTop.':B'.($headerTop + 1));
        $sheet->setCellValue('B'.$headerTop, 'Success Indicators (Targets + Measures)');
        $sheet->mergeCells('C'.$headerTop.':C'.($headerTop + 1));
        $sheet->setCellValue('C'.$headerTop, 'Weight');
        $sheet->mergeCells('D'.$headerTop.':D'.($headerTop + 1));
        $sheet->setCellValue('D'.$headerTop, 'Office Annual Physical Targets');
        $sheet->mergeCells('E'.$headerTop.':E'.($headerTop + 1));
        $sheet->setCellValue('E'.$headerTop, 'Individual Annual Targets');

        $sheet->mergeCells('F'.$headerTop.':G'.$headerTop);
        $sheet->setCellValue('F'.$headerTop, 'Quarter 3');
        $sheet->setCellValue('F'.($headerTop + 1), 'Target');
        $sheet->setCellValue('G'.($headerTop + 1), 'Actual');

        $sheet->mergeCells('H'.$headerTop.':I'.$headerTop);
        $sheet->setCellValue('H'.$headerTop, 'Quarter 4');
        $sheet->setCellValue('H'.($headerTop + 1), 'Target');
        $sheet->setCellValue('I'.($headerTop + 1), 'Actual');

        $sheet->mergeCells('J'.$headerTop.':K'.$headerTop);
        $sheet->setCellValue('J'.$headerTop, 'Total');
        $sheet->setCellValue('J'.($headerTop + 1), 'Target');
        $sheet->setCellValue('K'.($headerTop + 1), 'Actual');

        $sheet->mergeCells('L'.$headerTop.':L'.($headerTop + 1));
        $sheet->setCellValue('L'.$headerTop, '% Accomplishment');

        $sheet->mergeCells('M'.$headerTop.':P'.$headerTop);
        $sheet->setCellValue('M'.$headerTop, 'Rating');
        $sheet->setCellValue('M'.($headerTop + 1), 'Q');
        $sheet->setCellValue('N'.($headerTop + 1), 'E');
        $sheet->setCellValue('O'.($headerTop + 1), 'T');
        $sheet->setCellValue('P'.($headerTop + 1), 'Average');

        $sheet->mergeCells('Q'.$headerTop.':Q'.($headerTop + 1));
        $sheet->setCellValue('Q'.$headerTop, 'Remarks (Weighted)');

        self::styleTableHeaderBlock($sheet, $headerTop, $headerTop + 1);
        $r = $headerTop + 2;

        $commitments = $submission->commitments->sortBy([
            fn (Commitment $c) => $c->function_type === 'core' ? 0 : 1,
            fn (Commitment $c) => $c->id,
        ]);

        $core = $commitments->where('function_type', 'core');
        $strategic = $commitments->where('function_type', 'strategic');

        $corePct = round((float) $core->sum('weight'), 2);
        $strategicPct = round((float) $strategic->sum('weight'), 2);

        if ($core->isNotEmpty()) {
            $sheet->mergeCells("A{$r}:{$L}{$r}");
            $sheet->setCellValue("A{$r}", 'A. CORE FUNCTIONS ('.$corePct.'%)');
            self::styleSectionBanner($sheet, "A{$r}:{$L}{$r}");
            $r++;
            foreach ($core as $c) {
                self::writeCommitmentRow($sheet, $r, $c);
                $r++;
            }
        }

        if ($strategic->isNotEmpty()) {
            $sheet->mergeCells("A{$r}:{$L}{$r}");
            $sheet->setCellValue("A{$r}", 'B. STRATEGIC FUNCTIONS ('.$strategicPct.'%)');
            self::styleSectionBanner($sheet, "A{$r}:{$L}{$r}");
            $r++;
            foreach ($strategic as $c) {
                self::writeCommitmentRow($sheet, $r, $c);
                $r++;
            }
        }

        if ($commitments->isEmpty()) {
            $sheet->mergeCells("A{$r}:{$L}{$r}");
            $sheet->setCellValue("A{$r}", 'No commitments recorded for this submission.');
            $r++;
        }

        $totalRow = $r;
        $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        $sheet->setCellValue("C{$totalRow}", round((float) $commitments->sum('weight') / 100, 4));
        $sheet->getStyle("C{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
        $sheet->setCellValue("J{$totalRow}", (float) $commitments->sum('rating_target_total'));
        $sheet->setCellValue("K{$totalRow}", (float) $commitments->sum('rating_actual_total'));
        $sheet->setCellValue(
            "L{$totalRow}",
            self::totalAccomplishmentPercent($commitments)
        );
        $sheet->getStyle("J{$totalRow}:L{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("L{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00"%"');
        $sheet->setCellValue("Q{$totalRow}", round((float) $commitments->sum('rating_weighted'), 4));
        $sheet->getStyle("Q{$totalRow}")->getNumberFormat()->setFormatCode('0.0000');
        self::styleTotalRow($sheet, $totalRow);
        $r++;

        $finalRow = $r;
        $sheet->mergeCells("A{$finalRow}:P{$finalRow}");
        $sheet->setCellValue("A{$finalRow}", 'FINAL AVERAGE RATING (package score)');
        $sheet->setCellValue("Q{$finalRow}", $submission->overall_rating !== null ? (float) $submission->overall_rating : null);
        $sheet->getStyle("Q{$finalRow}")->getNumberFormat()->setFormatCode('0.0000');
        self::styleFinalRow($sheet, $finalRow);
        $r++;

        $r++;
        $sheet->mergeCells("A{$r}:{$L}{$r}");
        $sheet->setCellValue(
            "A{$r}",
            'Legend: % Accomplishment = (Q3 actual + Q4 actual) / (Q3 target + Q4 target) × 100 when targets are set. '
            .'Quality (Q) is derived from the accomplishment ratio; Efficiency (E) and Timeliness (T) are rated 1–5. '
            .'Average = (Q + E + T) / 3. Remarks (Weighted) = Average × (row weight ÷ 100). Final average rating is the sum of weighted remarks when weights total 100%.'
        );
        $sheet->getStyle("A{$r}")->getFont()->setSize(9);
        $sheet->getStyle("A{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getRowDimension($r)->setRowHeight(48);

        self::setColumnWidths($sheet);
        $firstData = $headerTop + 2;
        $lastData = $totalRow;
        self::outlineDataArea($sheet, $headerTop, $lastData, 1, self::LAST_COL_INDEX);
        self::freezeBelowHeader($sheet, $firstData);
    }

    private static function totalAccomplishmentPercent(Collection $commitments): ?float
    {
        $tgt = (float) $commitments->sum('rating_target_total');
        $act = (float) $commitments->sum('rating_actual_total');
        if ($tgt <= 0) {
            return null;
        }

        return round($act / $tgt * 100, 2);
    }

    private static function writeCommitmentRow(Worksheet $sheet, int $row, Commitment $c): void
    {
        $fn = $c->function_type === 'core' ? 'CORE' : 'STRATEGIC';
        $sheet->setCellValue(self::cell(1, $row), $fn);

        $indicator = (string) $c->title;
        if ($c->description) {
            $indicator .= "\n".$c->description;
        }
        $sheet->setCellValue(self::cell(2, $row), $indicator);
        $sheet->getStyle(self::cell(2, $row))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $weightPct = (float) $c->weight;
        $sheet->setCellValue(self::cell(3, $row), round($weightPct / 100, 4));
        $sheet->getStyle(self::cell(3, $row))->getNumberFormat()->setFormatCode('0.00');

        $sheet->setCellValue(self::cell(4, $row), '—');
        $sheet->setCellValue(self::cell(5, $row), '—');

        self::setDecimalCell($sheet, 6, $row, $c->rating_q3_target);
        self::setDecimalCell($sheet, 7, $row, $c->rating_q3_actual);
        self::setDecimalCell($sheet, 8, $row, $c->rating_q4_target);
        self::setDecimalCell($sheet, 9, $row, $c->rating_q4_actual);
        self::setDecimalCell($sheet, 10, $row, $c->rating_target_total);
        self::setDecimalCell($sheet, 11, $row, $c->rating_actual_total);

        if ($c->rating_percent !== null) {
            $sheet->setCellValue(self::cell(12, $row), round((float) $c->rating_percent * 100, 2));
            $sheet->getStyle(self::cell(12, $row))->getNumberFormat()->setFormatCode('#,##0.00"%"');
        }

        if ($c->rating_quality !== null) {
            $sheet->setCellValue(self::cell(13, $row), (int) $c->rating_quality);
        }
        if ($c->rating_efficiency !== null) {
            $sheet->setCellValue(self::cell(14, $row), (int) $c->rating_efficiency);
        }
        if ($c->rating_timeliness !== null) {
            $sheet->setCellValue(self::cell(15, $row), (int) $c->rating_timeliness);
        }
        if ($c->rating_average !== null) {
            $sheet->setCellValue(self::cell(16, $row), (float) $c->rating_average);
            $sheet->getStyle(self::cell(16, $row))->getNumberFormat()->setFormatCode('0.00');
        }
        if ($c->rating_weighted !== null) {
            $sheet->setCellValue(self::cell(17, $row), (float) $c->rating_weighted);
            $sheet->getStyle(self::cell(17, $row))->getNumberFormat()->setFormatCode('0.0000');
        }

        $sheet->getStyle(self::cell(6, $row).':'.self::cell(11, $row))->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getRowDimension($row)->setRowHeight(-1);
    }

    private static function setDecimalCell(Worksheet $sheet, int $col, int $row, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $sheet->setCellValue(self::cell($col, $row), (float) $value);
    }

    private static function styleTitleRow(Worksheet $sheet, string $range, int $size, bool $bold): void
    {
        $sheet->getStyle($range)->getFont()->setBold($bold)->setSize($size);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private static function styleTableHeaderBlock(Worksheet $sheet, int $r1, int $r2): void
    {
        $L = self::lastColLetter();
        $range = "A{$r1}:{$L}{$r2}";
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');
        self::thinBorders($sheet, $range);
        $sheet->getRowDimension($r1)->setRowHeight(22);
        $sheet->getRowDimension($r2)->setRowHeight(22);
    }

    private static function styleSectionBanner(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E7EEF7');
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        self::thinBorders($sheet, $range);
    }

    private static function styleTotalRow(Worksheet $sheet, int $row): void
    {
        $L = self::lastColLetter();
        $range = "A{$row}:{$L}{$row}";
        $sheet->getStyle($range)->getFont()->setBold(true);
        self::thinBorders($sheet, $range);
    }

    private static function styleFinalRow(Worksheet $sheet, int $row): void
    {
        $L = self::lastColLetter();
        $range = "A{$row}:{$L}{$row}";
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F2F2F2');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        self::thinBorders($sheet, $range);
    }

    private static function thinBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private static function outlineDataArea(Worksheet $sheet, int $r1, int $r2, int $c1, int $c2): void
    {
        $c1L = Coordinate::stringFromColumnIndex($c1);
        $c2L = Coordinate::stringFromColumnIndex($c2);
        $sheet->getStyle("{$c1L}{$r1}:{$c2L}{$r2}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private static function freezeBelowHeader(Worksheet $sheet, int $firstDataRow): void
    {
        $sheet->freezePane('A'.$firstDataRow);
    }

    private static function setColumnWidths(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(9);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(18);
        foreach (['F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(11);
        }
        foreach (['M', 'N', 'O', 'P'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(8);
        }
        $sheet->getColumnDimension('Q')->setWidth(14);
    }
}
