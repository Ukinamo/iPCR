<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    submissionId: {
        type: [Number, String],
        required: true,
    },
    mode: {
        type: String,
        default: 'supervisor',
        validator: (value) => ['supervisor', 'admin-submission', 'employee-submission'].includes(value),
    },
    show: {
        type: Boolean,
        default: true,
    },
    label: {
        type: String,
        default: 'Preview',
    },
});

const previewUrl = computed(() => {
    if (props.mode === 'admin-submission') {
        return route('admin.submissions.preview', props.submissionId);
    }

    if (props.mode === 'employee-submission') {
        return route('employee.submissions.preview', props.submissionId);
    }

    return route('supervisor.submissions.preview', props.submissionId);
});
</script>

<template>
    <Link
        v-if="show"
        :href="previewUrl"
        class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
    >
        <AppIcon name="export" class="h-4 w-4" />
        {{ label }}
    </Link>
</template>
