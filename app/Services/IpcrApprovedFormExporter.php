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
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Builds an IPCR Form 1 workbook that mirrors the CHED sample:
 * title block + commit paragraph + reviewed/noted/approved strip,
 * 3-row column header, CORE / STRATEGIC sections, TOTAL row,
 * FINAL AVERAGE RATING, comments + signature blocks, legend and rating scale.
 *
 * Column layout (A..R, 18 columns):
 *  A  Function
 *  B..C  Services / Programs / Projects / Indicators (merged)
 *  D  Weight
 *  E  Annual Office Target
 *  F  Individual Annual Targets
 *  G  Q3 Target     H  Q3 Actual
 *  I  Q4 Target     J  Q4 Actual
 *  K  Total Target  L  Total Actual  M  %
 *  N  Q   O  E   P  T   Q(col) Average
 *  R  Remarks
 */
final class IpcrApprovedFormExporter
{
    private const LAST_COL_INDEX = 18; // column R

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

    private static function L(): string
    {
        return Coordinate::stringFromColumnIndex(self::LAST_COL_INDEX);
    }

    private static function cell(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col).$row;
    }

    private static function periodWindow(IpcrSubmission $s): string
    {
        $y = (int) $s->evaluation_year;

        return match ((int) $s->evaluation_quarter) {
            1 => "January 1, {$y} to March 31, {$y}",
            2 => "April 1, {$y} to June 30, {$y}",
            3 => "July 1, {$y} to September 30, {$y}",
            4 => "October 1, {$y} to December 31, {$y}",
            default => 'Q'.$s->evaluation_quarter." {$y}",
        };
    }

    private static function renderSubmissionSheet(Worksheet $sheet, IpcrSubmission $submission, User $employee): void
    {
        self::setColumnWidths($sheet);

        $L = self::L();
        $institution = (string) config('app.name', 'I-PERFORM');
        $employeeName = strtoupper($employee->name);
        $supervisorName = $submission->supervisor?->name ?? '—';
        $periodText = self::periodWindow($submission);

        // --- Title block (rows 1-10) ---
        $sheet->setCellValue('Q1', 'FORM 1');
        $sheet->mergeCells('Q1:R1');
        $sheet->getStyle('Q1:R1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('Q1:R1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        self::thinBorders($sheet, 'Q1:R1');

        $sheet->mergeCells("A2:{$L}2");
        $sheet->setCellValue('A2', 'INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW FORM (IPCR)');
        self::titleStyle($sheet, "A2:{$L}2", 13, true);

        $sheet->mergeCells("A3:{$L}3");
        $sheet->setCellValue('A3', $institution);
        self::titleStyle($sheet, "A3:{$L}3", 11, false);

        $sheet->mergeCells("A5:{$L}6");
        $sheet->setCellValue(
            'A5',
            "I, {$employeeName}, of {$institution}, commit to deliver and agree to be rated on the attainment "
            ."of the following targets in accordance with the indicated measures for the period\n{$periodText}"
        );
        $sheet->getStyle('A5')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension(5)->setRowHeight(22);
        $sheet->getRowDimension(6)->setRowHeight(22);

        // Ratee / Date on the right (rows 8-10)
        $sheet->mergeCells('O8:R8');
        $sheet->setCellValue('O8', '');
        $sheet->getStyle('O8:R8')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $sheet->mergeCells('O9:R9');
        $sheet->setCellValue('O9', 'Ratee');
        $sheet->getStyle('O9:R9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('O9:R9')->getFont()->setBold(true);
        $sheet->setCellValue('O10', 'Date :');
        $sheet->getStyle('O10')->getFont()->setBold(true);
        $sheet->mergeCells('P10:R10');
        $sheet->getStyle('P10:R10')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

        // Reviewed / Noted / Approved strip (rows 12-14)
        $reviewHead = 12;
        $reviewSig = 14;
        $sheet->setCellValue("A{$reviewHead}", 'REVIEWED');
        $sheet->mergeCells("B{$reviewHead}:C{$reviewHead}");
        $sheet->setCellValue("B{$reviewHead}", 'NOTED');
        $sheet->setCellValue("D{$reviewHead}", 'Date');
        $sheet->mergeCells("E{$reviewHead}:P{$reviewHead}");
        $sheet->setCellValue("E{$reviewHead}", 'APPROVED BY');
        $sheet->mergeCells("Q{$reviewHead}:R{$reviewHead}");
        $sheet->setCellValue("Q{$reviewHead}", 'DATE');
        self::tableHeaderStyle($sheet, "A{$reviewHead}:R{$reviewHead}");

        $sheet->setCellValue("A{$reviewSig}", 'Supervising EPS/ Planning Officer');
        $sheet->mergeCells("B{$reviewSig}:C{$reviewSig}");
        $sheet->setCellValue("B{$reviewSig}", 'ChiefEPS');
        $sheet->setCellValue("D{$reviewSig}", '');
        $sheet->mergeCells("E{$reviewSig}:P{$reviewSig}");
        $sheet->setCellValue("E{$reviewSig}", 'DIRECTOR IV');
        $sheet->mergeCells("Q{$reviewSig}:R{$reviewSig}");
        self::thinBorders($sheet, "A{$reviewHead}:R{$reviewSig}");
        $sheet->getStyle("A{$reviewSig}:R{$reviewSig}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("A{$reviewSig}:R{$reviewSig}")->getFont()->setBold(true);
        $sheet->getRowDimension($reviewSig)->setRowHeight(30);

        // --- Main data header (rows 16-18) ---
        $h1 = 16;
        $h2 = 17;
        $h3 = 18;
        // Full-height vertical merges for non-grouped columns
        $sheet->mergeCells("A{$h1}:A{$h3}");
        $sheet->setCellValue("A{$h1}", 'Function');
        $sheet->mergeCells("B{$h1}:C{$h3}");
        $sheet->setCellValue("B{$h1}", 'SERVICES PROGRAMS / PROJECTS / INDICATORS');
        $sheet->mergeCells("D{$h1}:D{$h3}");
        $sheet->setCellValue("D{$h1}", 'Weight');
        $sheet->mergeCells("E{$h1}:E{$h3}");
        $sheet->setCellValue("E{$h1}", 'Annual Office Target');
        $sheet->mergeCells("F{$h1}:F{$h3}");
        $sheet->setCellValue("F{$h1}", 'INDIVIDUAL ANNUAL TARGETS');

        // Accomplishments super header (G..M)
        $sheet->mergeCells("G{$h1}:M{$h1}");
        $sheet->setCellValue("G{$h1}", 'ACCOMPLISHMENTS');
        // Q3 / Q4 / Total group labels
        $sheet->mergeCells("G{$h2}:H{$h2}");
        $sheet->setCellValue("G{$h2}", 'Q3');
        $sheet->mergeCells("I{$h2}:J{$h2}");
        $sheet->setCellValue("I{$h2}", 'Q4');
        $sheet->mergeCells("K{$h2}:M{$h2}");
        $sheet->setCellValue("K{$h2}", 'Total');
        $sheet->setCellValue("G{$h3}", 'Target');
        $sheet->setCellValue("H{$h3}", 'Actual');
        $sheet->setCellValue("I{$h3}", 'Target');
        $sheet->setCellValue("J{$h3}", 'Actual');
        $sheet->setCellValue("K{$h3}", 'Target');
        $sheet->setCellValue("L{$h3}", 'Actual');
        $sheet->setCellValue("M{$h3}", '%');

        // Rating super header (N..Q)
        $sheet->mergeCells("N{$h1}:Q{$h1}");
        $sheet->setCellValue("N{$h1}", 'Rating');
        $sheet->mergeCells("N{$h2}:N{$h3}");
        $sheet->setCellValue("N{$h2}", 'Q');
        $sheet->mergeCells("O{$h2}:O{$h3}");
        $sheet->setCellValue("O{$h2}", 'E');
        $sheet->mergeCells("P{$h2}:P{$h3}");
        $sheet->setCellValue("P{$h2}", 'T');
        $sheet->mergeCells("Q{$h2}:Q{$h3}");
        $sheet->setCellValue("Q{$h2}", 'Average');

        // Remarks (R)
        $sheet->mergeCells("R{$h1}:R{$h3}");
        $sheet->setCellValue("R{$h1}", 'Remarks');

        self::tableHeaderStyle($sheet, "A{$h1}:{$L}{$h3}");
        foreach ([$h1, $h2, $h3] as $rh) {
            $sheet->getRowDimension($rh)->setRowHeight(22);
        }

        // --- Data rows ---
        $commitments = $submission->commitments->sortBy([
            fn (Commitment $c) => $c->function_type === 'core' ? 0 : 1,
            fn (Commitment $c) => $c->id,
        ]);

        $core = $commitments->where('function_type', 'core');
        $strategic = $commitments->where('function_type', 'strategic');

        $corePct = round((float) $core->sum('weight'), 2);
        $stratPct = round((float) $strategic->sum('weight'), 2);

        $row = $h3 + 1;
        $dataFirst = $row;

        $row = self::writeSection($sheet, $row, 'CORE FUNCTIONS ('.rtrim(rtrim(number_format($corePct, 2), '0'), '.').'%)', $core);
        $row = self::writeSection($sheet, $row, 'STRATEGIC FUNCTIONS ('.rtrim(rtrim(number_format($stratPct, 2), '0'), '.').'%)', $strategic);

        if ($commitments->isEmpty()) {
            $sheet->mergeCells("A{$row}:{$L}{$row}");
            $sheet->setCellValue("A{$row}", 'No commitments recorded for this submission.');
            self::thinBorders($sheet, "A{$row}:{$L}{$row}");
            $row++;
        }

        // --- TOTAL row ---
        $totalRow = $row;
        $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $weightTotalPct = round((float) $commitments->sum('weight'), 2);
        $sheet->setCellValue("D{$totalRow}", $weightTotalPct / 100);
        $sheet->getStyle("D{$totalRow}")->getNumberFormat()->setFormatCode('0.00%');

        $sheet->setCellValue("K{$totalRow}", (float) $commitments->sum('rating_target_total'));
        $sheet->setCellValue("L{$totalRow}", (float) $commitments->sum('rating_actual_total'));
        $sheet->getStyle("K{$totalRow}:L{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.##');

        $pct = self::totalAccomplishmentPercent($commitments);
        if ($pct !== null) {
            $sheet->setCellValue("M{$totalRow}", $pct / 100);
            $sheet->getStyle("M{$totalRow}")->getNumberFormat()->setFormatCode('0.00%');
        }

        $avgSum = round((float) $commitments->sum('rating_average'), 2);
        $sheet->setCellValue("Q{$totalRow}", $avgSum);
        $sheet->getStyle("Q{$totalRow}")->getNumberFormat()->setFormatCode('0.00');

        $sheet->setCellValue("R{$totalRow}", round((float) $commitments->sum('rating_weighted'), 2));
        $sheet->getStyle("R{$totalRow}")->getNumberFormat()->setFormatCode('0.00');

        $sheet->getStyle("A{$totalRow}:{$L}{$totalRow}")->getFont()->setBold(true);
        self::thinBorders($sheet, "A{$h1}:{$L}{$totalRow}");
        $row = $totalRow + 1;

        // Spacer
        $row += 1;

        // --- FINAL AVERAGE RATING block ---
        $farRow = $row;
        $sheet->mergeCells("A{$farRow}:C{$farRow}");
        $sheet->setCellValue("A{$farRow}", 'FINAL AVERAGE RATING');
        $sheet->getStyle("A{$farRow}")->getFont()->setBold(true);
        $sheet->mergeCells("D{$farRow}:R{$farRow}");
        if ($submission->overall_rating !== null) {
            $sheet->setCellValue("D{$farRow}", (float) $submission->overall_rating);
            $sheet->getStyle("D{$farRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        self::thinBorders($sheet, "A{$farRow}:R{$farRow}");
        $row++;

        // Comments header row
        $sheet->setCellValue("A{$row}", 'COMMENTS AND RECOMMENDATIONS FOR DEVELOPMENT PURPOSES');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("G{$row}", 'DATE');
        $sheet->mergeCells("G{$row}:H{$row}");
        $sheet->setCellValue("I{$row}", 'ASSESSED BY');
        $sheet->mergeCells("I{$row}:K{$row}");
        $sheet->setCellValue("L{$row}", 'DATE');
        $sheet->mergeCells("L{$row}:M{$row}");
        $sheet->setCellValue("N{$row}", 'FINAL RATING APPROVED BY');
        $sheet->mergeCells("N{$row}:Q{$row}");
        $sheet->setCellValue("R{$row}", 'DATE');
        self::tableHeaderStyle($sheet, "A{$row}:R{$row}");
        $commentsHead = $row;
        $row++;

        // Two empty comment rows
        for ($i = 0; $i < 2; $i++) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->mergeCells("G{$row}:H{$row}");
            $sheet->mergeCells("I{$row}:K{$row}");
            $sheet->mergeCells("L{$row}:M{$row}");
            $sheet->mergeCells("N{$row}:Q{$row}");
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        // Discussed with / CEPS/CAO / DIRECTOR IV
        $sheet->setCellValue("A{$row}", 'DISCUSSED WITH');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->mergeCells("G{$row}:K{$row}");
        $sheet->setCellValue("G{$row}", 'CEPS/CAO');
        $sheet->mergeCells("L{$row}:R{$row}");
        $sheet->setCellValue("L{$row}", 'DIRECTOR IV');
        self::tableHeaderStyle($sheet, "A{$row}:R{$row}");
        $row++;

        // Signature spacer rows
        for ($i = 0; $i < 3; $i++) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->mergeCells("G{$row}:K{$row}");
            $sheet->mergeCells("L{$row}:R{$row}");
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        // EMPLOYEE line
        $sheet->setCellValue("A{$row}", 'EMPLOYEE');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->mergeCells("G{$row}:K{$row}");
        $sheet->mergeCells("L{$row}:R{$row}");
        self::thinBorders($sheet, "A{$commentsHead}:R{$row}");
        $row++;

        // Spacer
        $row += 1;

        // Legend
        $legendLines = [
            '1 - Effectiveness/Quality : The extent to which actual performance compares with targeted performance '
                .'(can be measured by quantity). The degree to which objectives are achieved and the extent to which '
                .'targeted problems are solved. In management, effectiveness relates to getting the right things done.',
            '2 - Efficiency : The extent to which time or resources is used for the intended task or purpose. '
                .'Measures whether targets are accomplished with a minimum amount or quantity of waste, expense, or unnecessary effort.',
            '3 - Timeliness : Measures whether the deliverable was done on time based on the requirements of the law '
                .'and/or clients/stakeholders. Time-related performance indicators evaluate such things as project completion deadlines, '
                .'time management skills, and other time-sensitive expectations.',
        ];
        $sheet->setCellValue("A{$row}", 'Legend:');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        foreach ($legendLines as $line) {
            $sheet->mergeCells("A{$row}:R{$row}");
            $sheet->setCellValue("A{$row}", $line);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}")->getFont()->setSize(9);
            $sheet->getRowDimension($row)->setRowHeight(36);
            $row++;
        }

        // Rating scale
        $row += 1;
        $sheet->setCellValue("A{$row}", 'Rating Scale:');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $scale = [
            ['5', 'Outstanding (130% above)', 'Performance represents an extraordinary level of achievement in terms of quality and time, technical skills and knowledge, ingenuity, creativity and initiative. Employees at this performance level should have demonstrated exceptional job mastery in all major areas of responsibility.'],
            ['4', 'Very Satisfactory (115-129%)', 'Performance succeeded expectations. All goals, objectives and targets were achieved above the established standards.'],
            ['3', 'Satisfactory (100-114%)', 'Performance met expectations in terms of quality of work, efficiency and timeliness. The most critical annual goals were met.'],
            ['2', 'Unsatisfactory (51-99%)', 'Performance failed to meet expectations, and/or one or more of the most critical goals were not met.'],
            ['1', 'Poor (50% & below)', 'Performance was consistently below expectations, and/or reasonable progress toward critical goals was not made. Significant improvement is needed in one or more important areas.'],
        ];
        foreach ($scale as [$score, $label, $desc]) {
            $sheet->setCellValue("A{$row}", $score);
            $sheet->mergeCells("B{$row}:D{$row}");
            $sheet->setCellValue("B{$row}", $label);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->mergeCells("E{$row}:R{$row}");
            $sheet->setCellValue("E{$row}", $desc);
            $sheet->getStyle("A{$row}:R{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}:R{$row}")->getFont()->setSize(9);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;
        }

        // Print / view niceties
        $sheet->freezePane("A{$dataFirst}");
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
    }

    private static function writeSection(Worksheet $sheet, int $row, string $label, Collection $rows): int
    {
        if ($rows->isEmpty()) {
            return $row;
        }
        $L = self::L();
        $sheet->mergeCells("A{$row}:{$L}{$row}");
        $sheet->setCellValue("A{$row}", $label);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E7EEF7');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        self::thinBorders($sheet, "A{$row}:{$L}{$row}");
        $row++;

        foreach ($rows as $c) {
            self::writeCommitmentRow($sheet, $row, $c);
            $row++;
        }

        return $row;
    }

    private static function writeCommitmentRow(Worksheet $sheet, int $row, Commitment $c): void
    {
        $L = self::L();

        // Function cell (we don't have a separate grouping field — leave the header's
        // function_type implicit via the CORE/STRATEGIC banner; put the title first line here).
        $sheet->setCellValue(self::cell(1, $row), '');

        // B..C merged: title + description
        $sheet->mergeCells(self::cell(2, $row).':'.self::cell(3, $row));
        $indicator = (string) $c->title;
        if (filled($c->description)) {
            $indicator .= "\n".$c->description;
        }
        $sheet->setCellValue(self::cell(2, $row), $indicator);
        $sheet->getStyle(self::cell(2, $row))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        // Weight as percent
        $sheet->setCellValue(self::cell(4, $row), ((float) $c->weight) / 100);
        $sheet->getStyle(self::cell(4, $row))->getNumberFormat()->setFormatCode('0%');

        // Annual Office Target / Individual Annual Targets — derive from totals if available
        $tgtTotal = $c->rating_target_total !== null ? (float) $c->rating_target_total : null;
        if ($tgtTotal !== null && $tgtTotal > 0) {
            $sheet->setCellValue(self::cell(5, $row), $tgtTotal);
            $sheet->setCellValue(self::cell(6, $row), $tgtTotal);
            $sheet->getStyle(self::cell(5, $row).':'.self::cell(6, $row))
                ->getNumberFormat()->setFormatCode('#,##0.##');
        }

        self::setNum($sheet, 7, $row, $c->rating_q3_target);
        self::setNum($sheet, 8, $row, $c->rating_q3_actual);
        self::setNum($sheet, 9, $row, $c->rating_q4_target);
        self::setNum($sheet, 10, $row, $c->rating_q4_actual);
        self::setNum($sheet, 11, $row, $c->rating_target_total);
        self::setNum($sheet, 12, $row, $c->rating_actual_total);
        $sheet->getStyle(self::cell(7, $row).':'.self::cell(12, $row))
            ->getNumberFormat()->setFormatCode('#,##0.##');

        if ($c->rating_percent !== null) {
            $sheet->setCellValue(self::cell(13, $row), (float) $c->rating_percent);
            $sheet->getStyle(self::cell(13, $row))->getNumberFormat()->setFormatCode('0%');
        }

        if ($c->rating_quality !== null) {
            $sheet->setCellValue(self::cell(14, $row), (int) $c->rating_quality);
        }
        if ($c->rating_efficiency !== null) {
            $sheet->setCellValue(self::cell(15, $row), (int) $c->rating_efficiency);
        }
        if ($c->rating_timeliness !== null) {
            $sheet->setCellValue(self::cell(16, $row), (int) $c->rating_timeliness);
        }
        if ($c->rating_average !== null) {
            $sheet->setCellValue(self::cell(17, $row), (float) $c->rating_average);
            $sheet->getStyle(self::cell(17, $row))->getNumberFormat()->setFormatCode('0.00');
        }
        if ($c->rating_weighted !== null) {
            $sheet->setCellValue(self::cell(18, $row), (float) $c->rating_weighted);
            $sheet->getStyle(self::cell(18, $row))->getNumberFormat()->setFormatCode('0.00');
        }

        $sheet->getStyle("A{$row}:{$L}{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("D{$row}:{$L}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        self::thinBorders($sheet, "A{$row}:{$L}{$row}");
        $sheet->getRowDimension($row)->setRowHeight(-1);
    }

    private static function setNum(Worksheet $sheet, int $col, int $row, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $sheet->setCellValue(self::cell($col, $row), (float) $value);
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

    private static function titleStyle(Worksheet $sheet, string $range, int $size, bool $bold): void
    {
        $sheet->getStyle($range)->getFont()->setBold($bold)->setSize($size);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private static function tableHeaderStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9D9D9');
        self::thinBorders($sheet, $range);
    }

    private static function thinBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private static function setColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 22,
            'B' => 22,
            'C' => 22,
            'D' => 9,
            'E' => 12,
            'F' => 12,
            'G' => 9,
            'H' => 9,
            'I' => 9,
            'J' => 9,
            'K' => 9,
            'L' => 9,
            'M' => 9,
            'N' => 6,
            'O' => 6,
            'P' => 6,
            'Q' => 9,
            'R' => 10,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }
}
