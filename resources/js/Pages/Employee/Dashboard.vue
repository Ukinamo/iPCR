<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    stats: Object,
    commitments: Array,
    accomplishments: Array,
    period: Object,
    submission: Object,
    reminder: String,
});

const tab = ref('commitments');

const commitmentForm = useForm({
    title: '',
    function_type: 'core',
    weight: 60,
    progress: 0,
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
    period_label: props.period.label,
});

const accomplishmentForm = useForm({
    title: '',
    description: '',
    commitment_id: null,
});

const editId = ref(null);
const editForm = useForm({
    title: '',
    function_type: 'core',
    weight: 60,
    progress: 0,
    period_label: props.period.label,
});

const submitPeriodForm = useForm({
    evaluation_year: props.period.year,
    evaluation_quarter: props.period.quarter,
});

const canSubmitPeriod = computed(
    () =>
        props.commitments.filter((c) => c.status === 'draft' || c.status === 'returned').length > 0 &&
        (!props.submission || props.submission.status === 'returned'),
);

function statusBadge(status) {
    const map = {
        approved: 'bg-emerald-50 text-emerald-800 ring-emerald-100',
        in_review: 'bg-sky-50 text-sky-800 ring-sky-100',
        draft: 'bg-slate-50 text-slate-700 ring-slate-100',
        returned: 'bg-amber-50 text-amber-900 ring-amber-100',
    };
    return map[status] ?? map.draft;
}

function startEdit(c) {
    editId.value = c.id;
    editForm.title = c.title;
    editForm.function_type = c.function_type;
    editForm.weight = Number(c.weight);
    editForm.progress = c.progress;
    editForm.period_label = c.period_label;
}

function saveEdit() {
    editForm.patch(route('employee.commitments.update', editId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editId.value = null;
        },
    });
}

