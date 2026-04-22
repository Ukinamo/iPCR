<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    employee: Object,
    submissions: Array,
});

function period(s) {
    return `Q${s.evaluation_quarter} ${s.evaluation_year}`;
}

function submissionTotals(submission) {
    const rows = submission?.commitments || [];
    const weight = rows.reduce((sum, c) => sum + Number(c.weight || 0), 0);
    const weighted = rows.reduce((sum, c) => sum + Number(c.rating_weighted || 0), 0);
    return {
        weight: weight.toFixed(2),
        weighted: weighted.toFixed(2),
    };
}
</script>

<template>
    <Head :title="`Ratings — ${employee.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">IPCR ratings</h2>
                    <p class="text-sm text-gray-500">{{ employee.name }} · {{ employee.email }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="route('admin.users.ratings.export', employee.id)"
                        class="inline-flex items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        Export to Excel
                    </a>
                    <Link :href="route('dashboard')">
                        <SecondaryButton type="button">Back to dashboard</SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <p class="text-sm text-slate-600">
                    Workbook-aligned: Q3/Q4 targets and actuals roll up into totals, accomplishment % drives auto-Quality, then Average and Weighted
                    score are computed per commitment.
                </p>

                <div v-if="!submissions?.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600 shadow-sm">
                    No approved IPCR packages yet for this employee.
                </div>

                <div v-for="s in submissions" :key="s.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3">
                        <div>
                            <p class="font-semibold text-slate-900">{{ period(s) }}</p>
                            <p class="text-xs text-slate-500">
                                Supervisor: {{ s.supervisor?.name ?? '—' }} · Reviewed {{ s.reviewed_at ? new Date(s.reviewed_at).toLocaleDateString() : '—' }}
                            </p>
                        </div>
                        <p v-if="s.overall_rating != null" class="text-lg font-bold text-amber-800">Overall: {{ s.overall_rating }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Commitment</th>
                                    <th class="px-3 py-2">Type</th>
                                    <th class="px-3 py-2">Wt%</th>
                                    <th class="px-3 py-2">Prog%</th>
                                    <th class="px-3 py-2">Q3 Tgt</th>
                                    <th class="px-3 py-2">Q3 Act</th>
                                    <th class="px-3 py-2">Q4 Tgt</th>
                                    <th class="px-3 py-2">Q4 Act</th>
                                    <th class="px-3 py-2">Actual</th>
                                    <th class="px-3 py-2">Target</th>
                                    <th class="px-3 py-2">% Accomp</th>
                                    <th class="px-3 py-2">Q</th>
                                    <th class="px-3 py-2">E</th>
                                    <th class="px-3 py-2">T</th>
                                    <th class="px-3 py-2">Avg</th>
                                    <th class="px-3 py-2">Weighted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="c in s.commitments || []" :key="c.id">
                                    <td class="px-3 py-2 font-medium text-slate-900">{{ c.title }}</td>
                                    <td class="px-3 py-2 capitalize text-slate-600">{{ c.function_type }}</td>
                                    <td class="px-3 py-2">{{ Number(c.weight) }}</td>
                                    <td class="px-3 py-2">{{ c.progress }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ c.rating_q3_target ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ c.rating_q3_actual ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ c.rating_q4_target ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ c.rating_q4_actual ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ c.rating_actual_total ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ c.rating_target_total ?? '—' }}</td>
                                    <td class="px-3 py-2 text-slate-700">{{ c.rating_percent != null ? (Number(c.rating_percent) * 100).toFixed(0) + '%' : '—' }}</td>
                                    <td class="px-3 py-2">{{ c.rating_quality ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ c.rating_efficiency ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ c.rating_timeliness ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ c.rating_average ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ c.rating_weighted ?? '—' }}</td>
                                </tr>
                                <tr class="bg-slate-50 font-semibold text-slate-800">
                                    <td class="px-3 py-2" colspan="2">TOTAL</td>
                                    <td class="px-3 py-2">{{ submissionTotals(s).weight }}</td>
                                    <td class="px-3 py-2" colspan="8"></td>
                                    <td class="px-3 py-2">{{ submissionTotals(s).weighted }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
