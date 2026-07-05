<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({
    pendingRequests: Array,
    recentRequests: Array,
});

const page = usePage();
const adminNotes = reactive({});
const processing = reactive({});

function approve(id) {
    processing[id] = 'approve';
    router.patch(route('admin.transfer-requests.approve', id), { admin_notes: adminNotes[id] || null }, {
        preserveScroll: true,
        onFinish: () => { processing[id] = null; },
    });
}

function reject(id) {
    processing[id] = 'reject';
    router.patch(route('admin.transfer-requests.reject', id), { admin_notes: adminNotes[id] || null }, {
        preserveScroll: true,
        onFinish: () => { processing[id] = null; },
    });
}

function formatWhen(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Head title="Supervisor transfer requests" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                        <AppIcon name="users" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-xl font-semibold leading-tight text-gray-800">Supervisor transfer requests</h2>
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
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="page.props.flash?.status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900">
                    {{ page.props.flash.status }}
                </div>

                <section class="space-y-4">
                    <h3 class="text-lg font-semibold text-slate-900">Pending approval</h3>

                    <div v-if="!pendingRequests?.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                        No supervisor transfer requests waiting for approval.
                    </div>

                    <div v-for="req in pendingRequests" :key="req.id" class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-900">{{ req.employee?.name }}</p>
                                <p class="text-sm text-slate-600">{{ req.employee?.email }}</p>
                                <p class="mt-2 text-sm text-slate-700">
                                    From <span class="font-medium">{{ req.fromSupervisor?.name ?? '—' }}</span>
                                    to <span class="font-medium">{{ req.toSupervisor?.name }}</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    Requested by {{ req.requestedBy?.name }} · {{ formatWhen(req.created_at) }}
                                </p>
                                <p v-if="req.reason" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ req.reason }}</p>
                            </div>
                            <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-100">pending</span>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-[1fr_auto_auto]">
                            <div>
                                <label :for="`notes-${req.id}`" class="text-sm font-medium text-slate-700">Admin notes (optional)</label>
                                <textarea
                                    :id="`notes-${req.id}`"
                                    v-model="adminNotes[req.id]"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                    placeholder="Optional note for the record"
                                />
                            </div>
                            <div class="flex items-end">
                                <PrimaryButton
                                    class="!bg-emerald-600 hover:!bg-emerald-700"
                                    :disabled="processing[req.id]"
                                    @click="approve(req.id)"
                                >
                                    Approve
                                </PrimaryButton>
                            </div>
                            <div class="flex items-end">
                                <SecondaryButton type="button" class="text-rose-700 ring-rose-200" :disabled="processing[req.id]" @click="reject(req.id)">
                                    Reject
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="recentRequests?.length" class="space-y-4">
                    <h3 class="text-lg font-semibold text-slate-900">Recent decisions</h3>
                    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Employee</th>
                                    <th class="px-4 py-3">Transfer</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Reviewed</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="req in recentRequests" :key="req.id">
                                    <td class="px-4 py-3 font-medium text-slate-900">{{ req.employee?.name }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ req.fromSupervisor?.name ?? '—' }} → {{ req.toSupervisor?.name }}</td>
                                    <td class="px-4 py-3 capitalize text-slate-700">{{ req.status }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-500">{{ formatWhen(req.reviewed_at || req.updated_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
