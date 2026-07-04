<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stats: Object,
    submissions: Array,
});

const tab = ref('team');

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
</script>

<template>
    <Head title="Supervisor Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Welcome, Supervisor</h2>
                <p class="text-sm text-gray-500">
                    Rate each commitment using IPCR Form 1 rules: Quality from accomplishment (or progress %), Efficiency and Timeliness (1–5), then
                    weighted scores sum to the package overall.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Team Members</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.teamMembers }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Approved</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.approved }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Pending Review</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.pendingReview }}</p>
                        <p v-if="stats.otherActive" class="mt-1 text-xs text-slate-500">{{ stats.otherActive }} returned / draft</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Average overall</p>
                        <p class="mt-2 text-3xl font-bold">{{ stats.averageRating }}</p>
                    </div>
                </div>

                <div class="flex gap-2 rounded-lg bg-slate-100 p-1 text-sm font-semibold text-slate-600">
                    <button type="button" class="flex-1 rounded-md px-3 py-2" :class="tab === 'team' ? 'bg-white shadow-sm' : ''" @click="tab = 'team'">
                        Team Members
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-2"
                        :class="tab === 'history' ? 'bg-white shadow-sm' : ''"
                        @click="tab = 'history'"
                    >
                        Rating history
                    </button>
                </div>

                <div v-show="tab === 'team'" class="space-y-4">
                    <div
                        v-if="needsReviewSubmissions.length"
                        class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950"
                    >
                        <p class="font-semibold text-sky-900">
                            {{ needsReviewSubmissions.length }} package{{ needsReviewSubmissions.length === 1 ? '' : 's' }} waiting for your review
                        </p>
                        <p class="mt-1 text-sky-900/85">Open each submission below to rate commitments, review evidence, and approve or return.</p>
                    </div>

                    <h3 class="text-lg font-semibold text-slate-900">Employee submissions</h3>

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
                                            <span v-if="evidenceCount(s)" class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-800 ring-1 ring-emerald-100">
                                                📎 {{ evidenceCount(s) }} evidence file{{ evidenceCount(s) === 1 ? '' : 's' }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-800 ring-1 ring-sky-100">
                                        in review
                                    </span>
                                    <Link :href="route('supervisor.submissions.show', s.id)">
                                        <PrimaryButton type="button" class="!bg-sky-600 hover:!bg-sky-700">Review package</PrimaryButton>
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
                                        <SecondaryButton type="button">{{ reviewButtonLabel(s) }}</SecondaryButton>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="!activeSubmissions.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
                        No active submissions right now. Check the <button type="button" class="font-semibold text-blue-700 hover:underline" @click="tab = 'history'">Rating history</button> tab for past approvals.
                    </div>
                </div>

                <div v-show="tab === 'history'" class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Rating history</h3>
                        <p class="text-xs text-slate-500">Approved IPCR submissions from your team. Open a row to view the full rating sheet and export options.</p>
                    </div>

                    <div v-if="!approvedSubmissions.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                        No approved submissions yet.
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
                                            class="inline-flex rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                        >
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
