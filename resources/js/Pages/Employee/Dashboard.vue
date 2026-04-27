<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    stats: Object,
    commitments: Array,
    approvedHistory: Array,
    period: Object,
    submission: Object,
    weightSummary: Object,
    canSubmitPeriod: Boolean,
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

const commitmentForm = useForm({
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
    period_label: props.period.label,
    entries: [],
});

let itemSeq = 0;

function newItem(weight = 0) {
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
        evidence: {
            title: '',
            description: '',
            files: [],
        },
    };
}

function setEntryEvidenceFiles(entry, event) {
    const fl = event.target.files;
    entry.evidence.files = fl && fl.length ? Array.from(fl) : [];
}

function removeEntryEvidenceFile(entry, index) {
    entry.evidence.files.splice(index, 1);
}

function addItemRow(entryIdx) {
    const entry = commitmentForm.entries[entryIdx];
    if (!entry) return;
    entry.items.push(newItem(0));
}

function removeItemRow(entryIdx, itemIdx) {
    const entry = commitmentForm.entries[entryIdx];
    if (!entry || entry.items.length <= 1) return;
    entry.items.splice(itemIdx, 1);
}

function entryWeightTotal(entry) {
    return (entry.items || []).reduce((sum, it) => sum + Number(it.weight || 0), 0);
}

function sectionCap(type) {
    return type === 'core'
        ? Number(props.weightSummary?.core_cap ?? 60)
        : Number(props.weightSummary?.strategic_cap ?? 40);
}

function sectionWeightTotal(type) {
    return commitmentForm.entries
        .filter((e) => e.enabled && e.function_type === type)
        .reduce((sum, e) => sum + entryWeightTotal(e), 0);
}

function sectionRemaining(type) {
    const remaining = sectionCap(type) - sectionWeightTotal(type);
    return Math.max(0, Math.round(remaining * 100) / 100);
}

function addFunctionEntry(type) {
    const last = [...commitmentForm.entries].reverse().findIndex((e) => e.function_type === type);
    const insertAt = last === -1
        ? commitmentForm.entries.length
        : commitmentForm.entries.length - last;
    commitmentForm.entries.splice(insertAt, 0, newEntry(type, sectionRemaining(type)));
}

function removeFunctionEntry(eIdx) {
    const sameType = commitmentForm.entries.filter(
        (e) => e.function_type === commitmentForm.entries[eIdx]?.function_type,
    );
    if (sameType.length <= 1) return;
    commitmentForm.entries.splice(eIdx, 1);
}

const commitmentErrorList = computed(() => {
    const errs = commitmentForm.errors || {};
    return Object.values(errs).filter((m) => typeof m === 'string' && m.length);
});

