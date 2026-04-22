<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});
</script>

<template>
    <Head title="I-PERFORM — IPCR for CHED MIMAROPA" />

    <div class="min-h-screen bg-white text-slate-900">
        <header class="border-b border-slate-200">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-md bg-blue-600 text-white">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 13l4 4L19 7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold tracking-tight text-slate-900">I-PERFORM</span>
                </Link>
                <div class="flex items-center gap-3">
                    <Link
                        v-if="canLogin"
                        :href="route('portal.role')"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Sign In
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="hidden rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 sm:inline-flex"
                    >
                        Register
                    </Link>
                </div>
            </div>
        </header>

        <main>
            <section class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:items-center lg:px-8 lg:py-24">
                <div>
                    <h1 class="text-4xl font-extrabold leading-tight text-slate-900 sm:text-5xl">
                        Transform Performance
                        <span class="text-blue-600">Management</span>
                    </h1>
                    <p class="mt-6 text-lg leading-relaxed text-slate-600">
                        I-PERFORM is a cloud-based IPCR management system for CHED MIMAROPA—streamlining commitments,
                        accomplishments, SPMS-aligned ratings, and transparent evaluation across your regional office.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            :href="route('portal.role')"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                        >
                            <span>Get Started Now</span>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M13 3L4 14h7l-1 7 9-11h-7l1-7z" />
                            </svg>
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="inline-flex items-center rounded-md border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Create account
                        </Link>
                    </div>
                </div>
                <div class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-8 shadow-sm">
                    <div class="space-y-3">
                        <div class="h-3 w-3/4 rounded bg-blue-200/70" />
                        <div class="h-3 w-full rounded bg-blue-100" />
                        <div class="h-3 w-5/6 rounded bg-blue-100" />
                        <div class="h-3 w-2/3 rounded bg-blue-100" />
                        <div class="mt-8 grid grid-cols-2 gap-4">
                            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Core</p>
                                <p class="mt-2 text-2xl font-bold text-blue-700">60%</p>
                                <p class="text-xs text-slate-500">SPMS weighting</p>
                            </div>
                            <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-slate-100">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Strategic</p>
                                <p class="mt-2 text-2xl font-bold text-blue-700">40%</p>
                                <p class="text-xs text-slate-500">SPMS weighting</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-200 bg-slate-50 py-16">
                <div class="mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 class="text-2xl font-bold text-slate-900">Powerful features for modern performance management</h2>
                    <p class="mt-3 text-slate-600">Built with government institutions in mind—employees, supervisors, and HR in one secure workspace.</p>
                </div>
            </section>
        </main>
    </div>
</template>
