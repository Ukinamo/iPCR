<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    submission: {
        type: Object,
        default: null,
    },
    supervisors: {
        type: Array,
        default: () => [],
    },
    pendingReviewTransfer: {
        type: Object,
        default: null,
    },
    incomingReviewTransfers: {
        type: Array,
        default: () => [],
    },
    outgoingReviewTransfers: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const transferForm = useForm({
    to_supervisor_id: '',
    reason: '',
});

const responseNotes = reactive({});

const canRequestTransfer = computed(() =>
    props.submission
    && ['in_review', 'returned'].includes(props.submission.status)
    && !props.pendingReviewTransfer,
);

function submitTransfer() {
    if (!props.submission) return;

    transferForm.post(route('supervisor.submissions.review-transfers.store', props.submission.id), {
        preserveScroll: true,
        onSuccess: () => transferForm.reset('to_supervisor_id', 'reason'),
    });
}

function cancelTransfer(id) {
    router.delete(route('supervisor.review-transfers.destroy', id), { preserveScroll: true });
}

function acceptTransfer(id) {
    router.patch(route('supervisor.review-transfers.accept', id), {
        response_notes: responseNotes[id] || null,
    }, { preserveScroll: true });
}

function rejectTransfer(id) {
    router.patch(route('supervisor.review-transfers.reject', id), {
        response_notes: responseNotes[id] || null,
    }, { preserveScroll: true });
}

function periodLabel(submission) {
    if (!submission) return '';
    return `Q${submission.evaluation_quarter} ${submission.evaluation_year}`;
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="incomingReviewTransfers.length" class="space-y-3">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <AppIcon name="arrow-top-right" class="h-3.5 w-3.5" />
                Incoming review transfers
            </p>
            <div
                v-for="req in incomingReviewTransfers"
                :key="'in-' + req.id"
                class="rounded-xl border border-sky-200 bg-sky-50/60 p-4"
            >
                <p class="font-semibold text-slate-900">
                    {{ req.submission?.employee?.name ?? 'Employee' }} · {{ periodLabel(req.submission) }}
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    From {{ req.requested_by?.name ?? req.from_supervisor?.name ?? 'supervisor' }}
                    · employee stays with their current supervisor
                </p>
                <p v-if="req.reason" class="mt-2 rounded-lg bg-white/70 p-2 text-sm text-slate-700">{{ req.reason }}</p>
                <div class="mt-3 grid gap-3 md:grid-cols-[1fr_auto_auto]">
                    <textarea
                        v-model="responseNotes[req.id]"
                        rows="2"
                        class="block w-full rounded-md border-slate-300 text-sm shadow-sm"
                        placeholder="Optional note to the requesting supervisor"
                    />
                    <PrimaryButton type="button" class="self-end !bg-emerald-600 hover:!bg-emerald-700" @click="acceptTransfer(req.id)">
                        <span class="inline-flex items-center gap-1.5">
                            <AppIcon name="check-badge" class="h-4 w-4" />
                            Accept review
                        </span>
                    </PrimaryButton>
                    <SecondaryButton type="button" class="self-end text-rose-700 ring-rose-200" @click="rejectTransfer(req.id)">
                        <span class="inline-flex items-center gap-1.5">
                            <AppIcon name="x-mark" class="h-4 w-4" />
                            Decline
                        </span>
                    </SecondaryButton>
                </div>
            </div>
        </div>

        <div v-if="outgoingReviewTransfers.length && !compact" class="space-y-3">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <AppIcon name="clock" class="h-3.5 w-3.5" />
                Outgoing review transfers
            </p>
            <div
                v-for="req in outgoingReviewTransfers"
                :key="'out-' + req.id"
                class="rounded-xl border border-amber-200 bg-amber-50/50 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ req.submission?.employee?.name ?? 'Employee' }} · {{ periodLabel(req.submission) }}
                        </p>
                        <p class="text-sm text-slate-600">Waiting for {{ req.to_supervisor?.name }} to accept</p>
                    </div>
                    <SecondaryButton type="button" @click="cancelTransfer(req.id)">
                        <span class="inline-flex items-center gap-1.5">
                            <AppIcon name="x-mark" class="h-3.5 w-3.5" />
                            Cancel
                        </span>
                    </SecondaryButton>
                </div>
            </div>
        </div>

        <div v-if="pendingReviewTransfer && submission" class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-amber-950">
            <div class="flex items-start gap-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                    <AppIcon name="clock" class="h-4 w-4" />
                </span>
                <div>
                    <p class="font-semibold">Review transfer pending</p>
                    <p class="mt-1">
                        Waiting for {{ pendingReviewTransfer.to_supervisor?.name }} to accept this package.
                        The employee will remain assigned to you.
                    </p>
                    <SecondaryButton type="button" class="mt-3" @click="cancelTransfer(pendingReviewTransfer.id)">
                        <span class="inline-flex items-center gap-1.5">
                            <AppIcon name="x-mark" class="h-3.5 w-3.5" />
                            Cancel transfer
                        </span>
                    </SecondaryButton>
                </div>
            </div>
        </div>

        <div v-if="canRequestTransfer && supervisors.length" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                    <AppIcon name="arrow-top-right" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-slate-900">Transfer review only</p>
                    <p class="mt-1 text-sm text-slate-600">
                        Send this package to another supervisor for review. The employee is not reassigned; no admin approval is required.
                    </p>
                    <form class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="submitTransfer">
                        <div>
                            <InputLabel for="review-transfer-supervisor" value="Transfer review to" />
                            <select
                                id="review-transfer-supervisor"
                                v-model="transferForm.to_supervisor_id"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                required
                            >
                                <option value="">Select supervisor</option>
                                <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }} — {{ s.email }}</option>
                            </select>
                            <p v-if="transferForm.errors.to_supervisor_id" class="mt-1 text-xs text-rose-600">{{ transferForm.errors.to_supervisor_id }}</p>
                        </div>
                        <div>
                            <InputLabel for="review-transfer-reason" value="Reason (optional)" />
                            <textarea
                                id="review-transfer-reason"
                                v-model="transferForm.reason"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                placeholder="Why should another supervisor review this package?"
                            />
                        </div>
                        <div class="md:col-span-2">
                            <PrimaryButton type="submit" :disabled="transferForm.processing">
                                <span class="inline-flex items-center gap-1.5">
                                    <AppIcon name="arrow-top-right" class="h-4 w-4" />
                                    Send review transfer
                                </span>
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
