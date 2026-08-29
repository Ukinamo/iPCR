<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import IpcrPreviewLink from '@/Components/IpcrPreviewLink.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { flattenFormEntries } from '@/utils/ipcrFormEntries';
import { formatDecimal, formatWholeNumber } from '@/utils/numberFormat';
import { includedQuartersOf, isRateableRow } from '@/utils/ipcrRating';
import { statusLabel } from '@/utils/statusLabels';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    stats: Object,
    packages: {
        type: Array,
        default: () => [],
    },
    availableTemplates: {
        type: Array,
        default: () => [],
    },
    approvedHistory: Array,
    period: Object,
    formWeightSummary: Object,
    reminder: String,
});

const tab = ref('commitments');
const creating = ref(false);
const selectedTemplateId = ref(props.availableTemplates[0]?.id ?? '');
let itemSeq = 0;

function newItem() {
    itemSeq += 1;
    return {
        _uid: itemSeq,
        description: '',
        weight: null,
        annual_office_target: '',
        individual_annual_targets: '',
    };
}

const createForm = useForm({
    title: '',
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
    entries: [
        { enabled: true, function_type: 'core', title: '', _uid: 'e-core-new', items: [newItem()] },
        { enabled: true, function_type: 'strategic', title: '', _uid: 'e-strategic-new', items: [newItem()] },
    ],
});

const copyForm = useForm({
    template_id: props.availableTemplates[0]?.id ?? '',
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
});

function flattenedEntries() {
    return flattenFormEntries(createForm.entries);
}

function useTemplate() {
    copyForm.template_id = selectedTemplateId.value;
    copyForm.post(route('employee.packages.from-template'), { preserveScroll: true });
}

function saveNewForm() {
    createForm.transform((data) => ({
        title: data.title,
        evaluation_year: data.evaluation_year,
        evaluation_quarter: data.evaluation_quarter,
        entries: flattenedEntries(),
    })).post(route('employee.packages.store'), {
        preserveScroll: true,
        onFinish: () => createForm.transform((data) => data),
    });
}

function deletePackage(id) {
    if (!confirm('Delete this IPCR form? This cannot be undone.')) {
        return;
    }

    router.delete(route('employee.packages.destroy', id), { preserveScroll: true });
}

const statCards = [
    { key: 'activeCommitments', label: 'Active forms', icon: 'clipboard', tone: 'bg-sky-100 text-sky-700' },
    { key: 'pendingReview', label: 'Pending Review', icon: 'clock', tone: 'bg-amber-100 text-amber-700' },
    { key: 'approvalRate', label: 'Approval Rate', icon: 'check-badge', tone: 'bg-emerald-100 text-emerald-700', suffix: '%' },
];

const tabs = [
    { id: 'commitments', label: 'My IPCR forms', icon: 'clipboard' },
    { id: 'history', label: 'Commitment history', icon: 'star' },
];

function statusBadge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        draft: 'bg-slate-50 text-slate-700 ring-slate-100',
        returned: 'bg-amber-50 text-amber-900 ring-amber-100',
        pending: 'bg-amber-50 text-amber-900 ring-amber-100',
    };
    return map[status] ?? map.draft;
}

function historyTotals(submission) {
    const rows = submission?.commitments || [];
    const weight = rows.reduce((sum, c) => sum + Number(c.weight || 0), 0);
    const rated = rows.filter((c) => isRateableRow(c));
    const average = rated.reduce((sum, c) => sum + Number(c.rating_average || 0), 0);
    const remarks = rated.reduce((sum, c) => {
        if (c.rating_weighted != null) {
            return sum + Number(c.rating_weighted);
        }
        if (c.rating_average != null) {
            return sum + Number(c.rating_average);
        }
        return sum;
    }, 0);
    return {
        weight: weight.toFixed(2),
        average: rated.some((c) => c.rating_average != null) ? formatDecimal(average, 2) : '—',
        weighted: rated.some((c) => c.rating_weighted != null || c.rating_average != null) ? formatDecimal(remarks, 2) : '—',
    };
}

function indicatorLines(c) {
    const desc = (c?.description ?? '').trim();
    if (!desc) return [''];
    const lines = desc.split(/\r\n|\r|\n/).map(l => l.trim()).filter(Boolean);
    return lines.length ? lines : [''];
}

function formatAverage(c) {
    if (!isRateableRow(c)) {
        return '—';
    }
    return formatDecimal(c?.rating_average, 2);
}

function formatWeighted(c) {
    if (!isRateableRow(c)) {
        return '—';
    }
    if (c?.rating_weighted != null) {
        return Number(c.rating_weighted).toFixed(2);
    }
    if (c?.rating_average != null) {
        if (c?.weight != null) {
            return (Number(c.rating_average) * (Number(c.weight) / 100)).toFixed(2);
        }
        return Number(c.rating_average).toFixed(2);
    }
    return '—';
}

function historyQuarters(s) {
    return includedQuartersOf(s);
}

function historyAccompColspan(s) {
    return historyQuarters(s).length * 2 + 3;
}

