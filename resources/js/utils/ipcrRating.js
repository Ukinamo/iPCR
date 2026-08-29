export function hasAnnualOfficeTarget(row) {
    const value = row?.annual_office_target;
    return value != null && String(value).trim() !== '';
}

export function hasWeight(row) {
    return parseNullableNum(row?.weight) != null;
}

export function isRateableRow(row) {
    return hasAnnualOfficeTarget(row) || hasWeight(row);
}

export function parseNullableNum(value) {
    if (value === '' || value == null) {
        return null;
    }
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
}

export function includedQuartersOf(source) {
    const raw = Array.isArray(source)
        ? source
        : (source?.included_quarters ?? [3, 4]);
    const unique = [...new Set(raw.map((q) => Number(q)).filter((q) => q >= 1 && q <= 4))];
    unique.sort((a, b) => a - b);
    return unique.length ? unique : [3, 4];
}

export function accomplishmentRatio(q3Target, q3Actual, q4Target, q4Actual) {
    return accomplishmentRatioForQuarters(
        {
            rating_q3_target: q3Target,
            rating_q3_actual: q3Actual,
            rating_q4_target: q4Target,
            rating_q4_actual: q4Actual,
        },
        [3, 4],
    );
}

export function accomplishmentRatioForQuarters(row, quarters = [3, 4]) {
    const list = includedQuartersOf(quarters);
    let any = false;
    let targetTotal = 0;
    let actualTotal = 0;

    for (const q of list) {
        const t = parseNullableNum(row[`rating_q${q}_target`]);
        const a = parseNullableNum(row[`rating_q${q}_actual`]);
        if (t !== null || a !== null) {
            any = true;
        }
        targetTotal += t ?? 0;
        actualTotal += a ?? 0;
    }

    if (!any) {
        return { targetTotal: null, actualTotal: null, percent: null };
    }

    const percent = targetTotal > 0 ? actualTotal / targetTotal : null;

    return { targetTotal, actualTotal, percent };
}

export function qualityFromRatio(n) {
    if (n >= 1.3) return 5;
    if (n >= 1.15) return 4;
    if (n >= 1.0) return 3;
    if (n >= 0.51) return 2;
    return 1;
}

export function suggestedRating(q3Target, q3Actual, q4Target, q4Actual) {
    const ratio = accomplishmentRatio(q3Target, q3Actual, q4Target, q4Actual);
    if (ratio.percent == null) {
        return null;
    }
    return qualityFromRatio(ratio.percent);
}

export function suggestedRatingForRow(row, quarters = [3, 4]) {
    const ratio = accomplishmentRatioForQuarters(row, quarters);
    if (ratio.percent == null) {
        return null;
    }
    return qualityFromRatio(ratio.percent);
}

export function rowPreview(row, quarters = [3, 4]) {
    const ratio = accomplishmentRatioForQuarters(row, quarters);

    if (!isRateableRow(row)) {
        return { ...ratio, q: null, e: null, t: null, avg: null, weighted: null, remarks: null };
    }

    const suggested = ratio.percent != null ? qualityFromRatio(ratio.percent) : null;
    const q = parseNullableNum(row.rating_quality) ?? suggested;
    const e = parseNullableNum(row.rating_efficiency) ?? suggested;
    const t = parseNullableNum(row.rating_timeliness) ?? suggested;

    if (q == null || e == null || t == null) {
        return { ...ratio, q: null, e: null, t: null, avg: null, weighted: null, remarks: null };
    }

    const avg = (q + e + t) / 3;
    const weightNum = parseNullableNum(row.weight);
    const weighted = weightNum != null ? avg * (weightNum / 100) : null;
    const remarks = weighted != null ? weighted : avg;

    return { ...ratio, q, e, t, avg, weighted, remarks };
}
