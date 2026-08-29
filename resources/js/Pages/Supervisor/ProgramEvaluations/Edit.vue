<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { statusLabel } from '@/utils/statusLabels';

const props = defineProps({
    formRecord: {
        type: Object,
        default: null,
    },
    periodYear: {
        type: Number,
        required: true,
    },
    viewer: {
        type: String,
        default: 'supervisor',
    },
});

const isAdmin = computed(() => props.viewer === 'admin');
const status = computed(() => {
    const raw = props.formRecord?.status;
    if (!raw) {
        return 'draft';
    }
    return typeof raw === 'string' ? raw : (raw.value || 'draft');
});
const canEdit = computed(() => !isAdmin.value && ['draft', 'returned'].includes(status.value));
const isEdit = Boolean(props.formRecord?.id);

const institutionTypes = ['SUCs', 'LUCs', 'PHEIs'];
const purposes = [
    'Curriculum Revisions',
    'Initial Permit',
    'Additional Major',
    'COPC',
    'Program Monitoring',
    'Application (Initial Permit, Renewal, Government Recognition, COPC)',
    'Others (indicate in the remarks column)',
];
const results = ['Favorable', 'Not Favorable'];

function blankRow() {
    return {
        id: null,
        institutional_code: '',
        hei_name: '',
        institutional_type: '',
        program_name: '',
        program_count: 1,
        purpose: '',
        effectivity_ay: '',
        date_applied: '',
        date_evaluated: '',
        result: '',
        final_recommendation: '',
    };
}

function rowsFromRecord(record) {
    const entries = record?.entries || [];
    if (!entries.length) {
        return [blankRow()];
    }

    return entries.map((entry) => ({
        id: entry.id,
        institutional_code: entry.institutional_code ?? '',
        hei_name: entry.hei_name ?? '',
        institutional_type: entry.institutional_type ?? '',
        program_name: entry.program_name ?? '',
        program_count: entry.program_count ?? '',
        purpose: entry.purpose ?? '',
        effectivity_ay: entry.effectivity_ay ?? '',
        date_applied: entry.date_applied ?? '',
        date_evaluated: entry.date_evaluated ?? '',
        result: entry.result ?? '',
        final_recommendation: entry.final_recommendation ?? '',
    }));
}

const form = useForm({
    title: props.formRecord?.title || 'Programs Monitored/Evaluated/Inspected',
    office_name: props.formRecord?.office_name || 'CHEDRO : MIMAROPA',
    evaluation_year: props.formRecord?.evaluation_year || props.periodYear,
    entries: rowsFromRecord(props.formRecord),
});

const inputClass = 'w-full min-w-0 rounded-md border-gray-300 px-1.5 py-1 text-[11px] shadow-sm focus:border-cyan-500 focus:ring-cyan-500';

const reviewForm = useForm({
    action: 'approve',
    review_notes: props.formRecord?.review_notes || '',
});

function addRow() {
    form.entries.push(blankRow());
}

function removeRow(index) {
    if (form.entries.length <= 1) {
        form.entries = [blankRow()];
        return;
    }
    form.entries.splice(index, 1);
}

