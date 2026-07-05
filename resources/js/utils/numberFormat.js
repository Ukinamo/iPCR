export function formatWholeNumber(value) {
    if (value === '' || value == null) {
        return '—';
    }

    const n = Number(value);
    if (!Number.isFinite(n)) {
        return '—';
    }

    return n.toLocaleString(undefined, {
        maximumFractionDigits: 0,
        minimumFractionDigits: 0,
    });
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
