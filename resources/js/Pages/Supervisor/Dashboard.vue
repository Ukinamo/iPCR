<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ReviewTransferPanel from '@/Components/ReviewTransferPanel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    stats: Object,
    submissions: Array,
    teamMembers: {
        type: Array,
        default: () => [],
    },
    supervisors: {
        type: Array,
        default: () => [],
    },
    transferRequests: {
        type: Array,
        default: () => [],
    },
    incomingReviewTransfers: {
        type: Array,
        default: () => [],
    },
    outgoingReviewTransfers: {
        type: Array,
        default: () => [],
    },
});

const tab = ref('team');
const transferForms = reactive({});
const processingCancel = reactive({});

const statCards = [
    { key: 'teamMembers', label: 'Team Members', icon: 'users', tone: 'bg-sky-100 text-sky-700' },
    { key: 'approved', label: 'Approved', icon: 'check-badge', tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'pendingReview', label: 'Pending Review', icon: 'clipboard', tone: 'bg-amber-100 text-amber-700' },
    { key: 'averageRating', label: 'Average overall', icon: 'star', tone: 'bg-violet-100 text-violet-700' },
];

const tabs = [
    { id: 'team', label: 'Submissions', icon: 'clipboard' },
    { id: 'roster', label: 'My team', icon: 'users' },
    { id: 'history', label: 'Rating history', icon: 'star' },
];

props.teamMembers.forEach((member) => {
    transferForms[member.id] = useForm({
        employee_id: member.id,
        to_supervisor_id: '',
        reason: '',
    });
});

const employeesWithPendingTransfer = computed(() => new Set((props.transferRequests || []).map((r) => r.employee_id)));

const activeSubmissions = computed(() =>
    (props.submissions || []).filter((s) => s.status !== 'approved'),
);

const needsReviewSubmissions = computed(() =>
    (props.submissions || []).filter((s) => s.status === 'in_review'),
);

const otherActiveSubmissions = computed(() =>
    (props.submissions || []).filter((s) => s.status !== 'approved' && s.status !== 'in_review'),
);

function evidenceCount(s) {
    return (s.commitments || []).reduce((sum, c) => sum + (c.accomplishments?.length || 0), 0);
}

const approvedSubmissions = computed(() =>
    (props.submissions || []).filter((s) => s.status === 'approved'),
);

function badge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        pending: 'bg-amber-50 text-amber-900 ring-amber-100',
        returned: 'bg-rose-50 text-rose-900 ring-rose-100',
    };
    return map[status] ?? 'bg-slate-50 text-slate-700 ring-slate-100';
}

