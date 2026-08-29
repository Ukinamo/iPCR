<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { flattenFormEntries, itemsToFormEntries } from '@/utils/ipcrFormEntries';

const props = defineProps({
    package: Object,
    weightSummary: Object,
});

const form = useForm({
    title: props.package.title ?? '',
    evaluation_year: props.package.evaluation_year,
    evaluation_quarter: props.package.evaluation_quarter,
    entries: itemsToFormEntries(props.package.commitments),
});

function flattenedEntries() {
    return flattenFormEntries(form.entries);
}

function save() {
    form.transform((data) => ({
        title: data.title,
        evaluation_year: data.evaluation_year,
        evaluation_quarter: data.evaluation_quarter,
        entries: flattenedEntries(),
    })).patch(route('employee.packages.update', props.package.id), {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
}
</script>

<template>
    <Head title="Edit my IPCR form" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit my IPCR form</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Changes apply only to your copy. The administrator’s original form is not updated.
                    </p>
                </div>
                <Link :href="route('dashboard')" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                    Back to dashboard
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <InputLabel value="Form title" />
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-1 block w-full max-w-lg rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <CommitmentPackageForm
                        v-model:entries="form.entries"
                        :weight-summary="weightSummary"
                        :errors="form.errors"
                        :processing="form.processing"
                        intro="Edit functions and indicators on your copy, then save to continue to ratings."
                        submit-label="Save my copy"
                        @submit="save"
                        @cancel="router.visit(route('dashboard'))"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
