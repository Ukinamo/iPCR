<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
    supervisors: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    supervisor_ids: props.supervisors.filter((s) => s.assigned).map((s) => Number(s.id)),
});

const grouped = computed(() => {
    const groups = { core: [], strategic: [] };
    const maps = { core: new Map(), strategic: new Map() };

    (props.template.items || []).forEach((item, index) => {
        const type = item.function_type === 'strategic' ? 'strategic' : 'core';
        const key = (item.title || '').trim() || `__blank_${type}_${index}`;
        if (!maps[type].has(key)) {
            const group = { key, title: item.title || '', items: [] };
            maps[type].set(key, group);
            groups[type].push(group);
        }
        maps[type].get(key).items.push(item);
    });

    return groups;
});

function selected(id) {
    return form.supervisor_ids.map(Number).includes(Number(id));
}

const allSelected = computed(() =>
    props.supervisors.length > 0 && props.supervisors.every((s) => selected(s.id)),
);

function toggleAll(event) {
    form.supervisor_ids = event.target.checked ? props.supervisors.map((s) => Number(s.id)) : [];
}

function toggleSupervisor(id, event) {
    const supervisorId = Number(id);
    if (event.target.checked) {
        if (!selected(supervisorId)) {
            form.supervisor_ids = [...form.supervisor_ids.map(Number), supervisorId];
        }
        return;
    }
    form.supervisor_ids = form.supervisor_ids.map(Number).filter((value) => value !== supervisorId);
}

function assign() {
    form.post(route('admin.forms.assign', props.template.id), {
        preserveScroll: true,
    });
}

function formatWeight(weight) {
    return weight != null && weight !== '' ? `${Number(weight).toFixed(0)}%` : '—';
}

function sectionWeight(type) {
    return (props.template.items || [])
        .filter((item) => item.function_type === type)
        .reduce((sum, item) => sum + Number(item.weight || 0), 0);
}

const totalWeight = computed(() =>
    (props.template.items || []).reduce((sum, item) => sum + Number(item.weight || 0), 0),
);
</script>

<template>
    <Head :title="template.title || 'IPCR form'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ template.title || 'IPCR form' }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ template.period_label }} · Core {{ Number(template.weight_summary?.core || 0).toFixed(0) }}%
                        / Strategic {{ Number(template.weight_summary?.strategic || 0).toFixed(0) }}%
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.forms.edit', template.id)"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <AppIcon name="pencil" class="h-4 w-4" />
                        Edit
                    </Link>
                    <Link
                        :href="`${route('dashboard')}?tab=forms`"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <AppIcon name="arrow-left" class="h-4 w-4" />
                        Back to forms
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="overflow-x-auto rounded-xl border border-slate-300 bg-white shadow-sm">
                    <table class="min-w-[1100px] w-full border-collapse text-[11px]">
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
                            <template v-for="groupType in ['core', 'strategic']" :key="groupType">
                                <tr
                                    v-if="grouped[groupType].length"
                                    :class="groupType === 'core' ? 'bg-blue-50/90' : 'bg-amber-50/90'"
                                >
                                    <td
                                        colspan="17"
                                        class="border border-slate-300 px-3 py-2 text-center text-[10px] font-bold uppercase tracking-wide"
                                        :class="groupType === 'core' ? 'text-blue-900' : 'text-amber-900'"
                                    >
                                        {{ groupType === 'core' ? 'Core Functions' : 'Strategic Functions' }}
                                        · {{ sectionWeight(groupType).toFixed(0) }}%
                                    </td>
                                </tr>
                                <template v-for="(fnGroup, fgIdx) in grouped[groupType]" :key="groupType + '-' + fnGroup.key">
                                    <tr v-if="fgIdx > 0" aria-hidden="true">
                                        <td colspan="17" class="h-2 border border-slate-300 bg-white p-0"></td>
                                    </tr>
                                    <tr
                                        v-for="(item, ri) in fnGroup.items"
                                        :key="item.id"
                                        class="align-top"
                                    >
                                        <td
                                            v-if="ri === 0"
                                            :rowspan="fnGroup.items.length"
                                            class="border border-slate-300 px-2 py-1.5 font-semibold text-slate-800"
                                        >
                                            {{ fnGroup.title || '—' }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 whitespace-pre-line text-slate-700">
                                            {{ item.description || '—' }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-center font-medium text-slate-800">
                                            {{ formatWeight(item.weight) }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-700">
                                            {{ item.annual_office_target || '—' }}
                                        </td>
                                        <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-700">
                                            {{ item.individual_annual_targets || '—' }}
                                        </td>
                                        <td v-for="n in 12" :key="n" class="border border-slate-300 px-2 py-1.5 text-center text-slate-400">—</td>
                                    </tr>
                                </template>
                            </template>
                            <tr class="bg-slate-100 font-semibold">
                                <td colspan="2" class="border border-slate-300 px-2 py-1.5 text-right">TOTAL</td>
                                <td class="border border-slate-300 px-2 py-1.5 text-center">{{ totalWeight.toFixed(0) }}%</td>
                                <td colspan="12" class="border border-slate-300"></td>
                                <td class="border border-slate-300 px-2 py-1.5 text-center">—</td>
                                <td class="border border-slate-300 px-2 py-1.5 text-center">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-slate-900">Assign supervisors</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        This form can be used by every selected supervisor. Employees under those supervisors will see it on their dashboard.
                    </p>

                    <p v-if="!template.meets_submit_requirement" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                        Weights must total 60% core and 40% strategic before assigning.
                    </p>

                    <div v-if="!supervisors.length" class="mt-4 text-sm text-slate-500">
                        No supervisors yet. Create supervisor accounts in User Management first.
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                :checked="allSelected"
                                @change="toggleAll"
                            />
                            Select all supervisors
                        </label>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <label
                                v-for="supervisor in supervisors"
                                :key="supervisor.id"
                                class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                                    :checked="selected(supervisor.id)"
                                    @change="toggleSupervisor(supervisor.id, $event)"
                                />
                                <span>
                                    <span class="font-medium text-slate-900">{{ supervisor.name }}</span>
                                    <span class="block text-xs text-slate-500">
                                        {{ supervisor.email }} · {{ supervisor.employee_count }} employee{{ supervisor.employee_count === 1 ? '' : 's' }}
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <InputError class="mt-3" :message="form.errors.supervisor_ids || form.errors.entries" />

                    <div class="mt-4 flex justify-end">
                        <PrimaryButton type="button" :disabled="form.processing" @click="assign">
                            {{ form.processing ? 'Saving…' : 'Save assignment' }}
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