function initials(name) {
    return (name || '')
        .split(' ')
        .map((p) => p[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function formatWhen(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function periodLabel(s) {
    return `Q${s.evaluation_quarter} ${s.evaluation_year}`;
}

function reviewButtonLabel(s) {
    if (s.status === 'in_review') return 'Review package';
    if (s.status === 'pending') return 'View package';
    if (s.status === 'returned') return 'View package';
    return 'View package';
}

function submitTransfer(employeeId) {
    transferForms[employeeId].post(route('supervisor.transfer-requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            transferForms[employeeId].reset('to_supervisor_id', 'reason');
        },
    });
}

function cancelTransfer(id) {
    processingCancel[id] = true;
    router.delete(route('supervisor.transfer-requests.destroy', id), {
        preserveScroll: true,
        onFinish: () => { processingCancel[id] = false; },
    });
}
</script>

<template>
    <Head title="Supervisor Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-cyan-100 text-cyan-700">
                    <AppIcon name="briefcase" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Supervisor Dashboard</h2>
                    <p class="text-sm text-gray-500">
                        Rate each commitment using IPCR Form 1 rules: Quality from accomplishment (or progress %), Efficiency and Timeliness (1–5), then
                        weighted scores sum to the package overall.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div
                        v-for="card in statCards"
                        :key="card.key"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm text-slate-600">{{ card.label }}</p>
                                <p class="mt-2 text-3xl font-bold text-slate-900">{{ stats[card.key] }}</p>
                                <p v-if="card.key === 'pendingReview' && stats.otherActive" class="mt-1 text-xs text-slate-500">
                                    {{ stats.otherActive }} returned / draft
                                </p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="card.tone">
                                <AppIcon :name="card.icon" class="h-5 w-5" />
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto rounded-lg bg-sky-50/60 p-1 text-sm font-semibold text-slate-700 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    <button
                        v-for="item in tabs"
                        :key="item.id"
                        type="button"
                        class="inline-flex min-w-0 flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-md px-3 py-2"
                        :class="tab === item.id ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="tab = item.id"
                    >
                        <AppIcon :name="item.icon" class="h-4 w-4 shrink-0" />
                        {{ item.label }}
                    </button>
                </div>

                <div v-show="tab === 'roster'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                <AppIcon name="users" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Team roster & transfers</h3>
                                <p class="mt-1 text-sm text-slate-600">
                                    Request to transfer an employee to another supervisor. An administrator must approve before the employee is reassigned.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="transferRequests?.length" class="space-y-3">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <AppIcon name="clock" class="h-3.5 w-3.5" />
                            Pending transfer requests
                        </p>
                        <div v-for="req in transferRequests" :key="req.id" class="rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-slate-900">{{ req.employee?.name }}</p>
                                    <p class="text-sm text-slate-600">Transfer to {{ req.to_supervisor?.name }} · awaiting admin approval</p>
                                </div>
                                <SecondaryButton type="button" :disabled="processingCancel[req.id]" @click="cancelTransfer(req.id)">
                                    <span class="inline-flex items-center gap-1.5">
                                        <AppIcon name="x-mark" class="h-3.5 w-3.5" />
                                        Cancel request
                                    </span>
                                </SecondaryButton>
                            </div>
                        </div>
                    </div>

                    <div v-if="!teamMembers?.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                        <AppIcon name="users" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">No employees are currently assigned to you.</p>
                    </div>

                    <div v-for="member in teamMembers" :key="member.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ member.name }}</p>
                                <p class="text-sm text-slate-600">{{ member.email }}</p>
                            </div>
                            <span
                                v-if="employeesWithPendingTransfer.has(member.id)"
                                class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900 ring-1 ring-amber-100"
                            >
                                <AppIcon name="clock" class="h-3 w-3" />
                                transfer pending
                            </span>
                        </div>

                        <form
                            v-if="!employeesWithPendingTransfer.has(member.id)"
                            class="mt-4 grid gap-4 md:grid-cols-2"
                            @submit.prevent="submitTransfer(member.id)"
                        >
                            <div>
                                <InputLabel :for="`supervisor-${member.id}`" value="Transfer to supervisor" />
                                <select
                                    :id="`supervisor-${member.id}`"
                                    v-model="transferForms[member.id].to_supervisor_id"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                    required
                                >
                                    <option value="">Select supervisor</option>
                                    <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }} — {{ s.email }}</option>
                                </select>
                                <p v-if="transferForms[member.id].errors.to_supervisor_id" class="mt-1 text-xs text-rose-600">{{ transferForms[member.id].errors.to_supervisor_id }}</p>
                            </div>
                            <div>
                                <InputLabel :for="`reason-${member.id}`" value="Reason (optional)" />
                                <textarea
                                    :id="`reason-${member.id}`"
                                    v-model="transferForms[member.id].reason"
                                    rows="2"
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                    placeholder="Why should this employee be reassigned?"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <PrimaryButton type="submit" :disabled="transferForms[member.id].processing">
                                    <span class="inline-flex items-center gap-1.5">
                                        <AppIcon name="arrow-top-right" class="h-4 w-4" />
                                        Request transfer
                                    </span>
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>

                <div v-show="tab === 'team'" class="space-y-4">
                    <ReviewTransferPanel
                        :incoming-review-transfers="incomingReviewTransfers"
                        :outgoing-review-transfers="outgoingReviewTransfers"
                        :supervisors="supervisors"
                    />

                    <div
                        v-if="needsReviewSubmissions.length"
                        class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                            <AppIcon name="exclamation-triangle" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-semibold text-sky-900">
                                {{ needsReviewSubmissions.length }} package{{ needsReviewSubmissions.length === 1 ? '' : 's' }} waiting for your review
                            </p>
                            <p class="mt-1 text-sky-900/85">Open each submission below to rate commitments, review evidence, and approve or return.</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                            <AppIcon name="clipboard" class="h-5 w-5" />
                        </span>
                        <h3 class="text-lg font-semibold text-slate-900">Employee submissions</h3>
                    </div>

                    <div v-if="needsReviewSubmissions.length" class="space-y-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Needs review</p>
                        <div
                            v-for="s in needsReviewSubmissions"
                            :key="'review-' + s.id"
                            class="rounded-xl border-2 border-sky-200 bg-white p-5 shadow-sm ring-1 ring-sky-100"
                        >
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-sky-100 text-sm font-bold text-sky-800">
                                        {{ initials(s.employee.name) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ s.employee.name }}</p>
                                        <p class="text-xs text-slate-500">{{ periodLabel(s) }} · Submitted {{ formatWhen(s.submitted_at) }}</p>
                                        <p class="mt-1 text-xs text-slate-600">
                                            {{ s.commitments?.length ?? 0 }} commitment(s)
                                            <span v-if="evidenceCount(s)" class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-800 ring-1 ring-emerald-100">
                                                <AppIcon name="paper-clip" class="h-3 w-3" />
                                                {{ evidenceCount(s) }} evidence file{{ evidenceCount(s) === 1 ? '' : 's' }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-800 ring-1 ring-sky-100">
                                        in review
                                    </span>
                                    <Link :href="route('supervisor.submissions.show', s.id)">
                                        <PrimaryButton type="button" class="!bg-sky-600 hover:!bg-sky-700">
                                            <span class="inline-flex items-center gap-1.5">
                                                <AppIcon name="pencil" class="h-4 w-4" />
                                                Review package
                                            </span>
                                        </PrimaryButton>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="otherActiveSubmissions.length" class="space-y-3">
                        <p v-if="needsReviewSubmissions.length" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Other active</p>
                        <div v-for="s in otherActiveSubmissions" :key="s.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">
                                        {{ initials(s.employee.name) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ s.employee.name }}</p>
                                        <p class="text-xs text-slate-500">{{ periodLabel(s) }} · Submitted {{ formatWhen(s.submitted_at) }}</p>
                                        <p v-if="s.commitments?.length" class="mt-1 text-xs text-slate-600">{{ s.commitments.length }} commitment(s) in package</p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="badge(s.status)">
                                        {{ s.status.replace('_', ' ') }}
                                    </span>
                                    <Link :href="route('supervisor.submissions.show', s.id)">
                                        <SecondaryButton type="button">
                                            <span class="inline-flex items-center gap-1.5">
                                                <AppIcon name="eye" class="h-4 w-4" />
                                                {{ reviewButtonLabel(s) }}
                                            </span>
                                        </SecondaryButton>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!activeSubmissions.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
                        <AppIcon name="document-chart-bar" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">
                            No active submissions right now. Check the
                            <button type="button" class="inline-flex items-center gap-1 font-semibold text-blue-700 hover:underline" @click="tab = 'history'">
                                <AppIcon name="star" class="h-3.5 w-3.5" />
                                Rating history
                            </button>
                            tab for past approvals.
                        </p>
                    </div>
                </div>

                <div v-show="tab === 'history'" class="space-y-4">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                                <AppIcon name="star" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Rating history</h3>
                                <p class="text-xs text-slate-500">Approved IPCR submissions from your team.</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="!approvedSubmissions.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                        <AppIcon name="star" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">No approved submissions yet.</p>
                    </div>

                    <div v-else class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-4 py-3 text-left">Employee</th>
                                    <th class="px-4 py-3 text-left">Period</th>
                                    <th class="px-4 py-3 text-left">Approved</th>
                                    <th class="px-4 py-3 text-center">Commitments</th>
                                    <th class="px-4 py-3 text-center">Overall</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="s in approvedSubmissions" :key="s.id" class="align-top">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-800">
                                                {{ initials(s.employee.name) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-slate-900">{{ s.employee.name }}</p>
                                                <p class="text-[11px] text-slate-500">{{ s.employee.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ periodLabel(s) }}</td>
                                    <td class="px-4 py-3 text-xs text-slate-600">{{ formatWhen(s.reviewed_at) }}</td>
                                    <td class="px-4 py-3 text-center text-slate-700">{{ s.commitments?.length ?? 0 }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-100">
                                            {{ s.overall_rating != null ? Number(s.overall_rating).toFixed(2) : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            :href="route('supervisor.submissions.show', s.id)"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                        >
                                            <AppIcon name="eye" class="h-3.5 w-3.5" />
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
