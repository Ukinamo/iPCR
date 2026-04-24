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
                        <table class="min-w-full border-collapse text-xs">
                            <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Function</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Services / Programs / Indicators</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Weight</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Annual Office Target</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Individual Annual Targets</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="7">Accomplishments</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="4">Rating</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="3">Remarks</th>
                                </tr>
                                <tr>
                                    <th class="border border-slate-300 px-2 py-1" colspan="2">Q3</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="2">Q4</th>
                                    <th class="border border-slate-300 px-2 py-1" colspan="3">Total</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">Q</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">E</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">T</th>
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">Avg</th>
                                </tr>
                                <tr>
                                    <th class="border border-slate-300 px-2 py-1">Target</th>
                                    <th class="border border-slate-300 px-2 py-1">Actual</th>
                                    <th class="border border-slate-300 px-2 py-1">Target</th>
                                    <th class="border border-slate-300 px-2 py-1">Actual</th>
                                    <th class="border border-slate-300 px-2 py-1">Target</th>
                                    <th class="border border-slate-300 px-2 py-1">Actual</th>
                                    <th class="border border-slate-300 px-2 py-1">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="group in ['core', 'strategic']" :key="group">
                                    <tr v-if="(s.commitments || []).some(c => c.function_type === group)" class="bg-slate-100 font-semibold">
                                        <td class="border border-slate-300 px-2 py-1 uppercase text-slate-700" colspan="17">
                                            {{ group === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                            ({{ (s.commitments || []).filter(c => c.function_type === group).reduce((a, c) => a + Number(c.weight || 0), 0) }}%)
                                        </td>
                                    </tr>
                                    <tr v-for="c in (s.commitments || []).filter(c => c.function_type === group)" :key="c.id" class="align-top">
                                        <td class="border border-slate-300 px-2 py-1 font-semibold text-slate-800">
                                            {{ c.title }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1 text-slate-700 whitespace-pre-line">
                                            {{ c.description || '—' }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ Number(c.weight) }}%</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_target_total ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_target_total ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q3_target ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q3_actual ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q4_target ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_q4_actual ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_target_total ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_actual_total ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_percent != null ? (Number(c.rating_percent) * 100).toFixed(0) + '%' : '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_quality ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_efficiency ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_timeliness ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_average ?? '—' }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_weighted ?? '—' }}</td>
                                    </tr>
                                </template>
                                <tr class="bg-slate-100 font-semibold">
                                    <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">TOTAL</td>
                                    <td class="border border-slate-300 px-2 py-1 text-center">{{ submissionTotals(s).weight }}%</td>
                                    <td class="border border-slate-300 px-2 py-1" colspan="13"></td>
                                    <td class="border border-slate-300 px-2 py-1 text-center">{{ submissionTotals(s).weighted }}</td>
                                </tr>
                                <tr class="bg-amber-50 font-semibold text-amber-900">
                                    <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">FINAL AVERAGE RATING</td>
                                    <td class="border border-slate-300 px-2 py-1" colspan="15">
                                        {{ s.overall_rating != null ? Number(s.overall_rating).toFixed(2) : '—' }}
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
