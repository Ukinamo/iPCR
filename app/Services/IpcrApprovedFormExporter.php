<?php

namespace App\Services;

use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * IPCR Form 1 spreadsheet exporter.
 *
 * Column layout (A..Q, 17 columns):
 *   A  Function
 *   B  Services / Programs / Projects / Indicators
 *   C  Weight
 *   D  Annual Office Target
 *   E  Individual Annual Targets
 *   F  Q3 Target     G  Q3 Actual
 *   H  Q4 Target     I  Q4 Actual
 *   J  Total Target  K  Total Actual   L  %
 *   M  Q   N  E   O  T   P  Average
 *   Q  Remarks
 *
 * For each commitment, the description is split into indicator lines and each
 * line becomes its own row in column B. Function (A), Weight (C), Targets (D/E),
 * Accomplishments/%/Rating/Remarks (F..Q) are vertically merged across the
 * indicator lines for that commitment.
 */
final class IpcrApprovedFormExporter
{
    private const LAST_COL_INDEX = 17; // column Q

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

    private static function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    private static function cell(int $col, int $row): string
    {
        return self::col($col).$row;
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

        $L = self::L(); // 'Q'
        $institution = (string) config('app.name', 'I-PERFORM');
        $employeeName = strtoupper($employee->name);
        $employeeRole = ucwords(strtolower($employee->role?->value ?? 'Employee'));
        $periodText = self::periodWindow($submission);

        // --- Title block (rows 1-6) ---
        // FORM 1 box top-right (P1:Q1)
        $sheet->mergeCells('P1:Q1');
        $sheet->setCellValue('P1', 'FORM 1');
        $sheet->getStyle('P1:Q1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('P1:Q1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        self::thinBorders($sheet, 'P1:Q1');

        $sheet->mergeCells("A2:{$L}2");
        $sheet->setCellValue('A2', 'INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW FORM (IPCR)');
        self::titleStyle($sheet, "A2:{$L}2", 13, true);

        $sheet->mergeCells("A3:{$L}3");
        $sheet->setCellValue('A3', $institution);
        self::titleStyle($sheet, "A3:{$L}3", 11, false);

        $sheet->mergeCells("A5:{$L}6");
        $sheet->setCellValue(
            'A5',
            "I, {$employeeName}, {$employeeRole}, of {$institution}, commit to deliver and agree to be rated on the attainment "
            ."of the following targets in accordance with the indicated measures for the period\n{$periodText}"
        );
        $sheet->getStyle('A5')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getRowDimension(5)->setRowHeight(22);
        $sheet->getRowDimension(6)->setRowHeight(22);

        // --- Ratee / Date block (rows 8-10, right side) ---
        $sheet->mergeCells('N8:Q8');
        $sheet->getStyle('N8:Q8')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $sheet->mergeCells('N9:Q9');
        $sheet->setCellValue('N9', 'Ratee');
        $sheet->getStyle('N9:Q9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('N9:Q9')->getFont()->setBold(true);
        $sheet->setCellValue('N10', 'Date :');
        $sheet->getStyle('N10')->getFont()->setBold(true);
        $sheet->mergeCells('O10:Q10');
        $sheet->getStyle('O10:Q10')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);

        // --- Reviewed / Noted / Approved strip (rows 12-15) ---
        $rhd = 12;
        $rsig = 15;
        $sheet->setCellValue("A{$rhd}", 'REVIEWED');
        $sheet->setCellValue("B{$rhd}", 'NOTED');
        $sheet->setCellValue("C{$rhd}", 'Date');
        $sheet->mergeCells("D{$rhd}:O{$rhd}");
        $sheet->setCellValue("D{$rhd}", 'APPROVED BY');
        $sheet->mergeCells("P{$rhd}:Q{$rhd}");
        $sheet->setCellValue("P{$rhd}", 'DATE');
        self::tableHeaderStyle($sheet, "A{$rhd}:{$L}{$rhd}");

        $sheet->getRowDimension(13)->setRowHeight(22);
        $sheet->getRowDimension(14)->setRowHeight(22);

        $sheet->setCellValue("A{$rsig}", 'Supervising EPS/ Planning Officer');
        $sheet->setCellValue("B{$rsig}", 'Chief EPS');
        $sheet->setCellValue("C{$rsig}", '');
        $sheet->mergeCells("D{$rsig}:O{$rsig}");
        $sheet->setCellValue("D{$rsig}", 'DIRECTOR IV');
        $sheet->mergeCells("P{$rsig}:Q{$rsig}");
        self::thinBorders($sheet, "A{$rhd}:{$L}{$rsig}");
        $sheet->getStyle("A{$rsig}:{$L}{$rsig}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle("A{$rsig}:{$L}{$rsig}")->getFont()->setBold(true);
        $sheet->getRowDimension($rsig)->setRowHeight(34);

        // --- Main data header (rows 17-19) ---
        $h1 = 17;
        $h2 = 18;
        $h3 = 19;

        // Vertical merges for standalone columns
        $sheet->mergeCells("A{$h1}:A{$h3}");
        $sheet->setCellValue("A{$h1}", 'Function');
        $sheet->mergeCells("B{$h1}:B{$h3}");
        $sheet->setCellValue("B{$h1}", 'SERVICES PROGRAMS / PROJECTS / INDICATORS');
        $sheet->mergeCells("C{$h1}:C{$h3}");
        $sheet->setCellValue("C{$h1}", 'Weight');
        $sheet->mergeCells("D{$h1}:D{$h3}");
        $sheet->setCellValue("D{$h1}", 'Annual Office Target');
        $sheet->mergeCells("E{$h1}:E{$h3}");
        $sheet->setCellValue("E{$h1}", 'INDIVIDUAL ANNUAL TARGETS');
        $sheet->getStyle("E{$h1}")->getAlignment()->setTextRotation(90);

        // Accomplishments super-header across F..L
        $sheet->mergeCells("F{$h1}:L{$h1}");
        $sheet->setCellValue("F{$h1}", 'ACCOMPLISHMENTS');
        // Q3 / Q4 / Total group labels on row h2
        $sheet->mergeCells("F{$h2}:G{$h2}");
        $sheet->setCellValue("F{$h2}", 'Q3');
        $sheet->mergeCells("H{$h2}:I{$h2}");
        $sheet->setCellValue("H{$h2}", 'Q4');
        $sheet->mergeCells("J{$h2}:L{$h2}");
        $sheet->setCellValue("J{$h2}", 'Total');
        // Leaf labels on h3
        $sheet->setCellValue("F{$h3}", 'Target');
        $sheet->setCellValue("G{$h3}", 'Actual');
        $sheet->setCellValue("H{$h3}", 'Target');
        $sheet->setCellValue("I{$h3}", 'Actual');
        $sheet->setCellValue("J{$h3}", 'Target');
        $sheet->setCellValue("K{$h3}", 'Actual');
        $sheet->setCellValue("L{$h3}", '%');

        // Highlight the "Actual" columns on row h3 in yellow to match the reference form.
        foreach (['G', 'I', 'K'] as $actualCol) {
            $sheet->getStyle("{$actualCol}{$h3}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FFFF00');
        }

        // Rating super-header M..P
        $sheet->mergeCells("M{$h1}:P{$h1}");
        $sheet->setCellValue("M{$h1}", 'Rating');
        $sheet->mergeCells("M{$h2}:M{$h3}");
        $sheet->setCellValue("M{$h2}", 'Q');
        $sheet->mergeCells("N{$h2}:N{$h3}");
        $sheet->setCellValue("N{$h2}", 'E');
        $sheet->mergeCells("O{$h2}:O{$h3}");
        $sheet->setCellValue("O{$h2}", 'T');
        $sheet->mergeCells("P{$h2}:P{$h3}");
        $sheet->setCellValue("P{$h2}", 'Average');

        // Remarks Q
        $sheet->mergeCells("Q{$h1}:Q{$h3}");
        $sheet->setCellValue("Q{$h1}", 'Remarks');

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

        $row = self::writeSection($sheet, $row, 'CORE FUNCTIONS ('.self::pctLabel($corePct).'%)', $core);
        $row = self::writeSection($sheet, $row, 'STRATEGIC FUNCTIONS ('.self::pctLabel($stratPct).'%)', $strategic);

        // --- "Other Strategic Assignments" label row with blank spacer rows (matches the IPCR Form 1 layout) ---
        $sheet->setCellValue("A{$row}", 'Other Strategic Assignments');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        self::thinBorders($sheet, "A{$row}:{$L}{$row}");
        $row++;

        for ($i = 0; $i < 4; $i++) {
            self::thinBorders($sheet, "A{$row}:{$L}{$row}");
            $sheet->getRowDimension($row)->setRowHeight(18);
            $row++;
        }

        if ($commitments->isEmpty()) {
            $sheet->mergeCells("A{$row}:{$L}{$row}");
            $sheet->setCellValue("A{$row}", 'No commitments recorded for this submission.');
            self::thinBorders($sheet, "A{$row}:{$L}{$row}");
            $row++;
        }

        // --- TOTAL row ---
        $totalRow = $row;
        $sheet->mergeCells("A{$totalRow}:B{$totalRow}");
        $sheet->setCellValue("A{$totalRow}", 'TOTAL');
        $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $weightTotalPct = round((float) $commitments->sum('weight'), 2);
        $sheet->setCellValue("C{$totalRow}", $weightTotalPct / 100);
        $sheet->getStyle("C{$totalRow}")->getNumberFormat()->setFormatCode('0.00%');

        $sheet->setCellValue("J{$totalRow}", (float) $commitments->sum('rating_target_total'));
        $sheet->setCellValue("K{$totalRow}", (float) $commitments->sum('rating_actual_total'));
        $sheet->getStyle("J{$totalRow}:K{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.##');

        $pct = self::totalAccomplishmentPercent($commitments);
        if ($pct !== null) {
            $sheet->setCellValue("L{$totalRow}", $pct / 100);
            $sheet->getStyle("L{$totalRow}")->getNumberFormat()->setFormatCode('0.00%');
        }

        $avgSum = round((float) $commitments->sum('rating_average'), 2);
        $sheet->setCellValue("P{$totalRow}", $avgSum);
        $sheet->getStyle("P{$totalRow}")->getNumberFormat()->setFormatCode('0.00');

        $sheet->setCellValue("Q{$totalRow}", round((float) $commitments->sum('rating_weighted'), 2));
        $sheet->getStyle("Q{$totalRow}")->getNumberFormat()->setFormatCode('0.00');

        $sheet->getStyle("A{$totalRow}:{$L}{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:{$L}{$totalRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        self::thinBorders($sheet, "A{$h1}:{$L}{$totalRow}");
        $row = $totalRow + 1;

        // Spacer
        $row += 1;

        // --- FINAL AVERAGE RATING ---
        $farRow = $row;
        $sheet->mergeCells("A{$farRow}:B{$farRow}");
        $sheet->setCellValue("A{$farRow}", 'FINAL AVERAGE RATING');
        $sheet->getStyle("A{$farRow}")->getFont()->setBold(true);
        $sheet->mergeCells("C{$farRow}:{$L}{$farRow}");
        if ($submission->overall_rating !== null) {
            $sheet->setCellValue("C{$farRow}", (float) $submission->overall_rating);
            $sheet->getStyle("C{$farRow}")->getNumberFormat()->setFormatCode('0.00');
        }
        self::thinBorders($sheet, "A{$farRow}:{$L}{$farRow}");
        $row++;

        // --- Comments / signatures block ---
        $sheet->setCellValue("A{$row}", 'COMMENTS AND RECOMMENDATIONS FOR DEVELOPMENT PURPOSES');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("G{$row}", 'DATE');
        $sheet->mergeCells("G{$row}:H{$row}");
        $sheet->setCellValue("I{$row}", 'ASSESSED BY');
        $sheet->mergeCells("I{$row}:K{$row}");
        $sheet->setCellValue("L{$row}", 'DATE');
        $sheet->mergeCells("L{$row}:M{$row}");
        $sheet->setCellValue("N{$row}", 'FINAL RATING APPROVED BY');
        $sheet->mergeCells("N{$row}:P{$row}");
        $sheet->setCellValue("Q{$row}", 'DATE');
        self::tableHeaderStyle($sheet, "A{$row}:{$L}{$row}");
        $commentsHead = $row;
        $row++;

        for ($i = 0; $i < 2; $i++) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->mergeCells("G{$row}:H{$row}");
            $sheet->mergeCells("I{$row}:K{$row}");
            $sheet->mergeCells("L{$row}:M{$row}");
            $sheet->mergeCells("N{$row}:P{$row}");
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'DISCUSSED WITH');
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->mergeCells("G{$row}:K{$row}");
        $sheet->setCellValue("G{$row}", 'CEPS/CAO');
        $sheet->mergeCells("L{$row}:{$L}{$row}");
        $sheet->setCellValue("L{$row}", 'DIRECTOR IV');
        self::tableHeaderStyle($sheet, "A{$row}:{$L}{$row}");
        $row++;

        for ($i = 0; $i < 3; $i++) {
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->mergeCells("G{$row}:K{$row}");
            $sheet->mergeCells("L{$row}:{$L}{$row}");
            $sheet->getRowDimension($row)->setRowHeight(20);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'EMPLOYEE');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->mergeCells("G{$row}:K{$row}");
        $sheet->mergeCells("L{$row}:{$L}{$row}");
        self::thinBorders($sheet, "A{$commentsHead}:{$L}{$row}");
        $row++;

        // Spacer
        $row += 1;

        // --- Legend ---
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
            $sheet->mergeCells("A{$row}:{$L}{$row}");
            $sheet->setCellValue("A{$row}", $line);
            $sheet->getStyle("A{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}")->getFont()->setSize(9);
            $sheet->getRowDimension($row)->setRowHeight(36);
            $row++;
        }

        // --- Rating scale ---
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
            $sheet->mergeCells("E{$row}:{$L}{$row}");
            $sheet->setCellValue("E{$row}", $desc);
            $sheet->getStyle("A{$row}:{$L}{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}:{$L}{$row}")->getFont()->setSize(9);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getRowDimension($row)->setRowHeight(30);
            $row++;
        }

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
        $sheet->getStyle("A{$row}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        self::thinBorders($sheet, "A{$row}:{$L}{$row}");
        $row++;

        foreach ($rows as $c) {
            $row = self::writeCommitmentBlock($sheet, $row, $c);
        }

        return $row;
    }

    /**
     * Writes one commitment as a vertically-grouped block, returns next free row.
     */
    private static function writeCommitmentBlock(Worksheet $sheet, int $row, Commitment $c): int
    {
        $L = self::L();
        $lines = self::indicatorLines($c);
        $n = max(1, count($lines));
        $last = $row + $n - 1;

        // Column A (Function) — vertically merged, holds the title
        if ($n > 1) {
            $sheet->mergeCells(self::cell(1, $row).':'.self::cell(1, $last));
        }
        $sheet->setCellValue(self::cell(1, $row), (string) $c->title);
        $sheet->getStyle(self::cell(1, $row))->getFont()->setBold(true);
        $sheet->getStyle(self::cell(1, $row))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        // Column B (Services / Indicators) — one row per indicator line
        foreach ($lines as $i => $line) {
            $r = $row + $i;
            $sheet->setCellValue(self::cell(2, $r), $line);
            $sheet->getStyle(self::cell(2, $r))->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_TOP)
                ->setWrapText(true);
        }

        // Vertically merge every other column across the block
        if ($n > 1) {
            for ($col = 3; $col <= self::LAST_COL_INDEX; $col++) {
                $sheet->mergeCells(self::cell($col, $row).':'.self::cell($col, $last));
            }
        }

        // Weight as percent (col C)
        $sheet->setCellValue(self::cell(3, $row), ((float) $c->weight) / 100);
        $sheet->getStyle(self::cell(3, $row))->getNumberFormat()->setFormatCode('0%');

        // Annual Office Target (D) / Individual Annual Targets (E) — prefer the fields filled on the commitment.
        $aot = trim((string) ($c->annual_office_target ?? ''));
        $iat = trim((string) ($c->individual_annual_targets ?? ''));
        if ($aot !== '') {
            $sheet->setCellValueExplicit(self::cell(4, $row), $aot, DataType::TYPE_STRING);
        } elseif ($c->rating_target_total !== null && (float) $c->rating_target_total > 0) {
            $sheet->setCellValue(self::cell(4, $row), (float) $c->rating_target_total);
            $sheet->getStyle(self::cell(4, $row))->getNumberFormat()->setFormatCode('#,##0.##');
        }
        if ($iat !== '') {
            $sheet->setCellValueExplicit(self::cell(5, $row), $iat, DataType::TYPE_STRING);
        }

        // Accomplishments (F..K) + % (L)
        self::setNum($sheet, 6, $row, $c->rating_q3_target);
        self::setNum($sheet, 7, $row, $c->rating_q3_actual);
        self::setNum($sheet, 8, $row, $c->rating_q4_target);
        self::setNum($sheet, 9, $row, $c->rating_q4_actual);
        self::setNum($sheet, 10, $row, $c->rating_target_total);
        self::setNum($sheet, 11, $row, $c->rating_actual_total);
        $sheet->getStyle(self::cell(6, $row).':'.self::cell(11, $row))
            ->getNumberFormat()->setFormatCode('#,##0.##');

        if ($c->rating_percent !== null) {
            $sheet->setCellValue(self::cell(12, $row), (float) $c->rating_percent);
            $sheet->getStyle(self::cell(12, $row))->getNumberFormat()->setFormatCode('0%');
        }

        // Rating (M..P)
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

        // Remarks (Q) — supervisor-entered remark, fall back to weighted score.
        $remark = trim((string) ($c->remarks ?? ''));
        if ($remark !== '') {
            $sheet->setCellValueExplicit(self::cell(17, $row), $remark, DataType::TYPE_STRING);
        } elseif ($c->rating_weighted !== null) {
            $sheet->setCellValue(self::cell(17, $row), (float) $c->rating_weighted);
            $sheet->getStyle(self::cell(17, $row))->getNumberFormat()->setFormatCode('0.00');
        }

        // Alignment + borders across whole block
        $rangeBlock = "A{$row}:{$L}{$last}";
        $sheet->getStyle($rangeBlock)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("C{$row}:{$L}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        self::thinBorders($sheet, $rangeBlock);

        return $last + 1;
    }

    /**
     * Splits a commitment's description into indicator lines. Falls back to [title]
     * when there is no description.
     *
     * @return list<string>
     */
    private static function indicatorLines(Commitment $c): array
    {
        $desc = (string) ($c->description ?? '');
        if (trim($desc) === '') {
            return [(string) $c->title];
        }
        $parts = preg_split('/\r\n|\r|\n/', $desc) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($l) => $l !== ''));
        if (empty($parts)) {
            return [(string) $c->title];
        }

        return $parts;
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

    private static function pctLabel(float $pct): string
    {
        return rtrim(rtrim(number_format($pct, 2), '0'), '.');
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
            'A' => 26,
            'B' => 38,
            'C' => 9,
            'D' => 12,
            'E' => 13,
            'F' => 9,
            'G' => 9,
            'H' => 9,
            'I' => 9,
            'J' => 9,
            'K' => 9,
            'L' => 9,
            'M' => 6,
            'N' => 6,
            'O' => 6,
            'P' => 9,
            'Q' => 10,
        ];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }
}
