<script setup>
import { Head, Link } from '@inertiajs/vue3';

const roles = [
    {
        key: 'employee',
        title: 'Employee',
        description: 'Encode commitments and track performance',
    },
    {
        key: 'supervisor',
        title: 'Supervisor / Rater',
        description: 'Review and rate employee performance',
    },
    {
        key: 'administrator',
        title: 'Administrator',
        description: 'Manage users and generate reports',
    },
];
</script>

<template>
    <Head title="Select your role — I-PERFORM" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-slate-950 via-slate-900 to-slate-950 px-4 py-12 text-white">
        <div class="w-full max-w-lg rounded-2xl border border-white/10 bg-slate-900/60 p-8 shadow-2xl backdrop-blur">
            <div class="text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">I-PERFORM</p>
                <h1 class="mt-2 text-2xl font-bold">Sign in to your account</h1>
                <p class="mt-2 text-sm text-slate-400">Choose the portal that matches your responsibility.</p>
            </div>

            <div class="mt-8 space-y-3">
                <Link
                    v-for="role in roles"
                    :key="role.key"
                    :href="route('login', { as: role.key })"
                    class="flex items-center justify-between rounded-xl border border-white/10 bg-slate-900/80 px-4 py-4 text-left transition hover:border-blue-400/60 hover:bg-slate-800/80"
                >
                    <div>
                        <p class="font-semibold">{{ role.title }}</p>
                        <p class="text-sm text-slate-400">{{ role.description }}</p>
                    </div>
                    <span class="text-slate-300">→</span>
                </Link>
            </div>

            <div class="mt-6 rounded-lg border border-white/5 bg-slate-950/60 p-3 text-xs text-slate-400">
                Demo tip: seeded accounts use password <span class="font-mono text-slate-200">password</span>. Registration creates an employee account.
            </div>
        </div>

        <Link href="/" class="mt-8 text-sm text-slate-400 underline decoration-slate-600 hover:text-white">
            Back to Home
        </Link>
    </div>
</template>
