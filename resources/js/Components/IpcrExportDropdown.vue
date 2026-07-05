<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { computed } from 'vue';

const props = defineProps({
    submissionId: {
        type: [Number, String],
        default: null,
    },
    userId: {
        type: [Number, String],
        default: null,
    },
    mode: {
        type: String,
        default: 'supervisor',
        validator: (value) => ['supervisor', 'admin-submission', 'admin-employee', 'employee-submission'].includes(value),
    },
    show: {
        type: Boolean,
        default: true,
    },
    label: {
        type: String,
        default: 'Export',
    },
});

const exportUrl = computed(() => (format) => {
    if (props.mode === 'admin-submission') {
        return route('admin.submissions.export', { submission: props.submissionId, format });
    }

    if (props.mode === 'admin-employee') {
        return route('admin.users.ratings.export', { user: props.userId, format });
    }

    if (props.mode === 'employee-submission') {
        return route('employee.submissions.export', { submission: props.submissionId, format });
    }

    return route('supervisor.submissions.export', { submission: props.submissionId, format });
});

const printUrl = computed(() => {
    if (props.mode === 'admin-submission') {
        return route('admin.submissions.print', props.submissionId);
    }

    if (props.mode === 'admin-employee') {
        return route('admin.users.ratings.print', props.userId);
    }

    if (props.mode === 'employee-submission') {
        return route('employee.submissions.print', props.submissionId);
    }

    return route('supervisor.submissions.print', props.submissionId);
});

function openPrint() {
    window.open(printUrl.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <Dropdown v-if="show" align="right" width="48">
        <template #trigger>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
            >
                <AppIcon name="export" class="h-4 w-4" />
                {{ label }}
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                </svg>
            </button>
        </template>

        <template #content>
            <a :href="exportUrl('xlsx')" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100">
                Export Excel (.xlsx)
            </a>
            <a :href="exportUrl('pdf')" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100">
                Export PDF (.pdf)
            </a>
            <a :href="exportUrl('csv')" class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100">
                Export CSV (.csv)
            </a>
            <button
                type="button"
                class="block w-full px-4 py-2 text-start text-sm text-gray-700 hover:bg-gray-100"
                @click="openPrint"
            >
                Print
            </button>
        </template>
    </Dropdown>
</template>
