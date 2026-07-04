<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import EvidencePanel from '@/Components/EvidencePanel.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    group: Object,
    commitments: Array,
});

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

function canManage(status) {
    return status === 'draft' || status === 'returned';
}

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

const editId = ref(null);
const editForm = useForm({
    title: '',
    description: '',
    function_type: 'core',
    weight: 0,
    annual_office_target: '',
    individual_annual_targets: '',
    period_label: props.group.period_label,
});

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
    if (confirm('Delete this indicator row?')) {
        router.delete(route('employee.commitments.destroy', id), { preserveScroll: true });
    }
}

function destroyEvidence(id) {
    if (confirm('Remove this evidence entry?')) {
        router.delete(route('employee.accomplishments.destroy', id), { preserveScroll: true });
    }
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

const canManagePackage = computed(() =>
    (props.commitments || []).some((c) => canManage(c.status)),
);

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
</script>

<template>
    <Head :title="group.title || 'Commitment'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('dashboard')"
                    class="text-sm font-medium text-slate-500 hover:text-slate-800"
                >
                    ← Back to dashboard
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 class="text-xl font-semibold text-slate-900">Commitment package</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ group.period_label }}
                                · {{ group.total_functions }} function{{ group.total_functions === 1 ? '' : 's' }}
                                · {{ group.total_indicators }} indicator{{ group.total_indicators === 1 ? '' : 's' }}
                                · Σ Weight <strong>{{ Number(group.total_weight).toFixed(2) }}%</strong>
                                <span
                                    v-if="group.total_evidence"
                                    class="ml-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 ring-1 ring-emerald-100"
                                >
                                    📎 {{ group.total_evidence }} evidence file{{ group.total_evidence === 1 ? '' : 's' }}
                                </span>
                                <span v-else class="ml-1 text-slate-400">· no evidence yet</span>
                            </p>
                            <p v-if="group.created_at" class="mt-0.5 text-xs text-slate-500">
                                Saved {{ new Date(group.created_at).toLocaleString() }}
                            </p>
                        </div>
                    </div>
                </div>

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
                                    <th class="border border-slate-200 px-2 py-2 text-center" style="min-width: 110px">Status</th>
                                    <th class="border border-slate-200 px-2 py-2 text-center" style="min-width: 120px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in block.items" :key="c.id">
                                    <td class="border border-slate-200 px-2 py-2 align-top whitespace-pre-line">{{ c.description || '—' }}</td>
                                    <td class="border border-slate-200 px-2 py-2 text-right align-top">{{ Number(c.weight).toFixed(2) }}%</td>
                                    <td class="border border-slate-200 px-2 py-2 align-top">{{ c.annual_office_target || '—' }}</td>
                                    <td class="border border-slate-200 px-2 py-2 align-top">{{ c.individual_annual_targets || '—' }}</td>
                                    <td class="border border-slate-200 px-2 py-2 text-center align-top">
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1" :class="statusBadge(c.status)">
                                            {{ c.status.replace('_', ' ') }}
                                        </span>
                                    </td>
                                    <td class="border border-slate-200 px-2 py-2 text-center align-top">
                                        <div v-if="canManage(c.status)" class="flex justify-center gap-1">
                                            <SecondaryButton class="text-[11px]" @click="startEdit(c)">Edit</SecondaryButton>
                                            <SecondaryButton class="text-[11px] text-rose-700 ring-rose-200" @click="destroyCommitment(c.id)">Delete</SecondaryButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

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

                <div
                    v-if="editId !== null"
                    class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h3 class="text-base font-semibold text-slate-900">Edit indicator</h3>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel value="Function title" />
                            <TextInput v-model="editForm.title" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="editForm.errors.title" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel value="Indicator / success measure" />
                            <textarea v-model="editForm.description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
                            <InputError class="mt-2" :message="editForm.errors.description" />
                        </div>
                        <div>
                            <InputLabel value="Function type" />
                            <select v-model="editForm.function_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <option value="core">Core</option>
                                <option value="strategic">Strategic</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Weight (%)" />
                            <TextInput v-model="editForm.weight" type="number" step="0.01" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="editForm.errors.weight" />
                        </div>
                        <div>
                            <InputLabel value="Annual Office Target" />
                            <TextInput v-model="editForm.annual_office_target" type="text" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel value="Individual Annual Targets" />
                            <TextInput v-model="editForm.individual_annual_targets" type="text" class="mt-1 block w-full" />
                        </div>
                        <div class="flex gap-2 md:col-span-2">
                            <PrimaryButton :disabled="editForm.processing" @click="saveEdit">Save</PrimaryButton>
                            <SecondaryButton @click="editId = null">Cancel</SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
