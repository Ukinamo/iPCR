<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { flattenFormEntries, itemsToFormEntries } from '@/utils/ipcrFormEntries';

const props = defineProps({
    template: Object,
    weightSummary: Object,
});

const form = useForm({
    title: props.template.title ?? '',
    evaluation_year: props.template.evaluation_year,
    included_quarters: (props.template.included_quarters || [3, 4]).map((q) => Number(q)),
    entries: itemsToFormEntries(props.template.items),
});

function flattenedEntries() {
    return flattenFormEntries(form.entries);
}

function save() {
    form.transform((data) => ({
        title: data.title,
        evaluation_year: data.evaluation_year,
        included_quarters: (data.included_quarters || []).map((q) => Number(q)),
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
                        Changes apply only to this catalog form. Employee copies already started are not updated.
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
                    <div class="grid gap-4 md:grid-cols-2">
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
                    </div>
                    <div class="mt-4">
                        <InputLabel value="Include in accomplishments" />
                        <p class="mt-1 text-xs text-slate-500">Checked quarters show as Target/Actual columns, plus Total.</p>
                        <div class="mt-2 flex flex-wrap gap-4">
                            <label v-for="q in [1, 2, 3, 4]" :key="q" class="inline-flex items-center gap-2 text-sm text-slate-800">
                                <input
                                    v-model="form.included_quarters"
                                    type="checkbox"
                                    :value="q"
                                    class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                />
                                Q{{ q }}
                            </label>
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
