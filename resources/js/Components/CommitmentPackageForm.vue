<script setup>
import EvidencePanel from '@/Components/EvidencePanel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref } from 'vue';

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
    intro: {
        type: String,
        default: 'Fill in commitments like the IPCR form. Use + Add row under a Function for each Services/Indicator that has its own Weight, Annual Office Target, and Individual Annual Targets. Drag the handle to rearrange rows.',
    },
    showCancel: {
        type: Boolean,
        default: true,
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

const sections = computed(() => [
    { type: 'core', label: 'Core Functions', list: coreEntries.value, tone: 'blue' },
    { type: 'strategic', label: 'Strategic Functions', list: strategicEntries.value, tone: 'amber' },
]);

const dragFrom = ref(null);
const dragOver = ref(null);

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
        _uid: `e-${type}-${Date.now()}-${Math.random()}`,
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

function moveFunction(entryIdx, delta) {
    const type = entries.value[entryIdx]?.function_type;
    if (!type) return;
    const same = entries.value
        .map((entry, idx) => ({ entry, idx }))
        .filter((x) => x.entry.function_type === type);
    const pos = same.findIndex((x) => x.idx === entryIdx);
    const target = same[pos + delta];
    if (!target) return;
    const copy = [...entries.value];
    const current = copy[entryIdx];
    copy[entryIdx] = copy[target.idx];
    copy[target.idx] = current;
    entries.value = copy;
}

function onItemDragStart(event, entryIdx, itemIdx) {
    dragFrom.value = { entryIdx, itemIdx };
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', `${entryIdx}:${itemIdx}`);
}

function onItemDragOver(event, entryIdx, itemIdx) {
    event.preventDefault();
    if (!dragFrom.value) return;
    if (entries.value[dragFrom.value.entryIdx]?.function_type !== entries.value[entryIdx]?.function_type) {
        return;
    }
    event.dataTransfer.dropEffect = 'move';
    dragOver.value = { entryIdx, itemIdx };
}

function onItemDrop(event, entryIdx, itemIdx) {
    event.preventDefault();
    const from = dragFrom.value;
    dragFrom.value = null;
    dragOver.value = null;
    if (!from) return;

    const source = entries.value[from.entryIdx];
    const target = entries.value[entryIdx];
    if (!source || !target || source.function_type !== target.function_type) {
        return;
    }

    if (from.entryIdx === entryIdx) {
        if (from.itemIdx === itemIdx) return;
        const next = [...source.items];
        const [row] = next.splice(from.itemIdx, 1);
        next.splice(itemIdx, 0, row);
        entries.value[entryIdx].items = next;
        return;
    }

    if (source.items.length <= 1) {
        return;
    }

    const nextSource = [...source.items];
    const [row] = nextSource.splice(from.itemIdx, 1);
    source.items = nextSource;
    const nextTarget = [...target.items];
    nextTarget.splice(itemIdx, 0, row);
    target.items = nextTarget;
}

function onItemDragEnd() {
    dragFrom.value = null;
    dragOver.value = null;
}

function isDropTarget(entryIdx, itemIdx) {
    return dragOver.value?.entryIdx === entryIdx && dragOver.value?.itemIdx === itemIdx;
}
</script>

<template>
    <div class="space-y-6">
        <p v-if="intro" class="text-xs text-slate-500">
            {{ intro }}
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
                            <span class="text-[10px] font-normal normal-case text-slate-500">
                                Drag the handle to rearrange rows
                            </span>
                        </th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 72px">Weight</th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 110px">Annual Office Target</th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 110px">Individual Annual Targets</th>
                        <th class="border border-slate-300 px-2 py-2 text-center" style="min-width: 88px">Move</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="section in sections" :key="section.type">
                        <tr :class="section.tone === 'core' || section.type === 'core' ? 'bg-blue-50' : 'bg-amber-50'">
                            <td
                                colspan="6"
                                class="border border-slate-300 px-2 py-1 text-center text-[11px] font-bold uppercase tracking-wide"
                                :class="sectionWeightTotal(section.type) > sectionCap(section.type) + 0.01
                                    ? 'bg-rose-100 text-rose-900'
                                    : section.type === 'core' ? 'text-blue-900' : 'text-amber-900'"
                            >
                                {{ section.label }} · Shared cap {{ sectionCap(section.type) }}% —
                                Σ <strong>{{ sectionWeightTotal(section.type) }}%</strong> used ·
                                <span :class="sectionRemaining(section.type) === 0 ? 'text-emerald-700' : ''">
                                    {{ sectionRemaining(section.type) }}% remaining
                                </span>
                            </td>
                        </tr>
                        <template v-for="({ entry, idx: eIdx }) in section.list" :key="entry._uid || (section.type + '-' + eIdx)">
                            <template v-for="(item, iIdx) in entry.items" :key="item._uid ?? item.id ?? iIdx">
                                <tr
                                    :class="[
                                        entry.enabled ? '' : 'opacity-50',
                                        isDropTarget(eIdx, iIdx) ? 'bg-sky-50 ring-2 ring-inset ring-sky-300' : '',
                                    ]"
                                    @dragover="onItemDragOver($event, eIdx, iIdx)"
                                    @drop="onItemDrop($event, eIdx, iIdx)"
                                    @dragend="onItemDragEnd"
                                >
                                    <td
                                        v-if="iIdx === 0"
                                        :rowspan="entry.items.length"
                                        class="border border-slate-300 px-2 py-1 align-top"
                                    >
                                        <TextInput
                                            v-model="entry.title"
                                            type="text"
                                            class="block w-full text-xs"
                                            :placeholder="section.type === 'core' ? 'e.g. Development of Standards... (optional)' : 'e.g. Strategic Project... (optional)'"
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
                                                class="inline-flex items-center rounded border px-2 py-1 text-[10px] font-semibold"
                                                :class="section.type === 'core'
                                                    ? 'border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100'
                                                    : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'"
                                                @click="addItemRow(eIdx)"
                                            >
                                                + Add row
                                            </button>
                                            <button
                                                v-if="section.list.length > 1"
                                                type="button"
                                                class="inline-flex items-center rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 hover:bg-rose-100"
                                                @click="removeFunctionEntry(eIdx)"
                                            >
                                                − Remove function
                                            </button>
                                        </div>
                                        <div v-if="section.list.length > 1" class="mt-2 flex gap-1">
                                            <button
                                                type="button"
                                                class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                                :disabled="section.list[0].idx === eIdx"
                                                title="Move function up"
                                                @click="moveFunction(eIdx, -1)"
                                            >
                                                ↑ Function
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40"
                                                :disabled="section.list[section.list.length - 1].idx === eIdx"
                                                title="Move function down"
                                                @click="moveFunction(eIdx, 1)"
                                            >
                                                ↓ Function
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
                                        <div class="flex items-center justify-center gap-1">
                                            <button
                                                v-if="entry.items.length > 1"
                                                type="button"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-sm font-bold text-rose-700 hover:bg-rose-100"
                                                title="Remove this row"
                                                @click="removeItemRow(eIdx, iIdx)"
                                            >
                                                ×
                                            </button>
                                            <button
                                                type="button"
                                                class="flex h-7 w-7 cursor-grab items-center justify-center rounded border border-slate-200 bg-slate-50 text-slate-500 hover:bg-slate-100 active:cursor-grabbing"
                                                title="Drag to reorder"
                                                draggable="true"
                                                @dragstart="onItemDragStart($event, eIdx, iIdx)"
                                            >
                                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M7 4h2v2H7V4zm4 0h2v2h-2V4zM7 9h2v2H7V9zm4 0h2v2h-2V9zM7 14h2v2H7v-2zm4 0h2v2h-2v-2z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </template>
                        <tr :class="section.type === 'core' ? 'bg-blue-50/40' : 'bg-amber-50/40'">
                            <td colspan="6" class="border border-slate-300 px-2 py-2 text-center">
                                <button
                                    type="button"
                                    class="inline-flex items-center rounded-md border bg-white px-3 py-1.5 text-[11px] font-semibold shadow-sm"
                                    :class="section.type === 'core'
                                        ? 'border-blue-300 text-blue-700 hover:bg-blue-50'
                                        : 'border-amber-300 text-amber-800 hover:bg-amber-50'"
                                    @click="addFunctionEntry(section.type)"
                                >
                                    + Add {{ section.type === 'core' ? 'Core' : 'Strategic' }} Function
                                </button>
                            </td>
                        </tr>
                    </template>
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
            <SecondaryButton v-if="showCancel" type="button" class="w-full justify-center sm:w-auto" :disabled="processing" @click="$emit('cancel')">
                Cancel
            </SecondaryButton>
        </div>
    </div>
</template>
