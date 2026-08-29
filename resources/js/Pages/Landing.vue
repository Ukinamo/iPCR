<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const slides = [
    {
        id: 'welcome',
        eyebrow: 'CHED MIMAROPA Regional Office',
        title: 'Transform Performance Management',
        highlight: 'with I-PERFORM',
        description:
            'A secure, cloud-based Individual Performance Commitment and Review (IPCR) platform built for government employees, supervisors, and administrators.',
        bullets: [
            'Official IPCR Form 1 layout',
            'Quarterly commitments and accomplishments',
            'SPMS-aligned ratings and exports',
        ],
        accent: 'from-blue-600 to-indigo-700',
    },
    {
        id: 'about',
        eyebrow: 'About the system',
        title: 'What I-PERFORM Does',
        highlight: 'for your office',
        description:
            'I-PERFORM digitizes the full IPCR cycle—from setting targets at the start of the period through administrator review, approval, and printable official forms.',
        bullets: [
            'Employees encode core and strategic commitments with weights',
            'Administrators review employee ratings and approve or reject packages',
            'Approved records export to Excel, PDF, or CSV',
        ],
        accent: 'from-emerald-600 to-teal-700',
    },
    {
        id: 'employees',
        eyebrow: 'For employees',
        title: 'Plan, Track, and Submit',
        highlight: 'your IPCR package',
        description:
            'Create commitment packages per evaluation period, attach evidence for accomplishments, and submit when ready for administrator review.',
        bullets: [
            'Draft and edit commitments before submission',
            'View approved history and overall ratings',
            'Preview and print official IPCR forms',
        ],
        accent: 'from-violet-600 to-purple-700',
    },
    {
        id: 'supervisors',
        eyebrow: 'For supervisors & admin',
        title: 'Review, Rate, and Report',
        highlight: 'with transparency',
        description:
            'Administrators review submitted packages, approve or reject them, and manage users. Supervisors follow their team’s progress.',
        bullets: [
            'Administrator review workflow with feedback',
            'User management, pending registrations, and roles',
            'Admin dashboards, ratings reports, and exports',
        ],
        accent: 'from-amber-500 to-orange-600',
    },
    {
        id: 'start',
        eyebrow: 'Ready to begin?',
        title: 'Get Started Today',
        highlight: 'Sign in to your portal',
        description:
            'Choose your role—Employee, Supervisor, or Administrator—and access the workspace designed for CHED MIMAROPA performance management.',
        bullets: [
            'Secure login with role-based access',
            'Register for a new employee account',
            'Admin approval for new registrations',
        ],
        accent: 'from-blue-600 to-sky-700',
        cta: true,
    },
];

const current = ref(0);
const touchStartX = ref(0);
const isAnimating = ref(false);

const AUTO_SLIDE_INTERVAL = 5000;
let autoSlideTimer = null;

const activeSlide = computed(() => slides[current.value]);
const isFirst = computed(() => current.value === 0);
const isLast = computed(() => current.value === slides.length - 1);

function startAutoSlide() {
    stopAutoSlide();
    autoSlideTimer = setInterval(() => {
        if (isAnimating.value) {
            return;
        }
        goTo(isLast.value ? 0 : current.value + 1);
    }, AUTO_SLIDE_INTERVAL);
}

function stopAutoSlide() {
    if (autoSlideTimer !== null) {
        clearInterval(autoSlideTimer);
        autoSlideTimer = null;
    }
}

function goTo(index) {
    if (isAnimating.value || index === current.value) {
        return;
    }
    if (index < 0 || index >= slides.length) {
        return;
    }
    isAnimating.value = true;
    current.value = index;
    setTimeout(() => {
        isAnimating.value = false;
    }, 450);
    startAutoSlide();
}

function next() {
    if (!isLast.value) {
        goTo(current.value + 1);
    }
}

function prev() {
    if (!isFirst.value) {
        goTo(current.value - 1);
    }
}

function onKeydown(event) {
    if (event.key === 'ArrowRight') {
        next();
    }
    if (event.key === 'ArrowLeft') {
        prev();
    }
}

function onTouchStart(event) {
    touchStartX.value = event.changedTouches[0]?.clientX ?? 0;
}

