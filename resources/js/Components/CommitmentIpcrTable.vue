<script setup>
import { groupFormRows } from '@/utils/ipcrFormEntries';
import { computed } from 'vue';

const props = defineProps({
    commitments: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const sortedCommitments = computed(() =>
    [...(props.commitments || [])].sort((a, b) => {
        const ta = a.function_type === 'core' ? 0 : 1;
        const tb = b.function_type === 'core' ? 0 : 1;
        if (ta !== tb) return ta - tb;
        const so = Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0);
        if (so !== 0) return so;
        return Number(a.id || 0) - Number(b.id || 0);
    }),
);

function indicatorText(c) {
    return (c?.description ?? '').trim();
}

function displayValue(value) {
    if (value == null) return '—';
    const text = String(value).trim();
    return text === '' ? '—' : text;
}

function buildSectionLayout(functionType) {
    return groupFormRows(sortedCommitments.value)[functionType].map((group) => ({
        title: (group.title || '').trim(),
        commitments: group.items,
        rowCount: group.items.length,
    }));
}

const sectionLayout = computed(() => ({
    core: buildSectionLayout('core'),
    strategic: buildSectionLayout('strategic'),
}));

function sectionWeightTotal(functionType) {
    return sortedCommitments.value
        .filter((c) => c.function_type === functionType)
        .reduce((sum, c) => sum + Number(c.weight || 0), 0);
}

const packageWeightTotal = computed(() =>
    sortedCommitments.value.reduce((sum, c) => sum + Number(c.weight || 0), 0),
);

const hasRows = computed(() => sortedCommitments.value.length > 0);
</script>

<template>
    <div v-if="hasRows" class="overflow-x-auto rounded-lg border border-slate-300 bg-white">
        <p class="border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] text-slate-500 sm:hidden">
            Swipe sideways to see all columns
        </p>
        <table class="min-w-[720px] w-full border-collapse text-[11px]">
            <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-700">
                <tr>
                    <th class="border border-slate-300 px-2 py-1.5">Function</th>
                    <th class="border border-slate-300 px-2 py-1.5">
                        Services / Programs / Indicators
                    </th>
                    <th class="border border-slate-300 px-2 py-1.5">Weight</th>
                    <th class="border border-slate-300 px-2 py-1.5">Annual Office Target</th>
                    <th class="border border-slate-300 px-2 py-1.5">Individual Annual Targets</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="group in ['core', 'strategic']" :key="group">
                    <tr v-if="sectionLayout[group].length" :class="group === 'core' ? 'bg-blue-50/90' : 'bg-amber-50/90'">
                        <td
                            colspan="5"
                            class="border border-slate-300 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wide"
                            :class="group === 'core' ? 'text-blue-900' : 'text-amber-900'"
                        >
                            {{ group === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                            · {{ sectionWeightTotal(group).toFixed(0) }}%
                        </td>
                    </tr>
                    <template v-for="(fnGroup, fgIdx) in sectionLayout[group]" :key="group + '-' + fgIdx">
                        <tr v-if="fgIdx > 0" aria-hidden="true">
                            <td colspan="5" class="h-2 border border-slate-300 bg-white p-0"></td>
                        </tr>
                        <tr
                            v-for="(commitment, ri) in fnGroup.commitments"
                            :key="commitment.id ?? `${group}-${fgIdx}-${ri}`"
                            class="align-top"
                        >
                            <td
                                v-if="ri === 0"
                                :rowspan="fnGroup.rowCount"
                                class="border border-slate-300 px-2 py-1.5 align-top font-semibold text-slate-800"
                                :class="compact ? 'max-w-[140px]' : 'min-w-[120px]'"
                            >
                                {{ fnGroup.title }}
                            </td>
                            <td class="border border-slate-300 px-2 py-1.5 whitespace-pre-line text-slate-700">
                                {{ indicatorText(commitment) }}
                            </td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center font-medium text-slate-800">
                                {{ commitment.weight != null && commitment.weight !== ''
                                    ? Number(commitment.weight).toFixed(0) + '%'
                                    : '—' }}
                            </td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-700">
                                {{ displayValue(commitment.annual_office_target) }}
                            </td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-700">
                                {{ displayValue(commitment.individual_annual_targets) }}
                            </td>
                        </tr>
                    </template>
                </template>
                <tr class="bg-slate-100 font-semibold">
                    <td colspan="2" class="border border-slate-300 px-2 py-1.5 text-right">TOTAL</td>
                    <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-800">
                        {{ packageWeightTotal.toFixed(0) }}%
                    </td>
                    <td colspan="2" class="border border-slate-300"></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
