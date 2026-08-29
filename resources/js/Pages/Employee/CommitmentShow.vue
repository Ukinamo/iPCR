<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EvidencePanel from '@/Components/EvidencePanel.vue';
import IpcrEmployeeAnswerTable from '@/Components/IpcrEmployeeAnswerTable.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { statusLabel } from '@/utils/statusLabels';
import { suggestedRatingForRow, isRateableRow } from '@/utils/ipcrRating';
import { ratingScaleForSubmit, roundWholeNumberForSubmit, wholeNumberOrEmpty } from '@/utils/numberFormat';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    group: Object,
    commitments: Array,
    weightSummary: Object,
    submission: {
        type: Object,
        default: null,
    },
});

function canManage(status) {
    return status === 'draft' || status === 'returned';
}

function statusBadge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        draft: 'bg-slate-50 text-slate-700 ring-slate-100',
        returned: 'bg-amber-50 text-amber-900 ring-amber-100',
    };
    return map[status] ?? map.draft;
}

function mapAnswerRow(c) {
    const row = {
        id: c.id,
        function_type: c.function_type === 'strategic' ? 'strategic' : 'core',
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
    };
    const suggested = isRateableRow(row)
        ? suggestedRatingForRow(row, props.submission?.included_quarters)
        : null;
    row.rating_quality = isRateableRow(row) ? (c.rating_quality ?? suggested) : null;
    row.rating_efficiency = isRateableRow(row) ? (c.rating_efficiency ?? suggested) : null;
    row.rating_timeliness = isRateableRow(row) ? (c.rating_timeliness ?? suggested) : null;

    return row;
}

const canManagePackage = computed(() =>
    (props.commitments || []).some((c) => canManage(c.status)),
);

const packageStatus = computed(() => {
    const rank = { draft: 0, returned: 1, in_review: 2, approved: 3 };
    return (props.commitments || []).reduce((best, c) => {
        if ((rank[c.status] ?? -1) < (rank[best] ?? 99)) return c.status;
        return best;
    }, 'draft');
});

const answerForm = useForm({
    submission_id: props.submission?.id,
    evaluation_year: props.group.evaluation_year,
    evaluation_quarter: props.group.evaluation_quarter,
    commitments: (props.commitments || []).map(mapAnswerRow),
});

const packageSubmitting = ref(false);
const page = usePage();
const submitError = computed(() => answerForm.errors.submission_id || page.props.errors?.submission_id);

function answerPayload(data) {
    return {
        submission_id: data.submission_id,
        commitments: data.commitments.map((row) => ({
            id: row.id,
            rating_q1_target: roundWholeNumberForSubmit(row.rating_q1_target),
            rating_q1_actual: roundWholeNumberForSubmit(row.rating_q1_actual),
            rating_q2_target: roundWholeNumberForSubmit(row.rating_q2_target),
            rating_q2_actual: roundWholeNumberForSubmit(row.rating_q2_actual),
            rating_q3_target: roundWholeNumberForSubmit(row.rating_q3_target),
            rating_q3_actual: roundWholeNumberForSubmit(row.rating_q3_actual),
            rating_q4_target: roundWholeNumberForSubmit(row.rating_q4_target),
            rating_q4_actual: roundWholeNumberForSubmit(row.rating_q4_actual),
            rating_quality: ratingScaleForSubmit(row.rating_quality),
            rating_efficiency: ratingScaleForSubmit(row.rating_efficiency),
            rating_timeliness: ratingScaleForSubmit(row.rating_timeliness),
        })),
    };
}

function saveAnswers() {
    answerForm.transform((data) => answerPayload(data)).patch(route('employee.form-answers.update'), {
        preserveScroll: true,
        onFinish: () => answerForm.transform((data) => data),
    });
}

function submitForReview() {
    if (!props.submission?.id) {
        return;
    }

    packageSubmitting.value = true;
    answerForm.transform((data) => answerPayload(data)).patch(route('employee.form-answers.update'), {
        preserveScroll: true,
        onSuccess: () => {
            router.post(route('employee.submissions.store'), { submission_id: props.submission.id }, {
                onFinish: () => {
                    packageSubmitting.value = false;
                    answerForm.transform((data) => data);
                },
            });
        },
        onError: () => {
            packageSubmitting.value = false;
            answerForm.transform((data) => data);
        },
    });
}

