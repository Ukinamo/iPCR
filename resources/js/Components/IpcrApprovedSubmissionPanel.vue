<script setup>
import IpcrPreviewLink from '@/Components/IpcrPreviewLink.vue';
import { formatDecimal, formatWholeNumber } from '@/utils/numberFormat';
import { isRateableRow } from '@/utils/ipcrRating';

defineProps({
    submission: {
        type: Object,
        required: true,
    },
    showExport: {
        type: Boolean,
        default: true,
    },
});

function period(s) {
    return `Q${s.evaluation_quarter} ${s.evaluation_year}`;
}

function submissionTotals(submission) {
    const rows = submission?.commitments || [];
    const weight = rows.reduce((sum, c) => sum + Number(c.weight || 0), 0);
    const rated = rows.filter((c) => isRateableRow(c));
    const average = rated.reduce((sum, c) => sum + Number(c.rating_average || 0), 0);
    const weighted = rated.reduce((sum, c) => sum + Number(c.rating_weighted || 0), 0);
    return {
        weight: weight.toFixed(0),
        average: rated.some((c) => c.rating_average != null) ? formatDecimal(average, 2) : '—',
        weighted: rated.some((c) => c.rating_weighted != null) ? formatDecimal(weighted, 2) : '—',
    };
}

function indicatorLines(c) {
    const desc = (c?.description ?? '').trim();
    if (!desc) return [''];
    const lines = desc.split(/\r\n|\r|\n/).map((l) => l.trim()).filter(Boolean);
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
        return formatDecimal(c.rating_weighted, 2);
    }
    if (c?.rating_average != null) {
        if (c?.weight != null) {
            return formatDecimal(Number(c.rating_average) * (Number(c.weight) / 100), 2);
        }
        return formatDecimal(c.rating_average, 2);
    }
    return '—';
}

function formatPercent(c) {
    if (c?.rating_percent == null) {
        return '—';
    }
    return `${(Number(c.rating_percent) * 100).toFixed(0)}%`;
}

function formatReviewed(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString(undefined, { dateStyle: 'medium' });
    } catch {
        return iso;
    }
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
            <div>
                <p class="font-semibold text-slate-900">{{ period(submission) }}</p>
                <p class="text-xs text-slate-500">
                    <span v-if="submission.employee?.name">{{ submission.employee.name }}</span>
                    <span v-if="submission.employee?.name && submission.supervisor?.name"> · </span>
                    Supervisor: {{ submission.supervisor?.name ?? '—' }}
                    · Reviewed {{ formatReviewed(submission.reviewed_at) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <p v-if="submission.overall_rating != null" class="text-lg font-bold text-amber-800">
                    Overall: {{ formatDecimal(submission.overall_rating, 2) }}
                </p>
                <IpcrPreviewLink v-if="showExport" mode="admin-submission" :submission-id="submission.id" />
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
                        <tr v-if="(submission.commitments || []).some(c => c.function_type === group)" class="bg-slate-100 font-semibold">
                            <td class="border border-slate-300 px-2 py-1 uppercase text-slate-700" colspan="17">
                                {{ group === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                ({{ (submission.commitments || []).filter(c => c.function_type === group).reduce((a, c) => a + Number(c.weight || 0), 0) }}%)
                            </td>
                        </tr>
                        <template v-for="c in (submission.commitments || []).filter(c => c.function_type === group)" :key="c.id">
                            <tr
                                v-for="(line, li) in indicatorLines(c)"
                                :key="c.id + '-' + li"
                                class="align-top"
                            >
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 font-semibold text-slate-800">
                                    {{ c.title }}
                                </td>
                                <td class="border border-slate-300 px-2 py-1 text-slate-700">{{ line }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.weight != null ? Number(c.weight).toFixed(0) + '%' : '—' }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.annual_office_target ?? '—' }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.individual_annual_targets ?? '—' }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q3_target) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q3_actual) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q4_target) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_q4_actual) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_target_total) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatWholeNumber(c.rating_actual_total) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatPercent(c) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_quality ?? '—' }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_efficiency ?? '—' }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.rating_timeliness ?? '—' }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ formatAverage(c) }}</td>
                                <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center text-amber-800">{{ formatWeighted(c) }}</td>
                            </tr>
                        </template>
                    </template>
                    <tr class="bg-slate-100 font-semibold">
                        <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">TOTAL</td>
                        <td class="border border-slate-300 px-2 py-1 text-center">{{ submissionTotals(submission).weight }}%</td>
                        <td class="border border-slate-300 px-2 py-1" colspan="2"></td>
                        <td class="border border-slate-300 px-2 py-1" colspan="7"></td>
                        <td class="border border-slate-300 px-2 py-1" colspan="3"></td>
                        <td class="border border-slate-300 px-2 py-1 text-center">{{ submissionTotals(submission).average }}</td>
                        <td class="border border-slate-300 px-2 py-1 text-center text-amber-800">{{ submissionTotals(submission).weighted }}</td>
                    </tr>
                    <tr class="bg-amber-50 font-semibold text-amber-900">
                        <td class="border border-slate-300 px-2 py-1 text-right" colspan="2">FINAL AVERAGE RATING</td>
                        <td class="border border-slate-300 px-2 py-1" colspan="15">
                            {{ submission.overall_rating != null ? formatDecimal(submission.overall_rating, 2) : '—' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