function destroyCommitment(id) {
    if (confirm('Delete this commitment?')) {
        router.delete(route('employee.commitments.destroy', id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Employee Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Welcome, Employee</h2>
                <p class="text-sm text-gray-500">Track your IPCR commitments and manage your performance evaluation.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-600">Active Commitments</p>
                            <span class="rounded-md bg-blue-50 p-2 text-blue-700">✓</span>
                        </div>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.activeCommitments }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-600">Pending Review</p>
                            <span class="rounded-md bg-amber-50 p-2 text-amber-700">⏱</span>
                        </div>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.pendingReview }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-slate-600">Approval Rate</p>
                            <span class="rounded-md bg-emerald-50 p-2 text-emerald-700">▤</span>
                        </div>
                        <p class="mt-3 text-3xl font-bold text-slate-900">{{ stats.approvalRate }}%</p>
                    </div>
                </div>

                <div class="flex gap-2 rounded-lg bg-slate-100 p-1 text-sm font-semibold text-slate-600">
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-2"
                        :class="tab === 'commitments' ? 'bg-white text-slate-900 shadow-sm' : ''"
                        @click="tab = 'commitments'"
                    >
                        My Commitments
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-md px-3 py-2"
                        :class="tab === 'accomplishments' ? 'bg-white text-slate-900 shadow-sm' : ''"
                        @click="tab = 'accomplishments'"
                    >
                        Accomplishments
                    </button>
                </div>

                <div v-show="tab === 'commitments'" class="space-y-4">
                    <div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Performance Commitments</h3>
                            <p class="text-sm text-slate-500">{{ period.label }} · SPMS core/strategic weights</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <SecondaryButton
                                v-if="canSubmitPeriod"
                                :disabled="submitPeriodForm.processing"
                                @click="submitPeriodForm.post(route('employee.submissions.store'))"
                            >
                                Submit for review
                            </SecondaryButton>
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h4 class="text-sm font-semibold text-slate-900">New commitment</h4>
                        <form class="mt-4 grid gap-4 md:grid-cols-2" @submit.prevent="commitmentForm.post(route('employee.commitments.store'))">
                            <div class="md:col-span-2">
                                <InputLabel for="title" value="Title" />
                                <TextInput id="title" v-model="commitmentForm.title" type="text" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="commitmentForm.errors.title" />
                            </div>
                            <div>
                                <InputLabel for="function_type" value="Function" />
                                <select
                                    id="function_type"
                                    v-model="commitmentForm.function_type"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="core">Core (60% guidance)</option>
                                    <option value="strategic">Strategic (40% guidance)</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="weight" value="Weight (%)" />
                                <TextInput id="weight" v-model="commitmentForm.weight" type="number" step="0.01" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="commitmentForm.errors.weight" />
                            </div>
                            <div>
                                <InputLabel for="progress" value="Progress (%)" />
                                <TextInput id="progress" v-model="commitmentForm.progress" type="number" class="mt-1 block w-full" required />
                            </div>
                            <div class="flex items-end">
                                <PrimaryButton :disabled="commitmentForm.processing">Save commitment</PrimaryButton>
                            </div>
                        </form>
                    </div>

                    <div v-for="c in commitments" :key="c.id" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-semibold text-slate-900">{{ c.title }}</h4>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="statusBadge(c.status)">
                                        {{ c.status.replace('_', ' ') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ c.period_label }} · Weight: {{ Number(c.weight) }}% · {{ c.function_type }}
                                </p>
                                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-blue-600" :style="{ width: c.progress + '%' }" />
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <SecondaryButton v-if="c.status === 'draft' || c.status === 'returned'" @click="startEdit(c)">Edit</SecondaryButton>
                                <SecondaryButton v-if="c.status === 'draft'" @click="destroyCommitment(c.id)">Delete</SecondaryButton>
                            </div>
                        </div>

                        <div v-if="editId === c.id" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <InputLabel value="Title" />
                                    <TextInput v-model="editForm.title" type="text" class="mt-1 block w-full" />
                                    <InputError class="mt-2" :message="editForm.errors.title" />
                                </div>
                                <div>
                                    <InputLabel value="Function" />
                                    <select v-model="editForm.function_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="core">Core</option>
                                        <option value="strategic">Strategic</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Weight (%)" />
                                    <TextInput v-model="editForm.weight" type="number" step="0.01" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <InputLabel value="Progress (%)" />
                                    <TextInput v-model="editForm.progress" type="number" class="mt-1 block w-full" />
                                </div>
                                <div class="flex gap-2 md:col-span-2">
                                    <PrimaryButton :disabled="editForm.processing" @click="saveEdit">Save</PrimaryButton>
                                    <SecondaryButton @click="editId = null">Cancel</SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-show="tab === 'accomplishments'" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-slate-900">Log an accomplishment</h3>
                        <form class="mt-4 space-y-3" @submit.prevent="accomplishmentForm.post(route('employee.accomplishments.store'))">
                            <div>
                                <InputLabel for="acc_title" value="Title" />
                                <TextInput id="acc_title" v-model="accomplishmentForm.title" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="accomplishmentForm.errors.title" />
                            </div>
                            <div>
                                <InputLabel for="acc_desc" value="Description" />
                                <textarea
                                    id="acc_desc"
                                    v-model="accomplishmentForm.description"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    rows="3"
                                />
                            </div>
                            <PrimaryButton :disabled="accomplishmentForm.processing">Save accomplishment</PrimaryButton>
                        </form>
                    </div>

                    <div v-for="a in accomplishments" :key="a.id" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="font-semibold text-slate-900">{{ a.title }}</p>
                        <p v-if="a.description" class="mt-1 text-sm text-slate-600">{{ a.description }}</p>
                    </div>
                </div>

                <div class="flex gap-3 rounded-lg border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900">
                    <div class="text-lg">ⓘ</div>
                    <div>
                        <p class="font-semibold">Important Reminder</p>
                        <p class="mt-1 text-sky-900/80">{{ reminder }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
