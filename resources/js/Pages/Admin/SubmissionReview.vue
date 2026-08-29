<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EvidencePanel from '@/Components/EvidencePanel.vue';
import IpcrPreviewLink from '@/Components/IpcrPreviewLink.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { groupFormRows } from '@/utils/ipcrFormEntries';
import { formatWholeNumber, roundWholeNumberForSubmit, setWholeNumberField, wholeNumberOrEmpty } from '@/utils/numberFormat';
import { includedQuartersOf, isRateableRow, rowPreview as computeRowPreview, suggestedRatingForRow } from '@/utils/ipcrRating';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    submission: Object,
    viewOnly: {
        type: Boolean,
        default: false,
    },
});

const isApproved = computed(() => props.submission?.status === 'approved');
const isEditable = computed(() => props.submission?.status === 'in_review' && !props.viewOnly);
const isPackageEditing = ref(false);
const packageSnapshot = ref(null);
const includedQuarters = computed(() => includedQuartersOf(props.submission));
const accompColspan = computed(() => includedQuarters.value.length * 2 + 3);
const tableColspan = computed(() => 5 + accompColspan.value + 5);
const quarterColWidth = computed(() => `${Math.max(3.5, 28 / Math.max(includedQuarters.value.length, 1))}%`);

function uid() {
    return `${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

function emptyQuarterRatings() {
    return {
        rating_q1_target: '',
        rating_q1_actual: '',
        rating_q2_target: '',
        rating_q2_actual: '',
        rating_q3_target: '',
        rating_q3_actual: '',
        rating_q4_target: '',
        rating_q4_actual: '',
    };
}

function mapCommitmentToRow(c) {
    const row = {
        _uid: uid(),
        id: c.id,
        function_type: c.function_type === 'strategic' ? 'strategic' : 'core',
        function_group: c.function_group ?? 0,
        title: c.title ?? '',
        description: c.description ?? '',
        weight: c.weight ?? null,
        annual_office_target: c.annual_office_target ?? '',
        individual_annual_targets: c.individual_annual_targets ?? '',
        rating_q1_target: wholeNumberOrEmpty(c.rating_q1_target),
        rating_q1_actual: wholeNumberOrEmpty(c.rating_q1_actual),
        rating_q2_target: wholeNumberOrEmpty(c.rating_q2_target),
        rating_q2_actual: wholeNumberOrEmpty(c.rating_q2_actual),
        rating_q3_target: wholeNumberOrEmpty(c.rating_q3_target),
        rating_q3_actual: wholeNumberOrEmpty(c.rating_q3_actual),
        rating_q4_target: wholeNumberOrEmpty(c.rating_q4_target),
        rating_q4_actual: wholeNumberOrEmpty(c.rating_q4_actual),
        remarks: c.remarks ?? '',
    };
    const suggested = suggestedRatingForRow(row, includedQuarters.value);

    return {
        ...row,
        rating_quality: isRateableRow(c) ? (c.rating_quality ?? suggested ?? 3) : null,
        rating_efficiency: isRateableRow(c) ? (c.rating_efficiency ?? suggested ?? 3) : null,
        rating_timeliness: isRateableRow(c) ? (c.rating_timeliness ?? suggested ?? 3) : null,
    };
}

const sortedCommitments = computed(() => [...(props.submission?.commitments || [])]);

const packageEvidence = computed(() => {
    const seen = new Set();
    const list = [];

    for (const c of sortedCommitments.value) {
        for (const ev of c.accomplishments || []) {
            if (seen.has(ev.id)) {
                continue;
            }
            seen.add(ev.id);
            list.push(ev);
        }
    }

    return list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

function formatAccomplishmentValue(value) {
    return formatWholeNumber(value);
}

function formatAccomplishmentPercent(percent) {
    return percent != null ? `${(percent * 100).toFixed(0)}%` : '—';
}

const reviewForm = useForm({
    action: 'approve',
    supervisor_feedback: props.submission?.supervisor_feedback ?? '',
    commitments: sortedCommitments.value.map(mapCommitmentToRow),
});

const editableGroups = computed(() => groupFormRows(reviewForm.commitments));

function rowPreview(row) {
    return computeRowPreview(row, includedQuarters.value);
}

function applySuggestedRatings(row) {
    if (!isRateableRow(row)) {
        row.rating_quality = null;
        row.rating_efficiency = null;
        row.rating_timeliness = null;
        return;
    }
    const suggested = suggestedRatingForRow(row, includedQuarters.value);
    if (suggested == null) {
        return;
    }
    row.rating_quality = suggested;
    row.rating_efficiency = suggested;
    row.rating_timeliness = suggested;
}

function onWholeAccomplishment(row, key, rawValue) {
    setWholeNumberField(row, key, rawValue);
    applySuggestedRatings(row);
}

function onWeightChange(row) {
    if (!isRateableRow(row)) {
        row.rating_quality = null;
        row.rating_efficiency = null;
        row.rating_timeliness = null;
        return;
    }
    if (row.rating_quality == null) {
        applySuggestedRatings(row);
        if (row.rating_quality == null) {
            row.rating_quality = 3;
            row.rating_efficiency = 3;
            row.rating_timeliness = 3;
        }
    }
}

function sumWeightedPreview() {
    let sum = 0;
    let hasAny = false;
    for (const row of reviewForm.commitments) {
        const p = rowPreview(row);
        if (p.weighted == null) {
            continue;
        }
        sum += p.weighted;
        hasAny = true;
    }
    return hasAny ? sum : null;
}

function sumAveragePreview() {
    let sum = 0;
    let hasAny = false;
    for (const row of reviewForm.commitments) {
        const p = rowPreview(row);
        if (p.avg == null) {
            continue;
        }
        sum += p.avg;
        hasAny = true;
    }
    return hasAny ? sum : null;
}

function sectionWeightTotalEditable(type) {
    return reviewForm.commitments
        .filter((c) => c.function_type === type)
        .reduce((sum, c) => sum + Number(c.weight || 0), 0);
}

function groupWeightTotal(group) {
    return group.indexes.reduce(
        (sum, index) => sum + Number(reviewForm.commitments[index]?.weight || 0),
        0,
    );
}

function addItemRow(group) {
    const first = reviewForm.commitments[group.indexes[0]];
    if (!first) {
        return;
    }
    const insertAt = group.indexes[group.indexes.length - 1] + 1;
    reviewForm.commitments.splice(insertAt, 0, {
        _uid: uid(),
        id: null,
        function_type: first.function_type,
        function_group: first.function_group,
        title: first.title,
        description: '',
        weight: null,
        annual_office_target: '',
        individual_annual_targets: '',
        rating_quality: null,
        rating_efficiency: null,
        rating_timeliness: null,
        ...emptyQuarterRatings(),
        remarks: '',
    });
}

function removeItemRow(index) {
    if (reviewForm.commitments.length <= 1) {
        return;
    }
    reviewForm.commitments.splice(index, 1);
}

function nextFunctionGroup() {
    const nums = reviewForm.commitments
        .map((row) => Number(row.function_group))
        .filter((n) => Number.isFinite(n));
    return (nums.length ? Math.max(...nums) : -1) + 1;
}

function addFunctionEntry(type) {
    const last = reviewForm.commitments
        .map((row, index) => ({ row, index }))
        .filter((x) => x.row.function_type === type)
        .at(-1);
    const insertAt = last ? last.index + 1 : reviewForm.commitments.length;
    reviewForm.commitments.splice(insertAt, 0, {
        _uid: uid(),
        id: null,
        function_type: type,
        function_group: nextFunctionGroup(),
        title: '',
        description: '',
        weight: null,
        annual_office_target: '',
        individual_annual_targets: '',
        rating_quality: null,
        rating_efficiency: null,
        rating_timeliness: null,
        ...emptyQuarterRatings(),
        remarks: '',
    });
}

function syncGroupTitle(group, value) {
    group.title = value;
    group.indexes.forEach((index) => {
        reviewForm.commitments[index].title = value;
    });
}

function removeFunctionGroup(group) {
    if (reviewForm.commitments.length <= group.indexes.length) {
        return;
    }
    [...group.indexes].sort((a, b) => b - a).forEach((index) => {
        reviewForm.commitments.splice(index, 1);
    });
}

function ratedAverageTotal(commitments) {
    const rated = commitments.filter((c) => isRateableRow(c) && c.rating_average != null);
    if (!rated.length) {
        return '—';
    }
    return rated.reduce((sum, c) => sum + Number(c.rating_average), 0).toFixed(2);
}

function rowWeightedDisplay(commitment, row) {
    if (isApproved.value && commitment.rating_weighted != null) {
        return Number(commitment.rating_weighted).toFixed(2);
    }
    if (isApproved.value && commitment.rating_average != null && isRateableRow(commitment)) {
        return Number(commitment.rating_average).toFixed(2);
    }
    const p = rowPreview(row);
    if (p.remarks != null) {
        return p.remarks.toFixed(2);
    }
    return p.weighted != null ? p.weighted.toFixed(2) : '—';
}

function indicatorLines(c) {
    const desc = (c?.description ?? '').trim();
    if (!desc) return [''];
    const lines = desc.split(/\r\n|\r|\n/).map((l) => l.trim()).filter(Boolean);
    return lines.length ? lines : [''];
}

function buildSectionLayout(functionType) {
    return groupFormRows(sortedCommitments.value)[functionType].map((group) => {
        const rows = [];

        for (const c of group.items) {
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
            title: (group.title || '').trim(),
            commitments: group.items,
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

function ratingRowReadonly(id) {
    return reviewForm.commitments.find((r) => r.id === id);
}

function cloneCommitments(rows) {
    return rows.map((row) => ({ ...row }));
}

function startPackageEdit() {
    packageSnapshot.value = cloneCommitments(reviewForm.commitments);
    isPackageEditing.value = true;
}

function cancelPackageEdit() {
    if (packageSnapshot.value) {
        reviewForm.commitments = cloneCommitments(packageSnapshot.value);
    }
    reviewForm.clearErrors();
    isPackageEditing.value = false;
    packageSnapshot.value = null;
}

function mergeCommitmentsFromProps(previousRows) {
    const prevById = new Map(
        previousRows.filter((row) => row.id != null).map((row) => [row.id, row]),
    );

    return sortedCommitments.value.map((commitment) => {
        const row = mapCommitmentToRow(commitment);
        const previous = prevById.get(commitment.id);
        if (!previous) {
            return row;
        }

        return {
            ...row,
            rating_quality: previous.rating_quality,
            rating_efficiency: previous.rating_efficiency,
            rating_timeliness: previous.rating_timeliness,
            rating_q1_target: previous.rating_q1_target,
            rating_q1_actual: previous.rating_q1_actual,
            rating_q2_target: previous.rating_q2_target,
            rating_q2_actual: previous.rating_q2_actual,
            rating_q3_target: previous.rating_q3_target,
            rating_q3_actual: previous.rating_q3_actual,
            rating_q4_target: previous.rating_q4_target,
            rating_q4_actual: previous.rating_q4_actual,
            remarks: previous.remarks,
        };
    });
}

function buildCommitmentsPayload(data) {
    return data.commitments.map((r, index) => {
        const base = {
            id: r.id || null,
            function_type: r.function_type,
            function_group: Number.isFinite(Number(r.function_group)) ? Number(r.function_group) : index,
            sort_order: index,
            title: r.title,
            description: r.description || null,
            weight: r.weight === '' || r.weight == null ? null : Number(r.weight),
            annual_office_target: r.annual_office_target || null,
            individual_annual_targets: r.individual_annual_targets || null,
            rating_q1_target: roundWholeNumberForSubmit(r.rating_q1_target),
            rating_q1_actual: roundWholeNumberForSubmit(r.rating_q1_actual),
            rating_q2_target: roundWholeNumberForSubmit(r.rating_q2_target),
            rating_q2_actual: roundWholeNumberForSubmit(r.rating_q2_actual),
            rating_q3_target: roundWholeNumberForSubmit(r.rating_q3_target),
            rating_q3_actual: roundWholeNumberForSubmit(r.rating_q3_actual),
            rating_q4_target: roundWholeNumberForSubmit(r.rating_q4_target),
            rating_q4_actual: roundWholeNumberForSubmit(r.rating_q4_actual),
        };

        if (data.action !== 'approve') {
            return base;
        }

        if (!isRateableRow(base)) {
            return {
                ...base,
                rating_quality: null,
                rating_efficiency: null,
                rating_timeliness: null,
            };
        }

        return {
            ...base,
            rating_quality: Number(r.rating_quality),
            rating_efficiency: Number(r.rating_efficiency),
            rating_timeliness: Number(r.rating_timeliness),
        };
    });
}

function savePackageEdit() {
    const previousAction = reviewForm.action;
    reviewForm.action = 'save';

    reviewForm.transform((data) => ({
        action: 'save',
        supervisor_feedback: data.supervisor_feedback,
        commitments: buildCommitmentsPayload({ ...data, action: 'save' }),
    })).patch(route('admin.submissions.update', props.submission.id), {
        preserveScroll: true,
        onSuccess: () => {
            const previous = cloneCommitments(reviewForm.commitments);
            reviewForm.commitments = mergeCommitmentsFromProps(previous);
            isPackageEditing.value = false;
            packageSnapshot.value = null;
        },
        onFinish: () => {
            reviewForm.action = previousAction;
            reviewForm.transform((data) => data);
        },
    });
}

function submitReview() {
    reviewForm.transform((data) => ({
        action: data.action,
        supervisor_feedback: data.supervisor_feedback,
        commitments: buildCommitmentsPayload(data),
    })).patch(route('admin.submissions.update', props.submission.id), {
        preserveScroll: true,
        onFinish: () => {
            reviewForm.transform((data) => data);
        },
    });
}

function formatWhen(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function periodLabel(s) {
    return includedQuartersOf(s).map((q) => `Q${q}`).join(', ') + ` ${s.evaluation_year}`;
}

function badge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        pending: 'bg-amber-50 text-amber-900 ring-amber-100',
        returned: 'bg-rose-50 text-rose-900 ring-rose-100',
    };
    return map[status] ?? 'bg-slate-50 text-slate-700 ring-slate-100';
}

const cell = 'border border-slate-300 px-0.5 py-1 text-center align-middle break-words';
const inputClass = 'h-7 w-full min-w-0 max-w-full rounded border-gray-300 px-0.5 py-0 text-center text-[10px] shadow-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none focus:border-blue-500 focus:ring-blue-500';
</script>

<template>
    <Head :title="`Review · ${submission.employee.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                        <AppIcon name="clipboard" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500">IPCR Submission</p>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">
                            {{ submission.employee.name }} — {{ periodLabel(submission) }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            Submitted {{ formatWhen(submission.submitted_at) }}
                            <span class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase ring-1" :class="badge(submission.status)">
                                {{ submission.status.replace('_', ' ') }}
                            </span>
                        </p>
                    </div>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <Link :href="route('dashboard')" class="inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 sm:w-auto">
                        <AppIcon name="arrow-left" class="h-4 w-4" />
                        Back to dashboard
                    </Link>
                    <IpcrPreviewLink v-if="isApproved" mode="admin-submission" :submission-id="submission.id" />
                </div>
            </div>
        </template>

        <div class="overflow-x-hidden py-6">
            <div class="mx-auto w-full max-w-[100%] space-y-5 px-3 sm:px-4 lg:px-6">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-2 shadow-sm sm:p-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <AppIcon name="star" class="h-4 w-4" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">IPCR Form 1 — Evaluation</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    <template v-if="isEditable && isPackageEditing">
                                        Editing Function, Services/Indicators, Weight, and Annual Targets.
                                        Use <strong>+ Add row</strong> / <strong>+ Add function</strong>, then <strong>Save</strong> or <strong>Cancel</strong>.
                                    </template>
                                    <template v-else-if="isEditable">
                                        Rate or edit the whole form. Accomplishments can be changed; Q, E, and T default from the accomplishment ratio.
                                        Average = (Q + E + T) ÷ 3. Rows with an Annual Office Target are rated even without weight. Remarks = Weight% × Average, or Average when weight is blank.
                                    </template>
                                    <template v-else-if="isApproved">
                                        This submission has been approved. Ratings shown below are read-only.
                                    </template>
                                    <template v-else>
                                        This submission is {{ submission.status.replace('_', ' ') }}. No ratings can be edited.
                                    </template>
                                </p>
                            </div>
                        </div>

                        <div v-if="isEditable" class="flex flex-wrap items-center gap-2">
                            <template v-if="isPackageEditing">
                                <SecondaryButton
                                    type="button"
                                    class="justify-center"
                                    :disabled="reviewForm.processing"
                                    @click="cancelPackageEdit"
                                >
                                    Cancel
                                </SecondaryButton>
                                <PrimaryButton
                                    type="button"
                                    class="justify-center"
                                    :disabled="reviewForm.processing"
                                    @click="savePackageEdit"
                                >
                                    Save
                                </PrimaryButton>
                            </template>
                            <SecondaryButton
                                v-else
                                type="button"
                                class="justify-center"
                                @click="startPackageEdit"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <AppIcon name="pencil" class="h-4 w-4" />
                                    Edit
                                </span>
                            </SecondaryButton>
                        </div>
                    </div>

                    <div class="mt-2 overflow-x-hidden rounded-lg border border-slate-300">
                        <table class="w-full table-fixed border-collapse text-[10px] leading-tight">
                            <colgroup>
                                <col style="width: 11%" />
                                <col style="width: 14%" />
                                <col style="width: 5%" />
                                <col style="width: 7%" />
                                <col style="width: 7%" />
                                <col
                                    v-for="n in includedQuarters.length * 2"
                                    :key="'cq-' + n"
                                    :style="{ width: quarterColWidth }"
                                />
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
                                    <th v-for="q in includedQuarters" :key="'h-' + q" :class="cell" colspan="2">Q{{ q }}</th>
                                    <th :class="cell" colspan="3">Total</th>
                                    <th :class="cell" rowspan="2">Q</th>
                                    <th :class="cell" rowspan="2">E</th>
                                    <th :class="cell" rowspan="2">T</th>
                                    <th :class="cell" rowspan="2">Avg</th>
                                </tr>
                                <tr>
                                    <template v-for="q in includedQuarters" :key="'ha-' + q">
                                        <th :class="cell">Target</th>
                                        <th :class="cell">Actual</th>
                                    </template>
                                    <th :class="cell">Target</th>
                                    <th :class="cell">Actual</th>
                                    <th :class="cell">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Package edit phase (5 fields editable) -->
                                <template v-if="isPackageEditing">
                                    <template v-for="groupType in ['core', 'strategic']" :key="'edit-' + groupType">
                                        <tr :class="groupType === 'core' ? 'bg-blue-50/90' : 'bg-amber-50/90'">
                                            <td
                                                :colspan="tableColspan"
                                                class="border border-slate-300 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wide"
                                                :class="groupType === 'core' ? 'text-blue-900' : 'text-amber-900'"
                                            >
                                                {{ groupType === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                                · {{ sectionWeightTotalEditable(groupType).toFixed(0) }}%
                                            </td>
                                        </tr>

                                        <template v-for="(fnGroup, fgIdx) in editableGroups[groupType]" :key="groupType + '-' + fnGroup.key">
                                            <tr v-if="fgIdx > 0" aria-hidden="true">
                                                <td :colspan="tableColspan" class="h-3 border border-slate-300 bg-white p-0"></td>
                                            </tr>
                                            <tr
                                                v-for="(rowIndex, ri) in fnGroup.indexes"
                                                :key="reviewForm.commitments[rowIndex]._uid"
                                                class="align-top"
                                            >
                                                <td
                                                    v-if="ri === 0"
                                                    :rowspan="fnGroup.indexes.length"
                                                    class="border border-slate-300 px-2 py-1 align-top"
                                                >
                                                    <TextInput
                                                        :model-value="fnGroup.title"
                                                        type="text"
                                                        class="block w-full min-w-0 text-xs"
                                                        placeholder="e.g. Development of Standards..."
                                                        @update:model-value="syncGroupTitle(fnGroup, $event)"
                                                    />
                                                    <InputError class="mt-1" :message="reviewForm.errors[`commitments.${fnGroup.indexes[0]}.title`]" />
                                                    <p class="mt-1 text-[10px] text-slate-500">
                                                        Σ wt: <strong>{{ groupWeightTotal(fnGroup).toFixed(0) }}%</strong>
                                                    </p>
                                                    <div class="mt-2 flex flex-wrap gap-1">
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 hover:bg-blue-100"
                                                            :class="groupType === 'strategic' ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' : ''"
                                                            @click="addItemRow(fnGroup)"
                                                        >
                                                            + Add row
                                                        </button>
                                                        <button
                                                            v-if="editableGroups[groupType].length > 1"
                                                            type="button"
                                                            class="inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                                            @click="removeFunctionGroup(fnGroup)"
                                                        >
                                                            − Remove function
                                                        </button>
                                                    </div>
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 align-top">
                                                    <textarea
                                                        v-model="reviewForm.commitments[rowIndex].description"
                                                        rows="3"
                                                        class="block w-full min-w-0 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                        placeholder="One indicator per line"
                                                    />
                                                    <button
                                                        v-if="fnGroup.indexes.length > 1"
                                                        type="button"
                                                        class="mt-1 inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                                        @click="removeItemRow(rowIndex)"
                                                    >
                                                        × Remove row
                                                    </button>
                                                    <InputError class="mt-1" :message="reviewForm.errors[`commitments.${rowIndex}.description`]" />
                                                </td>
                                                <td class="border border-slate-300 px-1 py-1 align-top">
                                                    <TextInput
                                                        v-model="reviewForm.commitments[rowIndex].weight"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        :class="inputClass"
                                                        placeholder="—"
                                                        @change="onWeightChange(reviewForm.commitments[rowIndex])"
                                                    />
                                                    <InputError class="mt-1" :message="reviewForm.errors[`commitments.${rowIndex}.weight`]" />
                                                </td>
                                                <td class="border border-slate-300 px-1 py-1 align-top">
                                                    <TextInput
                                                        v-model="reviewForm.commitments[rowIndex].annual_office_target"
                                                        type="text"
                                                        :class="inputClass"
                                                        @change="onWeightChange(reviewForm.commitments[rowIndex])"
                                                    />
                                                </td>
                                                <td class="border border-slate-300 px-1 py-1 align-top">
                                                    <TextInput
                                                        v-model="reviewForm.commitments[rowIndex].individual_annual_targets"
                                                        type="text"
                                                        :class="inputClass"
                                                    />
                                                </td>
                                                <template v-for="q in includedQuarters" :key="'edit-q-' + q">
                                                    <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                        {{ formatWholeNumber(reviewForm.commitments[rowIndex][`rating_q${q}_target`]) }}
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                        {{ formatWholeNumber(reviewForm.commitments[rowIndex][`rating_q${q}_actual`]) }}
                                                    </td>
                                                </template>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                    {{ formatAccomplishmentValue(rowPreview(reviewForm.commitments[rowIndex]).targetTotal) }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                    {{ formatAccomplishmentValue(rowPreview(reviewForm.commitments[rowIndex]).actualTotal) }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                    {{ formatAccomplishmentPercent(rowPreview(reviewForm.commitments[rowIndex]).percent) }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                    {{ reviewForm.commitments[rowIndex].rating_quality ?? '—' }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                    {{ reviewForm.commitments[rowIndex].rating_efficiency ?? '—' }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-500">
                                                    {{ reviewForm.commitments[rowIndex].rating_timeliness ?? '—' }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center font-semibold text-slate-500">
                                                    {{ rowPreview(reviewForm.commitments[rowIndex]).avg != null ? rowPreview(reviewForm.commitments[rowIndex]).avg.toFixed(2) : '—' }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center font-semibold text-slate-500">
                                                    {{ rowPreview(reviewForm.commitments[rowIndex]).remarks != null ? rowPreview(reviewForm.commitments[rowIndex]).remarks.toFixed(2) : '—' }}
                                                </td>
                                            </tr>
                                        </template>

                                        <tr :class="groupType === 'core' ? 'bg-blue-50/40' : 'bg-amber-50/40'">
                                            <td :colspan="tableColspan" class="border border-slate-300 px-2 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-md border bg-white px-3 py-1.5 text-[11px] font-semibold shadow-sm"
                                                    :class="groupType === 'core'
                                                        ? 'border-blue-300 text-blue-700 hover:bg-blue-50'
                                                        : 'border-amber-300 text-amber-800 hover:bg-amber-50'"
                                                    @click="addFunctionEntry(groupType)"
                                                >
                                                    + Add {{ groupType === 'core' ? 'Core' : 'Strategic' }} Function
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </template>

                                <!-- Rate phase: package fields locked, ratings editable -->
                                <template v-else-if="isEditable">
                                    <template v-for="groupType in ['core', 'strategic']" :key="'rate-' + groupType">
                                        <tr
                                            v-if="editableGroups[groupType].length"
                                            :class="groupType === 'core' ? 'bg-blue-50/90' : 'bg-amber-50/90'"
                                        >
                                            <td
                                                :colspan="tableColspan"
                                                class="border border-slate-300 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wide"
                                                :class="groupType === 'core' ? 'text-blue-900' : 'text-amber-900'"
                                            >
                                                {{ groupType === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                                · {{ sectionWeightTotalEditable(groupType).toFixed(0) }}%
                                            </td>
                                        </tr>
                                        <template v-for="(fnGroup, fgIdx) in editableGroups[groupType]" :key="'rate-' + groupType + '-' + fnGroup.key">
                                            <tr v-if="fgIdx > 0" aria-hidden="true">
                                                <td :colspan="tableColspan" class="h-3 border border-slate-300 bg-white p-0"></td>
                                            </tr>
                                            <tr
                                                v-for="(rowIndex, ri) in fnGroup.indexes"
                                                :key="'rate-' + reviewForm.commitments[rowIndex]._uid"
                                                class="align-top"
                                            >
                                                <td
                                                    v-if="ri === 0"
                                                    :rowspan="fnGroup.indexes.length"
                                                    class="border border-slate-300 px-0.5 py-1 text-left font-semibold break-words text-slate-800"
                                                >
                                                    {{ fnGroup.title }}
                                                </td>
                                                <td class="border border-slate-300 px-0.5 py-1 text-left break-words whitespace-pre-line text-slate-700">
                                                    {{ reviewForm.commitments[rowIndex].description }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center font-medium text-slate-800">
                                                    {{ reviewForm.commitments[rowIndex].weight != null && reviewForm.commitments[rowIndex].weight !== ''
                                                        ? Number(reviewForm.commitments[rowIndex].weight).toFixed(0) + '%'
                                                        : '—' }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ reviewForm.commitments[rowIndex].annual_office_target || '—' }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ reviewForm.commitments[rowIndex].individual_annual_targets || '—' }}
                                                </td>
                                                <template v-for="q in includedQuarters" :key="'rate-q-' + q">
                                                    <td class="border border-slate-300 px-1 py-1">
                                                        <TextInput
                                                            :model-value="reviewForm.commitments[rowIndex][`rating_q${q}_target`]"
                                                            type="number"
                                                            step="1"
                                                            min="0"
                                                            :class="inputClass"
                                                            @update:model-value="onWholeAccomplishment(reviewForm.commitments[rowIndex], `rating_q${q}_target`, $event)"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-1 py-1">
                                                        <TextInput
                                                            :model-value="reviewForm.commitments[rowIndex][`rating_q${q}_actual`]"
                                                            type="number"
                                                            step="1"
                                                            min="0"
                                                            :class="inputClass"
                                                            @update:model-value="onWholeAccomplishment(reviewForm.commitments[rowIndex], `rating_q${q}_actual`, $event)"
                                                        />
                                                    </td>
                                                </template>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ formatAccomplishmentValue(rowPreview(reviewForm.commitments[rowIndex]).targetTotal) }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ formatAccomplishmentValue(rowPreview(reviewForm.commitments[rowIndex]).actualTotal) }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ formatAccomplishmentPercent(rowPreview(reviewForm.commitments[rowIndex]).percent) }}
                                                </td>
                                                <td class="border border-slate-300 px-1 py-1">
                                                    <TextInput
                                                        v-if="isRateableRow(reviewForm.commitments[rowIndex])"
                                                        v-model="reviewForm.commitments[rowIndex].rating_quality"
                                                        type="number"
                                                        min="0"
                                                        max="5"
                                                        :class="inputClass"
                                                    />
                                                    <span v-else class="block text-center text-slate-400">—</span>
                                                </td>
                                                <td class="border border-slate-300 px-1 py-1">
                                                    <TextInput
                                                        v-if="isRateableRow(reviewForm.commitments[rowIndex])"
                                                        v-model="reviewForm.commitments[rowIndex].rating_efficiency"
                                                        type="number"
                                                        min="0"
                                                        max="5"
                                                        :class="inputClass"
                                                    />
                                                    <span v-else class="block text-center text-slate-400">—</span>
                                                </td>
                                                <td class="border border-slate-300 px-1 py-1">
                                                    <TextInput
                                                        v-if="isRateableRow(reviewForm.commitments[rowIndex])"
                                                        v-model="reviewForm.commitments[rowIndex].rating_timeliness"
                                                        type="number"
                                                        min="0"
                                                        max="5"
                                                        :class="inputClass"
                                                    />
                                                    <span v-else class="block text-center text-slate-400">—</span>
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center font-semibold text-slate-800">
                                                    {{ rowPreview(reviewForm.commitments[rowIndex]).avg != null ? rowPreview(reviewForm.commitments[rowIndex]).avg.toFixed(2) : '—' }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-center font-semibold text-amber-800">
                                                    {{ rowPreview(reviewForm.commitments[rowIndex]).remarks != null ? rowPreview(reviewForm.commitments[rowIndex]).remarks.toFixed(2) : '—' }}
                                                </td>
                                            </tr>
                                        </template>
                                    </template>
                                </template>

                                <!-- Fully read-only (approved / other statuses) -->
                                <template v-else>
                                    <template v-for="group in ['core', 'strategic']" :key="group">
                                        <tr v-if="sectionLayout[group].length" :class="group === 'core' ? 'bg-blue-50/90' : 'bg-amber-50/90'">
                                            <td
                                                :colspan="tableColspan"
                                                class="border border-slate-300 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wide"
                                                :class="group === 'core' ? 'text-blue-900' : 'text-amber-900'"
                                            >
                                                {{ group === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                                · {{ sectionWeightTotal(group).toFixed(0) }}%
                                            </td>
                                        </tr>
                                        <template v-for="(fnGroup, fgIdx) in sectionLayout[group]" :key="group + '-' + fgIdx">
                                            <tr v-if="fgIdx > 0" aria-hidden="true">
                                                <td :colspan="tableColspan" class="h-3 border border-slate-300 bg-white p-0"></td>
                                            </tr>
                                            <tr
                                                v-for="(row, ri) in fnGroup.rows"
                                                :key="row.commitment.id + '-' + row.lineIndex"
                                                class="align-top"
                                            >
                                                <td
                                                    v-if="ri === 0"
                                                    :rowspan="fnGroup.rowCount"
                                                    class="border border-slate-300 px-0.5 py-1 text-left font-semibold break-words text-slate-800"
                                                >
                                                    {{ fnGroup.title }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-slate-700">{{ row.line }}</td>
                                                <td
                                                    v-if="row.lineIndex === 0"
                                                    :rowspan="row.lineCount"
                                                    class="border border-slate-300 px-2 py-1 text-center font-medium text-slate-800"
                                                >
                                                    {{ row.commitment.weight != null ? Number(row.commitment.weight).toFixed(0) + '%' : '—' }}
                                                </td>
                                                <td
                                                    v-if="row.lineIndex === 0"
                                                    :rowspan="row.lineCount"
                                                    class="border border-slate-300 px-2 py-1 text-center text-slate-700"
                                                >
                                                    {{ row.commitment.annual_office_target ?? '—' }}
                                                </td>
                                                <td
                                                    v-if="row.lineIndex === 0"
                                                    :rowspan="row.lineCount"
                                                    class="border border-slate-300 px-2 py-1 text-center text-slate-700"
                                                >
                                                    {{ row.commitment.individual_annual_targets ?? '—' }}
                                                </td>
                                                <template v-if="row.lineIndex === 0 && ratingRowReadonly(row.commitment.id)">
                                                    <template v-for="q in includedQuarters" :key="'ro-q-' + q">
                                                        <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                            {{ formatWholeNumber(row.commitment[`rating_q${q}_target`]) }}
                                                        </td>
                                                        <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                            {{ formatWholeNumber(row.commitment[`rating_q${q}_actual`]) }}
                                                        </td>
                                                    </template>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                        {{ formatAccomplishmentValue(rowPreview(ratingRowReadonly(row.commitment.id)).targetTotal) }}
                                                    </td>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                        {{ formatAccomplishmentValue(rowPreview(ratingRowReadonly(row.commitment.id)).actualTotal) }}
                                                    </td>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                        {{ formatAccomplishmentPercent(rowPreview(ratingRowReadonly(row.commitment.id)).percent) }}
                                                    </td>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                        {{ isRateableRow(row.commitment) ? (row.commitment.rating_quality ?? '—') : '—' }}
                                                    </td>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                        {{ isRateableRow(row.commitment) ? (row.commitment.rating_efficiency ?? '—') : '—' }}
                                                    </td>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                        {{ isRateableRow(row.commitment) ? (row.commitment.rating_timeliness ?? '—') : '—' }}
                                                    </td>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center font-semibold text-slate-800">
                                                        {{ rowPreview(ratingRowReadonly(row.commitment.id)).avg != null ? rowPreview(ratingRowReadonly(row.commitment.id)).avg.toFixed(2) : (row.commitment.rating_average != null ? Number(row.commitment.rating_average).toFixed(2) : '—') }}
                                                    </td>
                                                    <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center font-semibold text-amber-800">
                                                        {{ rowWeightedDisplay(row.commitment, ratingRowReadonly(row.commitment.id)) }}
                                                    </td>
                                                </template>
                                            </tr>
                                        </template>
                                    </template>
                                </template>

                                <tr class="bg-slate-100 font-semibold">
                                    <td colspan="2" class="border border-slate-300 px-2 py-1 text-right">TOTAL</td>
                                    <td class="border border-slate-300 px-2 py-1 text-center">
                                        {{ isEditable
                                            ? reviewForm.commitments.reduce((a, c) => a + Number(c.weight || 0), 0).toFixed(0)
                                            : sortedCommitments.reduce((a, c) => a + Number(c.weight || 0), 0).toFixed(0) }}%
                                    </td>
                                    <td :colspan="2 + accompColspan + 3" class="border border-slate-300"></td>
                                    <td class="border border-slate-300 px-2 py-1 text-center text-slate-800">
                                        {{ isApproved
                                            ? ratedAverageTotal(sortedCommitments)
                                            : (sumAveragePreview() != null ? sumAveragePreview().toFixed(2) : '—') }}
                                    </td>
                                    <td class="border border-slate-300 px-2 py-1 text-center text-amber-800">
                                        {{ isApproved && submission.overall_rating != null
                                            ? Number(submission.overall_rating).toFixed(2)
                                            : (sumWeightedPreview() != null ? sumWeightedPreview().toFixed(2) : '—') }}
                                    </td>
                                </tr>
                                <tr v-if="isApproved && submission.overall_rating != null" class="bg-amber-50 font-semibold text-amber-900">
                                    <td colspan="2" class="border border-slate-300 px-2 py-1 text-right">FINAL AVERAGE RATING</td>
                                    <td :colspan="tableColspan - 2" class="border border-slate-300 px-2 py-1 text-center">
                                        {{ Number(submission.overall_rating).toFixed(2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p
                        v-if="isPackageEditing"
                        class="mt-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800"
                    >
                        Finish package edits with <strong>Save</strong> or <strong>Cancel</strong> before approving or returning.
                    </p>
                    <InputError class="mt-2" :message="reviewForm.errors.commitments" />
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                            <AppIcon name="paper-clip" class="h-4 w-4" />
                        </span>
                        <p class="text-sm font-semibold text-slate-800">Supporting evidence</p>
                    </div>
                    <EvidencePanel :items="packageEvidence" :show-form="false" />
                </div>

                <div v-if="isEditable && !isPackageEditing" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <AppIcon name="check-badge" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-800">Decision</p>
                            <p class="mt-1 text-xs text-slate-500">Approve after checking each row, or reject with comments (min. 20 characters).</p>

                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <button
                                    type="button"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                    :class="reviewForm.action === 'approve' ? 'border-blue-600 bg-blue-50 text-blue-800' : 'border-slate-200'"
                                    @click="reviewForm.action = 'approve'"
                                >
                                    <AppIcon name="check-badge" class="h-4 w-4" />
                                    Approve
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition"
                                    :class="reviewForm.action === 'return' ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-slate-200'"
                                    @click="reviewForm.action = 'return'"
                                >
                                    <AppIcon name="exclamation-triangle" class="h-4 w-4" />
                                    <span class="sm:hidden">Reject</span>
                                    <span class="hidden sm:inline">Reject for revision</span>
                                </button>
                            </div>

                            <div class="mt-4">
                                <InputLabel :value="reviewForm.action === 'return' ? 'Comments for employee (required when rejecting)' : 'Optional comments'" />
                                <textarea
                                    v-model="reviewForm.supervisor_feedback"
                                    rows="4"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    :placeholder="reviewForm.action === 'return'
                                        ? 'Explain what to fix (targets, evidence, weights, or narrative). Minimum 20 characters.'
                                        : 'Optional recognition or follow-up items.'"
                                />
                                <InputError class="mt-1" :message="reviewForm.errors.supervisor_feedback" />
                            </div>

                            <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <Link :href="route('dashboard')" class="w-full sm:w-auto">
                                    <SecondaryButton type="button" class="w-full justify-center sm:w-auto">
                                        <span class="inline-flex items-center gap-1.5">
                                            <AppIcon name="x-mark" class="h-4 w-4" />
                                            Cancel
                                        </span>
                                    </SecondaryButton>
                                </Link>
                                <PrimaryButton class="w-full justify-center sm:w-auto" :disabled="reviewForm.processing" @click="submitReview">
                                    <span class="inline-flex items-center gap-1.5">
                                        <AppIcon name="check-badge" class="h-4 w-4" />
                                        Submit decision
                                    </span>
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="!isEditable && submission.supervisor_feedback" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm text-sm sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                            <AppIcon name="pencil" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="font-semibold text-slate-800">Supervisor feedback</p>
                            <p class="mt-1 whitespace-pre-line text-slate-600">{{ submission.supervisor_feedback }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 text-[11px] md:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="flex items-center gap-1.5 font-semibold text-slate-700">
                            <AppIcon name="star" class="h-3.5 w-3.5 text-amber-600" />
                            Rating scale
                        </p>
                        <ul class="mt-1 list-disc pl-4 text-slate-600">
                            <li>5 — Outstanding (≥130%)</li>
                            <li>4 — Very Satisfactory (115–129%)</li>
                            <li>3 — Satisfactory (100–114%)</li>
                            <li>2 — Unsatisfactory (51–99%)</li>
                            <li>1 — Poor (≤50%)</li>
                        </ul>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="flex items-center gap-1.5 font-semibold text-slate-700">
                            <AppIcon name="document-chart-bar" class="h-3.5 w-3.5 text-sky-600" />
                            Legend
                        </p>
                        <p class="mt-1 text-slate-600">
                            Q, E, and T default from accomplishment % (see scale) and may be overridden. Average = (Q + E + T) ÷ 3.
                            Remarks = Weight% × Average when weight is present; otherwise Remarks = Average if Annual Office Target is filled. TOTAL Remarks = Σ weighted remarks.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

