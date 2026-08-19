<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import IpcrEmployeeAnswerTable from '@/Components/IpcrEmployeeAnswerTable.vue';
import IpcrPreviewLink from '@/Components/IpcrPreviewLink.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatDecimal, formatWholeNumber } from '@/utils/numberFormat';
import { suggestedRating } from '@/utils/ipcrRating';
import { statusLabel } from '@/utils/statusLabels';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stats: Object,
    commitments: Array,
    approvedHistory: Array,
    period: Object,
    submission: Object,
    weightSummary: Object,
    canSubmitPeriod: Boolean,
    canAnswerForm: {
        type: Boolean,
        default: false,
    },
    hasAssignedForm: {
        type: Boolean,
        default: false,
    },
    addCommitmentBlockedReason: {
        type: String,
        default: null,
    },
    submitSteps: {
        type: Array,
        default: () => [],
    },
    reminder: String,
});

/** First checklist row that is not done — what the employee should do next */
const firstBlockingSubmitStep = computed(() => props.submitSteps?.find((s) => !s.done) ?? null);

const isApproved = computed(() => props.submission?.status === 'approved');

const tab = ref(isApproved.value ? 'history' : 'commitments');

const statCards = [
    { key: 'activeCommitments', label: 'Active Commitments', icon: 'clipboard', tone: 'bg-sky-100 text-sky-700' },
    { key: 'pendingReview', label: 'Pending Review', icon: 'clock', tone: 'bg-amber-100 text-amber-700' },
    { key: 'approvalRate', label: 'Approval Rate', icon: 'check-badge', tone: 'bg-emerald-100 text-emerald-700', suffix: '%' },
];

const tabs = [
    { id: 'commitments', label: 'My IPCR form', icon: 'clipboard' },
    { id: 'history', label: 'Commitment history', icon: 'star' },
];

function mapAnswerRow(c) {
    const suggested = suggestedRating(
        c.rating_q3_target ?? '',
        c.rating_q3_actual ?? '',
        c.rating_q4_target ?? '',
        c.rating_q4_actual ?? '',
    );

    return {
        id: c.id,
        function_type: c.function_type === 'strategic' ? 'strategic' : 'core',
        title: c.title ?? '',
        description: c.description ?? '',
        weight: c.weight ?? null,
        annual_office_target: c.annual_office_target ?? '',
        individual_annual_targets: c.individual_annual_targets ?? '',
        rating_q3_target: c.rating_q3_target ?? '',
        rating_q3_actual: c.rating_q3_actual ?? '',
        rating_q4_target: c.rating_q4_target ?? '',
        rating_q4_actual: c.rating_q4_actual ?? '',
        rating_quality: c.rating_quality ?? suggested,
        rating_efficiency: c.rating_efficiency ?? suggested,
        rating_timeliness: c.rating_timeliness ?? suggested,
    };
}

const answerForm = useForm({
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
    commitments: (props.commitments || []).map(mapAnswerRow),
});

function saveAnswers() {
    answerForm.transform((data) => ({
        evaluation_year: data.evaluation_year,
        evaluation_quarter: data.evaluation_quarter,
        commitments: data.commitments.map((row) => ({
            id: row.id,
            rating_q3_target: row.rating_q3_target === '' ? null : row.rating_q3_target,
            rating_q3_actual: row.rating_q3_actual === '' ? null : row.rating_q3_actual,
            rating_q4_target: row.rating_q4_target === '' ? null : row.rating_q4_target,
            rating_q4_actual: row.rating_q4_actual === '' ? null : row.rating_q4_actual,
        })),
    })).patch(route('employee.form-answers.update'), {
        preserveScroll: true,
        onFinish: () => answerForm.transform((data) => data),
    });
}

function cancelAnswers() {
    answerForm.commitments = (props.commitments || []).map(mapAnswerRow);
    answerForm.clearErrors();
}

function openFormView() {
    const first = props.commitments?.[0];
    if (!first?.id) {
        return;
    }
    router.visit(route('employee.commitments.show', first.id));
}

const submitPeriodForm = useForm({
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
});

function statusBadge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        draft: 'bg-slate-50 text-slate-700 ring-slate-100',
        returned: 'bg-amber-50 text-amber-900 ring-amber-100',
        pending: 'bg-amber-50 text-amber-900 ring-amber-100',
    };
    return map[status] ?? map.draft;
}

