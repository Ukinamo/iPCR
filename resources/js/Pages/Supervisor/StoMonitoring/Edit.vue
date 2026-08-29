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
    reportType: {
        type: String,
        required: true,
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

const isStufap = computed(() => props.reportType === 'stufap');
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

const defaultTitle = computed(() =>
    isStufap.value
        ? 'REPORT ON STO: Monitoring of HEI with STUFAPs'
        : 'REPORT ON STO: Monitoring of Student Services',
);

const stufapPrograms = ['TD', 'SNPLP', 'Scholarship'];
const studentServices = ['General Monitoring'];

function blankRow() {
    return {
        id: null,
        hei_name: '',
        monitored_item: isStufap.value ? '' : 'General Monitoring',
        grantee_count: '',
        date_monitored: '',
        remarks: '',
    };
}

function rowsFromRecord(record) {
    const entries = record?.entries || [];
    if (!entries.length) {
        return [blankRow()];
    }

    return entries.map((entry) => ({
        id: entry.id,
        hei_name: entry.hei_name ?? '',
        monitored_item: entry.monitored_item ?? '',
        grantee_count: entry.grantee_count ?? '',
        date_monitored: entry.date_monitored ?? '',
        remarks: entry.remarks ?? '',
    }));
}

const form = useForm({
    report_type: props.reportType,
    title: props.formRecord?.title || defaultTitle.value,
    office_name: props.formRecord?.office_name || 'CHEDRO : MIMAROPA',
    evaluation_year: props.formRecord?.evaluation_year || props.periodYear,
    entries: rowsFromRecord(props.formRecord),
});

const reviewForm = useForm({
    action: 'approve',
    review_notes: props.formRecord?.review_notes || '',
});

const inputClass = 'w-full min-w-0 rounded-md border-gray-300 px-1.5 py-1 text-xs shadow-sm focus:border-cyan-500 focus:ring-cyan-500';

const uniqueHeiCount = computed(() => {
    const names = form.entries
        .map((row) => String(row.hei_name || '').trim().toLowerCase())
        .filter(Boolean);
    return new Set(names).size;
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

function dashboardHref() {
    return route('dashboard', { tab: isAdmin.value ? 'registers' : 'programs' });
}

function save(submit = false) {
    const payload = {
        report_type: form.report_type,
        title: form.title,
        office_name: form.office_name,
        evaluation_year: form.evaluation_year,
        submit,
        entries: form.entries.map((row) => ({
            ...row,
            grantee_count: row.grantee_count === '' || row.grantee_count == null ? null : Number(row.grantee_count),
        })),
    };

    if (isEdit) {
        form.transform(() => payload).patch(route('supervisor.sto-monitoring.update', props.formRecord.id), {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });
        return;
    }

    form.transform(() => payload).post(route('supervisor.sto-monitoring.store'), {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
}

function review(action) {
    reviewForm.action = action;
    reviewForm.patch(route('admin.sto-monitoring.update', props.formRecord.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="isEdit ? 'Edit STO report' : 'New STO report'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ isStufap ? 'Monitoring of HEI with STUFAPs' : 'Monitoring of Student Services' }}
                    </h2>
                    <p class="text-sm text-gray-500">{{ defaultTitle }}</p>
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
            <div class="mx-auto max-w-6xl space-y-4 px-4 sm:px-6 lg:px-8">
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
                    <table class="w-full table-fixed border-collapse text-xs">
                        <thead class="bg-slate-100 text-left font-semibold text-slate-700">
                            <tr>
                                <th class="border border-slate-300 px-2 py-2">List of HEI/s</th>
                                <th class="border border-slate-300 px-2 py-2">
                                    {{ isStufap ? 'STUFAP program monitored (TD, SNPLP, Scholarship, etc.)' : 'Type of student service monitored' }}
                                </th>
                                <th v-if="isStufap" class="w-[14%] border border-slate-300 px-2 py-2">No. of grantees / borrowers</th>
                                <th class="w-[16%] border border-slate-300 px-2 py-2">Date monitored</th>
                                <th class="w-[18%] border border-slate-300 px-2 py-2">Remarks</th>
                                <th v-if="canEdit" class="w-[6%] border border-slate-300 px-2 py-2 text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, index) in form.entries" :key="row.id || index">
                                <td class="border border-slate-300 p-1 align-top">
                                    <input v-model="row.hei_name" type="text" :disabled="!canEdit" :class="inputClass" />
                                </td>
                                <td class="border border-slate-300 p-1 align-top">
                                    <input
                                        v-model="row.monitored_item"
                                        type="text"
                                        :disabled="!canEdit"
                                        :list="isStufap ? 'stufap-programs' : 'student-services'"
                                        :class="inputClass"
                                    />
                                </td>
                                <td v-if="isStufap" class="border border-slate-300 p-1 align-top">
                                    <input v-model="row.grantee_count" type="number" min="0" :disabled="!canEdit" :class="inputClass" />
                                </td>
                                <td class="border border-slate-300 p-1 align-top">
                                    <input v-model="row.date_monitored" type="text" :disabled="!canEdit" :class="inputClass" />
                                </td>
                                <td class="border border-slate-300 p-1 align-top">
                                    <input v-model="row.remarks" type="text" :disabled="!canEdit" :class="inputClass" />
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
                    <datalist id="stufap-programs">
                        <option v-for="item in stufapPrograms" :key="item" :value="item" />
                    </datalist>
                    <datalist id="student-services">
                        <option v-for="item in studentServices" :key="item" :value="item" />
                    </datalist>
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 px-3 py-2">
                        <button v-if="canEdit" type="button" class="text-xs font-semibold text-cyan-800 hover:text-cyan-950" @click="addRow">
                            + Add HEI row
                        </button>
                        <p class="text-xs font-medium text-slate-700">
                            Total number of HEIs monitored {{ isStufap ? 'for STUFAP' : 'for Student Services' }}:
                            <strong>{{ uniqueHeiCount }}</strong>
                        </p>
                    </div>
                </div>

                <InputError :message="form.errors.entries || form.errors.report_type" />

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
