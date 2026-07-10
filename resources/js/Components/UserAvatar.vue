<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    photoUrl: {
        type: String,
        default: null,
    },
    size: {
        type: String,
        default: 'md',
    },
});

const imageFailed = ref(false);

const initials = computed(() => {
    const parts = props.name.trim().split(/\s+/).filter(Boolean);

    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
    }

    return props.name.slice(0, 2).toUpperCase();
});

const sizeClasses = computed(() => {
    const sizes = {
        sm: 'h-8 w-8 text-xs',
        md: 'h-16 w-16 text-lg',
        lg: 'h-24 w-24 text-2xl',
    };

    return sizes[props.size] ?? sizes.md;
});

const showPhoto = computed(() => Boolean(props.photoUrl) && !imageFailed.value);

function onImageError() {
    imageFailed.value = true;
}
</script>

<template>
    <div
        class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-200 font-semibold text-slate-700 ring-2 ring-white"
        :class="sizeClasses"
    >
        <img
            v-if="showPhoto"
            :src="photoUrl"
            :alt="`${name} profile photo`"
            class="block h-full w-full object-cover"
            @error="onImageError"
        />
        <span v-else>{{ initials }}</span>
    </div>
</template>
