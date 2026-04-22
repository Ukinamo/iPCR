<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    backHref: {
        type: String,
        default: null,
    },
    backLabel: {
        type: String,
        default: 'Back',
    },
});

const resolvedBackHref = computed(() => props.backHref ?? route('home'));
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-slate-100 via-white to-slate-100 px-4 py-10 sm:py-12"
    >
        <div
            class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5"
        >
            <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
                <Link
                    :href="resolvedBackHref"
                    class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path
                            fill-rule="evenodd"
                            d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"
                            clip-rule="evenodd"
                        />
                    </svg>
                    {{ backLabel }}
                </Link>
            </div>

            <div class="px-5 py-6 sm:px-6 sm:py-8">
                <slot />
            </div>
        </div>
    </div>
</template>