const packageEvidence = computed(() => {
    const list = [];
    for (const c of props.commitments || []) {
        for (const ev of c.accomplishments || []) {
            list.push({
                ...ev,
                can_remove: canManage(c.status),
            });
        }
    }
    return list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

const evidenceTargetCommitment = computed(() =>
    (props.commitments || []).find((c) => canManage(c.status)) ?? null,
);

const evidenceDraft = reactive({ title: '', description: '', files: [] });
const evidenceError = ref('');
const evidenceSubmitting = ref(false);

function submitEvidence() {
    const target = evidenceTargetCommitment.value;
    if (!target) return;

    if (!evidenceDraft.files.length && !evidenceDraft.title.trim()) {
        evidenceError.value = 'Attach at least one file, or provide a subject.';
        return;
    }
    evidenceError.value = '';

    const fd = new FormData();
    fd.append('commitment_id', String(target.id));
    fd.append('title', evidenceDraft.title ?? '');
    fd.append('description', evidenceDraft.description ?? '');
    evidenceDraft.files.forEach((f) => fd.append('files[]', f));

    evidenceSubmitting.value = true;
    router.post(route('employee.accomplishments.store'), fd, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            evidenceDraft.title = '';
            evidenceDraft.description = '';
            evidenceDraft.files = [];
            evidenceError.value = '';
        },
        onError: (errors) => {
            evidenceError.value = errors.files || errors.title || errors.commitment_id || 'Upload failed.';
        },
        onFinish: () => {
            evidenceSubmitting.value = false;
        },
    });
}

function destroyEvidence(id) {
    if (confirm('Remove this evidence entry?')) {
        router.delete(route('employee.accomplishments.destroy', id), { preserveScroll: true });
    }
}

function cancelSubmission() {
    if (!props.submission?.id) {
        return;
    }

    if (!window.confirm('Cancel this submitted package? It will be pulled back from administrator review so you can edit it again.')) {
        return;
    }

    router.post(route('employee.submissions.cancel', props.submission.id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="IPCR form" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Rate IPCR form</h2>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <Link
                        :href="route('dashboard')"
                        class="inline-flex items-center rounded-md border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm hover:bg-slate-50"
                    >
                        Back to dashboard
                    </Link>
                    <button
                        v-if="submission?.status === 'in_review'"
                        type="button"
                        class="inline-flex items-center rounded-md bg-black px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm hover:bg-zinc-800"
                        @click="cancelSubmission"
                    >
                        Cancel Submission
                    </button>
                    <PrimaryButton
                        v-if="canManagePackage && submission?.id"
                        type="button"
                        :disabled="answerForm.processing || packageSubmitting"
                        @click="submitForReview"
                    >
                        {{ answerForm.processing || packageSubmitting ? 'Submitting…' : 'Submit' }}
                    </PrimaryButton>
                </div>
            </div>
            <p v-if="submitError" class="mt-2 text-sm text-red-600">{{ submitError }}</p>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                <AppIcon :name="canManagePackage ? 'pencil' : 'clipboard'" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xl font-semibold text-slate-900">
                                    {{ submission?.title || (canManagePackage ? 'Complete IPCR form' : 'IPCR form') }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ group.period_label }}
                                    · {{ group.total_functions }} function{{ group.total_functions === 1 ? '' : 's' }}
                                    · {{ group.total_indicators }} indicator{{ group.total_indicators === 1 ? '' : 's' }}
                                    · Σ Weight <strong>{{ Number(group.total_weight).toFixed(2) }}%</strong>
                                </p>
                                <Link
                                    v-if="canManagePackage && submission?.id"
                                    :href="route('employee.packages.edit', submission.id)"
                                    class="mt-2 inline-block text-sm font-semibold text-indigo-700 hover:text-indigo-800"
                                >
                                    Edit form structure
                                </Link>
                            </div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="statusBadge(packageStatus)">
                            {{ statusLabel(packageStatus) }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="submission?.status === 'returned' && submission?.supervisor_feedback"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                >
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="exclamation-triangle" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="font-semibold text-amber-900">Supervisor comments</p>
                        <p class="mt-2 whitespace-pre-wrap text-amber-950/90">{{ submission.supervisor_feedback }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white p-2 shadow-sm sm:p-3">
                    <IpcrEmployeeAnswerTable
                        v-model:rows="answerForm.commitments"
                        :editable="canManagePackage"
                        :processing="answerForm.processing"
                        :errors="answerForm.errors"
                        :show-view="false"
                        :show-cancel="false"
                        :show-save-button="canManagePackage"
                        submit-label="Save ratings"
                        :included-quarters="submission?.included_quarters || [3, 4]"
                        @submit="saveAnswers"
                    />

                    <div class="mt-4 border-t border-slate-200 px-1 pt-4">
                        <div class="mb-3 flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                <AppIcon name="paper-clip" class="h-4 w-4" />
                            </span>
                            <p class="text-sm font-semibold text-slate-800">Supporting evidence</p>
                        </div>
                        <EvidencePanel
                            :items="packageEvidence"
                            :editable="canManagePackage"
                            :show-form="canManagePackage"
                            :submitting="evidenceSubmitting"
                            :error="evidenceError"
                            v-model:title="evidenceDraft.title"
                            v-model:description="evidenceDraft.description"
                            v-model:files="evidenceDraft.files"
                            @submit="submitEvidence"
                            @remove="destroyEvidence"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
