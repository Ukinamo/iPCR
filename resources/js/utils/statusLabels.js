const LABELS = {
    draft: 'Draft',
    in_review: 'In Review',
    approved: 'Approved',
    returned: 'Returned',
    pending: 'Pending',
};

export function statusLabel(status) {
    if (!status) {
        return '—';
    }

    return LABELS[status] ?? String(status).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}