function historyTableColspan(s) {
    return 5 + historyAccompColspan(s) + 5;
}

function historyPeriodLabel(s) {
    return historyQuarters(s).map((q) => `Q${q}`).join(', ') + ` ${s.evaluation_year}`;
}
</script>

<template>
    <Head title="Employee Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                    <AppIcon name="identification" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Employee Dashboard</h2>
                    <p class="text-sm text-gray-500">Choose an administrator form or create your own. You can submit more than one package.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div
                        v-for="card in statCards"
                        :key="card.key"
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-600">{{ card.label }}</p>
                                <p class="mt-3 text-3xl font-bold text-slate-900">
                                    {{ stats[card.key] }}{{ card.suffix ?? '' }}
                                </p>
                            </div>
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg" :class="card.tone">
                                <AppIcon :name="card.icon" class="h-5 w-5" />
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 overflow-x-auto rounded-lg bg-indigo-50/60 p-1 text-sm font-semibold text-slate-700 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
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

                <div v-show="tab === 'commitments'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-900">Use an administrator form</h3>
                        <p class="mt-1 text-sm text-slate-600">This copies the form. Editing your copy will not change the original.</p>
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <select v-model="selectedTemplateId" class="block w-full rounded-md border-gray-300 text-sm shadow-sm">
                                <option v-for="t in availableTemplates" :key="t.id" :value="t.id">{{ t.title }} · {{ t.period_label }}</option>
                            </select>
                            <PrimaryButton type="button" :disabled="!selectedTemplateId || copyForm.processing" @click="useTemplate">Use this form</PrimaryButton>
                        </div>
                        <p v-if="!availableTemplates.length" class="mt-2 text-sm text-slate-500">No administrator forms yet.</p>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-semibold text-slate-900">Create my own form</h3>
                            <button type="button" class="text-sm font-semibold text-indigo-700" @click="creating = !creating">{{ creating ? 'Hide' : 'Create' }}</button>
                        </div>
                        <div v-if="creating" class="mt-4 space-y-3">
                            <input v-model="createForm.title" type="text" placeholder="Form title" class="block w-full rounded-md border-gray-300 text-sm shadow-sm" />
                            <CommitmentPackageForm
                                v-model:entries="createForm.entries"
                                :weight-summary="formWeightSummary"
                                :errors="createForm.errors"
                                :processing="createForm.processing"
                                intro=""
                                submit-label="Create form"
                                @submit="saveNewForm"
                                @cancel="creating = false"
                            />
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Form</th>
                                    <th class="px-4 py-3">Period</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-if="!packages.length">
                                    <td colspan="4" class="px-4 py-6 text-center text-slate-500">No forms yet. Choose an administrator form or create your own.</td>
                                </tr>
                                <tr v-for="pkg in packages" :key="pkg.id">
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-slate-900">{{ pkg.title || 'IPCR form' }}</p>
                                        <p class="text-xs text-slate-500">{{ pkg.commitments?.length || 0 }} row(s)</p>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ pkg.period_label }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="statusBadge(pkg.status)">{{ statusLabel(pkg.status) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex flex-wrap justify-end gap-1.5">
                                            <Link
                                                v-if="pkg.can_edit"
                                                :href="route('employee.packages.edit', pkg.id)"
                                                title="Edit form"
                                                aria-label="Edit form"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
                                            >
                                                <AppIcon name="pencil" class="h-4 w-4" />
                                            </Link>
                                            <Link
                                                v-if="pkg.open_commitment_id"
                                                :href="route('employee.commitments.show', pkg.open_commitment_id)"
                                                title="Open"
                                                aria-label="Open"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-indigo-700 shadow-sm hover:bg-slate-50"
                                            >
                                                <AppIcon name="eye" class="h-4 w-4" />
                                            </Link>
                                            <button
                                                v-if="pkg.can_delete"
                                                type="button"
                                                title="Delete"
                                                aria-label="Delete"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-rose-200 bg-white text-rose-700 shadow-sm hover:bg-rose-50"
                                                @click="deletePackage(pkg.id)"
                                            >
                                                <AppIcon name="trash" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-show="tab === 'history'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                                    <AppIcon name="star" class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900">Commitment history (approved)</h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        View your approved periods and the ratings per commitment.
                                    </p>
                                </div>
                            </div>
                            <a
                                :href="route('employee.ratings.history.export')"
                                class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                <AppIcon name="arrow-down-tray" class="h-4 w-4" />
                                Export to Excel
                            </a>
                        </div>
                    </div>

                    <div v-if="!approvedHistory?.length" class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                        <AppIcon name="star" class="mx-auto h-8 w-8 text-slate-300" />
                        <p class="mt-3">No approved commitment history yet.</p>
                    </div>

                    <div v-for="s in approvedHistory" :key="s.id" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3">
                            <div>
                                <p class="font-semibold text-slate-900">{{ historyPeriodLabel(s) }}</p>
                                <p class="text-xs text-slate-500">
                                    Supervisor: {{ s.supervisor?.name ?? '—' }}
                                    <span v-if="s.reviewed_at"> · Reviewed {{ new Date(s.reviewed_at).toLocaleDateString() }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <p class="text-sm font-semibold text-amber-800">Overall: {{ s.overall_rating ?? '—' }}</p>
                                <IpcrPreviewLink
                                    :submission-id="s.id"
                                    mode="employee-submission"
                                />
                            </div>
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
                                        <th class="border border-slate-300 px-2 py-1" :colspan="historyAccompColspan(s)">Accomplishments</th>
                                        <th class="border border-slate-300 px-2 py-1" colspan="4">Rating</th>
                                        <th class="border border-slate-300 px-2 py-1" rowspan="3">Remarks</th>
                                    </tr>
                                    <tr>
                                        <th
                                            v-for="q in historyQuarters(s)"
                                            :key="'h-' + s.id + '-' + q"
                                            class="border border-slate-300 px-2 py-1"
                                            colspan="2"
                                        >
                                            Q{{ q }}
                                        </th>
                                        <th class="border border-slate-300 px-2 py-1" colspan="3">Total</th>
                                        <th class="border border-slate-300 px-2 py-1" rowspan="2">Q</th>
                                        <th class="border border-slate-300 px-2 py-1" rowspan="2">E</th>
                                        <th class="border border-slate-300 px-2 py-1" rowspan="2">T</th>
                                        <th class="border border-slate-300 px-2 py-1" rowspan="2">Avg</th>
                                    </tr>
                                    <tr>
                                        <template v-for="q in historyQuarters(s)" :key="'ha-' + s.id + '-' + q">
                                            <th class="border border-slate-300 px-2 py-1">Target</th>
                                            <th class="border border-slate-300 px-2 py-1">Actual</th>
                                        </template>
                                        <th class="border border-slate-300 px-2 py-1">Target</th>
                                        <th class="border border-slate-300 px-2 py-1">Actual</th>
                                        <th class="border border-slate-300 px-2 py-1">%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="group in ['core', 'strategic']" :key="group">
                                        <tr v-if="(s.commitments || []).some(c => c.function_type === group)" class="bg-slate-100 font-semibold">
                                            <td class="border border-slate-300 px-2 py-1 uppercase text-slate-700" :colspan="historyTableColspan(s)">
                                                {{ group === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                                ({{ (s.commitments || []).filter(c => c.function_type === group).reduce((a, c) => a + Number(c.weight || 0), 0) }}%)
                                            </td>
                                        </tr>
                                        <template v-for="c in (s.commitments || []).filter(c => c.function_type === group)" :key="c.id">
                                            <tr
                                                v-for="(line, li) in indicatorLines(c)"
                                                :key="c.id + '-' + li"
                                                class="align-top"
                                            >
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 font-semibold text-slate-800">
                                                    {{ c.title }}
                                                </td>
                                                <td class="border border-slate-300 px-2 py-1 text-slate-700">
                                                    {{ line }}
                                                </td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.weight != null ? Number(c.weight) + '%' : '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.annual_office_target ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.individual_annual_targets ?? '—' }}</td>
                                                <template v-if="li === 0">
                                                    <template v-for="q in historyQuarters(s)" :key="'q-' + c.id + '-' + q">
                                                        <td
                                                            :rowspan="indicatorLines(c).length"
                                                            class="border border-slate-300 px-2 py-1 text-center"
                                                        >
                                                            {{ formatWholeNumber(c[`rating_q${q}_target`]) }}
                                                        </td>
                                                        <td
                                                            :rowspan="indicatorLines(c).length"
                                                            class="border border-slate-300 px-2 py-1 text-center"
                                                        >
                                                            {{ formatWholeNumber(c[`rating_q${q}_actual`]) }}
                                                        </td>
                                                    </template>
                                                </template>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_target_total) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_actual_total) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_percent != null ? (Number(c.rating_percent) * 100).toFixed(0) + '%' : '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_quality ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_efficiency ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_timeliness ?? '—' }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatAverage(c) }}</td>
                                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWeighted(c) }}</td>
                                            </tr>
                                        </template>
                                    </template>
                                    <tr class="bg-slate-100 font-semibold">
                                        <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">TOTAL</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ historyTotals(s).weight }}%</td>
                                        <td class="border border-slate-300 px-2 py-1" :colspan="2 + historyAccompColspan(s) + 3"></td>
                                        <td class="border border-slate-300 px-2 py-1 text-center">{{ historyTotals(s).average }}</td>
                                        <td class="border border-slate-300 px-2 py-1 text-center text-amber-800">{{ historyTotals(s).weighted }}</td>
                                    </tr>
                                    <tr class="bg-amber-50 font-semibold text-amber-900">
                                        <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">FINAL AVERAGE RATING</td>
                                        <td class="border border-slate-300 px-2 py-1" :colspan="historyTableColspan(s) - 2">
                                            {{ s.overall_rating != null ? Number(s.overall_rating).toFixed(2) : '—' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 rounded-lg border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                        <AppIcon name="exclamation-triangle" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold">Important Reminder</p>
                        <p class="mt-1 text-sky-900/80">{{ reminder }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
