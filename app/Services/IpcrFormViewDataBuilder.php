<?php

namespace App\Services;

use App\Models\Commitment;
use App\Models\IpcrSubmission;
use App\Models\User;
use Illuminate\Support\Collection;

final class IpcrFormViewDataBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(IpcrSubmission $submission, User $employee, ?string $commitmentStatement = null): array
    {
        $submission->loadMissing(['commitments', 'employee', 'supervisor']);

        $commitments = $submission->commitments->sortBy([
            fn (Commitment $c) => $c->function_type === 'core' ? 0 : 1,
            fn (Commitment $c) => $c->id,
        ]);

        $core = $commitments->where('function_type', 'core')->values();
        $strategic = $commitments->where('function_type', 'strategic')->values();

        $sections = [];

        if ($core->isNotEmpty()) {
            $sections[] = self::buildSection(
                'CORE FUNCTIONS ('.self::pctLabel((float) $core->sum('weight')).')',
                $core,
            );
        }

        if ($strategic->isNotEmpty()) {
            $sections[] = self::buildSection(
                'STRATEGIC FUNCTIONS ('.self::pctLabel((float) $strategic->sum('weight')).')',
                $strategic,
            );
        }

        return [
            'form_number' => 'FORM 1',
            'office_name' => (string) config('ipcr.office_name'),
            'employee_name' => $employee->name,
            'employee_position' => (string) config('ipcr.default_position'),
            'commitment_statement' => $commitmentStatement ?? self::resolveCommitmentStatement($submission, $employee),
            'period_window' => self::periodWindow($submission),
            'ratee' => $employee->name,
            'sections' => $sections,
            'totals' => self::buildTotals($commitments),
            'final_rating' => $submission->overall_rating !== null
                ? number_format((float) $submission->overall_rating, 2)
                : null,
            'supervisor_feedback' => $submission->supervisor_feedback,
        ];
    }

    public static function defaultCommitmentStatement(User $employee): string
    {
        $name = $employee->name;
        $position = (string) config('ipcr.default_position');
        $office = (string) config('ipcr.office_name');

        return "I, {$name}, {$position}, of {$office}, commit to deliver and agree to be rated on the attainment "
            .'of the following targets in accordance with the indicated measures for the period';
    }

    public static function resolveCommitmentStatement(IpcrSubmission $submission, User $employee): string
    {
        $saved = trim((string) ($submission->commitment_statement ?? ''));

        return $saved !== '' ? $saved : self::defaultCommitmentStatement($employee);
    }

    public static function periodWindow(IpcrSubmission $submission): string
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

    /**
     * @param  Collection<int, Commitment>  $commitments
     * @return array<string, mixed>
     */
    private static function buildSection(string $label, Collection $commitments): array
    {
        $groups = self::groupCommitmentsByTitle($commitments);
        $rows = [];
        $groupIndex = 0;

        foreach ($groups as $group) {
            if ($groupIndex > 0) {
                $rows[] = ['type' => 'spacer'];
            }

            $title = self::functionTitleKey($group[0]);
            $groupRows = [];

            foreach ($group as $commitment) {
                $lines = self::indicatorLines($commitment);
                $lineCount = max(1, count($lines));
                $cells = self::commitmentCells($commitment);

                foreach ($lines as $lineIndex => $line) {
                    $groupRows[] = [
                        'type' => 'data',
                        'indicator' => $line,
                        'indent' => self::indentLevel($line, $lineIndex, count($lines)),
                        'bold' => $lineIndex === 0 && count($group) === 1 && $line === $title,
                        'show_title' => $lineIndex === 0 && $groupIndex >= 0 && count($group) > 0,
                        'cells' => $lineIndex === 0 ? $cells : null,
                        'rowspan' => $lineIndex === 0 ? $lineCount : 0,
                    ];
                }
            }

            $needsTitleRow = count($group) > 1 || ($groupRows[0]['indicator'] ?? '') !== $title;
            if ($needsTitleRow) {
                array_unshift($groupRows, [
                    'type' => 'title',
                    'indicator' => $title,
                    'indent' => 0,
                    'bold' => true,
                ]);
            } elseif (! empty($groupRows)) {
                $groupRows[0]['bold'] = true;
            }

            $rows = array_merge($rows, $groupRows);
            $groupIndex++;
        }

        $dataRowCount = count(array_filter(
            $rows,
            fn (array $r) => ($r['type'] ?? '') !== 'spacer',
        ));

        return [
            'label' => $label,
            'rowspan' => max(1, $dataRowCount),
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Commitment>  $commitments
     * @return array<string, mixed>
     */
    private static function buildTotals(Collection $commitments): array
    {
        $weightTotal = round((float) $commitments->sum(fn (Commitment $c) => $c->weight ?? 0), 2);
        $pct = self::totalAccomplishmentPercent($commitments);
        $avgSum = round((float) $commitments->sum(fn (Commitment $c) => $c->weight !== null ? ($c->rating_average ?? 0) : 0), 2);
        $weightedSum = round($commitments->sum(fn (Commitment $c) => self::weightedRemarkScore($c) ?? 0.0), 2);

        return [
            'weight' => $weightTotal > 0 ? self::formatWeight($weightTotal) : null,
            'percent' => $pct !== null ? number_format($pct, 2) : null,
            'average' => $commitments->contains(fn (Commitment $c) => $c->weight !== null && $c->rating_average !== null)
                ? number_format($avgSum, 2)
                : null,
            'weighted' => $commitments->contains(fn (Commitment $c) => self::weightedRemarkScore($c) !== null)
                ? number_format($weightedSum, 2)
                : null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function commitmentCells(Commitment $c): array
    {
        $individual = trim((string) ($c->individual_annual_targets ?? ''));

        return [
            'weight' => $c->weight !== null ? self::formatWeight((float) $c->weight) : null,
            'office_target' => self::displayText($c->annual_office_target),
            'q3_target' => $individual !== '' ? $individual : self::displayNum($c->rating_q3_target, true),
            'q3_actual' => self::displayNum($c->rating_q3_actual, true),
            'q4_target' => self::displayNum($c->rating_q4_target, true),
            'q4_actual' => self::displayNum($c->rating_q4_actual, true),
            'total_target' => self::displayNum($c->rating_target_total),
            'total_actual' => self::displayNum($c->rating_actual_total),
            'percent' => $c->rating_percent !== null ? number_format((float) $c->rating_percent, 2) : null,
            'quality' => $c->rating_quality !== null ? (string) (int) $c->rating_quality : null,
            'efficiency' => $c->rating_efficiency !== null ? (string) (int) $c->rating_efficiency : null,
            'timeliness' => $c->rating_timeliness !== null ? (string) (int) $c->rating_timeliness : null,
            'average' => $c->weight !== null && $c->rating_average !== null
                ? number_format((float) $c->rating_average, 2)
                : null,
            'weighted' => self::weightedRemarkScore($c) !== null
                ? number_format(self::weightedRemarkScore($c), 2)
                : null,
            'remarks' => self::weightedRemarkScore($c) !== null
                ? number_format(self::weightedRemarkScore($c), 2)
                : null,
        ];
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
            return [''];
        }

        $parts = preg_split('/\r\n|\r|\n/', $desc) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($line) => $line !== ''));

        return empty($parts) ? [''] : $parts;
    }

    private static function indentLevel(string $line, int $lineIndex, int $totalLines): int
    {
        if ($lineIndex === 0 && $totalLines === 1) {
            return 0;
        }

        if (preg_match('/^[a-z]\.\s/i', $line)) {
            return 2;
        }

        if (preg_match('/^(Enforcement|Monitoring|Others|HEI|Stake)/i', $line)) {
            return 2;
        }

        if (strlen($line) > 80) {
            return 3;
        }

        return 1;
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

    private static function formatWeight(float $weight): string
    {
        return rtrim(rtrim(number_format($weight, 2), '0'), '.').'%';
    }

    private static function displayText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : null;
    }

    private static function displayNum(mixed $value, bool $whole = false): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $num = (float) $value;

        return $whole ? (string) (int) round($num) : rtrim(rtrim(number_format($num, 2), '0'), '.');
    }
}
