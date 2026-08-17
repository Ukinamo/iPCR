<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    template: Object,
    weightSummary: Object,
});

function itemsToEntries(items) {
    const map = new Map();
    for (const item of items || []) {
        const key = `${item.function_type}|${item.title}`;
        if (!map.has(key)) {
            map.set(key, {
                enabled: true,
                function_type: item.function_type,
                title: item.title,
                items: [],
            });
        }
        map.get(key).items.push({
            id: item.id,
            _uid: item.id,
            description: item.description ?? '',
            weight: item.weight != null ? Number(item.weight) : null,
            annual_office_target: item.annual_office_target ?? '',
            individual_annual_targets: item.individual_annual_targets ?? '',
        });
    }

    const entries = Array.from(map.values());
    if (!entries.some((e) => e.function_type === 'core')) {
        entries.unshift({
            enabled: true,
            function_type: 'core',
            title: '',
            items: [{ _uid: Date.now(), description: '', weight: null, annual_office_target: '', individual_annual_targets: '' }],
        });
    }
    if (!entries.some((e) => e.function_type === 'strategic')) {
        entries.push({
            enabled: true,
            function_type: 'strategic',
            title: '',
            items: [{ _uid: Date.now() + 1, description: '', weight: null, annual_office_target: '', individual_annual_targets: '' }],
        });
    }

    return entries;
}

const form = useForm({
    title: props.template.title ?? '',
    evaluation_year: props.template.evaluation_year,
    evaluation_quarter: props.template.evaluation_quarter,
    period_label: props.template.period_label,
    entries: itemsToEntries(props.template.items),
});

function flattenedEntries() {
    return form.entries
        .filter((entry) => entry.enabled)
        .flatMap((entry) =>
            (entry.items || []).map((item) => ({
                id: item.id ?? null,
                function_type: entry.function_type,
                title: entry.title,
                description: item.description,
                weight: item.weight === '' || item.weight == null ? null : item.weight,
                annual_office_target: item.annual_office_target,
                individual_annual_targets: item.individual_annual_targets,
            })),
        );
}

function save() {
    form.transform((data) => ({
        title: data.title,
        evaluation_year: data.evaluation_year,
        evaluation_quarter: data.evaluation_quarter,
        period_label: `Q${data.evaluation_quarter} ${data.evaluation_year}`,
        entries: flattenedEntries(),
    })).patch(route('admin.forms.update', props.template.id), {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
}
</script>

<template>
    <Head title="Edit IPCR Form" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit IPCR form</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Changes update employees who have not yet submitted for review. Assign supervisors from the View page.
                    </p>
                </div>
                <Link :href="route('admin.forms.show', template.id)" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    Back to form
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <InputLabel value="Form title" />
                            <input
                                v-model="form.title"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            />
                        </div>
                        <div>
                            <InputLabel value="Year" />
                            <input
                                v-model="form.evaluation_year"
                                type="number"
                                min="2000"
                                max="2100"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            />
                        </div>
                        <div>
                            <InputLabel value="Quarter" />
                            <select
                                v-model="form.evaluation_quarter"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                            >
                                <option :value="1">Q1</option>
                                <option :value="2">Q2</option>
                                <option :value="3">Q3</option>
                                <option :value="4">Q4</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <CommitmentPackageForm
                        v-model:entries="form.entries"
                        :weight-summary="weightSummary"
                        :errors="form.errors"
                        :processing="form.processing"
                        intro="Employees cannot change these columns. They fill accomplishments; rating, average, and remarks (weight × average) compute automatically."
                        submit-label="Save"
                        @submit="save"
                        @cancel="router.visit(route('admin.forms.show', template.id))"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