function submissionTitle(status) {
    const m = {
        in_review: 'With supervisor',
        approved: 'Approved',
        returned: 'Returned for revision',
        pending: 'Not yet submitted',
    };
    return m[status] ?? status;
}

function pct(part, cap) {
    return Math.min(100, Math.round((part / cap) * 100));
}

function historyTotals(submission) {
    const rows = submission?.commitments || [];
    const weight = rows.reduce((sum, c) => sum + Number(c.weight || 0), 0);
    const rated = rows.filter((c) => c.weight != null);
    const average = rated.reduce((sum, c) => sum + Number(c.rating_average || 0), 0);
    const weighted = rated.reduce((sum, c) => sum + Number(c.rating_weighted || 0), 0);
    return {
        weight: weight.toFixed(2),
        average: rated.some((c) => c.rating_average != null) ? formatDecimal(average, 2) : '—',
        weighted: rated.some((c) => c.rating_weighted != null) ? formatDecimal(weighted, 2) : '—',
    };
}

function indicatorLines(c) {
    const desc = (c?.description ?? '').trim();
    if (!desc) return [''];
    const lines = desc.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean);
    return lines.length ? lines : [''];
}

function formatAverage(c) {
    if (c?.weight == null) {
        return '—';
    }
    return formatDecimal(c?.rating_average, 2);
}

function formatWeighted(c) {
    if (c?.weight == null) {
        return '—';
    }
    if (c?.rating_weighted != null) {
        return Number(c.rating_weighted).toFixed(2);
    }
    if (c?.rating_average != null) {
        return (Number(c.rating_average) * (Number(c.weight) / 100)).toFixed(2);
    }
    return '—';
}
</script>

