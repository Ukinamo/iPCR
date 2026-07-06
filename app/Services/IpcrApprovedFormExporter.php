<?php

namespace App\Services;

use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Fills the official IPCR Form 1 Excel template (ipcr_form.xlsx).
 *
 * Column layout (A..S):
 *   A      Function
 *   B..D   Services / Programs / Projects / Indicators
 *   E      Weight
 *   F      Annual Office Target
 *   G      Individual Annual Targets
 *   H..N   Accomplishments (Q3, Q4, Total, %)
 *   O..R   Rating (Q, E, T, Average)
 *   S      Remarks
 */
final class IpcrApprovedFormExporter
{
    private const LAST_COL = 19;

    private const COL_FUNCTION = 1;

    private const COL_INDICATOR = 2;

    private const COL_WEIGHT = 5;

    private const COL_OFFICE_TARGET = 6;

    private const COL_INDIVIDUAL_TARGET = 7;

    private const COL_Q3_TARGET = 8;

    private const COL_Q3_ACTUAL = 9;

    private const COL_Q4_TARGET = 10;

    private const COL_Q4_ACTUAL = 11;

    private const COL_TOTAL_TARGET = 12;

    private const COL_TOTAL_ACTUAL = 13;

    private const COL_PERCENT = 14;

    private const COL_QUALITY = 15;

    private const COL_EFFICIENCY = 16;

    private const COL_TIMELINESS = 17;

    private const COL_AVERAGE = 18;

    private const COL_REMARKS = 19;

