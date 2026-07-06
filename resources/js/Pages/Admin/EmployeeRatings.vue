<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IpcrApprovedSubmissionPanel from '@/Components/IpcrApprovedSubmissionPanel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    employee: Object,
    submissions: Array,
});
</script>

<template>
    <Head :title="`Ratings — ${employee.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="star" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">IPCR ratings</h2>
                        <p class="text-sm text-gray-500">{{ employee.name }} · {{ employee.email }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a
                        v-if="submissions?.length"
                        :href="route('admin.users.ratings.export', { user: employee.id, format: 'xlsx' })"
                        class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                    >
                        <AppIcon name="arrow-down-tray" class="h-4 w-4" />
                        Export all (Excel)
                    </a>
                    <Link :href="route('dashboard')" class="inline-flex">
                        <SecondaryButton type="button" class="inline-flex items-center gap-2">
                            <AppIcon name="arrow-left" class="h-4 w-4" />
                            Back to dashboard
                        </SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto w-full max-w-[100vw] space-y-6 px-3 sm:px-4 lg:px-8">
                <p class="text-sm text-slate-600">
                    Remarks = Weight% × Average per row; TOTAL Remarks = sum of row Remarks (final average rating).
                </p>

                <div v-if="!submissions?.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600 shadow-sm">
                    No approved IPCR packages yet for this employee.
                </div>

                <IpcrApprovedSubmissionPanel
                    v-for="s in submissions"
                    :key="s.id"
                    :submission="s"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