<template>
    <Head title="Employee Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                    <AppIcon name="identification" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Employee Dashboard</h2>
                    <p class="text-sm text-gray-500">Complete the assigned IPCR form and submit it for supervisor review.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div
                        v-for="card in statCards"
                        :key="card.key"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-600">{{ card.label }}</p>
                                <p class="mt-3 text-3xl font-bold text-slate-900">
                                    {{ stats[card.key] }}{{ card.suffix ?? '' }}
                                </p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="card.tone">
                                <AppIcon :name="card.icon" class="h-5 w-5" />
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="submission && !isApproved"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <AppIcon name="clipboard" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">IPCR package · {{ period.label }}</p>
                                <p class="mt-1 text-lg font-semibold text-slate-900">{{ submissionTitle(submission.status) }}</p>
                                <p v-if="submission.submitted_at" class="mt-1 text-sm text-slate-600">
                                    Submitted: {{ new Date(submission.submitted_at).toLocaleString() }}
                                </p>
                            </div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="statusBadge(submission.status)">
                            {{ statusLabel(submission.status) }}
                        </span>
                    </div>
                    <div
                        v-if="submission.status === 'returned' && submission.supervisor_feedback"
                        class="mt-4 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                    >
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <AppIcon name="exclamation-triangle" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="font-semibold text-amber-900">Supervisor comments</p>
                            <p class="mt-2 whitespace-pre-wrap text-amber-950/90">{{ submission.supervisor_feedback }}</p>
                            <p class="mt-2 text-xs text-amber-800">Update accomplishments below, then submit again when ready.</p>
                        </div>
                    </div>
                    <div v-if="submission.status === 'approved' && submission.overall_rating" class="mt-4 flex items-start gap-3 text-sm text-slate-700">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                            <AppIcon name="star" class="h-4 w-4" />
                        </span>
                        <div>
                            <span class="font-semibold">Overall SPMS rating:</span>
                            {{ submission.overall_rating }}
                            <span v-if="submission.supervisor_feedback" class="mt-2 block text-slate-600">{{ submission.supervisor_feedback }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="!isApproved" class="rounded-xl border border-indigo-100 bg-indigo-50/80 p-5 text-sm text-indigo-950 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                            <AppIcon name="chart-bar" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-indigo-900">SPMS weighting (assigned form)</p>
                            <p class="mt-1 text-indigo-900/85">
                                The administrator set this form to <strong>{{ weightSummary.core_cap }}% core</strong> and
                                <strong>{{ weightSummary.strategic_cap }}% strategic</strong>. You fill accomplishments; rating, average, and remarks compute automatically.
                            </p>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div>
                                    <div class="flex justify-between text-xs font-medium text-indigo-800">
                                        <span>Core ({{ weightSummary.core }}% / {{ weightSummary.core_cap }}%)</span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-white/80">
                                        <div
                                            class="h-2 rounded-full bg-indigo-600 transition-all"
                                            :style="{ width: pct(weightSummary.core, weightSummary.core_cap) + '%' }"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-medium text-indigo-800">
                                        <span>Strategic ({{ weightSummary.strategic }}% / {{ weightSummary.strategic_cap }}%)</span>
                                    </div>
                                    <div class="mt-1 h-2 overflow-hidden rounded-full bg-white/80">
                                        <div
                                            class="h-2 rounded-full bg-violet-500 transition-all"
                                            :style="{ width: pct(weightSummary.strategic, weightSummary.strategic_cap) + '%' }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto rounded-lg bg-indigo-50/60 p-1 text-sm font-semibold text-slate-700 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        class="inline-flex min-w-0 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-2"
                        :class="tab === item.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="tab = item.id"
                    >
                        <AppIcon :name="item.icon" class="h-4 w-4 shrink-0" />
                        {{ item.label }}
                    </button>
                </div>

                <div v-show="tab === 'commitments'" class="space-y-4">
                    <div
                        v-if="isApproved"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-950 shadow-sm"
                    >
                        <p class="font-semibold text-emerald-900">This period’s IPCR form is approved.</p>
                        <p class="mt-1 text-emerald-900/90">
                            It is no longer shown here. Open
                            <button type="button" class="font-semibold underline" @click="tab = 'history'">Commitment history</button>
                            to view it.
                        </p>
                    </div>

                    <template v-else>
                    <div v-if="submitSteps?.length" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                <AppIcon name="check-badge" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-slate-900">Before you can submit for review</h3>
                                <p class="mt-1 text-xs text-slate-500">
                                    Work through these in order. The highlighted step is what to do next.
                                </p>
                                <ol class="mt-4 list-none space-y-2">
                                    <li
                                        v-for="(step, idx) in submitSteps"
                                        :key="step.key"
                                        class="flex gap-3 rounded-lg border px-3 py-3 text-sm transition"
                                        :class="
                                            step.done
                                                ? 'border-emerald-200 bg-emerald-50/60 text-slate-700'
                                                : firstBlockingSubmitStep?.key === step.key
                                                  ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200'
                                                  : 'border-slate-100 bg-slate-50/90 text-slate-600'
                                        "
                                    >
                                        <span
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                            :class="step.done ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-800'"
                                            aria-hidden="true"
                                        >
                                            <AppIcon v-if="step.done" name="check-badge" class="h-4 w-4" />
                                            <template v-else>{{ idx + 1 }}</template>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-900">{{ step.title }}</p>
                                            <p v-if="step.detail" class="mt-1 text-xs text-slate-600">{{ step.detail }}</p>
                                        </div>
                                    </li>
                                </ol>
                                <p
                                    v-if="canSubmitPeriod"
                                    class="mt-4 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900"
                                >
                                    <AppIcon name="check-badge" class="h-4 w-4 shrink-0" />
                                    All set — you can submit your IPCR package for supervisor review using the button below.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                <AppIcon name="clipboard" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Assigned IPCR form</h3>
                                <p class="text-sm text-slate-500">{{ period.label }} · fill accomplishments, then submit</p>
                                <p v-if="!canSubmitPeriod && firstBlockingSubmitStep" class="mt-2 text-xs font-medium text-amber-800">
                                    Next: {{ firstBlockingSubmitStep.title }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col items-stretch gap-2 sm:items-end">
                            <PrimaryButton
                                v-if="canSubmitPeriod"
                                :disabled="submitPeriodForm.processing"
                                @click="submitPeriodForm.post(route('employee.submissions.store'))"
                            >
                                <span class="inline-flex items-center gap-1.5">
                                    <AppIcon name="arrow-top-right" class="h-4 w-4" />
                                    Submit for supervisor review
                                </span>
                            </PrimaryButton>
                            <SecondaryButton v-else type="button" class="cursor-not-allowed opacity-60" disabled>
                                <span class="inline-flex items-center gap-1.5">
                                    <AppIcon name="arrow-top-right" class="h-4 w-4" />
                                    Submit for supervisor review
                                </span>
                            </SecondaryButton>
                        </div>
                    </div>

                    <div
                        v-if="!hasAssignedForm && addCommitmentBlockedReason"
                        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                    >
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <AppIcon name="exclamation-triangle" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="font-semibold text-amber-900">No IPCR form assigned yet</p>
                            <p class="mt-1 text-amber-900/90">{{ addCommitmentBlockedReason }}</p>
                        </div>
                    </div>

                    <div
                        v-if="hasAssignedForm"
                        class="overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-sm sm:p-3"
                    >
                        <IpcrEmployeeAnswerTable
                            v-model:rows="answerForm.commitments"
                            :editable="canAnswerForm"
                            :processing="answerForm.processing"
                            :errors="answerForm.errors"
                            submit-label="Save accomplishments"
                            :show-cancel="canAnswerForm"
                            @submit="saveAnswers"
                            @cancel="cancelAnswers"
                            @view="openFormView"
                        />
                    </div>
                    </template>
                </div>

                <div v-show="tab === 'history'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                                    <AppIcon name="star" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">Commitment history (approved)</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        View your approved periods and the ratings per commitment.
                                    </p>
                                </div>
                            </div>
                            <a
                                :href="route('employee.ratings.history.export')"
                                class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                <AppIcon name="arrow-down-tray" class="h-4 w-4" />
                                Export to Excel
                            </a>
                        </div>
                    </div>

                    <div v-if="!approvedHistory?.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                        <AppIcon name="star" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">No approved commitment history yet.</p>
                    </div>

                    <div v-for="s in approvedHistory" :key="s.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3">
                            <div>
                                <p class="font-semibold text-slate-900">Q{{ s.evaluation_quarter }} {{ s.evaluation_year }}</p>
                                <p class="text-xs text-slate-500">
                                    Supervisor: {{ s.supervisor?.name ?? '—' }}
                                    <span v-if="s.reviewed_at"> · Reviewed {{ new Date(s.reviewed_at).toLocaleDateString() }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-sm font-semibold text-amber-800">Overall: {{ s.overall_rating ?? '—' }}</p>
                                <IpcrPreviewLink
                                    :submission-id="s.id"
                                    mode="employee-submission"
                                />
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse text-xs">
                                <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-600">
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
                                        <tr v-if="(s.commitments || []).some(c => c.function_type === group)" class="bg-slate-100 font-semibold">
                                            <td class="border border-slate-300 px-2 py-1 uppercase text-slate-700" colspan="17">
                                                {{ group === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                                ({{ (s.commitments || []).filter(c => c.function_type === group).reduce((a, c) => a + Number(c.weight || 0), 0) }}%)
                                            </td>
                                        </tr>
                                        <template v-for="c in (s.commitments || []).filter(c => c.function_type === group)" :key="c.id">
                                            <tr
                                                v-for="(line, li) in indicatorLines(c)"
                                                :key="c.id + '-' + li"
                                                class="align-top"
                                            >
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 font-semibold text-slate-800">
                                                    {{ c.title }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-slate-700">
                                                    {{ line }}
                                                </td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.weight != null ? Number(c.weight) + '%' : '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.annual_office_target ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.individual_annual_targets ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q3_target) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q3_actual) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q4_target) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q4_actual) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_target_total) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_actual_total) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_percent != null ? (Number(c.rating_percent) * 100).toFixed(0) + '%' : '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_quality ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_efficiency ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_timeliness ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatAverage(c) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWeighted(c) }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr class="bg-slate-100 font-semibold">
                                        <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">TOTAL</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ historyTotals(s).weight }}%</td>
                                        <td class="border border-slate-300 px-2 py-1" colspan="2"></td>
                                        <td class="border border-slate-300 px-2 py-1" colspan="7"></td>
                                        <td class="border border-slate-300 px-2 py-1" colspan="3"></td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ historyTotals(s).average }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center text-amber-800">{{ historyTotals(s).weighted }}</td>
                                    </tr>
                                    <tr class="bg-amber-50 font-semibold text-amber-900">
                                        <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">FINAL AVERAGE RATING</td>
                                        <td class="border border-slate-300 px-2 py-1" colspan="15">
                                            {{ s.overall_rating != null ? Number(s.overall_rating).toFixed(2) : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 rounded-lg border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                        <AppIcon name="exclamation-triangle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold">Important Reminder</p>
                        <p class="mt-1 text-sky-900/80">{{ reminder }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
