<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    submission: Object,
});

const isApproved = computed(() => props.submission?.status === 'approved');
const isEditable = computed(() => props.submission?.status === 'in_review');

const sortedCommitments = computed(() =>
    [...(props.submission?.commitments || [])].sort((a, b) => a.id - b.id),
);

const reviewForm = useForm({
    action: 'approve',
    supervisor_feedback: props.submission?.supervisor_feedback ?? '',
    commitments: sortedCommitments.value.map((c) => ({
        id: c.id,
        rating_efficiency: c.rating_efficiency ?? 3,
        rating_timeliness: c.rating_timeliness ?? 3,
        rating_q3_target: c.rating_q3_target ?? '',
        rating_q3_actual: c.rating_q3_actual ?? '',
        rating_q4_target: c.rating_q4_target ?? '',
        rating_q4_actual: c.rating_q4_actual ?? '',
        remarks: c.remarks ?? '',
    })),
});

function accomplishmentRatio(q3Target, q3Actual, q4Target, q4Actual) {
    const t3 = Number(q3Target || 0);
    const a3 = Number(q3Actual || 0);
    const t4 = Number(q4Target || 0);
    const a4 = Number(q4Actual || 0);
    const targetTotal = Math.max(0, t3 + t4);
    const actualTotal = Math.max(0, a3 + a4);
    const percent = targetTotal > 0 ? actualTotal / targetTotal : 0;
    return { targetTotal, actualTotal, percent };
}

function qualityFromRatio(n) {
    if (n >= 1.3) return 5;
    if (n >= 1.15) return 4;
    if (n >= 1.0) return 3;
    if (n >= 0.51) return 2;
    return 1;
}

function rowPreview(commitment, row) {
    const ratio = accomplishmentRatio(
        row.rating_q3_target,
        row.rating_q3_actual,
        row.rating_q4_target,
        row.rating_q4_actual,
    );
    const q = qualityFromRatio(ratio.percent);
    const e = Number(row.rating_efficiency);
    const t = Number(row.rating_timeliness);
    if (!Number.isFinite(e) || !Number.isFinite(t)) {
        return { ...ratio, q, avg: null, weighted: null };
    }
    const avg = (q + e + t) / 3;
    const w = Number(commitment.weight) / 100;
    return { ...ratio, q, avg, weighted: avg * w };
}

function sumWeightedPreview() {
    let sum = 0;
    for (const c of sortedCommitments.value) {
        const row = reviewForm.commitments.find((r) => r.id === c.id);
        if (!row) return null;
        const p = rowPreview(c, row);
        if (p.weighted == null) return null;
        sum += p.weighted;
    }
    return sum;
}

function indicatorLines(c) {
    const desc = (c?.description ?? '').trim();
    if (!desc) return [c?.title ?? ''];
    const lines = desc.split(/\r\n|\r|\n/).map((l) => l.trim()).filter(Boolean);
    return lines.length ? lines : [c?.title ?? ''];
}

function ratingRow(id) {
    return reviewForm.commitments.find((r) => r.id === id);
}

function submitReview() {
    reviewForm.transform((data) => {
        if (data.action !== 'approve') {
            return {
                action: data.action,
                supervisor_feedback: data.supervisor_feedback,
            };
        }
        return {
            action: data.action,
            supervisor_feedback: data.supervisor_feedback,
            commitments: data.commitments.map((r) => ({
                id: r.id,
                rating_efficiency: Number(r.rating_efficiency),
                rating_timeliness: Number(r.rating_timeliness),
                rating_q3_target: r.rating_q3_target === '' || r.rating_q3_target == null ? 0 : Number(r.rating_q3_target),
                rating_q3_actual: r.rating_q3_actual === '' || r.rating_q3_actual == null ? 0 : Number(r.rating_q3_actual),
                rating_q4_target: r.rating_q4_target === '' || r.rating_q4_target == null ? 0 : Number(r.rating_q4_target),
                rating_q4_actual: r.rating_q4_actual === '' || r.rating_q4_actual == null ? 0 : Number(r.rating_q4_actual),
                remarks: r.remarks ?? '',
            })),
        };
    }).patch(route('supervisor.submissions.update', props.submission.id), {
        preserveScroll: true,
    });
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

function badge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        pending: 'bg-amber-50 text-amber-900 ring-amber-100',
        returned: 'bg-rose-50 text-rose-900 ring-rose-100',
    };
    return map[status] ?? 'bg-slate-50 text-slate-700 ring-slate-100';
}
</script>

