<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: Object,
    supervisors: Array,
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    role: props.user.role,
    account_status: props.user.account_status,
    supervisor_id: props.user.supervisor_id ?? '',
});

function submit() {
    form.transform((data) => ({
        ...data,
        supervisor_id: data.role === 'employee' && data.supervisor_id ? data.supervisor_id : null,
        password: data.password || null,
    })).patch(route('admin.users.update', props.user.id));
}
</script>

<template>
    <Head :title="`Edit User - ${user.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Edit User</h2>
                    <p class="text-sm text-gray-500">Update account details from a dedicated edit page.</p>
                </div>
                <Link :href="route('admin.users.index')" class="inline-flex rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Back to users
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                        <div>
                            <InputLabel for="name" value="Name" />
                            <input id="name" v-model="form.name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="email" value="Email" />
                            <input id="email" v-model="form.email" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                        <div>
                            <InputLabel for="password" value="New password (optional)" />
                            <input id="password" v-model="form.password" type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" />
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
                        <div class="md:col-span-2">
                            <PrimaryButton class="bg-amber-600 hover:bg-amber-700 focus:ring-amber-500" :disabled="form.processing">Save changes</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
