<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    users: Array,
});

function roleBadge(role) {
    if (role === 'employee') return 'bg-sky-50 text-sky-800 ring-sky-100';
    if (role === 'supervisor') return 'bg-cyan-50 text-cyan-800 ring-cyan-100';
    return 'bg-violet-50 text-violet-800 ring-violet-100';
}

function statusBadge(status) {
    return status === 'active' ? 'bg-emerald-50 text-emerald-800 ring-emerald-100' : 'bg-slate-100 text-slate-700 ring-slate-200';
}

function destroyUser(id) {
    if (confirm('Remove this user?')) {
        router.delete(route('admin.users.destroy', id));
    }
}
</script>

<template>
    <Head title="User Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">User Management</h2>
                    <p class="text-sm text-gray-500">Create users and maintain roles in dedicated pages.</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('admin.users.create')" class="inline-flex rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        + Create user
                    </Link>
                    <Link :href="route('dashboard')" class="inline-flex rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Back dashboard
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
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
                            <tr v-for="u in users" :key="u.id">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ u.name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ u.email }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold ring-1" :class="roleBadge(u.role)">{{ u.role }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold ring-1" :class="statusBadge(u.account_status)">{{ u.account_status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Link v-if="u.role === 'employee'" :href="route('admin.users.ratings', u.id)" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
                                            Ratings
                                        </Link>
                                        <Link :href="route('admin.users.edit', u.id)" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50">
                                            Edit
                                        </Link>
                                        <SecondaryButton class="text-rose-700 ring-rose-200" @click="destroyUser(u.id)">Delete</SecondaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
