<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({
    pendingUsers: Array,
});

const page = usePage();
const processing = reactive({});

function approve(userId) {
    processing[userId] = true;

    router.patch(route('admin.users.approve', userId), {}, {
        preserveScroll: true,
        onFinish: () => {
            processing[userId] = false;
        },
    });
}

function reject(userId, name) {
    if (confirm(`Reject and remove the registration for ${name}?`)) {
        router.delete(route('admin.users.reject', userId), { preserveScroll: true });
    }
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Pending Registrations" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="clock" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">Pending Registrations</h2>
                        <p class="text-sm text-gray-500">Review new employee sign-ups before activating their account.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('admin.users.index')" class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <AppIcon name="users" class="h-4 w-4" />
                        All users
                    </Link>
                    <Link :href="route('dashboard')" class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <AppIcon name="arrow-left" class="h-4 w-4" />
                        Back dashboard
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                    {{ page.props.flash.status }}
                </div>

                <div v-if="!pendingUsers.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-10 text-center text-sm text-slate-500 shadow-sm">
                    No registrations waiting for approval.
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-for="u in pendingUsers"
                        :key="u.id"
                        class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-900">{{ u.name }}</p>
                                <p class="text-sm text-slate-600">{{ u.email }}</p>
                                <p class="mt-1 text-xs text-slate-500">Registered {{ formatDate(u.created_at) }}</p>
                            </div>
                            <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-100">pending approval</span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <PrimaryButton
                                class="bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500"
                                :disabled="processing[u.id]"
                                @click="approve(u.id)"
                            >
                                Approve
                            </PrimaryButton>
                            <SecondaryButton type="button" class="text-rose-700 ring-rose-200" @click="reject(u.id, u.name)">
                                Reject
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
