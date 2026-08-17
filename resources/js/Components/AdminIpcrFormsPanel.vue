<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
    period: {
        type: Object,
        required: true,
    },
    weightSummary: {
        type: Object,
        required: true,
    },
});

const creating = ref(false);
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

function newEntry(functionType) {
    return {
        enabled: true,
        function_type: functionType,
        title: '',
        items: [newItem()],
    };
}

const form = useForm({
    title: '',
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
    period_label: props.period.label,
    entries: [newEntry('core'), newEntry('strategic')],
});

function flattenedEntries() {
    return form.entries
        .filter((entry) => entry.enabled)
        .flatMap((entry) =>
            (entry.items || []).map((item) => ({
                function_type: entry.function_type,
                title: entry.title,
                description: item.description,
                weight: item.weight === '' || item.weight == null ? null : item.weight,
                annual_office_target: item.annual_office_target,
                individual_annual_targets: item.individual_annual_targets,
            })),
        );
}

function startCreate() {
    creating.value = true;
}

function cancelCreate() {
    creating.value = false;
}

function save() {
    form.transform((data) => ({
        title: data.title,
        evaluation_year: data.evaluation_year,
        evaluation_quarter: data.evaluation_quarter,
        period_label: `Q${data.evaluation_quarter} ${data.evaluation_year}`,
        entries: flattenedEntries(),
    })).post(route('admin.forms.store'), {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
}

function onQuarterChange() {
    form.period_label = `Q${form.evaluation_quarter} ${form.evaluation_year}`;
}

function statusBadge(status) {
    if (status === 'assigned') return 'bg-emerald-50 text-emerald-800 ring-emerald-100';
    return 'bg-slate-50 text-slate-700 ring-slate-100';
}

function supervisorNames(formRow) {
    const names = (formRow.supervisors || []).map((s) => s.name).filter(Boolean);
    return names.length ? names.join(', ') : 'Not assigned';
}

function destroyForm(id) {
    if (confirm('Remove this IPCR form? Employees who have not submitted yet will lose this assigned form.')) {
        router.delete(route('admin.forms.destroy', id));
    }
}
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-slate-900">IPCR forms</h3>
            <button
                v-if="!creating"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-700"
                @click="startCreate"
            >
                <AppIcon name="plus" class="h-4 w-4" />
                Create
            </button>
        </div>

        <div v-if="creating" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <InputLabel value="Title (optional)" />
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    />
                    <InputError class="mt-1" :message="form.errors.title" />
                </div>
                <div>
                    <InputLabel value="Year" />
                    <input
                        v-model="form.evaluation_year"
                        type="number"
                        min="2000"
                        max="2100"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        @change="onQuarterChange"
                    />
                </div>
                <div>
                    <InputLabel value="Quarter" />
                    <select
                        v-model="form.evaluation_quarter"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                        @change="onQuarterChange"
                    >
                        <option :value="1">Q1</option>
                        <option :value="2">Q2</option>
                        <option :value="3">Q3</option>
                        <option :value="4">Q4</option>
                    </select>
                </div>
            </div>

            <CommitmentPackageForm
                v-model:entries="form.entries"
                :weight-summary="weightSummary"
                :errors="form.errors"
                :processing="form.processing"
                intro=""
                submit-label="Create"
                @submit="save"
                @cancel="cancelCreate"
            />
        </div>

        <div v-else class="mt-4">
            <p v-if="!templates.length" class="text-sm text-slate-500">No forms yet. Click Create.</p>

            <div v-else class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                <div
                    v-for="row in templates"
                    :key="row.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 hover:bg-slate-50/70"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ row.title }}</p>
                        <p class="text-xs text-slate-500">
                            {{ row.period_label }}
                            · Core {{ Number(row.weight_summary?.core || 0).toFixed(0) }}%
                            / Strategic {{ Number(row.weight_summary?.strategic || 0).toFixed(0) }}%
                            · {{ supervisorNames(row) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="statusBadge(row.status)">
                            {{ row.status }}
                        </span>
                        <Link :href="route('admin.forms.show', row.id)" class="text-sm font-semibold text-amber-700 hover:text-amber-800">
                            View
                        </Link>
                        <Link :href="route('admin.forms.edit', row.id)" class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                            Edit
                        </Link>
                        <button type="button" class="text-sm font-semibold text-rose-700 hover:text-rose-800" @click="destroyForm(row.id)">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
