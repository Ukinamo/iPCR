<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import IpcrPreviewLink from '@/Components/IpcrPreviewLink.vue';
import { formatDecimal } from '@/utils/numberFormat';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    submissions: {
        type: Array,
        default: () => [],
    },
    reviewMonths: {
        type: Array,
        default: () => [],
    },
    filterMonth: {
        type: [String, null],
        default: null,
    },
    showFilters: {
        type: Boolean,
        default: true,
    },
    viewRouteName: {
        type: String,
        default: 'admin.reports.submissions.show',
    },
    filterMode: {
        type: String,
        default: 'client',
    },
});

const localMonth = ref(props.filterMonth ?? '');
const searchQuery = ref('');

const monthOptions = computed(() => {
    if (props.reviewMonths?.length) {
        return props.reviewMonths;
    }

    const months = new Set();
    for (const submission of props.submissions) {
        const key = reviewMonthKey(submission);
        if (key) {
            months.add(key);
        }
    }

    return [...months].sort().reverse();
});

const filtered = computed(() => {
    let rows = props.submissions;

    if (props.filterMode === 'client' && localMonth.value) {
        rows = rows.filter((s) => reviewMonthKey(s) === localMonth.value);
    }

    const query = searchQuery.value.trim().toLowerCase();
    if (!query) {
        return rows;
    }

    return rows.filter((s) => matchesSearch(s, query));
});

function reviewMonthKey(submission) {
    if (!submission?.reviewed_at) {
        return null;
    }

    const date = new Date(submission.reviewed_at);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${date.getFullYear()}-${month}`;
}

function formatMonthLabel(key) {
    if (!key) {
        return '';
    }

    const [year, month] = key.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString(undefined, {
        month: 'long',
        year: 'numeric',
    });
}

function period(s) {
    return `Q${s.evaluation_quarter} ${s.evaluation_year}`;
}

function formatReviewed(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
    } catch {
        return iso;
    }
}

function matchesSearch(submission, query) {
    const haystack = [
        submission.employee?.name,
        submission.employee?.email,
        submission.supervisor?.name,
        period(submission),
        formatReviewed(submission.reviewed_at),
        formatMonthLabel(reviewMonthKey(submission)),
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

    return haystack.includes(query);
}

function applyMonthFilter() {
    if (props.filterMode === 'server') {
        router.get(route('admin.reports.ratings'), localMonth.value ? { month: localMonth.value } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }
}
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="showFilters && (monthOptions.length || submissions.length)"
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-700" for="report-month-filter">
                    <AppIcon name="calendar" class="h-4 w-4 text-slate-500" />
                    Review month
                </label>
                <select
                    id="report-month-filter"
                    v-model="localMonth"
                    class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:w-auto"
                    @change="applyMonthFilter"
                >
                    <option value="">All months</option>
                    <option v-for="month in monthOptions" :key="month" :value="month">
                        {{ formatMonthLabel(month) }}
                    </option>
                </select>
                <p class="text-xs text-slate-500">{{ filtered.length }} approved package(s)</p>
            </div>

            <div class="relative w-full sm:w-72 sm:shrink-0">
                <label class="sr-only" for="report-search">Search ratings</label>
                <AppIcon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    id="report-search"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search employee, supervisor, period..."
                    class="w-full rounded-md border-slate-300 py-2 pl-9 pr-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                />
            </div>
        </div>

        <div v-if="!filtered.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-600 shadow-sm">
            No approved IPCR ratings found for this filter.
        </div>

        <div v-else class="w-full rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full table-fixed divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="w-[16%] px-3 py-3">Period</th>
                        <th class="px-3 py-3">Employee</th>
                        <th class="hidden w-[18%] px-3 py-3 lg:table-cell">Supervisor</th>
                        <th class="w-[16%] px-3 py-3">Reviewed</th>
                        <th class="w-[12%] px-3 py-3 text-center">Overall</th>
                        <th class="w-[22%] px-3 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="s in filtered" :key="s.id" class="hover:bg-slate-50/80">
                        <td class="break-words px-3 py-3 font-medium text-slate-900">{{ period(s) }}</td>
                        <td class="px-3 py-3">
                            <p class="break-words font-medium text-slate-900">{{ s.employee?.name ?? '—' }}</p>
                            <p class="break-words text-xs text-slate-500">{{ s.employee?.email ?? '' }}</p>
                        </td>
                        <td class="hidden break-words px-3 py-3 text-slate-700 lg:table-cell">{{ s.supervisor?.name ?? '—' }}</td>
                        <td class="break-words px-3 py-3 text-slate-600">{{ formatReviewed(s.reviewed_at) }}</td>
                        <td class="px-3 py-3 text-center font-semibold text-amber-800">
                            {{ s.overall_rating != null ? formatDecimal(s.overall_rating, 2) : '—' }}
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-1.5">
                                <Link
                                    :href="route(viewRouteName, s.id)"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800 shadow-sm hover:bg-slate-50"
                                >
                                    <AppIcon name="eye" class="h-3.5 w-3.5" />
                                    View
                                </Link>
                                <IpcrPreviewLink mode="admin-submission" :submission-id="s.id" label="Preview" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
