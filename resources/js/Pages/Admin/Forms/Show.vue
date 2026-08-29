<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { groupFormRows } from '@/utils/ipcrFormEntries';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
});

const grouped = computed(() => groupFormRows(props.template.items || []));

function formatWeight(weight) {
    return weight != null && weight !== '' ? `${Number(weight).toFixed(0)}%` : '—';
}

function sectionWeight(type) {
    return (props.template.items || [])
        .filter((item) => item.function_type === type)
        .reduce((sum, item) => sum + Number(item.weight || 0), 0);
}

const totalWeight = computed(() =>
    (props.template.items || []).reduce((sum, item) => sum + Number(item.weight || 0), 0),
);

const quarters = computed(() => {
    const list = (props.template.included_quarters || [])
        .map((q) => Number(q))
        .filter((q) => q >= 1 && q <= 4);
    return list.length ? [...new Set(list)].sort((a, b) => a - b) : [3, 4];
});
const accompColspan = computed(() => quarters.value.length * 2 + 3);
const tableColspan = computed(() => 5 + accompColspan.value + 5);
const placeholderCount = computed(() => accompColspan.value + 5);
</script>

<template>
    <Head :title="template.title || 'IPCR form'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ template.title || 'IPCR form' }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ template.period_label }} · Core {{ Number(template.weight_summary?.core || 0).toFixed(0) }}%
                        / Strategic {{ Number(template.weight_summary?.strategic || 0).toFixed(0) }}%
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.forms.edit', template.id)"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <AppIcon name="pencil" class="h-4 w-4" />
                        Edit
                    </Link>
                    <Link
                        :href="`${route('dashboard')}?tab=forms`"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <AppIcon name="arrow-left" class="h-4 w-4" />
                        Back to forms
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="overflow-x-auto rounded-xl border border-slate-300 bg-white shadow-sm">
                    <table class="min-w-[1100px] w-full border-collapse text-[11px]">
                        <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-700">
                            <tr>
                                <th class="border border-slate-300 px-2 py-1" rowspan="3">Function</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="3">Services / Programs / Indicators</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="3">Weight</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="3">Annual Office Target</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="3">Individual Annual Targets</th>
                                <th class="border border-slate-300 px-2 py-1" :colspan="accompColspan">Accomplishments</th>
                                <th class="border border-slate-300 px-2 py-1" colspan="4">Rating</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="3">Remarks</th>
                            </tr>
                            <tr>
                                <th
                                    v-for="q in quarters"
                                    :key="'h-' + q"
                                    class="border border-slate-300 px-2 py-1"
                                    colspan="2"
                                >
                                    Q{{ q }}
                                </th>
                                <th class="border border-slate-300 px-2 py-1" colspan="3">Total</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="2">Q</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="2">E</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="2">T</th>
                                <th class="border border-slate-300 px-2 py-1" rowspan="2">Avg</th>
                            </tr>
                            <tr>
                                <template v-for="q in quarters" :key="'ha-' + q">
                                    <th class="border border-slate-300 px-2 py-1">Target</th>
                                    <th class="border border-slate-300 px-2 py-1">Actual</th>
                                </template>
                                <th class="border border-slate-300 px-2 py-1">Target</th>
                                <th class="border border-slate-300 px-2 py-1">Actual</th>
                                <th class="border border-slate-300 px-2 py-1">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="groupType in ['core', 'strategic']" :key="groupType">
                                <tr
                                    v-if="grouped[groupType].length"
                                    :class="groupType === 'core' ? 'bg-blue-50/90' : 'bg-amber-50/90'"
                                >
                                    <td
                                        :colspan="tableColspan"
                                        class="border border-slate-300 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wide"
                                        :class="groupType === 'core' ? 'text-blue-900' : 'text-amber-900'"
                                    >
                                        {{ groupType === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                        · {{ sectionWeight(groupType).toFixed(0) }}%
                                    </td>
                                </tr>
                                <template v-for="(fnGroup, fgIdx) in grouped[groupType]" :key="groupType + '-' + fnGroup.key">
                                    <tr v-if="fgIdx > 0" aria-hidden="true">
                                        <td :colspan="tableColspan" class="h-2 border border-slate-300 bg-white p-0"></td>
                                    </tr>
                                    <tr
                                        v-for="(item, ri) in fnGroup.items"
                                        :key="item.id"
                                        class="align-top"
                                    >
                                        <td
                                            v-if="ri === 0"
                                            :rowspan="fnGroup.items.length"
                                            class="border border-slate-300 px-2 py-1.5 font-semibold text-slate-800"
                                        >
                                            {{ fnGroup.title }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 whitespace-pre-line text-slate-700">
                                            {{ item.description }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-center font-medium text-slate-800">
                                            {{ formatWeight(item.weight) }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-700">
                                            {{ item.annual_office_target || '—' }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-700">
                                            {{ item.individual_annual_targets || '—' }}
                                        </td>
                                        <td
                                            v-for="n in placeholderCount"
                                            :key="n"
                                            class="border border-slate-300 px-2 py-1.5 text-center text-slate-400"
                                        >—</td>
                                    </tr>
                                </template>
                            </template>
                            <tr class="bg-slate-100 font-semibold">
                                <td colspan="2" class="border border-slate-300 px-2 py-1.5 text-right">TOTAL</td>
                                <td class="border border-slate-300 px-2 py-1.5 text-center">{{ totalWeight.toFixed(0) }}%</td>
                                <td :colspan="2 + accompColspan + 3" class="border border-slate-300"></td>
                                <td class="border border-slate-300 px-2 py-1.5 text-center">—</td>
                                <td class="border border-slate-300 px-2 py-1.5 text-center">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Employee catalog</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        Employees choose this form themselves. Their copy can be edited; this original stays unchanged.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
