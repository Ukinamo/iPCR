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

function newEntry(functionType, defaultWeight) {
    return {
        enabled: true,
        function_type: functionType,
        title: '',
        description: '',
        weight: defaultWeight,
        progress: 0,
        evidence_title: '',
        evidence_description: '',
        evidence_file: null,
        fileKey: 0,
    };
}

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

function setCreateEvidenceFile(index, event) {
    const f = event.target.files?.[0];
    commitmentForm.entries[index].evidence_file = f || null;
}

function submitNewCommitment() {
    const payload = commitmentForm.entries
        .filter((e) => e.enabled)
        .map((e) => ({
            function_type: e.function_type,
            title: e.title,
            description: e.description,
            weight: e.weight,
            progress: e.progress,
            evidence_title: e.evidence_title,
            evidence_description: e.evidence_description,
            evidence_file: e.evidence_file,
        }));

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

const evidenceForm = useForm({
    commitment_id: '',
    title: '',
    description: '',
    file: null,
});

/** Per-commitment draft fields for adding evidence */
const evidenceDrafts = reactive({});

function evidenceDraft(commitmentId) {
    if (!evidenceDrafts[commitmentId]) {
        evidenceDrafts[commitmentId] = { title: '', description: '', file: null, fileKey: 0 };
    }
    return evidenceDrafts[commitmentId];
}

function setEvidenceFile(commitmentId, event) {
    const d = evidenceDraft(commitmentId);
    const f = event.target.files?.[0];
    d.file = f || null;
}

function submitEvidence(commitmentId) {
    const d = evidenceDraft(commitmentId);
    evidenceForm.commitment_id = commitmentId;
    evidenceForm.title = d.title;
    evidenceForm.description = d.description;
    evidenceForm.file = d.file;
    evidenceForm.post(route('employee.accomplishments.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            d.title = '';
            d.description = '';
            d.file = null;
            d.fileKey += 1;
            evidenceForm.reset();
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
    progress: 0,
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
    editForm.progress = c.progress;
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
                            <div class="grid gap-4 md:grid-cols-2">
                                <div
                                    v-for="(entry, idx) in commitmentForm.entries"
                                    :key="entry.function_type"
                                    class="rounded-lg border border-slate-200 bg-slate-50/70 p-4"
                                >
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-bold uppercase tracking-wide text-slate-700">
                                            {{ entry.function_type === 'core' ? 'Core function' : 'Strategic function' }}
                                        </p>
                                        <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                                            <input v-model="entry.enabled" type="checkbox" class="rounded border-slate-300 text-blue-600 shadow-sm" />
                                            Include
                                        </label>
                                    </div>
                                    <div v-if="entry.enabled" class="mt-3 space-y-3">
                                        <div>
                                            <InputLabel :for="`entry-title-${idx}`" value="Commitment title" />
                                            <TextInput :id="`entry-title-${idx}`" v-model="entry.title" type="text" class="mt-1 block w-full" required />
                                        </div>
                                        <div>
                                            <InputLabel :for="`entry-desc-${idx}`" value="Milestone / success measures (optional)" />
                                            <textarea
                                                :id="`entry-desc-${idx}`"
                                                v-model="entry.description"
                                                rows="2"
                                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            />
                                        </div>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <div>
                                                <InputLabel :for="`entry-weight-${idx}`" value="Weight (%)" />
                                                <TextInput :id="`entry-weight-${idx}`" v-model="entry.weight" type="number" step="0.01" class="mt-1 block w-full" required />
                                            </div>
                                            <div>
                                                <InputLabel :for="`entry-progress-${idx}`" value="Progress (%)" />
                                                <TextInput :id="`entry-progress-${idx}`" v-model="entry.progress" type="number" class="mt-1 block w-full" required />
                                            </div>
                                        </div>
                                        <div class="border-t border-slate-200 pt-3">
                                            <p class="text-xs font-semibold text-slate-600">Evidence (optional)</p>
                                            <div class="mt-2 space-y-2">
                                                <TextInput
                                                    :id="`entry-evtitle-${idx}`"
                                                    v-model="entry.evidence_title"
                                                    type="text"
                                                    class="block w-full"
                                                    placeholder="Subject / what you did"
                                                />
                                                <textarea
                                                    :id="`entry-evdesc-${idx}`"
                                                    v-model="entry.evidence_description"
                                                    rows="2"
                                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    placeholder="Evidence notes"
                                                />
                                                <input
                                                    :key="entry.fileKey"
                                                    type="file"
                                                    class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-100"
                                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,image/*,application/pdf"
                                                    @change="setCreateEvidenceFile(idx, $event)"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <InputError class="mt-2" :message="commitmentForm.errors.entries" />

                            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                                <PrimaryButton type="submit" :disabled="commitmentForm.processing">Save commitment & evidence</PrimaryButton>
                                <SecondaryButton type="button" :disabled="commitmentForm.processing" @click="closeCreateCommitmentPanel">Cancel</SecondaryButton>
                            </div>
                        </form>
                    </div>

                    <div v-for="c in commitments" :key="c.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-900">{{ c.title }}</h4>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="statusBadge(c.status)">
                                        {{ c.status.replace('_', ' ') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ c.period_label }} · Weight: {{ Number(c.weight) }}% · {{ c.function_type }}
                                </p>
                                <p v-if="c.description" class="mt-2 text-sm text-slate-600">{{ c.description }}</p>
                                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-blue-600" :style="{ width: c.progress + '%' }" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <SecondaryButton v-if="c.status === 'draft' || c.status === 'returned'" @click="startEdit(c)">Edit</SecondaryButton>
                                <SecondaryButton v-if="c.status === 'draft' || c.status === 'returned'" @click="destroyCommitment(c.id)">Delete</SecondaryButton>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Evidence of work</p>
                            <p class="mt-1 text-xs text-slate-500">Subject, short description, and optional file (photo, PDF, spreadsheet, etc.).</p>
                            <ul v-if="c.accomplishments?.length" class="mt-3 space-y-2">
                                <li
                                    v-for="ev in c.accomplishments"
                                    :key="ev.id"
                                    class="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-slate-100 bg-slate-50/80 px-3 py-2 text-sm"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-slate-900">{{ ev.title }}</p>
                                        <p v-if="ev.description" class="mt-1 text-xs text-slate-600">{{ ev.description }}</p>
                                        <p v-if="ev.original_filename" class="mt-1 text-xs text-slate-500">
                                            File: {{ ev.original_filename }}
                                            <span v-if="ev.file_size"> · {{ formatFileSize(ev.file_size) }}</span>
                                        </p>
                                        <a
                                            v-if="ev.file_url"
                                            :href="ev.file_url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="mt-1 inline-block text-xs font-semibold text-blue-700 hover:underline"
                                        >
                                            Open attachment
                                        </a>
                                    </div>
                                    <SecondaryButton
                                        v-if="canManageEvidence(c.status)"
                                        class="shrink-0 text-xs text-rose-700 ring-rose-200"
                                        @click="destroyEvidence(ev.id)"
                                    >
                                        Remove
                                    </SecondaryButton>
                                </li>
                            </ul>
                            <p v-else class="mt-2 text-xs text-slate-400">No evidence uploaded yet.</p>

                            <form
                                v-if="canManageEvidence(c.status)"
                                class="mt-4 grid gap-3 rounded-lg border border-dashed border-slate-200 bg-white p-4 md:grid-cols-2"
                                @submit.prevent="submitEvidence(c.id)"
                            >
                                <div class="md:col-span-2">
                                    <InputLabel :for="'ev-subj-' + c.id" value="Subject / what you did" />
                                    <TextInput
                                        :id="'ev-subj-' + c.id"
                                        v-model="evidenceDraft(c.id).title"
                                        type="text"
                                        class="mt-1 block w-full"
                                        required
                                        placeholder="e.g. Conducted monitoring visit at HEI X"
                                    />
                                    <InputError v-if="Number(evidenceForm.commitment_id) === c.id" class="mt-1" :message="evidenceForm.errors.title" />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel :for="'ev-desc-' + c.id" value="Description (optional)" />
                                    <textarea
                                        :id="'ev-desc-' + c.id"
                                        v-model="evidenceDraft(c.id).description"
                                        rows="2"
                                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="Context, dates, participants, or how this supports the commitment."
                                    />
                                    <InputError v-if="Number(evidenceForm.commitment_id) === c.id" class="mt-1" :message="evidenceForm.errors.description" />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel :for="'ev-file-' + c.id" value="Attachment (optional)" />
                                    <input
                                        :id="'ev-file-' + c.id"
                                        :key="evidenceDraft(c.id).fileKey"
                                        type="file"
                                        class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-800 hover:file:bg-slate-200"
                                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip,image/*,application/pdf"
                                        @change="setEvidenceFile(c.id, $event)"
                                    />
                                    <InputError v-if="Number(evidenceForm.commitment_id) === c.id" class="mt-1" :message="evidenceForm.errors.file" />
                                    <InputError v-if="Number(evidenceForm.commitment_id) === c.id" class="mt-1" :message="evidenceForm.errors.commitment_id" />
                                </div>
                                <div class="md:col-span-2">
                                    <PrimaryButton type="submit" :disabled="evidenceForm.processing">Add evidence</PrimaryButton>
                                </div>
                            </form>
                            <p v-else class="mt-3 text-xs text-slate-500">Evidence cannot be changed while this commitment is with your supervisor or approved.</p>
                        </div>

                        <div v-if="editId === c.id" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <InputLabel value="Title" />
                                    <TextInput v-model="editForm.title" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-2" :message="editForm.errors.title" />
                                </div>
                                <div class="md:col-span-2">
                                    <InputLabel value="Milestone / success measures" />
                                    <textarea v-model="editForm.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                                </div>
                                <div>
                                    <InputLabel value="Function" />
                                    <select v-model="editForm.function_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="core">Core</option>
                                        <option value="strategic">Strategic</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Weight (%)" />
                                    <TextInput v-model="editForm.weight" type="number" step="0.01" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Progress (%)" />
                                    <TextInput v-model="editForm.progress" type="number" class="mt-1 block w-full" />
                                </div>
                                <div class="flex gap-2 md:col-span-2">
                                    <PrimaryButton :disabled="editForm.processing" @click="saveEdit">Save</PrimaryButton>
                                    <SecondaryButton @click="editId = null">Cancel</SecondaryButton>
                                </div>
                            </div>
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
                            <table class="min-w-full divide-y divide-slate-200 text-sm">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-3 py-2">Commitment</th>
                                        <th class="px-3 py-2">Function</th>
                                        <th class="px-3 py-2">Weight</th>
                                        <th class="px-3 py-2">Q3 Tgt</th>
                                        <th class="px-3 py-2">Q3 Act</th>
                                        <th class="px-3 py-2">Q4 Tgt</th>
                                        <th class="px-3 py-2">Q4 Act</th>
                                        <th class="px-3 py-2">% Accomp</th>
                                        <th class="px-3 py-2">Q</th>
                                        <th class="px-3 py-2">E</th>
                                        <th class="px-3 py-2">T</th>
                                        <th class="px-3 py-2">Average</th>
                                        <th class="px-3 py-2">Weighted</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="c in s.commitments || []" :key="c.id">
                                        <td class="px-3 py-2 font-medium text-slate-900">{{ c.title }}</td>
                                        <td class="px-3 py-2 capitalize text-slate-600">{{ c.function_type }}</td>
                                        <td class="px-3 py-2">{{ Number(c.weight) }}%</td>
                                        <td class="px-3 py-2">{{ c.rating_q3_target ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_q3_actual ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_q4_target ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_q4_actual ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_percent != null ? (Number(c.rating_percent) * 100).toFixed(0) + '%' : '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_quality ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_efficiency ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_timeliness ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_average ?? '—' }}</td>
                                        <td class="px-3 py-2">{{ c.rating_weighted ?? '—' }}</td>
                                    </tr>
                                    <tr class="bg-slate-50 font-semibold text-slate-800">
                                        <td class="px-3 py-2" colspan="2">TOTAL</td>
                                        <td class="px-3 py-2">{{ historyTotals(s).weight }}%</td>
                                        <td class="px-3 py-2" colspan="7"></td>
                                        <td class="px-3 py-2">{{ historyTotals(s).weighted }}</td>
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
