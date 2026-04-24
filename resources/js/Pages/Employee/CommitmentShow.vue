<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    group: Object,
    commitments: Array,
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
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                    :class="group.function_type === 'core'
                                        ? 'bg-blue-100 text-blue-800'
                                        : 'bg-amber-100 text-amber-800'"
                                >
                                    {{ group.function_type }} function
                                </span>
                                <h2 class="text-xl font-semibold text-slate-900">
                                    {{ group.title || '(untitled function)' }}
                                </h2>
                            </div>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ group.period_label }}
                                · {{ group.total_indicators }} indicator{{ group.total_indicators === 1 ? '' : 's' }}
                                · Σ Weight <strong>{{ Number(group.total_weight).toFixed(2) }}%</strong>
                                · {{ group.total_evidence }} evidence file{{ group.total_evidence === 1 ? '' : 's' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full border-collapse text-xs">
                        <thead class="bg-slate-100 text-[11px] font-semibold text-slate-700">
                            <tr>
                                <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 300px">
                                    Services / Programs / Projects / Indicators
                                </th>
                                <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 72px">Weight</th>
                                <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 120px">Annual Office Target</th>
                                <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 140px">Individual Annual Targets</th>
                                <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 110px">Status</th>
                                <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 120px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in commitments" :key="c.id">
                                <td class="border border-slate-300 px-2 py-2 align-top whitespace-pre-line">{{ c.description || '—' }}</td>
                                <td class="border border-slate-300 px-2 py-2 text-right align-top">{{ Number(c.weight).toFixed(2) }}%</td>
                                <td class="border border-slate-300 px-2 py-2 align-top">{{ c.annual_office_target || '—' }}</td>
                                <td class="border border-slate-300 px-2 py-2 align-top">{{ c.individual_annual_targets || '—' }}</td>
                                <td class="border border-slate-300 px-2 py-2 text-center align-top">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1" :class="statusBadge(c.status)">
                                        {{ c.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="border border-slate-300 px-2 py-2 text-center align-top">
                                    <div v-if="canManage(c.status)" class="flex justify-center gap-1">
                                        <SecondaryButton class="text-[11px]" @click="startEdit(c)">Edit</SecondaryButton>
                                        <SecondaryButton class="text-[11px] text-rose-700 ring-rose-200" @click="destroyCommitment(c.id)">Delete</SecondaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-900">Evidence per indicator</h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Each indicator row has its own evidence block — one subject &amp; description, any number of files.
                    </p>

                    <div class="mt-5 space-y-5">
                        <div
                            v-for="c in commitments"
                            :key="'ev-' + c.id"
                            class="rounded-lg border border-slate-200 bg-slate-50/60 p-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ c.description ? c.description.split('\n')[0] : '(no description)' }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        Weight {{ Number(c.weight).toFixed(2) }}% ·
                                        <span class="rounded-full px-2 py-0.5 font-semibold ring-1" :class="statusBadge(c.status)">
                                            {{ c.status.replace('_', ' ') }}
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <ul v-if="c.accomplishments?.length" class="mt-3 space-y-2">
                                <li
                                    v-for="ev in c.accomplishments"
                                    :key="ev.id"
                                    class="flex flex-wrap items-start justify-between gap-2 rounded border border-slate-200 bg-white px-3 py-2 text-sm"
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
                                        v-if="canManage(c.status)"
                                        class="shrink-0 text-xs text-rose-700 ring-rose-200"
                                        @click="destroyEvidence(ev.id)"
                                    >
                                        Remove
                                    </SecondaryButton>
                                </li>
                            </ul>
                            <p v-else class="mt-3 text-xs text-slate-400">No evidence yet for this indicator.</p>

                            <form
                                v-if="canManage(c.status)"
                                class="mt-4 rounded-md border border-emerald-100 bg-emerald-50/40 p-3"
                                @submit.prevent="submitEvidence(c.id)"
                            >
                                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-900">Add evidence</p>
                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    <div>
                                        <InputLabel :value="'Subject / title'" />
                                        <TextInput
                                            :model-value="ensureEvidenceDraft(c.id).title"
                                            type="text"
                                            class="mt-1 block w-full text-xs"
                                            placeholder="e.g. Q3 accomplishment report"
                                            @update:model-value="(v) => (evidenceDrafts[c.id].title = v)"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel :value="'Description (optional)'" />
                                        <TextInput
                                            :model-value="ensureEvidenceDraft(c.id).description"
                                            type="text"
                                            class="mt-1 block w-full text-xs"
                                            placeholder="Short note"
                                            @update:model-value="(v) => (evidenceDrafts[c.id].description = v)"
                                        />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel :value="'Files (one or many)'" />
                                        <input
                                            :key="evidenceFileKeys[c.id] || 0"
                                            type="file"
                                            multiple
                                            class="mt-1 block w-full text-xs text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-blue-700"
                                            @change="(e) => setEvidenceFiles(c.id, e)"
                                        />
                                        <p class="mt-1 text-[10px] text-slate-500">
                                            jpg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, zip · up to 12 MB each, 20 files per upload.
                                        </p>
                                        <ul
                                            v-if="ensureEvidenceDraft(c.id).files.length"
                                            class="mt-2 space-y-1 text-[11px] text-slate-700"
                                        >
                                            <li
                                                v-for="(f, i) in ensureEvidenceDraft(c.id).files"
                                                :key="i"
                                                class="flex items-center justify-between gap-2 rounded border border-slate-200 bg-white px-2 py-1"
                                            >
                                                <span class="truncate">{{ f.name }} <span class="text-slate-400">· {{ formatFileSize(f.size) }}</span></span>
                                                <button type="button" class="text-rose-700 hover:underline" @click="removeEvidenceFile(c.id, i)">Remove</button>
                                            </li>
                                        </ul>
                                        <p v-if="evidenceErrors[c.id]" class="mt-2 text-xs text-rose-700">{{ evidenceErrors[c.id] }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <PrimaryButton type="submit" :disabled="!!evidenceSubmitting[c.id]">
                                        {{ evidenceSubmitting[c.id] ? 'Uploading…' : 'Save evidence' }}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
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
