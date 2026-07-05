<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import BaseChart from '@/Components/BaseChart.vue';
import { computed } from 'vue';

const props = defineProps({
    analytics: {
        type: Object,
        required: true,
    },
});

const summaryCards = computed(() => {
    const s = props.analytics?.summary ?? {};

    return [
        {
            label: 'Registrations this month',
            value: s.registrationsThisMonth ?? 0,
            icon: 'users',
            tone: 'bg-violet-100 text-violet-700',
        },
        {
            label: 'Submitted this month',
            value: s.submissionsThisMonth ?? 0,
            icon: 'clipboard',
            tone: 'bg-sky-100 text-sky-700',
        },
        {
            label: 'Approved this month',
            value: s.approvedThisMonth ?? 0,
            icon: 'check-badge',
            tone: 'bg-emerald-100 text-emerald-700',
        },
        {
            label: 'Returned this month',
            value: s.returnedThisMonth ?? 0,
            icon: 'exclamation-triangle',
            tone: 'bg-amber-100 text-amber-700',
        },
        {
            label: 'In review now',
            value: s.inReview ?? 0,
            icon: 'clock',
            tone: 'bg-cyan-100 text-cyan-700',
        },
        {
            label: 'Average rating',
            value: s.averageRating != null ? Number(s.averageRating).toFixed(2) : '—',
            icon: 'star',
            tone: 'bg-amber-100 text-amber-700',
        },
    ];
});

const registrationChart = computed(() => ({
    labels: props.analytics?.userRegistrations?.labels ?? [],
    datasets: [
        {
            label: 'New users',
            data: props.analytics?.userRegistrations?.values ?? [],
            borderColor: '#7c3aed',
            backgroundColor: 'rgba(124, 58, 237, 0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 5,
        },
    ],
}));

const evaluationChart = computed(() => ({
    labels: props.analytics?.evaluationActivity?.labels ?? [],
    datasets: [
        {
            label: 'Submitted',
            data: props.analytics?.evaluationActivity?.submitted ?? [],
            backgroundColor: 'rgba(56, 189, 248, 0.85)',
            borderRadius: 4,
        },
        {
            label: 'Approved',
            data: props.analytics?.evaluationActivity?.approved ?? [],
            backgroundColor: 'rgba(16, 185, 129, 0.85)',
            borderRadius: 4,
        },
        {
            label: 'Returned',
            data: props.analytics?.evaluationActivity?.returned ?? [],
            backgroundColor: 'rgba(245, 158, 11, 0.9)',
            borderRadius: 4,
        },
    ],
}));

const statusChart = computed(() => ({
    labels: props.analytics?.submissionsByStatus?.labels ?? [],
    datasets: [
        {
            data: props.analytics?.submissionsByStatus?.values ?? [],
            backgroundColor: ['#94a3b8', '#38bdf8', '#10b981', '#f59e0b'],
            borderWidth: 0,
            hoverOffset: 6,
        },
    ],
}));

const ratingChart = computed(() => ({
    labels: props.analytics?.ratingDistribution?.labels ?? [],
    datasets: [
        {
            label: 'Approved packages',
            data: props.analytics?.ratingDistribution?.values ?? [],
            backgroundColor: [
                'rgba(244, 63, 94, 0.75)',
                'rgba(245, 158, 11, 0.8)',
                'rgba(56, 189, 248, 0.8)',
                'rgba(16, 185, 129, 0.85)',
                'rgba(124, 58, 237, 0.85)',
            ],
            borderRadius: 6,
        },
    ],
}));

const evaluationOptions = {
    scales: {
        x: {
            stacked: false,
            grid: { display: false },
            ticks: { font: { size: 10 }, maxRotation: 45, minRotation: 0 },
        },
        y: {
            beginAtZero: true,
            ticks: { precision: 0, font: { size: 10 } },
            grid: { color: 'rgba(148, 163, 184, 0.2)' },
        },
    },
};

const doughnutOptions = {
    cutout: '62%',
    plugins: {
        legend: {
            position: 'bottom',
        },
    },
};
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                    <AppIcon name="chart-bar" class="h-5 w-5" />
                </span>
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">System analytics</h3>
                    <p class="text-sm text-slate-600">Trends for registrations, IPCR evaluations, returns, and rating distribution (last 12 months).</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 text-xs font-medium text-slate-600">
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-800 ring-1 ring-emerald-100">
                    Approval rate: {{ analytics.summary?.approvalRate ?? 0 }}%
                </span>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-900 ring-1 ring-amber-100">
                    Return rate: {{ analytics.summary?.returnRate ?? 0 }}%
                </span>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900">{{ card.value }}</p>
                    </div>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" :class="card.tone">
                        <AppIcon :name="card.icon" class="h-4 w-4" />
                    </span>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-semibold text-slate-900">User registrations</h4>
                <p class="mt-1 text-xs text-slate-500">New accounts created each month.</p>
                <div class="mt-4">
                    <BaseChart type="line" :data="registrationChart" />
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-semibold text-slate-900">Evaluation activity</h4>
                <p class="mt-1 text-xs text-slate-500">IPCR packages submitted, approved, and returned per month.</p>
                <div class="mt-4">
                    <BaseChart type="bar" :data="evaluationChart" :options="evaluationOptions" />
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-semibold text-slate-900">Submission pipeline</h4>
                <p class="mt-1 text-xs text-slate-500">Current IPCR packages by workflow status.</p>
                <div class="mt-4">
                    <BaseChart type="doughnut" :data="statusChart" :options="doughnutOptions" />
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h4 class="text-sm font-semibold text-slate-900">Approved rating distribution</h4>
                <p class="mt-1 text-xs text-slate-500">Final average ratings for approved IPCR packages.</p>
                <div class="mt-4">
                    <BaseChart type="bar" :data="ratingChart" :options="{ plugins: { legend: { display: false } } }" />
                </div>
            </section>
        </div>
    </div>
</template>
