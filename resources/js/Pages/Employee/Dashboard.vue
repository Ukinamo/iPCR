<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CommitmentIpcrTable from '@/Components/CommitmentIpcrTable.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import IpcrExportDropdown from '@/Components/IpcrExportDropdown.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { formatDecimal, formatWholeNumber } from '@/utils/numberFormat';
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
    canAddCommitment: {
        type: Boolean,
        default: true,
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

const tab = ref('commitments');
const showCreateCommitmentPanel = ref(false);

const statCards = [
    { key: 'activeCommitments', label: 'Active Commitments', icon: 'clipboard', tone: 'bg-sky-100 text-sky-700' },
    { key: 'pendingReview', label: 'Pending Review', icon: 'clock', tone: 'bg-amber-100 text-amber-700' },
    { key: 'approvalRate', label: 'Approval Rate', icon: 'check-badge', tone: 'bg-emerald-100 text-emerald-700', suffix: '%' },
];

const tabs = [
    { id: 'commitments', label: 'My Commitments', icon: 'clipboard' },
    { id: 'history', label: 'Commitment history', icon: 'star' },
];

const commitmentForm = useForm({
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
    period_label: props.period.label,
    entries: [],
    evidence: {
        title: '',
        description: '',
        files: [],
    },
});

let itemSeq = 0;

function newItem(weight = null) {
    itemSeq += 1;
    return {
        _uid: itemSeq,
        description: '',
        weight,
        annual_office_target: '',
        individual_annual_targets: '',
    };
}

function newEntry(functionType, defaultWeight) {
    return {
        enabled: true,
        function_type: functionType,
        title: '',
        items: [newItem(defaultWeight)],
    };
}

const groupedCommitments = computed(() => {
    const groups = new Map();
    const statusRank = { draft: 0, returned: 1, in_review: 2, approved: 3 };

    for (const c of props.commitments || []) {
        const key = packageGroupKey(c);
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                first_id: c.id,
                batch_id: c.batch_id,
                ipcr_submission_id: c.ipcr_submission_id,
                period_label: c.period_label,
                status: c.status,
                items: [],
                functionMap: new Map(),
                total_weight: 0,
                total_evidence: 0,
                created_at: c.created_at,
                has_core: false,
                has_strategic: false,
            });
        }
        const g = groups.get(key);
        if (!g.first_id || c.id < g.first_id) {
            g.first_id = c.id;
        }
        g.items.push(c);
        g.total_weight += Number(c.weight || 0);
        g.total_evidence += (c.accomplishments?.length || 0);
        if (c.function_type === 'core') {
            g.has_core = true;
        }
        if (c.function_type === 'strategic') {
            g.has_strategic = true;
        }
        if ((statusRank[c.status] ?? -1) < (statusRank[g.status] ?? -1)) {
            g.status = c.status;
        }
        if (!g.created_at || (c.created_at && c.created_at < g.created_at)) {
            g.created_at = c.created_at;
        }
        const fnKey = `${c.function_type}|${c.title}`;
        if (!g.functionMap.has(fnKey)) {
            g.functionMap.set(fnKey, { function_type: c.function_type, title: c.title, count: 0 });
        }
        g.functionMap.get(fnKey).count += 1;
    }

    return Array.from(groups.values()).map((g) => ({
        ...g,
        functions: Array.from(g.functionMap.values()),
    }));
});

function packageGroupKey(c) {
    if (c.batch_id) {
        return `batch:${c.batch_id}`;
    }

    if (c.ipcr_submission_id) {
        return `submission:${c.ipcr_submission_id}`;
    }

    return `solo:${c.id}`;
}

function packageCardTitle(group) {
    if (group.status === 'returned') {
        return 'Returned IPCR package';
    }

    if (group.has_core && group.has_strategic) {
        return 'IPCR package (Core + Strategic)';
    }

    return 'Commitment package';
}

function packageIsEditable(status) {
    return status === 'draft' || status === 'returned';
}

function formatBatchDate(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
}

function openCreateCommitmentPanel() {
    if (!props.canAddCommitment) {
        return;
    }
    showCreateCommitmentPanel.value = true;
    tab.value = 'commitments';
    if (!commitmentForm.entries.length) {
        resetCommitmentCreateForm();
    }
}

