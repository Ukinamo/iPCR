export function parseNullableNum(value) {
    if (value === '' || value == null) {
        return null;
    }
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
}

export function accomplishmentRatio(q3Target, q3Actual, q4Target, q4Actual) {
    const t3 = parseNullableNum(q3Target);
    const a3 = parseNullableNum(q3Actual);
    const t4 = parseNullableNum(q4Target);
    const a4 = parseNullableNum(q4Actual);

    if (t3 === null && a3 === null && t4 === null && a4 === null) {
        return { targetTotal: null, actualTotal: null, percent: null };
    }

    const targetTotal = (t3 ?? 0) + (t4 ?? 0);
    const actualTotal = (a3 ?? 0) + (a4 ?? 0);
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

export function rowPreview(row) {
    const ratio = accomplishmentRatio(
        row.rating_q3_target,
        row.rating_q3_actual,
        row.rating_q4_target,
        row.rating_q4_actual,
    );

    if (row.weight == null || row.weight === '') {
        return { ...ratio, q: null, e: null, t: null, avg: null, weighted: null };
    }

    const suggested = ratio.percent != null ? qualityFromRatio(ratio.percent) : null;
    const q = Number.isFinite(Number(row.rating_quality)) ? Number(row.rating_quality) : suggested;
    const e = Number.isFinite(Number(row.rating_efficiency)) ? Number(row.rating_efficiency) : suggested;
    const t = Number.isFinite(Number(row.rating_timeliness)) ? Number(row.rating_timeliness) : suggested;

    if (q == null || e == null || t == null) {
        return { ...ratio, q: null, e: null, t: null, avg: null, weighted: null };
    }

    const avg = (q + e + t) / 3;
    const w = Number(row.weight) / 100;

    return { ...ratio, q, e, t, avg, weighted: avg * w };
}