const groupedCommitments = computed(() => {
    const groups = new Map();
    const statusRank = { draft: 0, returned: 1, in_review: 2, approved: 3 };

    for (const c of props.commitments || []) {
        const key = c.batch_id || `solo-${c.id}`;
        if (!groups.has(key)) {
            groups.set(key, {
                key,
                first_id: c.id,
                batch_id: c.batch_id,
                period_label: c.period_label,
                status: c.status,
                items: [],
                functionMap: new Map(),
                total_weight: 0,
                total_evidence: 0,
                created_at: c.created_at,
            });
        }
        const g = groups.get(key);
        g.items.push(c);
        g.total_weight += Number(c.weight || 0);
        g.total_evidence += (c.accomplishments?.length || 0);
        if ((statusRank[c.status] ?? -1) < (statusRank[g.status] ?? -1)) {
            g.status = c.status;
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

const coreEntries = computed(() =>
    commitmentForm.entries
        .map((entry, idx) => ({ entry, idx }))
        .filter((x) => x.entry.function_type === 'core'),
);

const strategicEntries = computed(() =>
    commitmentForm.entries
        .map((entry, idx) => ({ entry, idx }))
        .filter((x) => x.entry.function_type === 'strategic'),
);

function openCreateCommitmentPanel() {
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
    commitmentForm.entries = [
        newEntry('core', 60),
        newEntry('strategic', 40),
    ];
}

function submitNewCommitment() {
    const payload = commitmentForm.entries
        .filter((e) => e.enabled)
        .flatMap((e) =>
            (e.items || []).map((it, idx) => {
                const row = {
                    function_type: e.function_type,
                    title: e.title,
                    description: it.description,
                    weight: it.weight,
                    annual_office_target: it.annual_office_target,
                    individual_annual_targets: it.individual_annual_targets,
                };
                if (idx === 0) {
                    row.evidence_title = e.evidence?.title ?? '';
                    row.evidence_description = e.evidence?.description ?? '';
                    row.evidence_files = e.evidence?.files ?? [];
                }
                return row;
            }),
        );

    if (!payload.length) {
        return;
    }

    commitmentForm.transform((data) => ({
        evaluation_year: data.evaluation_year,
        evaluation_quarter: data.evaluation_quarter,
        period_label: data.period_label,
        entries: payload,
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

const manageableCommitments = computed(() =>
    (props.commitments || []).filter((c) => canManageEvidence(c.status)),
);

const evidenceDrafts = reactive({});
const evidenceFileKeys = reactive({});
const evidenceErrors = reactive({});
const evidenceSubmitting = reactive({});

function ensureEvidenceDraft(commitmentId) {
    if (!evidenceDrafts[commitmentId]) {
        evidenceDrafts[commitmentId] = { title: '', description: '', files: [] };
    }
    if (evidenceFileKeys[commitmentId] == null) {
        evidenceFileKeys[commitmentId] = 0;
    }
    return evidenceDrafts[commitmentId];
}

function setEvidenceFiles(commitmentId, event) {
    const draft = ensureEvidenceDraft(commitmentId);
    const fl = event.target.files;
    draft.files = fl && fl.length ? Array.from(fl) : [];
}

function removeEvidenceFile(commitmentId, index) {
    const draft = ensureEvidenceDraft(commitmentId);
    draft.files.splice(index, 1);
}

function submitEvidence(commitmentId) {
    const draft = ensureEvidenceDraft(commitmentId);
    if (!draft.files.length && !draft.title.trim()) {
        evidenceErrors[commitmentId] = 'Attach at least one file, or provide a subject.';
        return;
    }
    evidenceErrors[commitmentId] = '';

    const fd = new FormData();
    fd.append('commitment_id', String(commitmentId));
    fd.append('title', draft.title ?? '');
    fd.append('description', draft.description ?? '');
    draft.files.forEach((f) => fd.append('files[]', f));

    evidenceSubmitting[commitmentId] = true;
    router.post(route('employee.accomplishments.store'), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            evidenceDrafts[commitmentId] = { title: '', description: '', files: [] };
            evidenceFileKeys[commitmentId] = (evidenceFileKeys[commitmentId] || 0) + 1;
            evidenceErrors[commitmentId] = '';
        },
        onError: (errors) => {
            evidenceErrors[commitmentId] = errors.files || errors.title || errors.commitment_id || 'Upload failed.';
        },
        onFinish: () => {
            evidenceSubmitting[commitmentId] = false;
        },
    });
}

function destroyEvidence(id) {
    if (confirm('Remove this evidence entry?')) {
        router.delete(route('employee.accomplishments.destroy', id), { preserveScroll: true });
    }
}

function canManageEvidence(status) {
    return status === 'draft' || status === 'returned';
}

function formatFileSize(bytes) {
    if (bytes == null || bytes === '') return '';
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) return '';
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

const editId = ref(null);
const editForm = useForm({
    title: '',
    description: '',
    function_type: 'core',
    weight: 60,
    annual_office_target: '',
    individual_annual_targets: '',
    period_label: props.period.label,
});

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

function startEdit(c) {
    editId.value = c.id;
    editForm.title = c.title;
    editForm.description = c.description ?? '';
    editForm.function_type = c.function_type;
    editForm.weight = Number(c.weight);
    editForm.annual_office_target = c.annual_office_target ?? '';
    editForm.individual_annual_targets = c.individual_annual_targets ?? '';
    editForm.period_label = c.period_label;
}

function saveEdit() {
    editForm.patch(route('employee.commitments.update', editId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editId.value = null;
        },
    });
}

function destroyCommitment(id) {
    if (confirm('Delete this commitment?')) {
        router.delete(route('employee.commitments.destroy', id), { preserveScroll: true });
    }
}

function pct(part, cap) {
    return Math.min(100, Math.round((part / cap) * 100));
}

function historyTotals(submission) {
    const rows = submission?.commitments || [];
    const weight = rows.reduce((sum, c) => sum + Number(c.weight || 0), 0);
    const weighted = rows.reduce((sum, c) => sum + Number(c.rating_weighted || 0), 0);
    return {
        weight: weight.toFixed(2),
        weighted: weighted.toFixed(2),
    };
}

function indicatorLines(c) {
    const desc = (c?.description ?? '').trim();
    if (!desc) return [c?.title ?? ''];
    const lines = desc.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean);
    return lines.length ? lines : [c?.title ?? ''];
}
</script>

<template>
    <Head title="Employee Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Welcome, Employee</h2>
                <p class="text-sm text-gray-500">Track your IPCR commitments and manage your performance evaluation.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-600">Active Commitments</p>
                            <span class="rounded-md bg-blue-50 p-2 text-blue-700">✓</span>
                        </div>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.activeCommitments }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-600">Pending Review</p>
                            <span class="rounded-md bg-amber-50 p-2 text-amber-700">⏱</span>
                        </div>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.pendingReview }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-600">Approval Rate</p>
                            <span class="rounded-md bg-emerald-50 p-2 text-emerald-700">▤</span>
                        </div>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.approvalRate }}%</p>
                    </div>
                </div>

                <div
                    v-if="submission"
                    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">IPCR package · {{ period.label }}</p>
                            <p class="mt-1 text-lg font-semibold text-slate-900">{{ submissionTitle(submission.status) }}</p>
                            <p v-if="submission.submitted_at" class="mt-1 text-sm text-slate-600">
                                Submitted: {{ new Date(submission.submitted_at).toLocaleString() }}
                            </p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="statusBadge(submission.status)">
                            {{ submission.status.replace('_', ' ') }}
                        </span>
                    </div>
                    <div
                        v-if="submission.status === 'returned' && submission.supervisor_feedback"
                        class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                    >
                        <p class="font-semibold text-amber-900">Supervisor comments</p>
                        <p class="mt-2 whitespace-pre-wrap text-amber-950/90">{{ submission.supervisor_feedback }}</p>
                        <p class="mt-2 text-xs text-amber-800">Update your commitments below, then submit again when ready.</p>
                    </div>
                    <div v-if="submission.status === 'approved' && submission.overall_rating" class="mt-4 text-sm text-slate-700">
                        <span class="font-semibold">Overall SPMS rating:</span>
                        {{ submission.overall_rating }}
                        <span v-if="submission.supervisor_feedback" class="mt-2 block text-slate-600">{{ submission.supervisor_feedback }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-indigo-100 bg-indigo-50/80 p-5 text-sm text-indigo-950 shadow-sm">
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

                <div class="flex gap-2 rounded-lg bg-slate-100 p-1 text-sm font-semibold text-slate-600">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-2"
                        :class="tab === 'commitments' ? 'bg-white text-slate-900 shadow-sm' : ''"
                        @click="tab = 'commitments'"
                    >
                        My Commitments
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-2"
                        :class="tab === 'history' ? 'bg-white text-slate-900 shadow-sm' : ''"
                        @click="tab = 'history'"
                    >
                        Commitment history
                    </button>
                </div>

                <div v-show="tab === 'commitments'" class="space-y-4">
                    <div v-if="submitSteps?.length" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
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
                                    {{ step.done ? '✓' : idx + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ step.title }}</p>
                                    <p v-if="step.detail" class="mt-1 text-xs text-slate-600">{{ step.detail }}</p>
                                </div>
                            </li>
                        </ol>
                        <p
                            v-if="canSubmitPeriod"
                            class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-900"
                        >
                            All set — you can submit your IPCR package for supervisor review using the button below.
                        </p>
                    </div>

                    <div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Performance Commitments</h3>
                            <p class="text-sm text-slate-500">{{ period.label }} · align weights before submission</p>
                            <p v-if="!canSubmitPeriod && firstBlockingSubmitStep" class="mt-2 text-xs font-medium text-amber-800">
                                Next: {{ firstBlockingSubmitStep.title }}
                            </p>
                        </div>
                        <div class="flex flex-col items-stretch gap-2 sm:items-end">
                            <PrimaryButton
                                v-if="canSubmitPeriod"
                                :disabled="submitPeriodForm.processing"
                                @click="submitPeriodForm.post(route('employee.submissions.store'))"
                            >
                                Submit for supervisor review
                            </PrimaryButton>
                            <SecondaryButton v-else type="button" class="cursor-not-allowed opacity-60" disabled>Submit for supervisor review</SecondaryButton>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-900">Commitments</h4>
                            <p class="mt-0.5 text-xs text-slate-500">Add a target and optional proof in one step.</p>
                        </div>
                        <PrimaryButton type="button" class="shrink-0" @click="openCreateCommitmentPanel">+ Add commitment</PrimaryButton>
                    </div>

                    <div
                        v-if="showCreateCommitmentPanel"
                        class="rounded-xl border-2 border-blue-200 bg-white p-6 shadow-lg ring-1 ring-blue-100"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h4 class="text-lg font-semibold text-slate-900">New commitment & evidence</h4>
                                <p class="mt-1 text-xs text-slate-500">
                                    Fill in the commitment first, then optionally attach what you already did (subject, notes, file). Everything saves in
                                    one click.
                                </p>
                            </div>
                        </div>

                        <form class="mt-6 space-y-6" @submit.prevent="submitNewCommitment">
                            <p class="text-xs text-slate-500">
                                Fill in your commitments like the IPCR form. Use <strong>+ Add row</strong> under a Function to list multiple
                                Services/Indicators with their own Weight, Annual Office Target and Individual Annual Targets. Your supervisor fills the
                                Accomplishments and Rating columns later.
                            </p>

                            <div class="overflow-x-auto rounded-lg border border-slate-300">
                                <table class="min-w-full border-collapse text-xs">
                                    <thead class="bg-slate-100 text-[11px] font-semibold text-slate-700">
                                        <tr>
                                            <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 190px">MFO / PAP<br />(Function)</th>
                                            <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 260px">
                                                Services / Programs / Projects / Indicators
                                                <br />
                                                <span class="text-[10px] font-normal normal-case text-slate-500">(one line per indicator — use Enter)</span>
                                            </th>
                                            <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 72px">Weight</th>
                                            <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 110px">Annual Office Target</th>
                                            <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 110px">Individual Annual Targets</th>
                                            <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 44px"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="bg-blue-50">
                                            <td
                                                colspan="6"
                                                class="border border-slate-300 px-2 py-1 text-center text-[11px] font-bold uppercase tracking-wide"
                                                :class="sectionWeightTotal('core') > sectionCap('core') + 0.01
                                                    ? 'bg-rose-100 text-rose-900'
                                                    : 'text-blue-900'"
                                            >
                                                Core Functions · Shared cap {{ sectionCap('core') }}% —
                                                Σ <strong>{{ sectionWeightTotal('core') }}%</strong> used ·
                                                <span :class="sectionRemaining('core') === 0 ? 'text-emerald-700' : ''">
                                                    {{ sectionRemaining('core') }}% remaining
                                                </span>
                                                <span v-if="sectionWeightTotal('core') > sectionCap('core') + 0.01">
                                                    · OVER BY {{ (sectionWeightTotal('core') - sectionCap('core')).toFixed(2) }}%
                                                </span>
                                            </td>
                                        </tr>
                                        <template v-for="({ entry, idx: eIdx }) in coreEntries" :key="'core-' + eIdx">
                                            <template v-for="(item, iIdx) in entry.items" :key="item._uid">
                                                <tr :class="entry.enabled ? '' : 'opacity-50'">
                                                    <td
                                                        v-if="iIdx === 0"
                                                        :rowspan="entry.items.length"
                                                        class="border border-slate-300 px-2 py-1 align-top"
                                                    >
                                                        <TextInput
                                                            v-model="entry.title"
                                                            type="text"
                                                            class="block w-full text-xs"
                                                            placeholder="e.g. Development of Standards..."
                                                            :required="entry.enabled"
                                                        />
                                                        <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-500">
                                                            <label class="inline-flex items-center gap-1">
                                                                <input
                                                                    v-model="entry.enabled"
                                                                    type="checkbox"
                                                                    class="rounded border-slate-300 text-blue-600 shadow-sm"
                                                                />
                                                                Include
                                                            </label>
                                                            <span>
                                                                Σ wt: <strong>{{ entryWeightTotal(entry) }}%</strong>
                                                            </span>
                                                        </div>
                                                        <div class="mt-2 flex flex-wrap gap-1">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 hover:bg-blue-100"
                                                                @click="addItemRow(eIdx)"
                                                            >
                                                                + Add row
                                                            </button>
                                                            <button
                                                                v-if="coreEntries.length > 1"
                                                                type="button"
                                                                class="inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                                                @click="removeFunctionEntry(eIdx)"
                                                            >
                                                                − Remove function
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <textarea
                                                            v-model="item.description"
                                                            rows="3"
                                                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            placeholder="One indicator per line"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <TextInput
                                                            v-model="item.weight"
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max="100"
                                                            class="block w-full text-xs"
                                                            :required="entry.enabled"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <TextInput
                                                            v-model="item.annual_office_target"
                                                            type="text"
                                                            class="block w-full text-xs"
                                                            placeholder="e.g. 60 or 100%"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <TextInput
                                                            v-model="item.individual_annual_targets"
                                                            type="text"
                                                            class="block w-full text-xs"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-1 py-1 text-center align-top">
                                                        <button
                                                            v-if="entry.items.length > 1"
                                                            type="button"
                                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-sm font-bold text-rose-700 hover:bg-rose-100"
                                                            title="Remove this row"
                                                            @click="removeItemRow(eIdx, iIdx)"
                                                        >
                                                            ×
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                        <tr class="bg-blue-50/40">
                                            <td colspan="6" class="border border-slate-300 px-2 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-md border border-blue-300 bg-white px-3 py-1.5 text-[11px] font-semibold text-blue-700 shadow-sm hover:bg-blue-50"
                                                    @click="addFunctionEntry('core')"
                                                >
                                                    + Add Core Function
                                                </button>
                                            </td>
                                        </tr>

                                        <tr class="bg-amber-50">
                                            <td
                                                colspan="6"
                                                class="border border-slate-300 px-2 py-1 text-center text-[11px] font-bold uppercase tracking-wide"
                                                :class="sectionWeightTotal('strategic') > sectionCap('strategic') + 0.01
                                                    ? 'bg-rose-100 text-rose-900'
                                                    : 'text-amber-900'"
                                            >
                                                Strategic Functions · Shared cap {{ sectionCap('strategic') }}% —
                                                Σ <strong>{{ sectionWeightTotal('strategic') }}%</strong> used ·
                                                <span :class="sectionRemaining('strategic') === 0 ? 'text-emerald-700' : ''">
                                                    {{ sectionRemaining('strategic') }}% remaining
                                                </span>
                                                <span v-if="sectionWeightTotal('strategic') > sectionCap('strategic') + 0.01">
                                                    · OVER BY {{ (sectionWeightTotal('strategic') - sectionCap('strategic')).toFixed(2) }}%
                                                </span>
                                            </td>
                                        </tr>
                                        <template v-for="({ entry, idx: eIdx }) in strategicEntries" :key="'strat-' + eIdx">
                                            <template v-for="(item, iIdx) in entry.items" :key="item._uid">
                                                <tr :class="entry.enabled ? '' : 'opacity-50'">
                                                    <td
                                                        v-if="iIdx === 0"
                                                        :rowspan="entry.items.length"
                                                        class="border border-slate-300 px-2 py-1 align-top"
                                                    >
                                                        <TextInput
                                                            v-model="entry.title"
                                                            type="text"
                                                            class="block w-full text-xs"
                                                            placeholder="e.g. Strategic Project..."
                                                            :required="entry.enabled"
                                                        />
                                                        <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-500">
                                                            <label class="inline-flex items-center gap-1">
                                                                <input
                                                                    v-model="entry.enabled"
                                                                    type="checkbox"
                                                                    class="rounded border-slate-300 text-blue-600 shadow-sm"
                                                                />
                                                                Include
                                                            </label>
                                                            <span>
                                                                Σ wt: <strong>{{ entryWeightTotal(entry) }}%</strong>
                                                            </span>
                                                        </div>
                                                        <div class="mt-2 flex flex-wrap gap-1">
                                                            <button
                                                                type="button"
                                                                class="inline-flex items-center rounded border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-800 hover:bg-amber-100"
                                                                @click="addItemRow(eIdx)"
                                                            >
                                                                + Add row
                                                            </button>
                                                            <button
                                                                v-if="strategicEntries.length > 1"
                                                                type="button"
                                                                class="inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                                                @click="removeFunctionEntry(eIdx)"
                                                            >
                                                                − Remove function
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <textarea
                                                            v-model="item.description"
                                                            rows="3"
                                                            class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                            placeholder="One indicator per line"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <TextInput
                                                            v-model="item.weight"
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            max="100"
                                                            class="block w-full text-xs"
                                                            :required="entry.enabled"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <TextInput
                                                            v-model="item.annual_office_target"
                                                            type="text"
                                                            class="block w-full text-xs"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-2 py-1 align-top">
                                                        <TextInput
                                                            v-model="item.individual_annual_targets"
                                                            type="text"
                                                            class="block w-full text-xs"
                                                        />
                                                    </td>
                                                    <td class="border border-slate-300 px-1 py-1 text-center align-top">
                                                        <button
                                                            v-if="entry.items.length > 1"
                                                            type="button"
                                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-sm font-bold text-rose-700 hover:bg-rose-100"
                                                            title="Remove this row"
                                                            @click="removeItemRow(eIdx, iIdx)"
                                                        >
                                                            ×
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </template>
                                        <tr class="bg-amber-50/40">
                                            <td colspan="6" class="border border-slate-300 px-2 py-2 text-center">
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center rounded-md border border-amber-300 bg-white px-3 py-1.5 text-[11px] font-semibold text-amber-800 shadow-sm hover:bg-amber-50"
                                                    @click="addFunctionEntry('strategic')"
                                                >
                                                    + Add Strategic Function
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-[11px] text-slate-500">
                                Note: the <strong>Accomplishments</strong> (Q3/Q4 target & actual, %) and the <strong>Rating</strong> (Q, E, T, Average)
                                columns are filled by your supervisor during evaluation.
                            </p>

                            <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h5 class="text-sm font-semibold text-emerald-950">Evidence (optional)</h5>
                                        <p class="text-[11px] text-emerald-900/75">
                                            One subject + one description per Function — attach <strong>one or many files</strong>. You can also add more
                                            evidence later from the commitment card after saving.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-3 space-y-3">
                                    <template v-for="(entry, eIdx) in commitmentForm.entries" :key="'ev-' + eIdx">
                                        <div
                                            v-if="entry.enabled"
                                            class="rounded-md border border-slate-200 bg-white p-3"
                                        >
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <p class="text-xs font-semibold text-slate-800">
                                                    <span
                                                        class="mr-2 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                                        :class="entry.function_type === 'core'
                                                            ? 'bg-blue-100 text-blue-800'
                                                            : 'bg-amber-100 text-amber-800'"
                                                    >
                                                        {{ entry.function_type }}
                                                    </span>
                                                    {{ entry.title || '(untitled function)' }}
                                                </p>
                                                <p v-if="entry.evidence.files.length" class="text-[11px] text-slate-500">
                                                    {{ entry.evidence.files.length }} file(s) selected
                                                </p>
                                            </div>

                                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                                <div>
                                                    <InputLabel :value="'Subject / title'" />
                                                    <TextInput
                                                        v-model="entry.evidence.title"
                                                        type="text"
                                                        class="mt-1 block w-full text-xs"
                                                        placeholder="e.g. Q3 accomplishment report"
                                                    />
                                                </div>
                                                <div>
                                                    <InputLabel :value="'Description (optional)'" />
                                                    <TextInput
                                                        v-model="entry.evidence.description"
                                                        type="text"
                                                        class="mt-1 block w-full text-xs"
                                                        placeholder="Short note about this evidence"
                                                    />
                                                </div>
                                                <div class="md:col-span-2">
                                                    <InputLabel :value="'Files (one or many)'" />
                                                    <input
                                                        type="file"
                                                        multiple
                                                        class="mt-1 block w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
                                                        @change="(ev) => setEntryEvidenceFiles(entry, ev)"
                                                    />
                                                    <p class="mt-1 text-[10px] text-slate-500">
                                                        jpg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, zip · up to 12 MB each, 20 files per function.
                                                    </p>
                                                    <ul v-if="entry.evidence.files.length" class="mt-2 space-y-1 text-[11px] text-slate-700">
                                                        <li
                                                            v-for="(f, i) in entry.evidence.files"
                                                            :key="i"
                                                            class="flex items-center justify-between gap-2 rounded border border-slate-200 bg-slate-50 px-2 py-1"
                                                        >
                                                            <span class="truncate">
                                                                {{ f.name }}
                                                                <span class="text-slate-400">· {{ formatFileSize(f.size) }}</span>
                                                            </span>
                                                            <button
                                                                type="button"
                                                                class="text-rose-700 hover:underline"
                                                                @click="removeEntryEvidenceFile(entry, i)"
                                                            >
                                                                Remove
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <InputError class="mt-2" :message="commitmentForm.errors.entries" />

                            <div
                                v-if="commitmentErrorList.length"
                                class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900"
                            >
                                <p class="font-semibold">Couldn't save — please fix these fields:</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    <li v-for="(msg, key) in commitmentErrorList" :key="key">{{ msg }}</li>
                                </ul>
                            </div>

                            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                                <PrimaryButton type="submit" :disabled="commitmentForm.processing">
                                    {{ commitmentForm.processing ? 'Saving…' : 'Save commitments' }}
                                </PrimaryButton>
                                <SecondaryButton type="button" :disabled="commitmentForm.processing" @click="closeCreateCommitmentPanel">Cancel</SecondaryButton>
                            </div>
                        </form>
                    </div>

                    <div
                        v-if="!groupedCommitments.length"
                        class="rounded-xl border border-dashed border-slate-300 bg-white/60 p-8 text-center text-sm text-slate-500"
                    >
                        No commitments for this quarter yet. Click <strong>+ Add commitment</strong> to get started.
                    </div>

                    <div
                        v-for="g in groupedCommitments"
                        :key="g.key"
                        class="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="text-base font-semibold text-slate-900">
                                    Commitment package
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
                                · {{ g.functions.length }} function{{ g.functions.length === 1 ? '' : 's' }}
                                · {{ g.items.length }} indicator{{ g.items.length === 1 ? '' : 's' }}
                                · Σ Weight <strong>{{ g.total_weight.toFixed(2) }}%</strong>
                                · {{ g.total_evidence }} evidence file{{ g.total_evidence === 1 ? '' : 's' }}
                            </p>
                            <ul class="mt-3 space-y-1">
                                <li
                                    v-for="(fn, i) in g.functions"
                                    :key="i"
                                    class="flex flex-wrap items-center gap-2 text-xs text-slate-700"
                                >
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                        :class="fn.function_type === 'core'
                                            ? 'bg-blue-100 text-blue-800'
                                            : 'bg-amber-100 text-amber-800'"
                                    >
                                        {{ fn.function_type }}
                                    </span>
                                    <span class="truncate">{{ fn.title || '(untitled)' }}</span>
                                    <span class="text-slate-400">· {{ fn.count }} indicator{{ fn.count === 1 ? '' : 's' }}</span>
                                </li>
                            </ul>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <PrimaryButton
                                type="button"
                                class="!bg-blue-600 hover:!bg-blue-700"
                                @click="router.visit(route('employee.commitments.show', g.first_id))"
                            >
                                View →
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <div v-show="tab === 'history'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Commitment history (approved)</h3>
                                <p class="mt-1 text-sm text-slate-500">
                                    View your approved periods and the ratings per commitment.
                                </p>
                            </div>
                            <a
                                :href="route('employee.ratings.history.export')"
                                class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                Export to Excel
                            </a>
                        </div>
                    </div>

                    <div v-if="!approvedHistory?.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                        No approved commitment history yet.
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
                            <p class="text-sm font-semibold text-amber-800">Overall: {{ s.overall_rating ?? '—' }}</p>
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
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ Number(c.weight) }}%</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.annual_office_target ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.individual_annual_targets ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q3_target ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q3_actual ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q4_target ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q4_actual ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_target_total ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_actual_total ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_percent != null ? (Number(c.rating_percent) * 100).toFixed(0) + '%' : '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_quality ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_efficiency ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_timeliness ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_average ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.remarks ?? (c.rating_weighted ?? '—') }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr class="bg-slate-100 font-semibold">
                                        <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">TOTAL</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ historyTotals(s).weight }}%</td>
                                        <td class="border border-slate-300 px-2 py-1" colspan="13"></td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ historyTotals(s).weighted }}</td>
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
                    <div class="text-lg">ⓘ</div>
                    <div>
                        <p class="font-semibold">Important Reminder</p>
                        <p class="mt-1 text-sky-900/80">{{ reminder }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
