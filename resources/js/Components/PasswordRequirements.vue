<script setup>
defineProps({
    password: {
        type: String,
        default: '',
    },
    showChecks: {
        type: Boolean,
        default: false,
    },
});

const rules = [
    { key: 'length', label: 'Minimum 8 characters', test: (value) => value.length >= 8 },
    { key: 'uppercase', label: 'At least one uppercase letter', test: (value) => /[A-Z]/.test(value) },
    { key: 'lowercase', label: 'At least one lowercase letter', test: (value) => /[a-z]/.test(value) },
    { key: 'number', label: 'At least one number', test: (value) => /\d/.test(value) },
    {
        key: 'special',
        label: 'At least one special character (e.g., !@#$%^&*)',
        test: (value) => /[^A-Za-z0-9]/.test(value),
    },
];
</script>

<template>
    <div class="mt-2 text-sm text-gray-600">
        <p class="font-medium text-gray-700">Password must include:</p>
        <ul class="mt-1 space-y-0.5">
            <li
                v-for="rule in rules"
                :key="rule.key"
                class="flex items-start gap-2"
                :class="showChecks && password ? (rule.test(password) ? 'text-emerald-700' : 'text-gray-500') : ''"
            >
                <span v-if="showChecks && password" class="mt-0.5 shrink-0" aria-hidden="true">
                    {{ rule.test(password) ? '✓' : '○' }}
                </span>
                <span>{{ rule.label }}</span>
            </li>
        </ul>
    </div>
</template>
