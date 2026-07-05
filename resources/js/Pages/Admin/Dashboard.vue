<script setup>
import AdminAnalyticsPanel from '@/Components/AdminAnalyticsPanel.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RatingReportList from '@/Components/RatingReportList.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    stats: Object,
    users: Array,
    approvedRatings: {
        type: Array,
        default: () => [],
    },
    reviewMonths: {
        type: Array,
        default: () => [],
    },
    analytics: {
        type: Object,
        default: () => ({}),
    },
});

const tab = ref('users');

const statCards = [
    { key: 'totalUsers', label: 'Total Users', icon: 'users', tone: 'bg-violet-100 text-violet-700' },
    { key: 'activeUsers', label: 'Active Users', icon: 'user-check', tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'supervisors', label: 'Supervisors', icon: 'briefcase', tone: 'bg-cyan-100 text-cyan-700' },
    { key: 'employees', label: 'Employees', icon: 'identification', tone: 'bg-sky-100 text-sky-700' },
    { key: 'pendingRegistrations', label: 'Pending Registrations', icon: 'clock', tone: 'bg-amber-100 text-amber-700' },
];

const tabs = [
    { id: 'users', label: 'User Management', icon: 'users' },
    { id: 'reports', label: 'Reports', icon: 'document-chart-bar' },
    { id: 'analytics', label: 'Analytics', icon: 'chart-bar' },
    { id: 'settings', label: 'Settings', icon: 'cog' },
];

function roleBadge(role) {
    if (role === 'employee') return 'bg-sky-50 text-sky-800 ring-sky-100';
    if (role === 'supervisor') return 'bg-cyan-50 text-cyan-800 ring-cyan-100';
    return 'bg-violet-50 text-violet-800 ring-violet-100';
}

function statusBadge(status) {
    if (status === 'active') return 'bg-emerald-50 text-emerald-800 ring-emerald-100';
    if (status === 'pending') return 'bg-amber-50 text-amber-900 ring-amber-100';
    return 'bg-slate-100 text-slate-700 ring-slate-200';
}

function destroyUser(id) {
    if (confirm('Remove this user?')) {
        router.delete(route('admin.users.destroy', id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="System Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                    <AppIcon name="building" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">System Dashboard</h2>
                    <p class="text-sm text-gray-500">Monitor performance evaluations and manage system-wide operations.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div
                        v-for="card in statCards"
                        :key="card.key"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm text-slate-600">{{ card.label }}</p>
                                <p class="mt-2 text-3xl font-bold text-slate-900">{{ stats[card.key] }}</p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="card.tone">
                                <AppIcon :name="card.icon" class="h-5 w-5" />
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="stats.pendingRegistrations > 0" class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="exclamation-triangle" class="h-5 w-5" />
                    </span>
                    <p>
                        <span class="font-semibold">{{ stats.pendingRegistrations }} registration(s)</span>
                        waiting for supervisor assignment.
                        <Link :href="route('admin.users.pending')" class="ml-1 inline-flex items-center gap-1 font-semibold text-amber-900 underline hover:text-amber-950">
                            Review now
                            <AppIcon name="arrow-top-right" class="h-3.5 w-3.5" />
                        </Link>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 rounded-lg bg-amber-50/60 p-1 text-sm font-semibold text-slate-700">
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        class="inline-flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 min-w-[9rem]"
                        :class="tab === item.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="tab = item.id"
                    >
                        <AppIcon :name="item.icon" class="h-4 w-4 shrink-0" />
                        {{ item.label }}
                    </button>
                </div>

                <div v-show="tab === 'users'" class="space-y-4">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <AppIcon name="users" class="h-5 w-5" />
                            </span>
                            <h3 class="text-lg font-semibold text-slate-900">Manage Users</h3>
                        </div>
                        <Link
                            :href="route('admin.users.index')"
                            class="inline-flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                        >
                            <AppIcon name="arrow-top-right" class="h-4 w-4" />
                            Open user management
                        </Link>
                    </div>

                    <p class="text-sm text-slate-500">Create and edit are now on dedicated pages for safer user administration.</p>

                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Email</th>
                                    <th class="px-4 py-3">Role</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="u in users" :key="u.id" class="hover:bg-slate-50/60">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ u.name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ u.email }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold ring-1" :class="roleBadge(u.role)">
                                            {{ u.role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold ring-1" :class="statusBadge(u.account_status)">
                                            {{ u.account_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <Link
                                                v-if="u.role === 'employee'"
                                                :href="route('admin.users.ratings', u.id)"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                                            >
                                                <AppIcon name="star" class="h-3.5 w-3.5 text-amber-600" />
                                                View ratings
                                            </Link>
                                            <Link
                                                :href="route('admin.users.edit', u.id)"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                                            >
                                                <AppIcon name="pencil" class="h-3.5 w-3.5" />
                                                Edit
                                            </Link>
                                            <SecondaryButton class="inline-flex items-center gap-1.5 text-rose-700 ring-rose-200" @click="destroyUser(u.id)">
                                                <AppIcon name="trash" class="h-3.5 w-3.5" />
                                                Delete
                                            </SecondaryButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-show="tab === 'reports'" class="space-y-6">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div class="flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                                    <AppIcon name="clipboard" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">Approved IPCR ratings</h3>
                                    <p class="mt-1 text-sm text-slate-600">
                                        All approved packages by review date. Open a row to view details or export.
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="route('admin.reports.ratings')"
                                class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                            >
                                <AppIcon name="arrow-top-right" class="h-4 w-4" />
                                Open full report
                            </Link>
                        </div>
                        <div class="mt-6">
                            <RatingReportList
                                :submissions="approvedRatings"
                                :review-months="reviewMonths"
                                filter-mode="client"
                            />
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                <AppIcon name="shield-check" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Compliance exports</h3>
                                <p class="mt-2 text-sm text-slate-600">Download roster data for HR compliance checks (CSV).</p>
                                <a
                                    :href="route('admin.reports.users')"
                                    class="mt-4 inline-flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                                >
                                    <AppIcon name="arrow-down-tray" class="h-4 w-4" />
                                    Download users.csv
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="tab === 'analytics'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <AdminAnalyticsPanel :analytics="analytics" />
                </div>

                <div v-show="tab === 'settings'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start gap-3 text-sm text-slate-600">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                            <AppIcon name="cog" class="h-5 w-5" />
                        </span>
                        <p>Institution-wide evaluation cycles, notification templates, and MFA policies can be centralized here in a future iteration.</p>
                    </div>
                </div>

                <div class="flex items-start gap-3 rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                        <AppIcon name="check-badge" class="h-5 w-5" />
                    </span>
                    <p>
                        <span class="font-semibold">System status:</span>
                        all services operational. Audit logging captures authentication and IPCR review events for accountability.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