<template>
    <Head :title="`Review · ${submission.employee.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">IPCR Submission</p>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ submission.employee.name }} — {{ periodLabel(submission) }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        Submitted {{ formatWhen(submission.submitted_at) }}
                        <span class="ml-2 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase ring-1" :class="badge(submission.status)">
                            {{ submission.status.replace('_', ' ') }}
                        </span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('dashboard')" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                        ← Back to dashboard
                    </Link>
                    <a
                        v-if="isApproved"
                        :href="route('supervisor.submissions.export', submission.id)"
                        class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                    >
                        Export Excel
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold text-slate-800">IPCR Form 1 — Evaluation</p>
                    <p class="mt-1 text-xs text-slate-500">
                        <template v-if="isEditable">
                            Fill in Q3 and Q4 <strong>Target</strong> / <strong>Actual</strong> per indicator. Quality is auto-computed from the
                            accomplishment ratio; supply Efficiency (E) and Timeliness (T). Average = (Q + E + T) ÷ 3.
                        </template>
                        <template v-else-if="isApproved">
                            This submission has been approved. Ratings shown below are read-only.
                        </template>
                        <template v-else>
                            This submission is {{ submission.status.replace('_', ' ') }}. No ratings can be edited.
                        </template>
                    </p>

                    <div class="mt-2 overflow-x-auto rounded-lg border border-slate-300">
                        <table class="min-w-full border-collapse text-[11px]">
                            <thead class="bg-slate-100 text-center font-semibold uppercase tracking-wide text-slate-700">
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
                                    <th class="border border-slate-300 px-2 py-1" rowspan="2">Average</th>
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
                                    <tr
                                        v-if="sortedCommitments.some((c) => c.function_type === group)"
                                        :class="group === 'core' ? 'bg-blue-50' : 'bg-amber-50'"
                                    >
                                        <td
                                            colspan="17"
                                            class="border border-slate-300 px-2 py-1 text-center text-[11px] font-bold uppercase tracking-wide"
                                            :class="group === 'core' ? 'text-blue-900' : 'text-amber-900'"
                                        >
                                            {{ group === 'core' ? 'Core Functions (60%)' : 'Strategic Functions (40%)' }}
                                        </td>
                                    </tr>
                                    <template
                                        v-for="c in sortedCommitments.filter((c) => c.function_type === group)"
                                        :key="c.id"
                                    >
                                        <tr
                                            v-for="(line, li) in indicatorLines(c)"
                                            :key="c.id + '-' + li"
                                            class="align-top"
                                        >
                                            <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 font-semibold text-slate-800">
                                                {{ c.title }}
                                            </td>
                                            <td class="border border-slate-300 px-2 py-1 text-slate-700">{{ line }}</td>
                                            <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ Number(c.weight) }}%</td>
                                            <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.annual_office_target ?? '—' }}</td>
                                            <td v-if="li === 0" :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center">{{ c.individual_annual_targets ?? '—' }}</td>
                                            <template v-if="li === 0 && ratingRow(c.id)">
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable" v-model="ratingRow(c.id).rating_q3_target" type="number" step="any" class="w-20 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ ratingRow(c.id).rating_q3_target ?? '—' }}</span>
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable" v-model="ratingRow(c.id).rating_q3_actual" type="number" step="any" class="w-20 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ ratingRow(c.id).rating_q3_actual ?? '—' }}</span>
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable" v-model="ratingRow(c.id).rating_q4_target" type="number" step="any" class="w-20 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ ratingRow(c.id).rating_q4_target ?? '—' }}</span>
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable" v-model="ratingRow(c.id).rating_q4_actual" type="number" step="any" class="w-20 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ ratingRow(c.id).rating_q4_actual ?? '—' }}</span>
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ rowPreview(c, ratingRow(c.id)).targetTotal.toFixed(0) }}
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ rowPreview(c, ratingRow(c.id)).actualTotal.toFixed(0) }}
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ (rowPreview(c, ratingRow(c.id)).percent * 100).toFixed(0) }}%
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center text-slate-700">
                                                    {{ rowPreview(c, ratingRow(c.id)).q }}
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable" v-model="ratingRow(c.id).rating_efficiency" type="number" min="1" max="5" class="w-14 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ ratingRow(c.id).rating_efficiency ?? '—' }}</span>
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable" v-model="ratingRow(c.id).rating_timeliness" type="number" min="1" max="5" class="w-14 text-xs" />
                                                    <span v-else class="block text-center text-slate-700">{{ ratingRow(c.id).rating_timeliness ?? '—' }}</span>
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-2 py-1 text-center font-semibold text-slate-800">
                                                    {{ rowPreview(c, ratingRow(c.id)).avg != null ? rowPreview(c, ratingRow(c.id)).avg.toFixed(2) : '—' }}
                                                </td>
                                                <td :rowspan="indicatorLines(c).length" class="border border-slate-300 px-1 py-1">
                                                    <TextInput v-if="isEditable" v-model="ratingRow(c.id).remarks" type="text" class="w-32 text-xs" />
                                                    <span v-else class="block text-slate-700">{{ ratingRow(c.id).remarks || '—' }}</span>
                                                </td>
                                            </template>
                                        </tr>
                                        <tr v-if="c.accomplishments?.length" class="bg-slate-50/80">
                                            <td colspan="17" class="border border-slate-300 px-2 py-1 text-[11px] text-slate-600">
                                                <span class="font-semibold text-slate-700">Evidence:</span>
                                                <span v-for="(ev, i) in c.accomplishments" :key="ev.id" class="ml-1">
                                                    <span class="font-medium text-slate-800">{{ ev.title }}</span>
                                                    <span v-if="ev.description"> — {{ ev.description }}</span>
                                                    <a
                                                        v-if="ev.file_url"
                                                        :href="ev.file_url"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="ml-1 font-semibold text-blue-700 hover:underline"
                                                    >
                                                        [file]
                                                    </a>
                                                    <span v-if="i < c.accomplishments.length - 1">;</span>
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </template>
                                <tr class="bg-slate-100 font-semibold">
                                    <td colspan="2" class="border border-slate-300 px-2 py-1 text-right">TOTAL</td>
                                    <td class="border border-slate-300 px-2 py-1 text-center">
                                        {{ sortedCommitments.reduce((a, c) => a + Number(c.weight || 0), 0).toFixed(0) }}%
                                    </td>
                                    <td colspan="11" class="border border-slate-300"></td>
                                    <td class="border border-slate-300 px-2 py-1 text-center text-amber-800">
                                        {{ isApproved && submission.overall_rating != null
                                            ? Number(submission.overall_rating).toFixed(2)
                                            : (sumWeightedPreview() != null ? sumWeightedPreview().toFixed(2) : '—') }}
                                    </td>
                                    <td class="border border-slate-300"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <InputError class="mt-2" :message="reviewForm.errors.commitments" />
                </div>

                <div v-if="isEditable" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm font-semibold text-slate-800">Decision</p>
                    <p class="mt-1 text-xs text-slate-500">Approve after completing each row, or return with actionable comments (min. 20 characters).</p>

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

                    <div class="mt-4">
                        <InputLabel :value="reviewForm.action === 'return' ? 'Comments for employee (required when returning)' : 'Optional comments'" />
                        <textarea
                            v-model="reviewForm.supervisor_feedback"
                            rows="4"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            :placeholder="reviewForm.action === 'return'
                                ? 'Explain what to fix (targets, evidence, weights, or narrative). Minimum 20 characters.'
                                : 'Optional recognition or follow-up items.'"
                        />
                        <InputError class="mt-1" :message="reviewForm.errors.supervisor_feedback" />
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <Link :href="route('dashboard')">
                            <SecondaryButton type="button">Cancel</SecondaryButton>
                        </Link>
                        <PrimaryButton :disabled="reviewForm.processing" @click="submitReview">Submit decision</PrimaryButton>
                    </div>
                </div>

                <div v-else-if="submission.supervisor_feedback" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm text-sm">
                    <p class="font-semibold text-slate-800">Supervisor feedback</p>
                    <p class="mt-1 whitespace-pre-line text-slate-600">{{ submission.supervisor_feedback }}</p>
                </div>

                <div class="grid gap-3 text-[11px] md:grid-cols-2">
                    <div class="rounded border border-slate-200 bg-slate-50 p-3">
                        <p class="font-semibold text-slate-700">Rating scale</p>
                        <ul class="mt-1 list-disc pl-4 text-slate-600">
                            <li>5 — Outstanding (≥130%)</li>
                            <li>4 — Very Satisfactory (115–129%)</li>
                            <li>3 — Satisfactory (100–114%)</li>
                            <li>2 — Unsatisfactory (51–99%)</li>
                            <li>1 — Poor (≤50%)</li>
                        </ul>
                    </div>
                    <div class="rounded border border-slate-200 bg-slate-50 p-3">
                        <p class="font-semibold text-slate-700">Legend</p>
                        <p class="mt-1 text-slate-600">
                            Q = Quality (auto), E = Efficiency, T = Timeliness. Average = (Q + E + T) ÷ 3.
                            Package overall = Σ (Average × Weight%).
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