function onTouchEnd(event) {
    const delta = (event.changedTouches[0]?.clientX ?? 0) - touchStartX.value;
    if (Math.abs(delta) < 50) {
        return;
    }
    if (delta < 0) {
        next();
    } else {
        prev();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    startAutoSlide();
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
    stopAutoSlide();
});
</script>

<template>
    <Head title="I-PERFORM — IPCR for CHED MIMAROPA" />

    <div class="flex h-dvh flex-col overflow-hidden bg-white text-slate-900">
        <header class="shrink-0 border-b border-slate-200 bg-white/80 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-2.5 sm:px-6 lg:px-8">
                <Link href="/" class="flex items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 shadow-md shadow-blue-600/20">
                        <AppIcon name="clipboard" class="h-4 w-4 text-white" />
                    </span>
                    <div>
                        <span class="block text-base font-bold tracking-tight text-slate-900 sm:text-lg">I-PERFORM</span>
                        <span class="hidden text-[10px] font-medium uppercase tracking-wider text-slate-500 sm:block">IPCR Management</span>
                    </div>
                </Link>
                <div class="flex items-center gap-2 sm:gap-3">
                    <Link
                        v-if="canLogin"
                        :href="route('portal.role')"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Sign In
                    </Link>
                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-semibold text-white shadow-md shadow-blue-600/20 hover:bg-blue-500"
                    >
                        Register
                    </Link>
                </div>
            </div>
        </header>

        <main class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
            <!-- Carousel -->
            <section
                class="relative flex min-h-0 flex-1 flex-col"
                aria-roledescription="carousel"
                :aria-label="`Slide ${current + 1} of ${slides.length}`"
                @touchstart.passive="onTouchStart"
                @touchend.passive="onTouchEnd"
                @mouseenter="stopAutoSlide"
                @mouseleave="startAutoSlide"
            >
                <div class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div
                        class="absolute -left-1/4 top-0 h-[500px] w-[500px] rounded-full bg-blue-200/40 blur-3xl transition-all duration-700"
                        :class="isAnimating ? 'scale-110 opacity-80' : 'scale-100 opacity-100'"
                    />
                    <div
                        class="absolute -right-1/4 bottom-0 h-[400px] w-[400px] rounded-full bg-indigo-200/30 blur-3xl transition-all duration-700"
                    />
                </div>

                <div class="relative mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col justify-center overflow-y-auto px-4 py-3 sm:px-6 lg:overflow-y-hidden lg:px-8 lg:py-4">
                    <div class="min-h-0 overflow-hidden">
                        <div
                            class="flex transition-transform duration-500 ease-out"
                            :style="{ transform: `translateX(-${current * 100}%)` }"
                        >
                            <article
                                v-for="slide in slides"
                                :key="slide.id"
                                class="w-full shrink-0 px-10 sm:px-1"
                                aria-hidden="false"
                            >
                                <div class="grid gap-6 lg:grid-cols-2 lg:items-center lg:gap-10">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-600 sm:text-sm">
                                            {{ slide.eyebrow }}
                                        </p>
                                        <h1 class="mt-2 text-xl font-extrabold leading-tight tracking-tight text-slate-900 sm:mt-3 sm:text-3xl lg:text-4xl xl:text-[2.75rem]">
                                            {{ slide.title }}
                                            <span
                                                class="mt-0.5 block bg-gradient-to-r bg-clip-text text-transparent sm:mt-1"
                                                :class="slide.accent"
                                            >
                                                {{ slide.highlight }}
                                            </span>
                                        </h1>
                                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-600 sm:mt-4 sm:text-base lg:text-lg">
                                            {{ slide.description }}
                                        </p>
                                        <ul class="mt-4 space-y-1.5 sm:mt-5 sm:space-y-2">
                                            <li
                                                v-for="(item, i) in slide.bullets"
                                                :key="i"
                                                class="flex items-start gap-2 text-sm text-slate-700 sm:gap-3"
                                            >
                                                <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600 sm:mt-1 sm:h-5 sm:w-5">
                                                    <svg class="h-3 w-3" viewBox="0 0 12 12" fill="currentColor">
                                                        <path d="M10.28 2.28a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 0 1-1.06 0L1.72 6.34a.75.75 0 0 1 1.06-1.06l2.19 2.19 4.72-4.72a.75.75 0 0 1 1.06 0Z" />
                                                    </svg>
                                                </span>
                                                <span>{{ item }}</span>
                                            </li>
                                        </ul>
                                        <div v-if="slide.cta" class="mt-5 flex flex-wrap gap-2 sm:mt-6 sm:gap-3">
                                            <Link
                                                :href="route('portal.role')"
                                                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-600/25 hover:bg-blue-500 sm:rounded-xl sm:px-6 sm:py-3"
                                            >
                                                Get Started
                                                <AppIcon name="arrow-top-right" class="h-4 w-4" />
                                            </Link>
                                            <Link
                                                v-if="canRegister"
                                                :href="route('register')"
                                                class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:rounded-xl sm:px-6 sm:py-3"
                                            >
                                                Create account
                                            </Link>
                                        </div>
                                    </div>

                                    <div class="hidden min-w-0 lg:block">
                                        <div
                                            class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-5 shadow-sm xl:p-6"
                                        >
                                            <div
                                                class="mb-4 inline-flex rounded-xl bg-gradient-to-br p-3 shadow-lg"
                                                :class="slide.accent"
                                            >
                                                <AppIcon
                                                    :name="slide.id === 'welcome' ? 'clipboard' : slide.id === 'about' ? 'document-chart-bar' : slide.id === 'employees' ? 'briefcase' : slide.id === 'supervisors' ? 'users' : 'arrow-top-right'"
                                                    class="h-8 w-8 text-white"
                                                />
                                            </div>
                                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                                IPCR at a glance
                                            </p>
                                            <div class="mt-3 grid grid-cols-2 gap-3">
                                                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
                                                    <p class="text-xs text-slate-500">Core functions</p>
                                                    <p class="mt-0.5 text-2xl font-bold text-blue-700">60%</p>
                                                </div>
                                                <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-slate-100">
                                                    <p class="text-xs text-slate-500">Strategic</p>
                                                    <p class="mt-0.5 text-2xl font-bold text-blue-700">40%</p>
                                                </div>
                                            </div>
                                            <div class="mt-4 space-y-1 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 sm:text-sm">
                                                <p class="font-semibold text-slate-900">Evaluation cycle</p>
                                                <p>Commitments → Accomplishments → Review → Approval → Export</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <!-- Slide counter -->
                    <p class="mt-3 shrink-0 text-center text-xs text-slate-500 sm:text-sm lg:text-left">
                        {{ current + 1 }} / {{ slides.length }} — {{ activeSlide.eyebrow }}
                    </p>
                </div>

                <!-- Left arrow -->
                <button
                    type="button"
                    class="absolute left-1 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-lg transition hover:scale-105 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30 sm:left-3 lg:left-6"
                    :disabled="isFirst"
                    aria-label="Previous slide"
                    @click="prev"
                >
                    <AppIcon name="arrow-left" class="h-5 w-5" />
                </button>

                <!-- Right arrow -->
                <button
                    type="button"
                    class="absolute right-1 top-1/2 z-20 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-lg transition hover:scale-105 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-30 sm:right-3 lg:right-6"
                    :disabled="isLast"
                    aria-label="Next slide"
                    @click="next"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>
            </section>

            <!-- Dot indicators -->
            <div class="flex shrink-0 justify-center gap-2 px-4 pb-2 pt-1">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.id"
                    type="button"
                    class="h-2 rounded-full transition-all duration-300"
                    :class="index === current ? 'w-7 bg-blue-600' : 'w-2 bg-slate-300 hover:bg-slate-400'"
                    :aria-label="`Go to slide ${index + 1}: ${slide.eyebrow}`"
                    @click="goTo(index)"
                />
            </div>
        </main>

        <footer class="shrink-0 border-t border-slate-200 bg-slate-50 px-4 py-2 text-center text-[11px] leading-snug text-slate-500 sm:text-xs">
            <p class="whitespace-normal">I-PERFORM · CHED – MIMAROPA Regional Office · Individual Performance Commitment and Review</p>
        </footer>
    </div>
</template>
