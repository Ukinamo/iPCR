<script setup>
import EvidencePanel from '@/Components/EvidencePanel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed } from 'vue';

const entries = defineModel('entries', { type: Array, required: true });

const evidence = defineModel('evidence', {
    type: Object,
    default: () => ({ title: '', description: '', files: [] }),
});

const props = defineProps({
    weightSummary: {
        type: Object,
        required: true,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    processing: {
        type: Boolean,
        default: false,
    },
    showEvidence: {
        type: Boolean,
        default: false,
    },
    submitLabel: {
        type: String,
        default: 'Save commitments',
    },
});

defineEmits(['submit', 'cancel']);

const errorList = computed(() =>
    Object.values(props.errors || {}).filter((m) => typeof m === 'string' && m.length),
);

const coreEntries = computed(() =>
    entries.value
        .map((entry, idx) => ({ entry, idx }))
        .filter((x) => x.entry.function_type === 'core'),
);

const strategicEntries = computed(() =>
    entries.value
        .map((entry, idx) => ({ entry, idx }))
        .filter((x) => x.entry.function_type === 'strategic'),
);

function sectionCap(type) {
    return type === 'core'
        ? Number(props.weightSummary?.core_cap ?? 60)
        : Number(props.weightSummary?.strategic_cap ?? 40);
}

function entryWeightTotal(entry) {
    return (entry.items || []).reduce((sum, it) => sum + Number(it.weight || 0), 0);
}

function sectionWeightTotal(type) {
    return entries.value
        .filter((e) => e.enabled && e.function_type === type)
        .reduce((sum, e) => sum + entryWeightTotal(e), 0);
}

function sectionRemaining(type) {
    return Math.max(0, Math.round((sectionCap(type) - sectionWeightTotal(type)) * 100) / 100);
}

function addItemRow(entryIdx) {
    const entry = entries.value[entryIdx];
    if (!entry) return;
    entry.items.push({
        _uid: Date.now() + Math.random(),
        id: null,
        description: '',
        weight: null,
        annual_office_target: '',
        individual_annual_targets: '',
    });
}

function removeItemRow(entryIdx, itemIdx) {
    const entry = entries.value[entryIdx];
    if (!entry || entry.items.length <= 1) return;
    entry.items.splice(itemIdx, 1);
}

function addFunctionEntry(type) {
    const last = [...entries.value].reverse().findIndex((e) => e.function_type === type);
    const insertAt = last === -1 ? entries.value.length : entries.value.length - last;
    entries.value.splice(insertAt, 0, {
        enabled: true,
        function_type: type,
        title: '',
        items: [{
            _uid: Date.now() + Math.random(),
            id: null,
            description: '',
            weight: null,
            annual_office_target: '',
            individual_annual_targets: '',
        }],
    });
}

function removeFunctionEntry(eIdx) {
    const type = entries.value[eIdx]?.function_type;
    const sameType = entries.value.filter((e) => e.function_type === type);
    if (sameType.length <= 1) return;
    entries.value.splice(eIdx, 1);
}
</script>

<template>
    <div class="space-y-6">
        <p class="text-xs text-slate-500">
            Fill in your commitments like the IPCR form. Use <strong>+ Add row</strong> under a Function to list multiple
            Services/Indicators with their own Weight, Annual Office Target and Individual Annual Targets.
        </p>

        <div class="overflow-x-auto rounded-lg border border-slate-300">
            <p class="border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-[10px] text-slate-500 sm:hidden">
                Swipe sideways to edit all columns
            </p>
            <table class="min-w-[720px] w-full border-collapse text-xs">
                <thead class="bg-slate-100 text-[11px] font-semibold text-slate-700">
                    <tr>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 190px">MFO / PAP<br />(Function)</th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 260px">
                            Services / Programs / Projects / Indicators
                            <br />
                            <span class="text-[10px] font-normal normal-case text-slate-500">(one line per indicator — use Enter)</span>
                        </th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 72px">Weight</th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 110px">Annual Office Target</th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 110px">Individual Annual Targets</th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 44px"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="bg-blue-50">
                        <td
                            colspan="6"
                            class="border border-slate-300 px-2 py-1 text-center text-[11px] font-bold uppercase tracking-wide"
                            :class="sectionWeightTotal('core') > sectionCap('core') + 0.01
                                ? 'bg-rose-100 text-rose-900'
                                : 'text-blue-900'"
                        >
                            Core Functions · Shared cap {{ sectionCap('core') }}% —
                            Σ <strong>{{ sectionWeightTotal('core') }}%</strong> used ·
                            <span :class="sectionRemaining('core') === 0 ? 'text-emerald-700' : ''">
                                {{ sectionRemaining('core') }}% remaining
                            </span>
                        </td>
                    </tr>
                    <template v-for="({ entry, idx: eIdx }) in coreEntries" :key="'core-' + eIdx">
                        <template v-for="(item, iIdx) in entry.items" :key="item._uid ?? item.id">
                            <tr :class="entry.enabled ? '' : 'opacity-50'">
                                <td
                                    v-if="iIdx === 0"
                                    :rowspan="entry.items.length"
                                    class="border border-slate-300 px-2 py-1 align-top"
                                >
                                    <TextInput
                                        v-model="entry.title"
                                        type="text"
                                        class="block w-full text-xs"
                                        placeholder="e.g. Development of Standards..."
                                        :required="entry.enabled"
                                    />
                                    <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-500">
                                        <label class="inline-flex items-center gap-1">
                                            <input
                                                v-model="entry.enabled"
                                                type="checkbox"
                                                class="rounded border-slate-300 text-blue-600 shadow-sm"
                                            />
                                            Include
                                        </label>
                                        <span>Σ wt: <strong>{{ entryWeightTotal(entry) }}%</strong></span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-2 py-1 text-[10px] font-semibold text-blue-700 hover:bg-blue-100"
                                            @click="addItemRow(eIdx)"
                                        >
                                            + Add row
                                        </button>
                                        <button
                                            v-if="coreEntries.length > 1"
                                            type="button"
                                            class="inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                            @click="removeFunctionEntry(eIdx)"
                                        >
                                            − Remove function
                                        </button>
                                    </div>
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <textarea
                                        v-model="item.description"
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="One indicator per line"
                                    />
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <TextInput
                                        v-model="item.weight"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="block w-full text-xs"
                                        placeholder="Optional"
                                    />
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <TextInput v-model="item.annual_office_target" type="text" class="block w-full text-xs" />
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <TextInput v-model="item.individual_annual_targets" type="text" class="block w-full text-xs" />
                                </td>
                                <td class="border border-slate-300 px-1 py-1 text-center align-top">
                                    <button
                                        v-if="entry.items.length > 1"
                                        type="button"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-sm font-bold text-rose-700 hover:bg-rose-100"
                                        title="Remove this row"
                                        @click="removeItemRow(eIdx, iIdx)"
                                    >
                                        ×
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </template>
                    <tr class="bg-blue-50/40">
                        <td colspan="6" class="border border-slate-300 px-2 py-2 text-center">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-blue-300 bg-white px-3 py-1.5 text-[11px] font-semibold text-blue-700 shadow-sm hover:bg-blue-50"
                                @click="addFunctionEntry('core')"
                            >
                                + Add Core Function
                            </button>
                        </td>
                    </tr>

                    <tr class="bg-amber-50">
                        <td
                            colspan="6"
                            class="border border-slate-300 px-2 py-1 text-center text-[11px] font-bold uppercase tracking-wide"
                            :class="sectionWeightTotal('strategic') > sectionCap('strategic') + 0.01
                                ? 'bg-rose-100 text-rose-900'
                                : 'text-amber-900'"
                        >
                            Strategic Functions · Shared cap {{ sectionCap('strategic') }}% —
                            Σ <strong>{{ sectionWeightTotal('strategic') }}%</strong> used ·
                            <span :class="sectionRemaining('strategic') === 0 ? 'text-emerald-700' : ''">
                                {{ sectionRemaining('strategic') }}% remaining
                            </span>
                        </td>
                    </tr>
                    <template v-for="({ entry, idx: eIdx }) in strategicEntries" :key="'strat-' + eIdx">
                        <template v-for="(item, iIdx) in entry.items" :key="item._uid ?? item.id">
                            <tr :class="entry.enabled ? '' : 'opacity-50'">
                                <td
                                    v-if="iIdx === 0"
                                    :rowspan="entry.items.length"
                                    class="border border-slate-300 px-2 py-1 align-top"
                                >
                                    <TextInput
                                        v-model="entry.title"
                                        type="text"
                                        class="block w-full text-xs"
                                        placeholder="e.g. Strategic Project..."
                                        :required="entry.enabled"
                                    />
                                    <div class="mt-2 flex items-center justify-between gap-2 text-[10px] text-slate-500">
                                        <label class="inline-flex items-center gap-1">
                                            <input
                                                v-model="entry.enabled"
                                                type="checkbox"
                                                class="rounded border-slate-300 text-blue-600 shadow-sm"
                                            />
                                            Include
                                        </label>
                                        <span>Σ wt: <strong>{{ entryWeightTotal(entry) }}%</strong></span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex items-center rounded border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-800 hover:bg-amber-100"
                                            @click="addItemRow(eIdx)"
                                        >
                                            + Add row
                                        </button>
                                        <button
                                            v-if="strategicEntries.length > 1"
                                            type="button"
                                            class="inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                            @click="removeFunctionEntry(eIdx)"
                                        >
                                            − Remove function
                                        </button>
                                    </div>
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <textarea
                                        v-model="item.description"
                                        rows="3"
                                        class="block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        placeholder="One indicator per line"
                                    />
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <TextInput
                                        v-model="item.weight"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="block w-full text-xs"
                                        placeholder="Optional"
                                    />
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <TextInput v-model="item.annual_office_target" type="text" class="block w-full text-xs" />
                                </td>
                                <td class="border border-slate-300 px-2 py-1 align-top">
                                    <TextInput v-model="item.individual_annual_targets" type="text" class="block w-full text-xs" />
                                </td>
                                <td class="border border-slate-300 px-1 py-1 text-center align-top">
                                    <button
                                        v-if="entry.items.length > 1"
                                        type="button"
                                        class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-sm font-bold text-rose-700 hover:bg-rose-100"
                                        title="Remove this row"
                                        @click="removeItemRow(eIdx, iIdx)"
                                    >
                                        ×
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </template>
                    <tr class="bg-amber-50/40">
                        <td colspan="6" class="border border-slate-300 px-2 py-2 text-center">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-md border border-amber-300 bg-white px-3 py-1.5 text-[11px] font-semibold text-amber-800 shadow-sm hover:bg-amber-50"
                                @click="addFunctionEntry('strategic')"
                            >
                                + Add Strategic Function
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EvidencePanel
            v-if="showEvidence"
            embedded
            compact
            form-heading="Evidence (optional)"
            v-model:title="evidence.title"
            v-model:description="evidence.description"
            v-model:files="evidence.files"
        />

        <InputError :message="errors.entries" />

        <div
            v-if="errorList.length"
            class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-900"
        >
            <p class="font-semibold">Couldn't save — please fix these fields:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li v-for="(msg, key) in errorList" :key="key">{{ msg }}</li>
            </ul>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:flex-wrap">
            <PrimaryButton type="button" class="w-full justify-center sm:w-auto" :disabled="processing" @click="$emit('submit')">
                {{ processing ? 'Saving…' : submitLabel }}
            </PrimaryButton>
            <SecondaryButton type="button" class="w-full justify-center sm:w-auto" :disabled="processing" @click="$emit('cancel')">
                Cancel
            </SecondaryButton>
        </div>
    </div>
</template>