function save(submit = false) {
    const payload = {
        title: form.title,
        office_name: form.office_name,
        evaluation_year: form.evaluation_year,
        entries: form.entries,
        submit,
    };

    if (isEdit) {
        form.transform(() => payload).patch(route('supervisor.program-evaluations.update', props.formRecord.id), {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
        return;
    }

    form.transform(() => payload).post(route('supervisor.program-evaluations.store'), {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
}

function review(action) {
    reviewForm.action = action;
    reviewForm.patch(route('admin.program-evaluations.update', props.formRecord.id), {
        preserveScroll: true,
    });
}

function dashboardHref() {
    return route('dashboard', { tab: isAdmin.value ? 'registers' : 'programs' });
}
</script>

<template>
    <Head :title="isEdit ? 'Edit programs evaluated' : 'New programs evaluated'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ isEdit ? 'Edit programs evaluated' : 'New programs evaluated' }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        Programs Monitored / Evaluated / Inspected — same columns as the CHEDRO register.
                    </p>
                </div>
                <Link
                    :href="dashboardHref()"
                    class="text-sm font-medium text-slate-600 hover:text-slate-900"
                >
                    Back to dashboard
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto w-full max-w-[100vw] space-y-4 px-3 sm:px-4 lg:px-6">
                <div class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-3">
                    <div>
                        <InputLabel value="Title" />
                        <input v-model="form.title" type="text" :disabled="!canEdit" :class="inputClass + ' mt-1'" />
                    </div>
                    <div>
                        <InputLabel value="CHEDRO / Office" />
                        <input v-model="form.office_name" type="text" :disabled="!canEdit" :class="inputClass + ' mt-1'" />
                    </div>
                    <div>
                        <InputLabel value="Year" />
                        <input v-model="form.evaluation_year" type="number" min="2000" max="2100" :disabled="!canEdit" :class="inputClass + ' mt-1'" />
                    </div>
                </div>

                <div
                    v-if="isEdit"
                    class="rounded-lg border px-3 py-2 text-xs"
                    :class="status === 'approved' ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : status === 'returned' ? 'border-rose-200 bg-rose-50 text-rose-900' : status === 'in_review' ? 'border-sky-200 bg-sky-50 text-sky-900' : 'border-slate-200 bg-slate-50 text-slate-700'"
                >
                    <span class="font-semibold">{{ statusLabel(status) }}.</span>
                    <span v-if="isAdmin && formRecord.supervisor"> Submitted by {{ formRecord.supervisor.name }}.</span>
                    <span v-else-if="status === 'in_review'"> Waiting for administrator review.</span>
                    <span v-else-if="status === 'returned' && formRecord.review_notes"> {{ formRecord.review_notes }}</span>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="w-full overflow-visible">
                        <table class="w-full table-fixed border-collapse text-[11px]">
                            <thead class="bg-slate-100 text-left font-semibold text-slate-700">
                                <tr>
                                    <th class="w-[7%] border border-slate-300 px-1 py-2">Institutional Code</th>
                                    <th class="w-[12%] border border-slate-300 px-1 py-2">Name of HEI</th>
                                    <th class="w-[8%] border border-slate-300 px-1 py-2">Type (SUC, LUC, Private)</th>
                                    <th class="w-[14%] border border-slate-300 px-1 py-2">Program name</th>
                                    <th class="w-[5%] border border-slate-300 px-1 py-2">No. of programs</th>
                                    <th class="w-[11%] border border-slate-300 px-1 py-2">Purpose of evaluation / inspection</th>
                                    <th class="w-[7%] border border-slate-300 px-1 py-2">Effectivity (AY)</th>
                                    <th class="w-[7%] border border-slate-300 px-1 py-2">Date applied</th>
                                    <th class="w-[7%] border border-slate-300 px-1 py-2">Date evaluated / inspected</th>
                                    <th class="w-[8%] border border-slate-300 px-1 py-2">Result</th>
                                    <th class="w-[10%] border border-slate-300 px-1 py-2">Final recommendation</th>
                                    <th v-if="canEdit" class="w-[4%] border border-slate-300 px-1 py-2 text-center"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in form.entries" :key="row.id || index">
                                    <td class="border border-slate-300 p-1 align-top">
                                        <input v-model="row.institutional_code" type="text" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <input v-model="row.hei_name" type="text" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <select v-model="row.institutional_type" :disabled="!canEdit" :class="inputClass">
                                            <option value="">Select</option>
                                            <option v-for="type in institutionTypes" :key="type" :value="type">{{ type }}</option>
                                        </select>
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <textarea v-model="row.program_name" rows="2" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <input v-model="row.program_count" type="number" min="0" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <input v-model="row.purpose" type="text" list="purpose-options" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <input v-model="row.effectivity_ay" type="text" placeholder="2024-2025" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <input v-model="row.date_applied" type="text" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <input v-model="row.date_evaluated" type="text" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <select v-model="row.result" :disabled="!canEdit" :class="inputClass">
                                            <option value="">Select</option>
                                            <option v-for="item in results" :key="item" :value="item">{{ item }}</option>
                                        </select>
                                    </td>
                                    <td class="border border-slate-300 p-1 align-top">
                                        <textarea v-model="row.final_recommendation" rows="2" :disabled="!canEdit" :class="inputClass" />
                                    </td>
                                    <td v-if="canEdit" class="border border-slate-300 p-1 text-center align-top">
                                        <button
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100"
                                            title="Remove row"
                                            @click="removeRow(index)"
                                        >
                                            ×
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <datalist id="purpose-options">
                        <option v-for="purpose in purposes" :key="purpose" :value="purpose" />
                    </datalist>
                    <div v-if="canEdit" class="border-t border-slate-100 px-3 py-2">
                        <button
                            type="button"
                            class="text-xs font-semibold text-cyan-800 hover:text-cyan-950"
                            @click="addRow"
                        >
                            + Add program row
                        </button>
                    </div>
                </div>

                <InputError :message="form.errors.entries" />

                <div v-if="isAdmin && status === 'in_review'" class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
                    <InputLabel value="Review notes (required when returning)" />
                    <textarea v-model="reviewForm.review_notes" rows="3" :class="inputClass + ' mt-1'" />
                    <InputError :message="reviewForm.errors.review_notes" />
                    <div class="flex flex-wrap gap-2">
                        <PrimaryButton type="button" :disabled="reviewForm.processing" @click="review('approve')">
                            Approve report
                        </PrimaryButton>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-800 hover:bg-rose-100 disabled:opacity-50"
                            :disabled="reviewForm.processing"
                            @click="review('return')"
                        >
                            Return for revision
                        </button>
                    </div>
                </div>

                <div v-else-if="canEdit" class="flex flex-wrap gap-2">
                    <PrimaryButton type="button" :disabled="form.processing" @click="save(true)">
                        {{ form.processing ? 'Submitting…' : 'Submit for administrator review' }}
                    </PrimaryButton>
                    <SecondaryButton type="button" :disabled="form.processing" @click="save(false)">
                        Save draft
                    </SecondaryButton>
                    <SecondaryButton type="button" :disabled="form.processing" @click="router.visit(dashboardHref())">
                        Cancel
                    </SecondaryButton>
                </div>
                <div v-else class="flex flex-wrap gap-2">
                    <SecondaryButton type="button" @click="router.visit(dashboardHref())">
                        Back
                    </SecondaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
