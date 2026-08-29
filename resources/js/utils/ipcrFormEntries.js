function rowType(row) {
    return row?.function_type === 'strategic' ? 'strategic' : 'core';
}

function rowTitle(row) {
    return row?.title ?? '';
}

function sameFunctionGroup(last, row) {
    if (!last) {
        return false;
    }
    if (last.function_type && rowType(last) !== rowType(row)) {
        return false;
    }
    if (last.function_group != null && row.function_group != null) {
        return Number(last.function_group) === Number(row.function_group);
    }

    return rowTitle(last) === rowTitle(row);
}

export function itemsToFormEntries(items) {
    const entries = [];
    let current = null;

    for (const item of items || []) {
        if (!sameFunctionGroup(current, item)) {
            current = {
                enabled: true,
                function_type: rowType(item),
                title: rowTitle(item),
                function_group: item.function_group ?? null,
                _uid: `e-${rowType(item)}-${item.function_group ?? entries.length}-${item.id ?? Date.now()}`,
                items: [],
            };
            entries.push(current);
        }
        current.title = rowTitle(item);
        current.items.push({
            id: item.id ?? null,
            _uid: item.id ?? `i-${Date.now()}-${Math.random()}`,
            description: item.description ?? '',
            weight: item.weight != null && item.weight !== '' ? Number(item.weight) : null,
            annual_office_target: item.annual_office_target ?? '',
            individual_annual_targets: item.individual_annual_targets ?? '',
        });
    }

    if (!entries.some((entry) => entry.function_type === 'core')) {
        entries.unshift({
            enabled: true,
            function_type: 'core',
            title: '',
            function_group: null,
            _uid: `e-core-new-${Date.now()}`,
            items: [{
                _uid: `i-core-new-${Date.now()}`,
                description: '',
                weight: null,
                annual_office_target: '',
                individual_annual_targets: '',
            }],
        });
    }

    if (!entries.some((entry) => entry.function_type === 'strategic')) {
        entries.push({
            enabled: true,
            function_type: 'strategic',
            title: '',
            function_group: null,
            _uid: `e-strategic-new-${Date.now()}`,
            items: [{
                _uid: `i-strategic-new-${Date.now() + 1}`,
                description: '',
                weight: null,
                annual_office_target: '',
                individual_annual_targets: '',
            }],
        });
    }

    return entries;
}

export function flattenFormEntries(entries) {
    let group = 0;
    let sortOrder = 0;

    return (entries || [])
        .filter((entry) => entry.enabled !== false)
        .flatMap((entry) => {
            const functionGroup = group;
            group += 1;

            return (entry.items || []).map((item) => ({
                id: item.id ?? null,
                function_type: entry.function_type,
                function_group: functionGroup,
                sort_order: sortOrder++,
                title: entry.title,
                description: item.description,
                weight: item.weight === '' || item.weight == null ? null : item.weight,
                annual_office_target: item.annual_office_target,
                individual_annual_targets: item.individual_annual_targets,
            }));
        });
}

export function groupFormRows(rows) {
    const groups = { core: [], strategic: [] };
    const lastByType = { core: null, strategic: null };

    (rows || []).forEach((row, index) => {
        const type = rowType(row);
        const last = lastByType[type];
        if (!sameFunctionGroup(last, { ...row, function_type: type })) {
            const group = {
                key: `${type}-${row.function_group ?? 'i'}-${index}`,
                title: rowTitle(row),
                function_type: type,
                function_group: row.function_group ?? null,
                items: [],
                indexes: [],
            };
            groups[type].push(group);
            lastByType[type] = group;
        }
        lastByType[type].title = rowTitle(row) || lastByType[type].title;
        lastByType[type].items.push(row);
        lastByType[type].indexes.push(index);
    });

    return groups;
}
