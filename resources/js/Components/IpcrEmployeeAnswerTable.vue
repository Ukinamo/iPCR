<script setup>
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatWholeNumber, setRatingScaleField, setWholeNumberField } from '@/utils/numberFormat';
import { groupFormRows } from '@/utils/ipcrFormEntries';
import { isRateableRow, rowPreview, suggestedRatingForRow } from '@/utils/ipcrRating';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const rows = defineModel('rows', { type: Array, required: true });

const props = defineProps({
    editable: {
        type: Boolean,
        default: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    submitLabel: {
        type: String,
        default: 'Save accomplishments',
    },
    showView: {
        type: Boolean,
        default: true,
    },
    showCancel: {
        type: Boolean,
        default: true,
    },
    showBack: {
        type: Boolean,
        default: false,
    },
    backHref: {
        type: String,
        default: '',
    },
    showPackageSubmit: {
        type: Boolean,
        default: false,
    },
    packageSubmitProcessing: {
        type: Boolean,
        default: false,
    },
    showSaveButton: {
        type: Boolean,
        default: true,
    },
    includedQuarters: {
        type: Array,
        default: () => [3, 4],
    },
});

const emit = defineEmits(['submit', 'cancel', 'view', 'package-submit']);

function confirmCancel() {
    if (window.confirm('Do you want to cancel?')) {
        emit('cancel');
    }
}

const grouped = computed(() => groupFormRows(rows.value));

function onWholeAccomplishment(row, key, event) {
    setWholeNumberField(row, key, event?.target?.value);
    onAccomplishmentChange(row);
}

function onAccomplishmentChange(row) {
    const suggested = suggestedRatingForRow(row, props.includedQuarters);
    if (!isRateableRow(row)) {
        row.rating_quality = null;
        row.rating_efficiency = null;
        row.rating_timeliness = null;
        return;
    }
    if (suggested == null) {
        return;
    }
    row.rating_quality = suggested;
    row.rating_efficiency = suggested;
    row.rating_timeliness = suggested;
}

function onRatingScale(row, key, event) {
    if (!isRateableRow(row)) {
        row.rating_quality = null;
        row.rating_efficiency = null;
        row.rating_timeliness = null;
        return;
    }
    setRatingScaleField(row, key, event?.target?.value);
}

function preview(row) {
    return rowPreview(row, props.includedQuarters);
}

function formatPercent(percent) {
    return percent != null ? `${(percent * 100).toFixed(0)}%` : '—';
}

function sectionWeight(type) {
    return rows.value
        .filter((row) => row.function_type === type)
        .reduce((sum, row) => sum + Number(row.weight || 0), 0);
}

const totalWeight = computed(() =>
    rows.value.reduce((sum, row) => sum + Number(row.weight || 0), 0),
);

const totalAverage = computed(() => {
    let sum = 0;
    let hasAny = false;
    for (const row of rows.value) {
        const p = preview(row);
        if (p.avg == null) continue;
        sum += p.avg;
        hasAny = true;
    }
    return hasAny ? sum : null;
});

const totalWeighted = computed(() => {
    let sum = 0;
    let hasAny = false;
    for (const row of rows.value) {
        const p = preview(row);
        if (p.weighted == null) continue;
        sum += p.weighted;
        hasAny = true;
    }
    return hasAny ? sum : null;
});

const cell = 'border border-slate-300 px-0.5 py-1 text-center align-middle';
const inputClass = 'h-7 w-full min-w-0 rounded border-gray-300 px-0.5 py-0 text-center text-[10px] shadow-none focus:border-indigo-500 focus:ring-indigo-500';

const quarters = computed(() => {
    const list = (props.includedQuarters || []).map((q) => Number(q)).filter((q) => q >= 1 && q <= 4);
    return list.length ? [...new Set(list)].sort((a, b) => a - b) : [3, 4];
});
const accompColspan = computed(() => quarters.value.length * 2 + 3);
const tableColspan = computed(() => 5 + accompColspan.value + 5);
const quarterColWidth = computed(() => `${Math.max(3.5, 28 / Math.max(quarters.value.length, 1))}%`);
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-start justify-between gap-3 px-1">
            <p class="text-sm text-slate-600">
                Function, Indicators, Weight, and Targets come from your administrator.
                Enter accomplishments for the included quarters. Q, E, and T auto-fill from accomplishment % when Weight or Annual Office Target is filled; you can still edit them (0–5). Average and Remarks follow those ratings.
            </p>
            <div class="flex shrink-0 flex-wrap justify-end gap-2">
                <SecondaryButton v-if="showView" type="button" @click="emit('view')">
                    View
                </SecondaryButton>
                <SecondaryButton v-if="showCancel" type="button" :disabled="processing" @click="confirmCancel" class="inline-flex items-center rounded-md bg-black px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm hover:bg-zinc-800">
                    Cancel Submission
                </SecondaryButton>
            </div>
        </div>

        <div class="w-full overflow-hidden rounded-lg border border-slate-300">
            <table class="w-full table-fixed border-collapse text-[10px] leading-tight">
                <colgroup>
                    <col style="width: 11%" />
                    <col style="width: 14%" />
                    <col style="width: 5%" />
                    <col style="width: 7%" />
                    <col style="width: 7%" />
                    <col v-for="n in quarters.length * 2" :key="'cq-' + n" :style="{ width: quarterColWidth }" />
                    <col style="width: 4.5%" />
                    <col style="width: 4.5%" />
                    <col style="width: 4%" />
                    <col style="width: 4%" />
                    <col style="width: 4%" />
                    <col style="width: 4%" />
                    <col style="width: 5%" />
                    <col style="width: 5.5%" />
                </colgroup>
                <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-700">
                    <tr>
                        <th :class="cell" rowspan="3">Function</th>
                        <th :class="cell" rowspan="3">Services / Programs / Indicators</th>
                        <th :class="cell" rowspan="3">Weight</th>
                        <th :class="cell" rowspan="3">Annual Office Target</th>
                        <th :class="cell" rowspan="3">Individual Annual Targets</th>
                        <th :class="cell" :colspan="accompColspan">Accomplishments</th>
                        <th :class="cell" colspan="4">Rating</th>
                        <th :class="cell" rowspan="3">Remarks</th>
                    </tr>
                    <tr>
                        <th v-for="q in quarters" :key="'h-' + q" :class="cell" colspan="2">Q{{ q }}</th>
                        <th :class="cell" colspan="3">Total</th>
                        <th :class="cell" rowspan="2">Q</th>
                        <th :class="cell" rowspan="2">E</th>
                        <th :class="cell" rowspan="2">T</th>
                        <th :class="cell" rowspan="2">Avg</th>
                    </tr>
                    <tr>
                        <template v-for="q in quarters" :key="'ha-' + q">
                            <th :class="cell">Target</th>
                            <th :class="cell">Actual</th>
                        </template>
                        <th :class="cell">Target</th>
                        <th :class="cell">Actual</th>
                        <th :class="cell">%</th>
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
                                class="border border-slate-300 px-2 py-1.5 text-center text-[10px] font-bold uppercase tracking-wide"
                                :class="groupType === 'core' ? 'text-blue-900' : 'text-amber-900'"
                            >
                                {{ groupType === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                · {{ sectionWeight(groupType).toFixed(0) }}%
                            </td>
                        </tr>
                        <template v-for="(fnGroup, fgIdx) in grouped[groupType]" :key="groupType + '-' + fnGroup.key">
                            <tr v-if="fgIdx > 0" aria-hidden="true">
                                <td :colspan="tableColspan" class="h-1.5 border border-slate-300 bg-white p-0"></td>
                            </tr>
                            <tr
                                v-for="(rowIndex, ri) in fnGroup.indexes"
                                :key="rows[rowIndex].id ?? rowIndex"
                                class="align-top"
                            >
                                <td
                                    v-if="ri === 0"
                                    :rowspan="fnGroup.indexes.length"
                                    class="border border-slate-300 px-1 py-1 text-left font-semibold break-words text-slate-800"
                                >
                                    {{ fnGroup.title }}
                                </td>
                                <td class="border border-slate-300 px-1 py-1 text-left break-words text-slate-700">
                                    {{ rows[rowIndex].description }}
                                </td>
                                <td :class="cell + ' font-medium text-slate-800'">
                                    {{ rows[rowIndex].weight != null && rows[rowIndex].weight !== ''
                                        ? Number(rows[rowIndex].weight).toFixed(0) + '%'
                                        : '—' }}
                                </td>
                                <td :class="cell + ' break-words text-slate-700'">
                                    {{ rows[rowIndex].annual_office_target || '—' }}
                                </td>
                                <td :class="cell + ' break-words text-slate-700'">
                                    {{ rows[rowIndex].individual_annual_targets || '—' }}
                                </td>
                                <template v-for="q in quarters" :key="'q-' + q">
                                    <td :class="cell">
                                        <input
                                            v-if="editable"
                                            :value="rows[rowIndex][`rating_q${q}_target`]"
                                            type="number"
                                            step="1"
                                            min="0"
                                            inputmode="numeric"
                                            :class="inputClass"
                                            @input="onWholeAccomplishment(rows[rowIndex], `rating_q${q}_target`, $event)"
                                        />
                                        <span v-else>{{ formatWholeNumber(rows[rowIndex][`rating_q${q}_target`]) }}</span>
                                    </td>
                                    <td :class="cell">
                                        <input
                                            v-if="editable"
                                            :value="rows[rowIndex][`rating_q${q}_actual`]"
                                            type="number"
                                            step="1"
                                            min="0"
                                            inputmode="numeric"
                                            :class="inputClass"
                                            @input="onWholeAccomplishment(rows[rowIndex], `rating_q${q}_actual`, $event)"
                                        />
                                        <span v-else>{{ formatWholeNumber(rows[rowIndex][`rating_q${q}_actual`]) }}</span>
                                    </td>
                                </template>
                                <td :class="cell + ' text-slate-700'">
                                    {{ formatWholeNumber(preview(rows[rowIndex]).targetTotal) }}
                                </td>
                                <td :class="cell + ' text-slate-700'">
                                    {{ formatWholeNumber(preview(rows[rowIndex]).actualTotal) }}
                                </td>
                                <td :class="cell + ' text-slate-700'">
                                    {{ formatPercent(preview(rows[rowIndex]).percent) }}
                                </td>
                                <td :class="cell">
                                    <input
                                        v-if="editable && isRateableRow(rows[rowIndex])"
                                        :value="rows[rowIndex].rating_quality"
                                        type="number"
                                        min="0"
                                        max="5"
                                        step="1"
                                        inputmode="numeric"
                                        :class="inputClass"
                                        @input="onRatingScale(rows[rowIndex], 'rating_quality', $event)"
                                    />
                                    <span v-else class="font-semibold text-slate-800">{{ preview(rows[rowIndex]).q ?? '—' }}</span>
                                </td>
                                <td :class="cell">
                                    <input
                                        v-if="editable && isRateableRow(rows[rowIndex])"
                                        :value="rows[rowIndex].rating_efficiency"
                                        type="number"
                                        min="0"
                                        max="5"
                                        step="1"
                                        inputmode="numeric"
                                        :class="inputClass"
                                        @input="onRatingScale(rows[rowIndex], 'rating_efficiency', $event)"
                                    />
                                    <span v-else class="font-semibold text-slate-800">{{ preview(rows[rowIndex]).e ?? '—' }}</span>
                                </td>
                                <td :class="cell">
                                    <input
                                        v-if="editable && isRateableRow(rows[rowIndex])"
                                        :value="rows[rowIndex].rating_timeliness"
                                        type="number"
                                        min="0"
                                        max="5"
                                        step="1"
                                        inputmode="numeric"
                                        :class="inputClass"
                                        @input="onRatingScale(rows[rowIndex], 'rating_timeliness', $event)"
                                    />
                                    <span v-else class="font-semibold text-slate-800">{{ preview(rows[rowIndex]).t ?? '—' }}</span>
                                </td>
                                <td :class="cell + ' font-semibold text-slate-800'">
                                    {{ preview(rows[rowIndex]).avg != null ? preview(rows[rowIndex]).avg.toFixed(2) : '—' }}
                                </td>
                                <td :class="cell + ' font-semibold text-amber-800'">
                                    {{ preview(rows[rowIndex]).remarks != null ? preview(rows[rowIndex]).remarks.toFixed(2) : '—' }}
                                </td>
                            </tr>
                        </template>
                    </template>
                    <tr class="bg-slate-100 font-semibold">
                        <td colspan="2" class="border border-slate-300 px-1 py-1 text-right">TOTAL</td>
                        <td :class="cell">{{ totalWeight.toFixed(0) }}%</td>
                        <td :colspan="2 + accompColspan + 3" class="border border-slate-300"></td>
                        <td :class="cell">
                            {{ totalAverage != null ? totalAverage.toFixed(2) : '—' }}
                        </td>
                        <td :class="cell + ' text-amber-800'">
                            {{ totalWeighted != null ? totalWeighted.toFixed(2) : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <InputError :message="errors.commitments || errors.entries || errors.submission_id" />

        <div
            v-if="showBack || showPackageSubmit || (editable && showSaveButton)"
            class="flex flex-wrap items-center gap-2 px-1"
        >
            <Link
                v-if="showBack && backHref"
                :href="backHref"
                class="inline-flex items-center rounded-md border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm hover:bg-slate-50"
            >
                Back to dashboard
            </Link>
            <PrimaryButton
                v-if="showPackageSubmit && editable"
                type="button"
                :disabled="processing || packageSubmitProcessing"
                @click="emit('package-submit')"
            >
                {{ packageSubmitProcessing || processing ? 'Submitting…' : 'Submit' }}
            </PrimaryButton>
            <PrimaryButton
                v-if="editable && showSaveButton"
                type="button"
                class="ml-auto"
                :disabled="processing || packageSubmitProcessing"
                @click="emit('submit')"
            >
                {{ processing ? 'Saving…' : submitLabel }}
            </PrimaryButton>
        </div>
    </div>
</template>
