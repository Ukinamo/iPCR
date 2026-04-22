<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    stats: Object,
    submissions: Array,
});

const tab = ref('team');
const selectedSubmission = ref(null);

const reviewForm = useForm({
    action: 'approve',
    quality: 5,
    efficiency: 5,
    timeliness: 5,
    supervisor_feedback: '',
});

function openReview(submission) {
    selectedSubmission.value = submission;
    reviewForm.reset();
    reviewForm.action = 'approve';
    reviewForm.quality = 5;
    reviewForm.efficiency = 5;
    reviewForm.timeliness = 5;
}

function closeReview() {
    selectedSubmission.value = null;
}

function submitReview() {
    if (!selectedSubmission.value) return;
    reviewForm.patch(route('supervisor.submissions.update', selectedSubmission.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selectedSubmission.value = null;
        },
    });
}

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
    return name
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
</script>

<template>
    <Head title="Supervisor Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Welcome, Supervisor</h2>
                <p class="text-sm text-gray-500">Review commitments, apply SPMS ratings, or return packages with clear guidance.</p>
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
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-600">Average Rating</p>
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
                        :class="tab === 'summary' ? 'bg-white shadow-sm' : ''"
                        @click="tab = 'summary'"
                    >
                        Rating Summary
                    </button>
                </div>

                <div v-show="tab === 'team'" class="space-y-4">
                    <h3 class="text-lg font-semibold text-slate-900">Employee submissions</h3>
                    <div v-for="s in submissions" :key="s.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">
                                    {{ initials(s.employee.name) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ s.employee.name }}</p>
                                    <p class="text-xs text-slate-500">{{ periodLabel(s) }} · Submitted {{ formatWhen(s.submitted_at) }}</p>
                                    <p v-if="s.commitments?.length" class="mt-1 text-xs text-slate-600">{{ s.commitments.length }} commitment(s) in package</p>
                                    <p v-if="s.overall_rating" class="mt-1 text-sm font-semibold text-amber-700">Rating: {{ s.overall_rating }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="badge(s.status)">
                                    {{ s.status.replace('_', ' ') }}
                                </span>
                                <SecondaryButton v-if="s.status === 'in_review'" @click="openReview(s)">Review package</SecondaryButton>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="tab === 'summary'" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm text-sm text-slate-600">
                    <p>
                        SPMS aggregate rating uses the mean of Quality, Efficiency, and Timeliness (1–5). Each submission should reflect the
                        employee’s full commitment set for the quarter (60% core / 40% strategic) before you approve.
                    </p>
                </div>

                <div v-if="selectedSubmission" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="closeReview">
                    <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-6 shadow-2xl">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase text-slate-500">Review IPCR package</p>
                                <h4 class="text-xl font-semibold text-slate-900">{{ selectedSubmission.employee.name }}</h4>
                                <p class="text-sm text-slate-600">{{ periodLabel(selectedSubmission) }} · {{ formatWhen(selectedSubmission.submitted_at) }}</p>
                            </div>
                            <SecondaryButton @click="closeReview">Close</SecondaryButton>
                        </div>

                        <div class="mt-4">
                            <p class="text-sm font-semibold text-slate-800">Commitments in this submission</p>
                            <div class="mt-2 overflow-hidden rounded-lg border border-slate-200">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th class="px-3 py-2">Title</th>
                                            <th class="px-3 py-2">Type</th>
                                            <th class="px-3 py-2">Weight</th>
                                            <th class="px-3 py-2">Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="c in selectedSubmission.commitments || []" :key="c.id">
                                            <td class="px-3 py-2">
                                                <span class="font-medium text-slate-900">{{ c.title }}</span>
                                                <p v-if="c.description" class="mt-1 text-xs text-slate-600">{{ c.description }}</p>
                                            </td>
                                            <td class="px-3 py-2 capitalize text-slate-700">{{ c.function_type }}</td>
                                            <td class="px-3 py-2 text-slate-800">{{ Number(c.weight) }}%</td>
                                            <td class="px-3 py-2">
                                                <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100">
                                                    <div class="h-1.5 rounded-full bg-blue-600" :style="{ width: c.progress + '%' }" />
                                                </div>
                                                <span class="text-xs text-slate-500">{{ c.progress }}%</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-slate-100 pt-4">
                            <p class="text-sm font-semibold text-slate-800">Decision</p>
                            <p class="mt-1 text-xs text-slate-500">Approve with SPMS Q/E/T scores, or return with actionable comments (min. 20 characters).</p>

                            <div class="mt-3 flex gap-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-md border px-3 py-2 text-sm font-semibold"
                                    :class="reviewForm.action === 'approve' ? 'border-blue-600 bg-blue-50 text-blue-800' : 'border-slate-200'"
                                    @click="reviewForm.action = 'approve'"
                                >
                                    Approve
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-md border px-3 py-2 text-sm font-semibold"
                                    :class="reviewForm.action === 'return' ? 'border-amber-500 bg-amber-50 text-amber-900' : 'border-slate-200'"
                                    @click="reviewForm.action = 'return'"
                                >
                                    Return for revision
                                </button>
                            </div>

                            <div v-if="reviewForm.action === 'approve'" class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div>
                                    <InputLabel value="Quality (1–5)" />
                                    <TextInput v-model="reviewForm.quality" type="number" min="1" max="5" class="mt-1 block w-full" />
                                    <InputError class="mt-1" :message="reviewForm.errors.quality" />
                                </div>
                                <div>
                                    <InputLabel value="Efficiency (1–5)" />
                                    <TextInput v-model="reviewForm.efficiency" type="number" min="1" max="5" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Timeliness (1–5)" />
                                    <TextInput v-model="reviewForm.timeliness" type="number" min="1" max="5" class="mt-1 block w-full" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <InputLabel :value="reviewForm.action === 'return' ? 'Comments for employee (required when returning)' : 'Optional comments'" />
                                <textarea
                                    v-model="reviewForm.supervisor_feedback"
                                    rows="4"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    :placeholder="
                                        reviewForm.action === 'return'
                                            ? 'Explain what to fix (targets, evidence, weights, or narrative). Minimum 20 characters.'
                                            : 'Optional recognition or follow-up items.'
                                    "
                                />
                                <InputError class="mt-1" :message="reviewForm.errors.supervisor_feedback" />
                            </div>

                            <div class="mt-6 flex justify-end gap-2">
                                <SecondaryButton @click="closeReview">Cancel</SecondaryButton>
                                <PrimaryButton :disabled="reviewForm.processing" @click="submitReview">Submit decision</PrimaryButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
