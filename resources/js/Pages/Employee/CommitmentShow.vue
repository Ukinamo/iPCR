<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CommitmentIpcrTable from '@/Components/CommitmentIpcrTable.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import EvidencePanel from '@/Components/EvidencePanel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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

function newEmptyEntry(type) {
    return {
        enabled: true,
        function_type: type,
        title: '',
        items: [{
            _uid: Date.now() + Math.random(),
            id: null,
            description: '',
            weight: null,
            annual_office_target: '',
            individual_annual_targets: '',
        }],
    };
}

function commitmentsToEntries(rows) {
    const order = { core: 0, strategic: 1 };
    const map = new Map();

    for (const c of rows || []) {
        const key = `${c.function_type}|${c.title}`;
        if (!map.has(key)) {
            map.set(key, {
                enabled: true,
                function_type: c.function_type,
                title: c.title,
                items: [],
            });
        }
        map.get(key).items.push({
            id: c.id,
            _uid: c.id,
            description: c.description ?? '',
            weight: c.weight != null ? Number(c.weight) : null,
            annual_office_target: c.annual_office_target ?? '',
            individual_annual_targets: c.individual_annual_targets ?? '',
        });
    }

    const entries = Array.from(map.values()).sort((a, b) => {
        const ta = order[a.function_type] ?? 9;
        const tb = order[b.function_type] ?? 9;
        if (ta !== tb) return ta - tb;
        return (a.title || '').localeCompare(b.title || '');
    });

    if (!entries.some((e) => e.function_type === 'core')) {
        entries.unshift(newEmptyEntry('core'));
    }
    if (!entries.some((e) => e.function_type === 'strategic')) {
        entries.push(newEmptyEntry('strategic'));
    }

    return entries;
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

const packageForm = useForm({
    period_label: props.group.period_label,
    entries: commitmentsToEntries(props.commitments),
});

const anchorCommitmentId = computed(() => props.commitments?.[0]?.id);

function submitPackage() {
    const payload = packageForm.entries
        .filter((e) => e.enabled)
        .flatMap((e) =>
            (e.items || []).map((it) => ({
                id: it.id ?? null,
                function_type: e.function_type,
                title: e.title,
                description: it.description,
                weight: it.weight === '' || it.weight == null ? null : it.weight,
                annual_office_target: it.annual_office_target,
                individual_annual_targets: it.individual_annual_targets,
            })),
        );

    if (!payload.length || !anchorCommitmentId.value) {
        return;
    }

    packageForm.transform(() => ({
        period_label: props.group.period_label,
        entries: payload,
    }));

    packageForm.patch(route('employee.commitments.updateBatch', anchorCommitmentId.value), {
        preserveScroll: true,
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
</script>

<template>
    <Head title="Commitment package" />

    <AuthenticatedLayout>
        <template #header>
            <Link
                :href="route('dashboard')"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-800"
            >
                <AppIcon name="arrow-left" class="h-4 w-4" />
                Back to dashboard
            </Link>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                <AppIcon :name="canManagePackage ? 'pencil' : 'clipboard'" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <h2 class="text-xl font-semibold text-slate-900">
                                    {{ canManagePackage ? 'Edit commitment package' : 'Commitment package' }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ group.period_label }}
                                    · {{ group.total_functions }} function{{ group.total_functions === 1 ? '' : 's' }}
                                    · {{ group.total_indicators }} indicator{{ group.total_indicators === 1 ? '' : 's' }}
                                    · Σ Weight <strong>{{ Number(group.total_weight).toFixed(2) }}%</strong>
                                </p>
                                <p v-if="group.created_at" class="mt-0.5 text-xs text-slate-500">
                                    Saved {{ new Date(group.created_at).toLocaleString() }}
                                </p>
                            </div>
                        </div>
                        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="statusBadge(packageStatus)">
                            {{ packageStatus.replace('_', ' ') }}
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
                        <p class="mt-2 text-xs text-amber-800">Update your commitments below, then submit again from the dashboard when ready.</p>
                    </div>
                </div>

                <div
                    v-if="canManagePackage"
                    class="rounded-xl border-2 border-blue-200 bg-white p-6 shadow-lg ring-1 ring-blue-100"
                >
                    <div class="mb-4 flex items-center gap-2 border-b border-slate-100 pb-4">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                            <AppIcon name="pencil" class="h-4 w-4" />
                        </span>
                        <p class="text-sm font-semibold text-slate-800">Edit commitments</p>
                    </div>
                    <CommitmentPackageForm
                        v-model:entries="packageForm.entries"
                        :weight-summary="weightSummary"
                        :errors="packageForm.errors"
                        :processing="packageForm.processing"
                        submit-label="Save changes"
                        @submit="submitPackage"
                        @cancel="router.visit(route('dashboard'))"
                    />
                </div>

                <div v-else class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                            <AppIcon name="clipboard" class="h-4 w-4" />
                        </span>
                        <p class="text-sm font-semibold text-slate-800">IPCR commitment table</p>
                    </div>
                    <CommitmentIpcrTable :commitments="commitments" />
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
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

                <div v-if="canManagePackage" class="flex justify-end">
                    <PrimaryButton type="button" @click="router.visit(route('dashboard'))">
                        <span class="inline-flex items-center gap-1.5">
                            <AppIcon name="arrow-top-right" class="h-4 w-4" />
                            Back to dashboard to submit
                        </span>
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
