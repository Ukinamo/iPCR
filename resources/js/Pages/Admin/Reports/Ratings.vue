<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RatingReportList from '@/Components/RatingReportList.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    submissions: Array,
    reviewMonths: Array,
    filterMonth: [String, null],
});
</script>

<template>
    <Head title="Ratings report" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="document-chart-bar" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">Approved IPCR ratings</h2>
                        <p class="text-sm text-gray-500">All approved packages ordered by review date.</p>
                    </div>
                </div>
                <Link :href="route('dashboard')" class="inline-flex">
                    <SecondaryButton type="button" class="inline-flex items-center gap-2">
                        <AppIcon name="arrow-left" class="h-4 w-4" />
                        Back to dashboard
                    </SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <RatingReportList
                    :submissions="submissions"
                    :review-months="reviewMonths"
                    :filter-month="filterMonth"
                    filter-mode="server"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