function closeCreateCommitmentPanel() {
    showCreateCommitmentPanel.value = false;
    resetCommitmentCreateForm();
}

function resetCommitmentCreateForm() {
    commitmentForm.reset();
    commitmentForm.evaluation_year = props.period.year;
    commitmentForm.evaluation_quarter = props.period.quarter;
    commitmentForm.period_label = props.period.label;
    commitmentForm.evidence = { title: '', description: '', files: [] };
    commitmentForm.entries = [
        newEntry('core', 0),
        newEntry('strategic', 0),
    ];
}

function submitNewCommitment() {
    const payload = commitmentForm.entries
        .filter((e) => e.enabled)
        .flatMap((e) =>
            (e.items || []).map((it) => ({
                function_type: e.function_type,
                title: e.title,
                description: it.description,
                weight: it.weight === '' || it.weight == null ? null : it.weight,
                annual_office_target: it.annual_office_target,
                individual_annual_targets: it.individual_annual_targets,
            })),
        );

    if (!payload.length) {
        return;
    }

    commitmentForm.transform((data) => ({
        evaluation_year: data.evaluation_year,
        evaluation_quarter: data.evaluation_quarter,
        period_label: data.period_label,
        entries: payload,
        evidence_title: data.evidence?.title ?? '',
        evidence_description: data.evidence?.description ?? '',
        evidence_files: data.evidence?.files ?? [],
    }));

    commitmentForm.post(route('employee.commitments.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showCreateCommitmentPanel.value = false;
            resetCommitmentCreateForm();
        },
    });
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
    if (!desc) return [c?.title ?? ''];
    const lines = desc.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean);
    return lines.length ? lines : [c?.title ?? ''];
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
                    <p class="text-sm text-gray-500">Track your IPCR commitments and manage your performance evaluation.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
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
                    v-if="submission"
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
                            {{ submission.status.replace('_', ' ') }}
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
                            <p class="mt-2 text-xs text-amber-800">Update your commitments below, then submit again when ready.</p>
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

                <div class="rounded-xl border border-indigo-100 bg-indigo-50/80 p-5 text-sm text-indigo-950 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                            <AppIcon name="chart-bar" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-indigo-900">SPMS weighting (this quarter, editable drafts)</p>
                            <p class="mt-1 text-indigo-900/85">
                                Targets must total <strong>{{ weightSummary.core_cap }}% core</strong> and
                                <strong>{{ weightSummary.strategic_cap }}% strategic</strong> before you can submit for review.
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

                <div class="flex flex-wrap gap-2 rounded-lg bg-indigo-50/60 p-1 text-sm font-semibold text-slate-700">
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 min-w-[9rem]"
                        :class="tab === item.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="tab = item.id"
                    >
                        <AppIcon :name="item.icon" class="h-4 w-4 shrink-0" />
                        {{ item.label }}
                    </button>
                </div>

                <div v-show="tab === 'commitments'" class="space-y-4">
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
                                <h3 class="text-lg font-semibold text-slate-900">Performance Commitments</h3>
                                <p class="text-sm text-slate-500">{{ period.label }} · align weights before submission</p>
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
                        v-if="!canAddCommitment && addCommitmentBlockedReason"
                        class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                    >
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                            <AppIcon name="exclamation-triangle" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="font-semibold text-amber-900">Adding commitments is paused</p>
                            <p class="mt-1 text-amber-900/90">{{ addCommitmentBlockedReason }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <AppIcon name="plus" class="h-4 w-4" />
                            </span>
                            <div>
                                <h4 class="text-sm font-semibold text-slate-900">Commitments</h4>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    <template v-if="canAddCommitment">Add a target and optional proof in one step.</template>
                                    <template v-else>Unavailable while your package is under review or this quarter is approved.</template>
                                </p>
                            </div>
                        </div>
                        <PrimaryButton
                            v-if="canAddCommitment"
                            type="button"
                            class="shrink-0"
                            @click="openCreateCommitmentPanel"
                        >
                            <span class="inline-flex items-center gap-1.5">
                                <AppIcon name="plus" class="h-4 w-4" />
                                Add commitment
                            </span>
                        </PrimaryButton>
                        <SecondaryButton
                            v-else
                            type="button"
                            class="shrink-0 cursor-not-allowed opacity-60"
                            disabled
                        >
                            <span class="inline-flex items-center gap-1.5">
                                <AppIcon name="plus" class="h-4 w-4" />
                                Add commitment
                            </span>
                        </SecondaryButton>
                    </div>

                    <div
                        v-if="showCreateCommitmentPanel && canAddCommitment"
                        class="rounded-xl border-2 border-blue-200 bg-white p-6 shadow-lg ring-1 ring-blue-100"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                    <AppIcon name="plus" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h4 class="text-lg font-semibold text-slate-900">New commitment & evidence</h4>
                                    <p class="mt-1 text-xs text-slate-500">
                                        Fill in your commitments like the IPCR form, then optionally attach up to 3 evidence files for the whole package in one step.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form class="mt-6" @submit.prevent="submitNewCommitment">
                            <CommitmentPackageForm
                                v-model:entries="commitmentForm.entries"
                                v-model:evidence="commitmentForm.evidence"
                                :weight-summary="weightSummary"
                                :errors="commitmentForm.errors"
                                :processing="commitmentForm.processing"
                                show-evidence
                                submit-label="Save commitments"
                                @submit="submitNewCommitment"
                                @cancel="closeCreateCommitmentPanel"
                            />
                        </form>
                    </div>

                    <div
                        v-if="!groupedCommitments.length && canAddCommitment"
                        class="rounded-xl border border-dashed border-slate-300 bg-white/60 p-8 text-center text-sm text-slate-500"
                    >
                        <AppIcon name="clipboard" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">
                            No commitments for this quarter yet. Click
                            <strong class="inline-flex items-center gap-1">
                                <AppIcon name="plus" class="h-3.5 w-3.5" />
                                Add commitment
                            </strong>
                            to get started.
                        </p>
                    </div>
                    <div
                        v-else-if="!groupedCommitments.length"
                        class="rounded-xl border border-dashed border-slate-300 bg-white/60 p-8 text-center text-sm text-slate-500"
                    >
                        <AppIcon name="clipboard" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">No editable commitments for this quarter right now.</p>
                    </div>

                    <div
                        v-for="g in groupedCommitments"
                        :key="g.key"
                        class="overflow-hidden rounded-xl border bg-white shadow-sm"
                        :class="g.status === 'returned'
                            ? 'border-amber-300 ring-1 ring-amber-100'
                            : 'border-slate-200'"
                    >
                        <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-900">
                                        {{ packageCardTitle(g) }}
                                        <span v-if="g.created_at" class="ml-1 text-xs font-normal text-slate-500">
                                            · saved {{ formatBatchDate(g.created_at) }}
                                        </span>
                                    </h4>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="statusBadge(g.status)">
                                        {{ g.status.replace('_', ' ') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ g.period_label }}
                                    <span v-if="g.has_core && g.has_strategic" class="font-medium text-slate-600">
                                        · Core + Strategic
                                    </span>
                                    · {{ g.functions.length }} function{{ g.functions.length === 1 ? '' : 's' }}
                                    · {{ g.items.length }} indicator{{ g.items.length === 1 ? '' : 's' }}
                                    · Σ Weight <strong>{{ g.total_weight.toFixed(2) }}%</strong>
                                    <span
                                        v-if="g.total_evidence"
                                        class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 ring-1 ring-emerald-100"
                                    >
                                        <AppIcon name="paper-clip" class="h-3 w-3" />
                                        {{ g.total_evidence }} evidence file{{ g.total_evidence === 1 ? '' : 's' }}
                                    </span>
                                    <span v-else class="ml-1 text-slate-400">· no evidence yet</span>
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <PrimaryButton
                                    type="button"
                                    class="!bg-blue-600 hover:!bg-blue-700"
                                    @click="router.visit(route('employee.commitments.show', g.first_id))"
                                >
                                    <span class="inline-flex items-center gap-1.5">
                                        <AppIcon :name="packageIsEditable(g.status) ? 'pencil' : 'eye'" class="h-4 w-4" />
                                        {{ packageIsEditable(g.status) ? 'Edit' : 'View' }}
                                    </span>
                                </PrimaryButton>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            <CommitmentIpcrTable :commitments="g.items" />
                        </div>
                    </div>
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
                                <IpcrExportDropdown
                                    :submission-id="s.id"
                                    mode="employee-submission"
                                    label="Export"
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
