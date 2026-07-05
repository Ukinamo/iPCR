<script setup>
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
        const typeOrder = { core: 0, strategic: 1 };
        const ta = typeOrder[a.function_type] ?? 9;
        const tb = typeOrder[b.function_type] ?? 9;
        if (ta !== tb) return ta - tb;
        return (a.title || '').localeCompare(b.title || '');
    }),
);

function indicatorLines(c) {
    const desc = (c?.description ?? '').trim();
    if (!desc) return [c?.title ?? ''];
    const lines = desc.split(/\r\n|\r|\n/).map((l) => l.trim()).filter(Boolean);
    return lines.length ? lines : [c?.title ?? ''];
}

function functionTitleKey(c) {
    return (c.title || '').trim() || '(untitled function)';
}

function buildSectionLayout(functionType) {
    const commitments = sortedCommitments.value.filter((c) => c.function_type === functionType);
    const order = [];
    const map = new Map();

    for (const c of commitments) {
        const key = functionTitleKey(c);
        if (!map.has(key)) {
            map.set(key, { title: key, commitments: [] });
            order.push(key);
        }
        map.get(key).commitments.push(c);
    }

    return order.map((key) => {
        const group = map.get(key);
        const rows = [];

        for (const c of group.commitments) {
            const lines = indicatorLines(c);
            lines.forEach((line, lineIndex) => {
                rows.push({
                    commitment: c,
                    line,
                    lineIndex,
                    lineCount: lines.length,
                });
            });
        }

        return {
            title: group.title,
            rows,
            rowCount: rows.length,
        };
    });
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
        <table class="min-w-full border-collapse text-[11px]">
            <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-700">
                <tr>
                    <th class="border border-slate-300 px-2 py-1.5" rowspan="2">Function</th>
                    <th class="border border-slate-300 px-2 py-1.5" rowspan="2">
                        Services / Programs / Indicators
                    </th>
                    <th class="border border-slate-300 px-2 py-1.5" rowspan="2">Weight</th>
                    <th class="border border-slate-300 px-2 py-1.5" rowspan="2">Annual Office Target</th>
                    <th class="border border-slate-300 px-2 py-1.5" rowspan="2">Individual Annual Targets</th>
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
                            v-for="(row, ri) in fnGroup.rows"
                            :key="row.commitment.id + '-' + row.lineIndex"
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
                            <td class="border border-slate-300 px-2 py-1.5 text-slate-700">{{ row.line }}</td>
                            <td
                                v-if="row.lineIndex === 0"
                                :rowspan="row.lineCount"
                                class="border border-slate-300 px-2 py-1.5 text-center font-medium text-slate-800"
                            >
                                {{ row.commitment.weight != null ? Number(row.commitment.weight).toFixed(0) + '%' : '—' }}
                            </td>
                            <td
                                v-if="row.lineIndex === 0"
                                :rowspan="row.lineCount"
                                class="border border-slate-300 px-2 py-1.5 text-center text-slate-700"
                            >
                                {{ row.commitment.annual_office_target || '—' }}
                            </td>
                            <td
                                v-if="row.lineIndex === 0"
                                :rowspan="row.lineCount"
                                class="border border-slate-300 px-2 py-1.5 text-center text-slate-700"
                            >
                                {{ row.commitment.individual_annual_targets || '—' }}
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
