<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    notifications: Object,
});

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function openNotification(id) {
    router.patch(route('notifications.read', id));
}
</script>

<template>
    <Head title="Notifications" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                        <AppIcon name="bell" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">Notifications</h2>
                        <p class="text-sm text-gray-500">System alerts for transfers, submissions, and reviews.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <SecondaryButton type="button" @click="router.post(route('notifications.read-all'))">Mark all read</SecondaryButton>
                    <Link :href="route('dashboard')">
                        <SecondaryButton type="button" class="inline-flex items-center gap-2">
                            <AppIcon name="arrow-left" class="h-4 w-4" />
                            Dashboard
                        </SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div v-if="!notifications.data?.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                    No notifications yet.
                </div>

                <div v-else class="space-y-3">
                    <button
                        v-for="n in notifications.data"
                        :key="n.id"
                        type="button"
                        class="block w-full rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm hover:bg-slate-50"
                        :class="!n.read_at ? 'border-blue-200 bg-blue-50/30' : ''"
                        @click="openNotification(n.id)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ n.title }}</p>
                                <p class="mt-1 text-sm text-slate-600">{{ n.message }}</p>
                                <p class="mt-2 text-xs text-slate-400">{{ formatWhen(n.created_at) }}</p>
                            </div>
                            <span v-if="!n.read_at" class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold uppercase text-blue-800">New</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
