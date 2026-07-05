<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IpcrApprovedSubmissionPanel from '@/Components/IpcrApprovedSubmissionPanel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    submission: Object,
});

function period(s) {
    return `Q${s.evaluation_quarter} ${s.evaluation_year}`;
}
</script>

<template>
    <Head :title="`IPCR — ${submission.employee?.name ?? 'Report'}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="clipboard" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">
                            {{ period(submission) }} · {{ submission.employee?.name }}
                        </h2>
                        <p class="text-sm text-gray-500">
                            Supervisor: {{ submission.supervisor?.name ?? '—' }}
                        </p>
                    </div>
                </div>
                <Link :href="route('admin.reports.ratings')" class="inline-flex">
                    <SecondaryButton type="button" class="inline-flex items-center gap-2">
                        <AppIcon name="arrow-left" class="h-4 w-4" />
                        Back to reports
                    </SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto w-full max-w-[100vw] space-y-4 px-3 sm:px-4 lg:px-8">
                <p class="text-sm text-slate-600">
                    Remarks = Weight% × Average per row; TOTAL Remarks = sum of row Remarks (final average rating).
                </p>
                <IpcrApprovedSubmissionPanel :submission="submission" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
