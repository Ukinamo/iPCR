<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps({
    pendingRequests: {
        type: Array,
        default: () => [],
    },
    recentRequests: {
        type: Array,
        default: () => [],
    },
});

const adminNotes = reactive({});
const processing = reactive({});

function approve(id) {
    processing[id] = 'approve';
    router.patch(route('admin.transfer-requests.approve', id), { admin_notes: adminNotes[id] || null }, {
        preserveScroll: true,
        onFinish: () => {
            processing[id] = null;
        },
    });
}

function reject(id) {
    processing[id] = 'reject';
    router.patch(route('admin.transfer-requests.reject', id), { admin_notes: adminNotes[id] || null }, {
        preserveScroll: true,
        onFinish: () => {
            processing[id] = null;
        },
    });
}

function formatWhen(iso) {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString();
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Transfer requests</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Approve or reject employee reassignments requested by supervisors.
                </p>
            </div>
        </div>

        <section class="space-y-4">
            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Pending approval</h4>

            <div
                v-if="!pendingRequests?.length"
                class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500"
            >
                No supervisor transfer requests waiting for approval.
            </div>

            <div
                v-for="req in pendingRequests"
                :key="req.id"
                class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="font-semibold text-slate-900">{{ req.employee?.name }}</p>
                        <p class="text-sm text-slate-600">{{ req.employee?.email }}</p>
                        <p class="mt-2 text-sm text-slate-700">
                            From <span class="font-medium">{{ req.from_supervisor?.name ?? req.requested_by?.name ?? '—' }}</span>
                            to <span class="font-medium">{{ req.to_supervisor?.name ?? '—' }}</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Requested by {{ req.requested_by?.name ?? '—' }} · {{ formatWhen(req.created_at) }}
                        </p>
                        <p v-if="req.reason" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ req.reason }}</p>
                    </div>
                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-100">pending</span>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto]">
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
                    <div class="grid grid-cols-2 gap-2 sm:contents">
                        <div class="flex items-end">
                            <PrimaryButton
                                class="w-full !bg-emerald-600 hover:!bg-emerald-700 sm:w-auto"
                                :disabled="processing[req.id]"
                                @click="approve(req.id)"
                            >
                                Approve
                            </PrimaryButton>
                        </div>
                        <div class="flex items-end">
                            <SecondaryButton
                                type="button"
                                class="w-full justify-center text-rose-700 ring-rose-200 sm:w-auto"
                                :disabled="processing[req.id]"
                                @click="reject(req.id)"
                            >
                                Reject
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="recentRequests?.length" class="space-y-4">
            <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Recent decisions</h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-[520px] w-full divide-y divide-slate-200 text-sm">
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
                            <td class="px-4 py-3 text-slate-600">{{ req.from_supervisor?.name ?? req.requested_by?.name ?? '—' }} → {{ req.to_supervisor?.name ?? '—' }}</td>
                            <td class="px-4 py-3 capitalize text-slate-700">{{ req.status }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ formatWhen(req.reviewed_at || req.updated_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
