<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { statusLabel } from '@/utils/statusLabels';

const props = defineProps({
    stats: Object,
    submissions: Array,
    approvedSubmissions: {
        type: Array,
        default: () => [],
    },
    programEvaluationForms: {
        type: Array,
        default: () => [],
    },
    stoMonitoringForms: {
        type: Array,
        default: () => [],
    },
});

const tab = ref('history');

onMounted(() => {
    const requestedTab = new URLSearchParams(window.location.search).get('tab');
    if (['history', 'programs', 'team'].includes(requestedTab)) {
        tab.value = requestedTab;
    }
});

const statCards = [
    { key: 'approved', label: 'Approved', icon: 'check-badge', tone: 'bg-emerald-100 text-emerald-700' },
    { key: 'pendingReview', label: 'With administrator', icon: 'clipboard', tone: 'bg-amber-100 text-amber-700' },
    { key: 'averageRating', label: 'Average overall', icon: 'star', tone: 'bg-violet-100 text-violet-700' },
];

const tabs = [
    { id: 'history', label: 'Approved', icon: 'check-badge' },
    { id: 'programs', label: 'Registers', icon: 'document-chart-bar' },
    { id: 'team', label: 'Submissions', icon: 'clipboard' },
];

const activeSubmissions = computed(() => props.submissions || []);

function evidenceCount(s) {
    return (s.commitments || []).reduce((sum, c) => sum + (c.accomplishments?.length || 0), 0);
}

function badge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        pending: 'bg-amber-50 text-amber-900 ring-amber-100',
        draft: 'bg-amber-50 text-amber-900 ring-amber-100',
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
    const qs = (s.included_quarters || []).map((q) => Number(q)).filter((q) => q >= 1 && q <= 4);
    const list = qs.length ? [...new Set(qs)].sort((a, b) => a - b) : [s.evaluation_quarter].filter(Boolean);
    if (!list.length) {
        return `Q${s.evaluation_quarter} ${s.evaluation_year}`;
    }
    return list.map((q) => `Q${q}`).join(', ') + ` ${s.evaluation_year}`;
}

function destroyProgramForm(id) {
    if (!confirm('Delete this programs evaluated form?')) {
        return;
    }
    router.delete(route('supervisor.program-evaluations.destroy', id), { preserveScroll: true });
}

function destroyStoForm(id) {
    if (!confirm('Delete this STO monitoring report?')) {
        return;
    }
    router.delete(route('supervisor.sto-monitoring.destroy', id), { preserveScroll: true });
}

function stoReportType(form) {
    const value = form?.report_type;
    if (typeof value === 'string') {
        return value;
    }
    return value?.value || '';
}

function stoFormsOf(type) {
    return (props.stoMonitoringForms || []).filter((form) => stoReportType(form) === type);
}