    public static function templatePath(): string
    {
        $candidates = [
            base_path('ipcr_form.xlsx'),
            resource_path('templates/ipcr_form.xlsx'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('IPCR form template (ipcr_form.xlsx) was not found.');
    }

    public static function exportToSpreadsheet(Collection $submissions, User $employee): Spreadsheet
    {
        if ($submissions->isEmpty()) {
            $spreadsheet = IOFactory::load(self::templatePath());
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A19', 'No approved IPCR submissions to export.');

            return $spreadsheet;
        }

        $spreadsheet = null;

        foreach ($submissions as $index => $submission) {
            $filled = IOFactory::load(self::templatePath());

            if ($index === 0) {
                $spreadsheet = $filled;
            } else {
                $clone = clone $filled->getActiveSheet();
                $clone->setTitle(self::safeSheetTitle($submission, $index));
                $spreadsheet->addSheet($clone);
            }

            $sheet = $index === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->getSheet($spreadsheet->getSheetCount() - 1);
            if ($index > 0) {
                $sheet->setTitle(self::safeSheetTitle($submission, $index));
            }

            self::fillSheet($sheet, $submission, $employee);
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

    private static function fillSheet(Worksheet $sheet, IpcrSubmission $submission, User $employee): void
    {
        $office = (string) config('ipcr.office_name', 'CHED – MIMAROPA Regional Office');

        $sheet->setCellValue('A3', $office);
        $sheet->setCellValue(
            'A5',
            IpcrFormViewDataBuilder::resolveCommitmentStatement($submission, $employee),
        );
        $sheet->setCellValue('A6', self::periodWindow($submission));

        $styleReference = IOFactory::load(self::templatePath())->getActiveSheet();
        $dataAnchorRow = 19;
        $totalRow = self::findRow($sheet, 'TOTAL');
        $placeholderRows = $totalRow - $dataAnchorRow;

        if ($placeholderRows > 0) {
            $sheet->removeRow($dataAnchorRow, $placeholderRows);
        }

        $commitments = $submission->commitments->sortBy([
            fn (Commitment $c) => $c->function_type === 'core' ? 0 : 1,
            fn (Commitment $c) => $c->id,
        ]);

        $core = $commitments->where('function_type', 'core');
        $strategic = $commitments->where('function_type', 'strategic');
        $neededRows = self::countNeededDataRows($core, $strategic);

        if ($neededRows > 0) {
            $sheet->insertNewRowBefore($dataAnchorRow, $neededRows);
        }

        $row = $dataAnchorRow;

        if ($core->isNotEmpty()) {
            $row = self::writeSectionHeader($sheet, $row, 'CORE FUNCTIONS ('.self::pctLabel((float) $core->sum('weight')).')', $styleReference, 19);
            $row = self::writeCommitmentGroups($sheet, $row, $core, $styleReference);
        }

        if ($strategic->isNotEmpty()) {
            $row = self::writeSectionHeader($sheet, $row, 'STRATEGIC FUNCTIONS ('.self::pctLabel((float) $strategic->sum('weight')).')', $styleReference, 19);
            $row = self::writeCommitmentGroups($sheet, $row, $strategic, $styleReference);
        }

        $totalRow = self::findRow($sheet, 'TOTAL');
        self::patchTotalRow($sheet, $totalRow, $commitments, $styleReference);

        $finalRow = self::findRow($sheet, 'FINAL AVERAGE RATING');
        self::patchFinalRatingRow($sheet, $finalRow, $submission, $styleReference);

        self::finalizeSheetLayout($sheet, $dataAnchorRow, max($dataAnchorRow, $totalRow - 1));
    }

    /**
     * @param  Collection<int, Commitment>  $core
     * @param  Collection<int, Commitment>  $strategic
     */
    private static function countNeededDataRows(Collection $core, Collection $strategic): int
    {
        $rows = 0;

        if ($core->isNotEmpty()) {
            $rows++;
            $rows += self::countSectionRows($core);
        }

        if ($strategic->isNotEmpty()) {
            $rows++;
            $rows += self::countSectionRows($strategic);
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     */
    private static function countSectionRows(Collection $commitments): int
    {
        $groups = self::groupCommitmentsByTitle($commitments);
        $rows = max(0, count($groups) - 1);

        foreach ($groups as $group) {
            foreach ($group as $commitment) {
                $rows += max(1, count(self::indicatorLines($commitment)));
            }
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     * @return list<list<Commitment>>
     */
    private static function groupCommitmentsByTitle(Collection $commitments): array
    {
        $order = [];
        $groups = [];

        foreach ($commitments as $commitment) {
            $key = self::functionTitleKey($commitment);
            if (! array_key_exists($key, $groups)) {
                $order[] = $key;
                $groups[$key] = [];
            }
            $groups[$key][] = $commitment;
        }

        return array_map(fn (string $key) => $groups[$key], $order);
    }

    /**
     * @param  list<Commitment>  $commitments
     */
    private static function countRowsInTitleGroup(array $commitments): int
    {
        $rows = 0;
        foreach ($commitments as $commitment) {
            $rows += max(1, count(self::indicatorLines($commitment)));
        }

        return $rows;
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     */
    private static function countCommitmentRows(Collection $commitments): int
    {
        return self::countSectionRows($commitments);
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     */
    private static function writeCommitmentGroups(Worksheet $sheet, int $row, Collection $commitments, Worksheet $styleReference): int
    {
        $groups = self::groupCommitmentsByTitle($commitments);

        foreach ($groups as $index => $group) {
            if ($index > 0) {
                $row = self::writeFunctionSpacerRow($sheet, $row, $styleReference);
            }

            $row = self::writeFunctionTitleGroup(
                $sheet,
                $row,
                self::functionTitleKey($group[0]),
                $group,
                $styleReference,
            );
        }

        return $row;
    }

    /**
     * @param  list<Commitment>  $commitments
     */
    private static function writeFunctionTitleGroup(
        Worksheet $sheet,
        int $row,
        string $title,
        array $commitments,
        Worksheet $styleReference,
    ): int {
        $groupStart = $row;
        $groupEnd = $groupStart + self::countRowsInTitleGroup($commitments) - 1;

        if ($groupEnd > $groupStart) {
            $sheet->mergeCells(self::cell(self::COL_FUNCTION, $groupStart).':'.self::cell(self::COL_FUNCTION, $groupEnd));
        }

        $sheet->setCellValue(self::cell(self::COL_FUNCTION, $groupStart), $title);
        $sheet->getStyle(self::cell(self::COL_FUNCTION, $groupStart))
            ->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ($commitments as $commitment) {
            $row = self::writeCommitmentBlock($sheet, $row, $commitment, $styleReference, false);
        }

        return $groupEnd + 1;
    }

    private static function writeFunctionSpacerRow(Worksheet $sheet, int $row, Worksheet $styleReference): int
    {
        self::copyRowStyles($sheet, $row, $styleReference, 21, 1, self::LAST_COL);
        self::applyThinBorders($sheet, self::cell(1, $row).':'.self::col(self::LAST_COL).$row);
        $sheet->getRowDimension($row)->setRowHeight(12);

        return $row + 1;
    }

    private static function writeSectionHeader(Worksheet $sheet, int $row, string $label, Worksheet $styleReference, int $referenceRow): int
    {
        $sheet->mergeCells(self::cell(1, $row).':'.self::cell(4, $row));
        $sheet->setCellValue(self::cell(1, $row), $label);
        self::copyRowStyles($sheet, $row, $styleReference, $referenceRow, 1, self::LAST_COL);
        self::applyThinBorders($sheet, self::cell(1, $row).':'.self::col(self::LAST_COL).$row);

        return $row + 1;
    }

    private static function writeCommitmentBlock(
        Worksheet $sheet,
        int $row,
        Commitment $c,
        Worksheet $styleReference,
        bool $includeFunctionColumn = true,
    ): int {
        $lines = self::indicatorLines($c);
        $lineCount = max(1, count($lines));
        $start = $row;
        $end = $row + $lineCount - 1;

        if ($includeFunctionColumn) {
            if ($lineCount > 1) {
                $sheet->mergeCells(self::cell(self::COL_FUNCTION, $start).':'.self::cell(self::COL_FUNCTION, $end));
            }

            $sheet->setCellValue(self::cell(self::COL_FUNCTION, $start), (string) $c->title);
        }

        foreach ($lines as $index => $line) {
            $current = $start + $index;
            $sheet->mergeCells(self::cell(self::COL_INDICATOR, $current).':'.self::cell(4, $current));
            $sheet->setCellValue(self::cell(self::COL_INDICATOR, $current), $line);
            self::copyRowStyles($sheet, $current, $styleReference, $index === 0 ? 20 : 21, 1, self::LAST_COL);
        }

        if ($lineCount > 1) {
            for ($col = self::COL_WEIGHT; $col <= self::LAST_COL; $col++) {
                $sheet->mergeCells(self::cell($col, $start).':'.self::cell($col, $end));
            }
        }

        if ($c->weight !== null) {
            $sheet->setCellValue(self::cell(self::COL_WEIGHT, $start), ((float) $c->weight) / 100);
        }

        $officeTarget = trim((string) ($c->annual_office_target ?? ''));
        if ($officeTarget !== '') {
            $sheet->setCellValueExplicit(self::cell(self::COL_OFFICE_TARGET, $start), $officeTarget, DataType::TYPE_STRING);
        }

        $individualTarget = trim((string) ($c->individual_annual_targets ?? ''));
        if ($individualTarget !== '') {
            $sheet->setCellValueExplicit(self::cell(self::COL_INDIVIDUAL_TARGET, $start), $individualTarget, DataType::TYPE_STRING);
        }

        self::setWholeNum($sheet, self::COL_Q3_TARGET, $start, $c->rating_q3_target);
        self::setWholeNum($sheet, self::COL_Q3_ACTUAL, $start, $c->rating_q3_actual);
        self::setWholeNum($sheet, self::COL_Q4_TARGET, $start, $c->rating_q4_target);
        self::setWholeNum($sheet, self::COL_Q4_ACTUAL, $start, $c->rating_q4_actual);
        self::setNum($sheet, self::COL_TOTAL_TARGET, $start, $c->rating_target_total);
        self::setNum($sheet, self::COL_TOTAL_ACTUAL, $start, $c->rating_actual_total);

        if ($c->rating_percent !== null) {
            $sheet->setCellValue(self::cell(self::COL_PERCENT, $start), (float) $c->rating_percent);
        }

        if ($c->rating_quality !== null) {
            $sheet->setCellValue(self::cell(self::COL_QUALITY, $start), (int) $c->rating_quality);
        }
        if ($c->rating_efficiency !== null) {
            $sheet->setCellValue(self::cell(self::COL_EFFICIENCY, $start), (int) $c->rating_efficiency);
        }
        if ($c->rating_timeliness !== null) {
            $sheet->setCellValue(self::cell(self::COL_TIMELINESS, $start), (int) $c->rating_timeliness);
        }
        if ($c->weight !== null && $c->rating_average !== null) {
            $sheet->setCellValue(self::cell(self::COL_AVERAGE, $start), (float) $c->rating_average);
        }

        $weighted = self::weightedRemarkScore($c);
        if ($weighted !== null) {
            $sheet->setCellValue(self::cell(self::COL_REMARKS, $start), $weighted);
        }

        self::applyThinBorders($sheet, self::cell(1, $start).':'.self::col(self::LAST_COL).$end);
        self::applyTextCellAlignment($sheet, $start, $end);
        self::applyDataColumnAlignment($sheet, $start, $end);

        return $end + 1;
    }

    private static function weightedRemarkScore(Commitment $c): ?float
    {
        if ($c->weight === null) {
            return null;
        }

        if ($c->rating_weighted !== null) {
            return round((float) $c->rating_weighted, 2);
        }

        if ($c->rating_average !== null) {
            return IpcrFormRatingCalculator::weightedFromAverageAndWeight(
                (float) $c->rating_average,
                (float) $c->weight,
            );
        }

        return null;
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     */
    private static function patchTotalRow(Worksheet $sheet, int $row, Collection $commitments, Worksheet $styleReference): void
    {
        self::copyRowStyles($sheet, $row, $styleReference, 56, 1, self::LAST_COL);

        $weightTotal = round((float) $commitments->sum(fn (Commitment $c) => $c->weight ?? 0), 2);
        $sheet->setCellValue(self::cell(self::COL_WEIGHT, $row), $weightTotal / 100);

        $pct = self::totalAccomplishmentPercent($commitments);
        if ($pct !== null) {
            $sheet->setCellValue(self::cell(self::COL_PERCENT, $row), $pct);
        }

        $avgSum = round((float) $commitments->sum(fn (Commitment $c) => $c->weight !== null ? ($c->rating_average ?? 0) : 0), 2);
        if ($commitments->contains(fn (Commitment $c) => $c->weight !== null && $c->rating_average !== null)) {
            $sheet->setCellValue(self::cell(self::COL_AVERAGE, $row), $avgSum);
        }

        $weightedSum = round($commitments->sum(fn (Commitment $c) => self::weightedRemarkScore($c) ?? 0.0), 2);
        if ($commitments->contains(fn (Commitment $c) => self::weightedRemarkScore($c) !== null)) {
            $sheet->setCellValue(self::cell(self::COL_REMARKS, $row), $weightedSum);
        }

        self::applyDataColumnAlignment($sheet, $row, $row);
    }

    private static function patchFinalRatingRow(Worksheet $sheet, int $row, IpcrSubmission $submission, Worksheet $styleReference): void
    {
        self::copyRowStyles($sheet, $row, $styleReference, 59, 1, self::LAST_COL);

        if ($submission->overall_rating === null) {
            return;
        }

        $sheet->setCellValue(self::cell(2, $row), (float) $submission->overall_rating);
    }

    private static function findRow(Worksheet $sheet, string $needle): int
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            for ($col = 1; $col <= self::LAST_COL; $col++) {
                $value = (string) $sheet->getCell(self::cell($col, $row))->getValue();
                if ($value !== '' && str_contains($value, $needle)) {
                    return $row;
                }
            }
        }

        throw new RuntimeException("Could not locate template row containing \"{$needle}\".");
    }

    private static function copyRowStyles(Worksheet $target, int $targetRow, Worksheet $reference, int $referenceRow, int $fromCol, int $toCol): void
    {
        for ($col = $fromCol; $col <= $toCol; $col++) {
            $address = self::cell($col, $targetRow);
            $target->duplicateStyle($reference->getStyle(self::cell($col, $referenceRow)), $address);
        }
    }

    private static function applyThinBorders(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private static function functionTitleKey(Commitment $c): string
    {
        return (string) ($c->title ?? '');
    }

    /**
     * @return list<string>
     */
    private static function indicatorLines(Commitment $c): array
    {
        $desc = (string) ($c->description ?? '');
        if (trim($desc) === '') {
            return [(string) $c->title];
        }

        $parts = preg_split('/\r\n|\r|\n/', $desc) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($line) => $line !== ''));

        return empty($parts) ? [(string) $c->title] : $parts;
    }

    private static function setWholeNum(Worksheet $sheet, int $col, int $row, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $cell = self::cell($col, $row);
        $sheet->setCellValue($cell, (float) round((float) $value));
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
    }

    private static function setNum(Worksheet $sheet, int $col, int $row, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $sheet->setCellValue(self::cell($col, $row), (float) $value);
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     */
    private static function totalAccomplishmentPercent(Collection $commitments): ?float
    {
        $target = (float) $commitments->sum('rating_target_total');
        $actual = (float) $commitments->sum('rating_actual_total');
        if ($target <= 0) {
            return null;
        }

        return round($actual / $target * 100, 2);
    }

    private static function pctLabel(float $pct): string
    {
        return rtrim(rtrim(number_format($pct, 2), '0'), '.').'%';
    }

    private static function periodWindow(IpcrSubmission $submission): string
    {
        $year = (int) $submission->evaluation_year;

        return match ((int) $submission->evaluation_quarter) {
            1 => "January 1, {$year} to March 31, {$year}",
            2 => "April 1, {$year} to June 30, {$year}",
            3 => "July 1, {$year} to September 30, {$year}",
            4 => "October 1, {$year} to December 31, {$year}",
            default => 'Q'.$submission->evaluation_quarter." {$year}",
        };
    }

    private static function col(int $index): string
    {
        return Coordinate::stringFromColumnIndex($index);
    }

    private static function cell(int $col, int $row): string
    {
        return self::col($col).$row;
    }

    private static function finalizeSheetLayout(Worksheet $sheet, int $dataStartRow, int $dataEndRow): void
    {
        self::applyHeaderReadability($sheet);
        self::applyCommitmentTextReadability($sheet, $dataStartRow, $dataEndRow);
        self::applyDataColumnAlignment($sheet, $dataStartRow, $dataEndRow);

        $highestRow = $sheet->getHighestRow();
        $printArea = 'A1:'.self::col(self::LAST_COL).$highestRow;
        $sheet->getPageSetup()->setPrintArea($printArea);
    }

    private static function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 24,
            'B' => 18,
            'C' => 10,
            'D' => 10,
            'E' => 8,
            'F' => 11,
            'G' => 11,
            'H' => 8,
            'I' => 8,
            'J' => 8,
            'K' => 8,
            'L' => 8,
            'M' => 8,
            'N' => 8,
            'O' => 6,
            'P' => 6,
            'Q' => 6,
            'R' => 8,
            'S' => 14,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private static function applyHeaderReadability(Worksheet $sheet): void
    {
        foreach ([5, 6] as $row) {
            $sheet->getStyle("A{$row}:".self::col(self::LAST_COL).$row)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            self::autoFitRowHeight($sheet, $row, [
                [1, 120],
            ]);
        }

        foreach ([16, 17, 18] as $row) {
            $sheet->getStyle("A{$row}:".self::col(self::LAST_COL).$row)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($row)->setRowHeight($row === 16 ? 36 : 28);
        }
    }

    private static function applyCommitmentTextReadability(Worksheet $sheet, int $startRow, int $endRow): void
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $label = (string) $sheet->getCell(self::cell(1, $row))->getValue();
            if ($label !== '' && (str_contains($label, 'FUNCTIONS') || str_contains($label, 'Other Strategic'))) {
                $sheet->getStyle(self::cell(1, $row).':'.self::col(self::LAST_COL).$row)
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($row)->setRowHeight(22);

                continue;
            }

            $indicator = trim((string) $sheet->getCell(self::cell(self::COL_INDICATOR, $row))->getValue());
            if ($indicator === '') {
                $sheet->getRowDimension($row)->setRowHeight(12);

                continue;
            }

            self::applyFunctionIndicatorAlignment($sheet, $row, $row);
            self::autoFitRowHeight($sheet, $row, [
                [self::COL_FUNCTION, 24],
                [self::COL_INDICATOR, 38],
                [self::COL_OFFICE_TARGET, 11],
                [self::COL_INDIVIDUAL_TARGET, 11],
                [self::COL_REMARKS, 14],
            ]);
        }
    }

    private static function applyTextCellAlignment(Worksheet $sheet, int $startRow, int $endRow): void
    {
        self::applyFunctionIndicatorAlignment($sheet, $startRow, $endRow);

        foreach ([self::COL_OFFICE_TARGET, self::COL_INDIVIDUAL_TARGET, self::COL_REMARKS] as $col) {
            $range = self::cell($col, $startRow).':'.self::cell($col, $endRow);
            $sheet->getStyle($range)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_BOTTOM)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    private static function applyFunctionIndicatorAlignment(Worksheet $sheet, int $startRow, int $endRow): void
    {
        foreach ([self::COL_FUNCTION, self::COL_INDICATOR] as $col) {
            $range = self::cell($col, $startRow).':'.self::cell($col, $endRow);
            $sheet->getStyle($range)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP)
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    private static function applyDataColumnAlignment(Worksheet $sheet, int $startRow, int $endRow): void
    {
        for ($col = self::COL_WEIGHT; $col <= self::COL_REMARKS; $col++) {
            $range = self::cell($col, $startRow).':'.self::cell($col, $endRow);
            $sheet->getStyle($range)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_BOTTOM)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    /**
     * @param  list<array{0: int, 1: float}>  $columns
     */
    private static function autoFitRowHeight(Worksheet $sheet, int $row, array $columns): void
    {
        $maxLines = 1;

        foreach ($columns as [$col, $width]) {
            $value = (string) $sheet->getCell(self::cell($col, $row))->getCalculatedValue();
            if ($value === '') {
                continue;
            }

            $charsPerLine = max(10, (int) floor($width * 1.05));
            $wrappedLines = 0;
            foreach (preg_split('/\r\n|\r|\n/', $value) ?: [$value] as $segment) {
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }
                $wrappedLines += max(1, (int) ceil(mb_strlen($segment) / $charsPerLine));
            }

            $maxLines = max($maxLines, max(1, $wrappedLines));
        }

        $sheet->getRowDimension($row)->setRowHeight(max(18, min(96, $maxLines * 15 + 6)));
    }

    private static function improveLegendAndRatingScale(Worksheet $sheet): void
    {
        $legendRow = self::findRow($sheet, 'Legend');
        $clearThrough = $sheet->getHighestRow();

        self::unmergeRows($sheet, $legendRow, $clearThrough);

        if ($clearThrough > $legendRow) {
            $sheet->removeRow($legendRow + 1, $clearThrough - $legendRow);
        }

        $legendLines = [
            '1 - Effectiveness/Quality: The extent to which actual performance compares with targeted performance (can be measured by quantity). The degree to which objectives are achieved and the extent to which targeted problems are solved. In management, effectiveness relates to getting the right things done.',
            '2 - Efficiency: The extent to which time or resources is used for the intended task or purpose. Measures whether targets are accomplished with a minimum amount or quantity of waste, expense, or unnecessary effort.',
            '3 - Timeliness: Measures whether the deliverable was done on time based on the requirements of the law and/or clients/stakeholders. Time-related performance indicators evaluate such things as project completion deadlines, time management skills, and other time-sensitive expectations.',
        ];

        $sheet->setCellValue(self::cell(1, $legendRow), 'Legend:');
        $sheet->getStyle(self::cell(1, $legendRow))->getFont()->setBold(true);
        $sheet->getRowDimension($legendRow)->setRowHeight(18);

        $row = $legendRow + 1;
        foreach ($legendLines as $line) {
            $sheet->mergeCells(self::cell(1, $row).':'.self::col(self::LAST_COL).$row);
            $sheet->setCellValue(self::cell(1, $row), $line);
            $sheet->getStyle(self::cell(1, $row).':'.self::col(self::LAST_COL).$row)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP)
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle(self::cell(1, $row).':'.self::col(self::LAST_COL).$row)->getFont()->setSize(9);
            $sheet->getRowDimension($row)->setRowHeight(42);
            $row++;
        }

        $row++;

        $scaleRows = [
            [
                'label' => '5 - Outstanding (130% and above)',
                'description' => 'Performance represents an extraordinary level of achievement in terms of quality and time, technical skills and knowledge, ingenuity, creativity and initiative. Employees at this performance level should have demonstrated exceptional job mastery in all major areas of responsibility. Employee achievement and contributions to the organization are of marked excellence.',
                'height' => 54,
            ],
            [
                'label' => '4 - Very Satisfactory (115-129%)',
                'description' => 'Performance exceeded expectations. All goals, objectives and targets were achieved above the established standards.',
                'height' => 36,
            ],
            [
                'label' => '3 - Satisfactory (100-114%)',
                'description' => 'Performance met expectations in terms of quality of work, efficiency and timeliness. The most critical annual goals were met.',
                'height' => 36,
            ],
            [
                'label' => '2 - Unsatisfactory (51-99%)',
                'description' => 'Performance failed to meet expectations, and/or one or more of the most critical goals were not met.',
                'height' => 30,
            ],
            [
                'label' => '1 - Poor (50% and below)',
                'description' => 'Performance was consistently below expectations, and/or reasonable progress toward critical goals was not made. Significant improvement is needed in one or more important areas.',
                'height' => 36,
            ],
        ];

        $sheet->setCellValue(self::cell(1, $row), 'Rating Scale:');
        $sheet->getStyle(self::cell(1, $row))->getFont()->setBold(true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        foreach ($scaleRows as $entry) {
            $sheet->mergeCells(self::cell(1, $row).':'.self::cell(2, $row));
            $sheet->mergeCells(self::cell(3, $row).':'.self::col(self::LAST_COL).$row);
            $sheet->setCellValue(self::cell(1, $row), $entry['label']);
            $sheet->setCellValue(self::cell(3, $row), $entry['description']);

            $sheet->getStyle(self::cell(1, $row).':'.self::col(self::LAST_COL).$row)
                ->getAlignment()
                ->setWrapText(true)
                ->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle(self::cell(1, $row).':'.self::cell(2, $row))
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle(self::cell(1, $row).':'.self::cell(2, $row))
                ->getFont()
                ->setBold(true)
                ->setSize(9);
            $sheet->getStyle(self::cell(3, $row).':'.self::col(self::LAST_COL).$row)
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle(self::cell(3, $row).':'.self::col(self::LAST_COL).$row)->getFont()->setSize(9);
            $sheet->getRowDimension($row)->setRowHeight($entry['height']);
            self::applyThinBorders($sheet, self::cell(1, $row).':'.self::col(self::LAST_COL).$row);
            $row++;
        }
    }

    private static function unmergeRows(Worksheet $sheet, int $startRow, int $endRow): void
    {
        foreach (array_keys($sheet->getMergeCells()) as $merge) {
            if (preg_match('/\d+/', $merge, $match) && (int) $match[0] >= $startRow && (int) $match[0] <= $endRow) {
                $sheet->unmergeCells($merge);
            }
        }
    }
}
