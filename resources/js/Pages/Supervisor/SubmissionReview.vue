<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EvidencePanel from '@/Components/EvidencePanel.vue';
import IpcrExportDropdown from '@/Components/IpcrExportDropdown.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ReviewTransferPanel from '@/Components/ReviewTransferPanel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatWholeNumber, roundWholeNumberForSubmit } from '@/utils/numberFormat';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    submission: Object,
    supervisors: {
        type: Array,
        default: () => [],
    },
    pendingReviewTransfer: {
        type: Object,
        default: null,
    },
});

const isApproved = computed(() => props.submission?.status === 'approved');
const isEditable = computed(() => props.submission?.status === 'in_review');

const sortedCommitments = computed(() =>
    [...(props.submission?.commitments || [])].sort((a, b) => a.id - b.id),
);

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

function parseNullableNum(value) {
    if (value === '' || value == null) {
        return null;
    }
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
}

function accomplishmentRatio(q3Target, q3Actual, q4Target, q4Actual) {
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

function qualityFromRatio(n) {
    if (n >= 1.3) return 5;
    if (n >= 1.15) return 4;
    if (n >= 1.0) return 3;
    if (n >= 0.51) return 2;
    return 1;
}

function suggestedRating(q3Target, q3Actual, q4Target, q4Actual) {
    const ratio = accomplishmentRatio(q3Target, q3Actual, q4Target, q4Actual);
    if (ratio.percent == null) {
        return null;
    }
    return qualityFromRatio(ratio.percent);
}

function formatAccomplishmentValue(value) {
    return formatWholeNumber(value);
}

function formatAccomplishmentPercent(percent) {
    return percent != null ? `${(percent * 100).toFixed(0)}%` : '—';
}


const reviewForm = useForm({
    action: 'approve',
    supervisor_feedback: props.submission?.supervisor_feedback ?? '',
    commitments: sortedCommitments.value.map((c) => {
        const suggested = suggestedRating(
            c.rating_q3_target ?? '',
            c.rating_q3_actual ?? '',
            c.rating_q4_target ?? '',
            c.rating_q4_actual ?? '',
        );
        return {
            id: c.id,
            rating_quality: c.weight != null ? (c.rating_quality ?? suggested ?? 3) : null,
            rating_efficiency: c.weight != null ? (c.rating_efficiency ?? suggested ?? 3) : null,
            rating_timeliness: c.weight != null ? (c.rating_timeliness ?? suggested ?? 3) : null,
            rating_q3_target: c.rating_q3_target ?? '',
            rating_q3_actual: c.rating_q3_actual ?? '',
            rating_q4_target: c.rating_q4_target ?? '',
            rating_q4_actual: c.rating_q4_actual ?? '',
        };
    }),
});

function rowPreview(commitment, row) {
    const ratio = accomplishmentRatio(
        row.rating_q3_target,
        row.rating_q3_actual,
        row.rating_q4_target,
        row.rating_q4_actual,
    );
    if (commitment.weight == null) {
        return { ...ratio, q: null, e: null, t: null, avg: null, weighted: null };
    }
    const q = Number(row.rating_quality);
    const e = Number(row.rating_efficiency);
    const t = Number(row.rating_timeliness);
    if (!Number.isFinite(q) || !Number.isFinite(e) || !Number.isFinite(t)) {
        return { ...ratio, q: null, e: null, t: null, avg: null, weighted: null };
    }
    const avg = (q + e + t) / 3;
    const w = Number(commitment.weight) / 100;
    return { ...ratio, q, e, t, avg, weighted: avg * w };
}

function applySuggestedRatings(row) {
    const suggested = suggestedRating(
        row.rating_q3_target,
        row.rating_q3_actual,
        row.rating_q4_target,
        row.rating_q4_actual,
    );
    if (suggested == null) {
        return;
    }
    row.rating_quality = suggested;
    row.rating_efficiency = suggested;
    row.rating_timeliness = suggested;
}

function sumWeightedPreview() {
    let sum = 0;
    let hasAny = false;
    for (const c of sortedCommitments.value) {
        if (c.weight == null) {
            continue;
        }
        const row = reviewForm.commitments.find((r) => r.id === c.id);
        if (!row) {
            continue;
        }
        const p = rowPreview(c, row);
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
    for (const c of sortedCommitments.value) {
        if (c.weight == null) {
            continue;
        }
        const row = reviewForm.commitments.find((r) => r.id === c.id);
        if (!row) {
            continue;
        }
        const p = rowPreview(c, row);
        if (p.avg == null) {
            continue;
        }
        sum += p.avg;
        hasAny = true;
    }
    return hasAny ? sum : null;
}

function ratedAverageTotal(commitments) {
    const rated = commitments.filter((c) => c.weight != null && c.rating_average != null);
    if (!rated.length) {
        return '—';
    }
    return rated.reduce((sum, c) => sum + Number(c.rating_average), 0).toFixed(2);
}

function rowWeightedDisplay(commitment, row) {
    if (isApproved.value && commitment.rating_weighted != null) {
        return Number(commitment.rating_weighted).toFixed(2);
    }
    const p = rowPreview(commitment, row);
    return p.weighted != null ? p.weighted.toFixed(2) : '—';
}

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
            commitments: group.commitments,
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

function ratingRow(id) {
    return reviewForm.commitments.find((r) => r.id === id);
}

function submitReview() {
    reviewForm.transform((data) => {
        if (data.action !== 'approve') {
            return {
                action: data.action,
                supervisor_feedback: data.supervisor_feedback,
            };
        }
        return {
            action: data.action,
            supervisor_feedback: data.supervisor_feedback,
            commitments: data.commitments.map((r) => {
                const commitment = sortedCommitments.value.find((c) => c.id === r.id);
                const base = {
                    id: r.id,
                    rating_q3_target: roundWholeNumberForSubmit(r.rating_q3_target),
                    rating_q3_actual: roundWholeNumberForSubmit(r.rating_q3_actual),
                    rating_q4_target: roundWholeNumberForSubmit(r.rating_q4_target),
                    rating_q4_actual: roundWholeNumberForSubmit(r.rating_q4_actual),
                };
                if (commitment?.weight == null) {
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
            }),
        };
    }).patch(route('supervisor.submissions.update', props.submission.id), {
        preserveScroll: true,
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
    return `Q${s.evaluation_quarter} ${s.evaluation_year}`;
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
                <div class="flex gap-2">
                    <Link :href="route('dashboard')" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        <AppIcon name="arrow-left" class="h-4 w-4" />
                        Back to dashboard
                    </Link>
                    <IpcrExportDropdown v-if="isApproved" :submission-id="submission.id" />
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto w-full max-w-[100vw] space-y-5 px-3 sm:px-4 lg:px-6">
                <ReviewTransferPanel
                    :submission="submission"
                    :supervisors="supervisors"
                    :pending-review-transfer="pendingReviewTransfer"
                />

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                            <AppIcon name="star" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-800">IPCR Form 1 — Evaluation</p>
                            <p class="mt-1 text-xs text-slate-500">
                        <template v-if="isEditable">
                            Fill in Q3 and Q4 <strong>Target</strong> / <strong>Actual</strong> per indicator (optional).
                            Q, E, and T default from the accomplishment ratio when targets and actuals are provided.
                            Average = (Q + E + T) ÷ 3. Remarks = Weight% × Average; TOTAL Remarks = sum of row Remarks (final rating).
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

                    <div class="mt-2 overflow-x-auto rounded-lg border border-slate-300">
                        <table class="min-w-full border-collapse text-[11px]">
                            <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-700">
                                <tr>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Function</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Services / Programs / Indicators</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Weight</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Annual Office Target</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Individual Annual Targets</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="7">Accomplishments</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="4">Rating</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Remarks</th>
                                </tr>
                                <tr>
                                    <th class="border border-slate-300 px-2 py-1" colspan="2">Q3</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="2">Q4</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="3">Total</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">Q</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">E</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">T</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">Avg</th>
                                </tr>
                                <tr>
                                    <th class="border border-slate-300 px-2 py-1">Target</th>
                                    <th class="border border-slate-300 px-2 py-1">Actual</th>
                                    <th class="border border-slate-300 px-2 py-1">Target</th>
                                    <th class="border border-slate-300 px-2 py-1">Actual</th>
                                    <th class="border border-slate-300 px-2 py-1">Target</th>
                                    <th class="border border-slate-300 px-2 py-1">Actual</th>
                                    <th class="border border-slate-300 px-2 py-1">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="group in ['core', 'strategic']" :key="group">
                                    <tr v-if="sectionLayout[group].length" :class="group === 'core' ? 'bg-blue-50/90' : 'bg-amber-50/90'">
                                        <td
                                            colspan="17"
                                            class="border border-slate-300 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wide"
                                            :class="group === 'core' ? 'text-blue-900' : 'text-amber-900'"
                                        >
                                            {{ group === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                            · {{ sectionWeightTotal(group).toFixed(0) }}%
                                        </td>
                                    </tr>
                                    <template v-for="(fnGroup, fgIdx) in sectionLayout[group]" :key="group + '-' + fgIdx">
                                        <tr v-if="fgIdx > 0" aria-hidden="true">
                                            <td colspan="17" class="h-3 border border-slate-300 bg-white p-0"></td>
                                        </tr>
                                        <tr
                                            v-for="(row, ri) in fnGroup.rows"
                                            :key="row.commitment.id + '-' + row.lineIndex"
                                            class="align-top"
                                        >
                                            <td
                                                v-if="ri === 0"
                                                :rowspan="fnGroup.rowCount"
                                                class="border border-slate-300 px-2 py-1 align-top font-semibold text-slate-800"
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
                                            <template v-if="row.lineIndex === 0 && ratingRow(row.commitment.id)">
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-1 py-1">
                                                    <TextInput
                                                        v-if="isEditable"
                                                        v-model="ratingRow(row.commitment.id).rating_q3_target"
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        class="w-16 text-xs"
                                                        @change="applySuggestedRatings(ratingRow(row.commitment.id))"
                                                    />
                                                    <span v-else class="block text-center text-slate-700">{{ formatWholeNumber(row.commitment.rating_q3_target) }}</span>
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-1 py-1">
                                                    <TextInput
                                                        v-if="isEditable"
                                                        v-model="ratingRow(row.commitment.id).rating_q3_actual"
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        class="w-16 text-xs"
                                                        @change="applySuggestedRatings(ratingRow(row.commitment.id))"
                                                    />
                                                    <span v-else class="block text-center text-slate-700">{{ formatWholeNumber(row.commitment.rating_q3_actual) }}</span>
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-1 py-1">
                                                    <TextInput
                                                        v-if="isEditable"
                                                        v-model="ratingRow(row.commitment.id).rating_q4_target"
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        class="w-16 text-xs"
                                                        @change="applySuggestedRatings(ratingRow(row.commitment.id))"
                                                    />
                                                    <span v-else class="block text-center text-slate-700">{{ formatWholeNumber(row.commitment.rating_q4_target) }}</span>
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-1 py-1">
                                                    <TextInput
                                                        v-if="isEditable"
                                                        v-model="ratingRow(row.commitment.id).rating_q4_actual"
                                                        type="number"
                                                        step="1"
                                                        min="0"
                                                        class="w-16 text-xs"
                                                        @change="applySuggestedRatings(ratingRow(row.commitment.id))"
                                                    />
                                                    <span v-else class="block text-center text-slate-700">{{ formatWholeNumber(row.commitment.rating_q4_actual) }}</span>
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ formatAccomplishmentValue(rowPreview(row.commitment, ratingRow(row.commitment.id)).targetTotal) }}
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ formatAccomplishmentValue(rowPreview(row.commitment, ratingRow(row.commitment.id)).actualTotal) }}
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ formatAccomplishmentPercent(rowPreview(row.commitment, ratingRow(row.commitment.id)).percent) }}
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable && row.commitment.weight != null" v-model="ratingRow(row.commitment.id).rating_quality" type="number" min="1" max="5" class="w-14 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ row.commitment.weight != null ? (row.commitment.rating_quality ?? '—') : '—' }}</span>
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable && row.commitment.weight != null" v-model="ratingRow(row.commitment.id).rating_efficiency" type="number" min="1" max="5" class="w-14 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ row.commitment.weight != null ? (row.commitment.rating_efficiency ?? '—') : '—' }}</span>
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable && row.commitment.weight != null" v-model="ratingRow(row.commitment.id).rating_timeliness" type="number" min="1" max="5" class="w-14 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ row.commitment.weight != null ? (row.commitment.rating_timeliness ?? '—') : '—' }}</span>
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center font-semibold text-slate-800">
                                                    {{ rowPreview(row.commitment, ratingRow(row.commitment.id)).avg != null ? rowPreview(row.commitment, ratingRow(row.commitment.id)).avg.toFixed(2) : '—' }}
                                                </td>
                                                <td :rowspan="row.lineCount" class="border border-slate-300 px-2 py-1 text-center font-semibold text-amber-800">
                                                    {{ rowWeightedDisplay(row.commitment, ratingRow(row.commitment.id)) }}
                                                </td>
                                            </template>
                                        </tr>
                                    </template>
                                </template>
                                <tr class="bg-slate-100 font-semibold">
                                    <td colspan="2" class="border border-slate-300 px-2 py-1 text-right">TOTAL</td>
                                    <td class="border border-slate-300 px-2 py-1 text-center">
                                        {{ sortedCommitments.reduce((a, c) => a + Number(c.weight || 0), 0).toFixed(0) }}%
                                    </td>
                                    <td colspan="2" class="border border-slate-300"></td>
                                    <td colspan="7" class="border border-slate-300"></td>
                                    <td colspan="3" class="border border-slate-300"></td>
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
                                    <td colspan="15" class="border border-slate-300 px-2 py-1 text-center">
                                        {{ Number(submission.overall_rating).toFixed(2) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

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

                <div v-if="isEditable" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <AppIcon name="check-badge" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-800">Decision</p>
                            <p class="mt-1 text-xs text-slate-500">Approve after completing each row, or return with actionable comments (min. 20 characters).</p>

                            <div class="mt-3 flex gap-2">
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
                                    Return for revision
                                </button>
                            </div>

                            <div class="mt-4">
                                <InputLabel :value="reviewForm.action === 'return' ? 'Comments for employee (required when returning)' : 'Optional comments'" />
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

                            <div class="mt-6 flex justify-end gap-2">
                                <Link :href="route('dashboard')">
                                    <SecondaryButton type="button">
                                        <span class="inline-flex items-center gap-1.5">
                                            <AppIcon name="x-mark" class="h-4 w-4" />
                                            Cancel
                                        </span>
                                    </SecondaryButton>
                                </Link>
                                <PrimaryButton :disabled="reviewForm.processing" @click="submitReview">
                                    <span class="inline-flex items-center gap-1.5">
                                        <AppIcon name="check-badge" class="h-4 w-4" />
                                        Submit decision
                                    </span>
                                </PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="submission.supervisor_feedback" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm text-sm sm:p-5">
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
                            Remarks = Weight% × Average. TOTAL Remarks = Σ Remarks = final average rating.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