function displayStatus(status) {
    if (status === 'in_review') {
        return 'Awaiting administrator';
    }

    return statusLabel(status);
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
                    Follow approved IPCR packages, then create Programs Evaluated and STO monitoring registers.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
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

                <div v-show="tab === 'team'" class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                            <AppIcon name="clipboard" class="h-5 w-5" />
                        </span>
                        <h3 class="text-lg font-semibold text-slate-900">Employee submissions</h3>
                    </div>

                    <div v-if="!activeSubmissions.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-sm text-slate-500">
                        <AppIcon name="document-chart-bar" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">
                            No active submissions right now. Check
                            <button type="button" class="font-semibold text-blue-700 hover:underline" @click="tab = 'history'">Rating history</button>
                            for past approvals.
                        </p>
                    </div>

                    <div
                        v-for="s in activeSubmissions"
                        :key="s.id"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-800">
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
                            <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1" :class="badge(s.status)">
                                {{ displayStatus(s.status) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-show="tab === 'history'" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Approved IPCR packages</h3>
                            <p class="text-xs text-slate-500">Every approved package from your team.</p>
                        </div>
                    </div>

                    <div v-if="!approvedSubmissions.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500">
                        <AppIcon name="star" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">No approved submissions yet.</p>
                    </div>

                    <table v-else class="w-full table-fixed divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white text-sm shadow-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">Employee</th>
                                <th class="w-[18%] px-3 py-2 text-left">Period</th>
                                <th class="w-[18%] px-3 py-2 text-left">Approved</th>
                                <th class="w-[12%] px-3 py-2 text-center">Overall</th>
                                <th class="w-[14%] px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="s in approvedSubmissions" :key="s.id" class="align-top">
                                <td class="px-3 py-2">
                                    <p class="break-words font-semibold text-slate-900">{{ s.employee.name }}</p>
                                    <p class="break-words text-[11px] text-slate-500">{{ s.employee.email }}</p>
                                </td>
                                <td class="px-3 py-2 text-slate-700">{{ periodLabel(s) }}</td>
                                <td class="px-3 py-2 text-xs text-slate-600">{{ formatWhen(s.reviewed_at) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-100">
                                        {{ s.overall_rating != null ? Number(s.overall_rating).toFixed(2) : '—' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <Link
                                        :href="route('supervisor.submissions.preview', s.id)"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                    >
                                        <AppIcon name="eye" class="h-3.5 w-3.5" />
                                        Preview
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-show="tab === 'programs'" class="space-y-8">
                    <section class="space-y-3">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">Programs evaluated</h3>
                                <p class="text-xs text-slate-500">Programs Monitored / Evaluated / Inspected.</p>
                            </div>
                            <Link
                                :href="route('supervisor.program-evaluations.create')"
                                class="inline-flex items-center justify-center gap-1.5 rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-800"
                            >
                                <AppIcon name="plus" class="h-4 w-4" />
                                New form
                            </Link>
                        </div>
                        <div v-if="!programEvaluationForms.length" class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-xs text-slate-500">
                            No programs evaluated forms yet.
                        </div>
                        <table v-else class="w-full table-fixed divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white text-sm shadow-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">Title</th>
                                    <th class="w-[12%] px-3 py-2 text-left">Year</th>
                                    <th class="w-[14%] px-3 py-2 text-center">Rows</th>
                                    <th class="w-[16%] px-3 py-2 text-left">Status</th>
                                    <th class="w-[22%] px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="form in programEvaluationForms" :key="'pe-' + form.id">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-slate-900">{{ form.title }}</p>
                                        <p class="text-[11px] text-slate-500">{{ form.office_name }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ form.evaluation_year }}</td>
                                    <td class="px-3 py-2 text-center text-slate-700">{{ form.entries_count ?? 0 }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="badge(form.status)">{{ displayStatus(form.status) }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap justify-end gap-1.5">
                                            <Link
                                                :href="route('supervisor.program-evaluations.edit', form.id)"
                                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50"
                                            >
                                                {{ form.can_edit ? 'Edit' : 'View' }}
                                            </Link>
                                            <button v-if="form.can_edit" type="button" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100" @click="destroyProgramForm(form.id)">
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="space-y-3">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">REPORT ON STO: Monitoring of HEI with STUFAPs</h3>
                                <p class="text-xs text-slate-500">HEI, STUFAP program, grantees/borrowers, date monitored, remarks.</p>
                            </div>
                            <Link
                                :href="route('supervisor.sto-monitoring.create', { type: 'stufap' })"
                                class="inline-flex items-center justify-center gap-1.5 rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-800"
                            >
                                <AppIcon name="plus" class="h-4 w-4" />
                                New STUFAP report
                            </Link>
                        </div>
                        <div v-if="!stoFormsOf('stufap').length" class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-xs text-slate-500">
                            No STUFAP monitoring reports yet.
                        </div>
                        <table v-else class="w-full table-fixed divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white text-sm shadow-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">Title</th>
                                    <th class="w-[12%] px-3 py-2 text-left">Year</th>
                                    <th class="w-[14%] px-3 py-2 text-center">Rows</th>
                                    <th class="w-[16%] px-3 py-2 text-left">Status</th>
                                    <th class="w-[22%] px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="form in stoFormsOf('stufap')" :key="'stufap-' + form.id">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-slate-900">{{ form.title }}</p>
                                        <p class="text-[11px] text-slate-500">{{ form.office_name }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ form.evaluation_year }}</td>
                                    <td class="px-3 py-2 text-center text-slate-700">{{ form.entries_count ?? 0 }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="badge(form.status)">{{ displayStatus(form.status) }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap justify-end gap-1.5">
                                            <Link :href="route('supervisor.sto-monitoring.edit', form.id)" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">{{ form.can_edit ? 'Edit' : 'View' }}</Link>
                                            <button v-if="form.can_edit" type="button" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100" @click="destroyStoForm(form.id)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <section class="space-y-3">
                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">REPORT ON STO: Monitoring of Student Services</h3>
                                <p class="text-xs text-slate-500">HEI, type of student service, date monitored, remarks.</p>
                            </div>
                            <Link
                                :href="route('supervisor.sto-monitoring.create', { type: 'student_services' })"
                                class="inline-flex items-center justify-center gap-1.5 rounded-md bg-cyan-700 px-3 py-2 text-sm font-semibold text-white hover:bg-cyan-800"
                            >
                                <AppIcon name="plus" class="h-4 w-4" />
                                New student services report
                            </Link>
                        </div>
                        <div v-if="!stoFormsOf('student_services').length" class="rounded-xl border border-dashed border-slate-200 bg-white p-6 text-center text-xs text-slate-500">
                            No student services monitoring reports yet.
                        </div>
                        <table v-else class="w-full table-fixed divide-y divide-slate-200 rounded-xl border border-slate-200 bg-white text-sm shadow-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                <tr>
                                    <th class="px-3 py-2 text-left">Title</th>
                                    <th class="w-[12%] px-3 py-2 text-left">Year</th>
                                    <th class="w-[14%] px-3 py-2 text-center">Rows</th>
                                    <th class="w-[16%] px-3 py-2 text-left">Status</th>
                                    <th class="w-[22%] px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="form in stoFormsOf('student_services')" :key="'ss-' + form.id">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-slate-900">{{ form.title }}</p>
                                        <p class="text-[11px] text-slate-500">{{ form.office_name }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">{{ form.evaluation_year }}</td>
                                    <td class="px-3 py-2 text-center text-slate-700">{{ form.entries_count ?? 0 }}</td>
                                    <td class="px-3 py-2">
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1" :class="badge(form.status)">{{ displayStatus(form.status) }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="flex flex-wrap justify-end gap-1.5">
                                            <Link :href="route('supervisor.sto-monitoring.edit', form.id)" class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-800 hover:bg-slate-50">{{ form.can_edit ? 'Edit' : 'View' }}</Link>
                                            <button v-if="form.can_edit" type="button" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100" @click="destroyStoForm(form.id)">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
