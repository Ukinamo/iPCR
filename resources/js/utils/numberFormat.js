export function formatWholeNumber(value) {
    if (value === '' || value == null) {
        return '—';
    }

    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '—';
    }

    return Math.round(n).toLocaleString(undefined, {
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
    });
}

export function wholeNumberOrEmpty(value) {
    if (value === '' || value == null) {
        return '';
    }

    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '';
    }

    return Math.round(n);
}

export function setWholeNumberField(row, key, rawValue) {
    if (rawValue === '' || rawValue == null) {
        row[key] = '';
        return;
    }

    const n = Number(rawValue);
    row[key] = Number.isFinite(n) ? Math.max(0, Math.round(n)) : '';
}

export function formatDecimal(value, decimals = 2) {
    if (value === '' || value == null) {
        return '—';
    }

    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '—';
    }

    return n.toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

export function roundWholeNumberForSubmit(value) {
    if (value === '' || value == null) {
        return null;
    }

    const n = Number(value);
    if (!Number.isFinite(n)) {
        return null;
    }

    return Math.round(n);
}

export function setRatingScaleField(row, key, rawValue) {
    if (rawValue === '' || rawValue == null) {
        row[key] = '';
        return;
    }

    const n = Number(rawValue);
    if (!Number.isFinite(n)) {
        row[key] = '';
        return;
    }

    row[key] = Math.min(5, Math.max(0, Math.round(n)));
}

export function ratingScaleForSubmit(value) {
    if (value === '' || value == null) {
        return null;
    }

    const n = Number(value);
    if (!Number.isFinite(n)) {
        return null;
    }

    return Math.min(5, Math.max(0, Math.round(n)));
}
