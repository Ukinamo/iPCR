<script setup>
import AdminTransferRequestsPanel from '@/Components/AdminTransferRequestsPanel.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    pendingRequests: Array,
    recentRequests: Array,
});

const page = usePage();
</script>

<template>
    <Head title="Transfer requests" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="users" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">Transfer requests</h2>
                        <p class="text-sm text-gray-500">Approve or reject employee reassignments requested by supervisors.</p>
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

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                    {{ page.props.flash.status }}
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <AdminTransferRequestsPanel
                        :pending-requests="pendingRequests"
                        :recent-requests="recentRequests"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
