<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    submission: Object,
    employee: Object,
    commitmentStatement: String,
    periodWindow: String,
    officeName: String,
    documentUrl: String,
    printUrl: String,
    exportUrls: Object,
    updateUrl: String,
    backUrl: String,
});

const form = useForm({
    commitment_statement: props.commitmentStatement,
});

const iframeKey = ref(0);

watch(
    () => props.commitmentStatement,
    (value) => {
        form.commitment_statement = value;
    },
);

function saveCommitment() {
    form.patch(props.updateUrl, {
        preserveScroll: true,
        onSuccess: () => {
            iframeKey.value += 1;
        },
    });
}

function openPrint() {
    window.location.assign(props.printUrl);
}

const periodLabel = `Q${props.submission.evaluation_quarter} ${props.submission.evaluation_year}`;
</script>

<template>
    <Head :title="`IPCR Preview — ${periodLabel}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        IPCR Form Preview
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ employee.name }} · {{ periodLabel }} · {{ officeName }}
                    </p>
                </div>
                <Link
                    :href="backUrl"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 hover:text-gray-900"
                >
                    <AppIcon name="arrow-left" class="h-4 w-4" />
                    Back
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-900">
                        Commitment statement
                    </h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Edit the commitment paragraph before printing or exporting. The rating period
                        (<strong>{{ periodWindow }}</strong>) is shown on the next line of the form.
                    </p>
                    <textarea
                        v-model="form.commitment_statement"
                        rows="4"
                        class="mt-3 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                    />
                    <p v-if="form.errors.commitment_statement" class="mt-2 text-sm text-red-600">
                        {{ form.errors.commitment_statement }}
                    </p>
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <PrimaryButton
                            type="button"
                            :disabled="form.processing"
                            @click="saveCommitment"
                        >
                            Save commitment
                        </PrimaryButton>
                        <SecondaryButton type="button" @click="openPrint">
                            <AppIcon name="export" class="mr-1.5 h-4 w-4" />
                            Print
                        </SecondaryButton>
                        <a
                            :href="exportUrls.xlsx"
                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Excel
                        </a>
                        <a
                            :href="exportUrls.pdf"
                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            PDF
                        </a>
                        <a
                            :href="exportUrls.csv"
                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            CSV
                        </a>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100 shadow-sm">
                    <iframe
                        :key="iframeKey"
                        :src="documentUrl"
                        title="IPCR form preview"
                        class="h-[1100px] w-full bg-white"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
