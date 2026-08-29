<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import CommitmentPackageForm from '@/Components/CommitmentPackageForm.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { flattenFormEntries } from '@/utils/ipcrFormEntries';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
    period: {
        type: Object,
        required: true,
    },
    weightSummary: {
        type: Object,
        required: true,
    },
});

const creating = ref(false);
let itemSeq = 0;

function newItem(weight = null) {
    itemSeq += 1;
    return {
        _uid: itemSeq,
        description: '',
        weight,
        annual_office_target: '',
        individual_annual_targets: '',
    };
}

function newEntry(functionType) {
    return {
        enabled: true,
        function_type: functionType,
        title: '',
        _uid: `e-${functionType}-${Date.now()}-${Math.random()}`,
        items: [newItem()],
    };
}

const form = useForm({
    title: '',
    evaluation_year: props.period.year,
    included_quarters: [3, 4],
    entries: [newEntry('core'), newEntry('strategic')],
});

function flattenedEntries() {
    return flattenFormEntries(form.entries);
}

function startCreate() {
    creating.value = true;
}

function cancelCreate() {
    creating.value = false;
}

function save() {
    form.transform((data) => ({
        title: data.title,
        evaluation_year: data.evaluation_year,
        included_quarters: (data.included_quarters || []).map((q) => Number(q)),
        entries: flattenedEntries(),
    })).post(route('admin.forms.store'), {
        preserveScroll: true,
        onFinish: () => form.transform((data) => data),
    });
}

function statusBadge() {
    return 'bg-emerald-50 text-emerald-800 ring-emerald-100';
}

function destroyForm(id) {
    if (confirm('Remove this catalog form? Employee copies already started will not be deleted.')) {
        router.delete(route('admin.forms.destroy', id));
    }
}
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-slate-900">IPCR form catalog</h3>
            <button
                v-if="!creating"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-700"
                @click="startCreate"
            >
                <AppIcon name="plus" class="h-4 w-4" />
                Create
            </button>
        </div>

        <div v-if="creating" class="mt-4 space-y-4 border-t border-slate-100 pt-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <InputLabel value="Title (optional)" />
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    />
                    <InputError class="mt-1" :message="form.errors.title" />
                </div>
                <div>
                    <InputLabel value="Year" />
                    <input
                        v-model="form.evaluation_year"
                        type="number"
                        min="2000"
                        max="2100"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500"
                    />
                </div>
            </div>

            <div>
                <InputLabel value="Include in accomplishments" />
                <p class="mt-1 text-xs text-slate-500">Checked quarters show as Target/Actual columns, plus Total.</p>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label v-for="q in [1, 2, 3, 4]" :key="q" class="inline-flex items-center gap-2 text-sm text-slate-800">
                        <input
                            v-model="form.included_quarters"
                            type="checkbox"
                            :value="q"
                            class="rounded border-gray-300 text-amber-600 focus:ring-amber-500"
                        />
                        Q{{ q }}
                    </label>
                </div>
                <InputError class="mt-1" :message="form.errors.included_quarters" />
            </div>

            <CommitmentPackageForm
                v-model:entries="form.entries"
                :weight-summary="weightSummary"
                :errors="form.errors"
                :processing="form.processing"
                intro=""
                submit-label="Create"
                @submit="save"
                @cancel="cancelCreate"
            />
        </div>

        <div v-else class="mt-4">
            <p v-if="!templates.length" class="text-sm text-slate-500">No forms yet. Click Create.</p>

            <div v-else class="mt-3 divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200">
                <div
                    v-for="row in templates"
                    :key="row.id"
                    class="flex flex-wrap items-center justify-between gap-2 px-3 py-2.5 hover:bg-slate-50/70"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900">{{ row.title }}</p>
                        <p class="text-xs text-slate-500">
                            {{ row.period_label }}
                            · Core {{ Number(row.weight_summary?.core || 0).toFixed(0) }}%
                            / Strategic {{ Number(row.weight_summary?.strategic || 0).toFixed(0) }}%
                            · {{ row.items_count }} row(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold ring-1" :class="statusBadge()">
                            Available
                        </span>
                        <Link
                            :href="route('admin.forms.show', row.id)"
                            title="View"
                            aria-label="View"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-amber-700 shadow-sm hover:bg-slate-50"
                        >
                            <AppIcon name="eye" class="h-4 w-4" />
                        </Link>
                        <Link
                            :href="route('admin.forms.edit', row.id)"
                            title="Edit"
                            aria-label="Edit"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            <AppIcon name="pencil" class="h-4 w-4" />
                        </Link>
                        <button
                            type="button"
                            title="Delete"
                            aria-label="Delete"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-rose-200 bg-white text-rose-700 shadow-sm hover:bg-rose-50"
                            @click="destroyForm(row.id)"
                        >
                            <AppIcon name="trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
