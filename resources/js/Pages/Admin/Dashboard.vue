<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    stats: Object,
    users: Array,
    supervisors: Array,
});

const tab = ref('users');

const form = useForm({
    name: '',
    email: '',
    password: '',
    role: 'employee',
    account_status: 'active',
    supervisor_id: '',
});

function roleBadge(role) {
    if (role === 'employee') return 'bg-sky-50 text-sky-800 ring-sky-100';
    if (role === 'supervisor') return 'bg-cyan-50 text-cyan-800 ring-cyan-100';
    return 'bg-violet-50 text-violet-800 ring-violet-100';
}

function statusBadge(status) {
    return status === 'active' ? 'bg-emerald-50 text-emerald-800 ring-emerald-100' : 'bg-slate-100 text-slate-700 ring-slate-200';
}

function submitUser() {
    form.transform((data) => ({
        ...data,
        supervisor_id: data.role === 'employee' && data.supervisor_id ? data.supervisor_id : null,
    })).post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'name', 'email'),
    });
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
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">System Dashboard</h2>
                <p class="text-sm text-gray-500">Monitor performance evaluations and manage system-wide operations.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Total Users</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.totalUsers }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Active Users</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.activeUsers }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Supervisors</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.supervisors }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Employees</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.employees }}</p>
                    </div>
                </div>

                <div class="flex gap-2 rounded-lg bg-amber-50/60 p-1 text-sm font-semibold text-slate-700">
                    <button type="button" class="flex-1 rounded-md px-3 py-2" :class="tab === 'users' ? 'bg-white shadow-sm' : ''" @click="tab = 'users'">
                        User Management
                    </button>
                    <button type="button" class="flex-1 rounded-md px-3 py-2" :class="tab === 'reports' ? 'bg-white shadow-sm' : ''" @click="tab = 'reports'">
                        Reports
                    </button>
                    <button type="button" class="flex-1 rounded-md px-3 py-2" :class="tab === 'analytics' ? 'bg-white shadow-sm' : ''" @click="tab = 'analytics'">
                        Analytics
                    </button>
                    <button type="button" class="flex-1 rounded-md px-3 py-2" :class="tab === 'settings' ? 'bg-white shadow-sm' : ''" @click="tab = 'settings'">
                        Settings
                    </button>
                </div>

                <div v-show="tab === 'users'" class="space-y-4">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <h3 class="text-lg font-semibold text-slate-900">Manage Users</h3>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h4 class="text-sm font-semibold text-slate-900">Add user</h4>
                        <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="submitUser">
                            <div>
                                <InputLabel for="name" value="Name" />
                                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>
                            <div>
                                <InputLabel for="email" value="Email" />
                                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>
                            <div>
                                <InputLabel for="password" value="Temporary password" />
                                <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>
                            <div>
                                <InputLabel for="role" value="Role" />
                                <select id="role" v-model="form.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="employee">Employee</option>
                                    <option value="supervisor">Supervisor</option>
                                    <option value="administrator">Administrator</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="account_status" value="Status" />
                                <select id="account_status" v-model="form.account_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div v-if="form.role === 'employee'">
                                <InputLabel for="supervisor_id" value="Supervisor" />
                                <select id="supervisor_id" v-model="form.supervisor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    <option value="">Select supervisor</option>
                                    <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }} — {{ s.email }}</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.supervisor_id" />
                            </div>
                            <div class="flex items-end">
                                <PrimaryButton class="bg-amber-600 hover:bg-amber-700 focus:ring-amber-500" :disabled="form.processing">
                                    + Add User
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>

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
                                        <SecondaryButton class="text-rose-700 ring-rose-200" @click="destroyUser(u.id)">Delete</SecondaryButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-show="tab === 'reports'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Compliance exports</h3>
                    <p class="mt-2 text-sm text-slate-600">Download roster data for HR compliance checks (CSV).</p>
                    <a
                        :href="route('admin.reports.users')"
                        class="mt-4 inline-flex rounded-md bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                    >
                        Download users.csv
                    </a>
                </div>

                <div v-show="tab === 'analytics'" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
                    Analytics views (trends, distribution of SPMS ratings) can plug into this tab using the same datasets powering supervisor dashboards.
                </div>

                <div v-show="tab === 'settings'" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
                    Institution-wide evaluation cycles, notification templates, and MFA policies can be centralized here in a future iteration.
                </div>

                <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900">
                    <span class="font-semibold">System status:</span>
                    all services operational. Audit logging captures authentication and IPCR review events for accountability.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
