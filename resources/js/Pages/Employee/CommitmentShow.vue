<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
            weight: 0,
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
            weight: Number(c.weight),
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
                weight: it.weight,
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

const functionBlocks = computed(() => {
    const order = { core: 0, strategic: 1 };
    const map = new Map();
    for (const c of props.commitments || []) {
        const key = `${c.function_type}|${c.title}`;
        if (!map.has(key)) {
            map.set(key, {
                function_type: c.function_type,
                title: c.title,
                items: [],
                weight_total: 0,
            });
        }
        const block = map.get(key);
        block.items.push(c);
        block.weight_total += Number(c.weight || 0);
    }
    return Array.from(map.values()).sort((a, b) => {
        const ta = order[a.function_type] ?? 9;
        const tb = order[b.function_type] ?? 9;
        if (ta !== tb) return ta - tb;
        return (a.title || '').localeCompare(b.title || '');
    });
});

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
                class="text-sm font-medium text-slate-500 hover:text-slate-800"
            >
                ← Back to dashboard
            </Link>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
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
                        <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="statusBadge(packageStatus)">
                            {{ packageStatus.replace('_', ' ') }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="submission?.status === 'returned' && submission?.supervisor_feedback"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"
                >
                    <p class="font-semibold text-amber-900">Supervisor comments</p>
                    <p class="mt-2 whitespace-pre-wrap text-amber-950/90">{{ submission.supervisor_feedback }}</p>
                    <p class="mt-2 text-xs text-amber-800">Update your commitments below, then submit again from the dashboard when ready.</p>
                </div>

                <div
                    v-if="canManagePackage"
                    class="rounded-xl border-2 border-blue-200 bg-white p-6 shadow-lg ring-1 ring-blue-100"
                >
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

                <template v-else>
                    <div
                        v-for="(block, bIdx) in functionBlocks"
                        :key="bIdx"
                        class="rounded-xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3"
                            :class="block.function_type === 'core'
                                ? 'bg-blue-50 border-blue-100'
                                : 'bg-amber-50 border-amber-100'"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                    :class="block.function_type === 'core'
                                        ? 'bg-blue-100 text-blue-800'
                                        : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ block.function_type }}
                                </span>
                                <h3 class="text-sm font-semibold text-slate-900">
                                    {{ block.title || '(untitled function)' }}
                                </h3>
                            </div>
                            <p class="text-xs text-slate-600">
                                {{ block.items.length }} indicator{{ block.items.length === 1 ? '' : 's' }}
                                · Σ Weight <strong>{{ block.weight_total.toFixed(2) }}%</strong>
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full border-collapse text-xs">
                                <thead class="bg-slate-50 text-[11px] font-semibold text-slate-700">
                                    <tr>
                                        <th class="border border-slate-200 px-2 py-2 text-center" style="min-width: 300px">
                                            Services / Programs / Projects / Indicators
                                        </th>
                                        <th class="border border-slate-200 px-2 py-2 text-center" style="min-width: 72px">Weight</th>
                                        <th class="border border-slate-200 px-2 py-2 text-center" style="min-width: 120px">Annual Office Target</th>
                                        <th class="border border-slate-200 px-2 py-2 text-center" style="min-width: 140px">Individual Annual Targets</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in block.items" :key="c.id">
                                        <td class="border border-slate-200 px-2 py-2 align-top whitespace-pre-line">{{ c.description || '—' }}</td>
                                        <td class="border border-slate-200 px-2 py-2 text-right align-top">{{ Number(c.weight).toFixed(2) }}%</td>
                                        <td class="border border-slate-200 px-2 py-2 align-top">{{ c.annual_office_target || '—' }}</td>
                                        <td class="border border-slate-200 px-2 py-2 align-top">{{ c.individual_annual_targets || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
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
                        Back to dashboard to submit
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
