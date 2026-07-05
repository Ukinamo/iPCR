<script setup>
import AppIcon from '@/Components/AppIcon.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const unreadCount = computed(() => page.props.unreadNotificationsCount ?? 0);
const recent = computed(() => page.props.recentNotifications ?? []);

function formatWhen(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return iso;
    }
}

function openNotification(id) {
    router.patch(route('notifications.read', id), {}, { preserveScroll: true });
}
</script>

<template>
    <Dropdown align="right" width="96">
        <template #trigger>
            <button
                type="button"
                class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 hover:text-slate-900"
                aria-label="Notifications"
            >
                <AppIcon name="bell" class="h-5 w-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </span>
            </button>
        </template>

        <template #content>
            <div class="border-b border-slate-100 px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-slate-900">Notifications</p>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-xs font-semibold text-blue-700 hover:text-blue-900"
                        @click="router.post(route('notifications.read-all'), {}, { preserveScroll: true })"
                    >
                        Mark all read
                    </button>
                </div>
            </div>

            <div v-if="!recent.length" class="px-4 py-6 text-center text-sm text-slate-500">
                No notifications yet.
            </div>

            <div v-else class="max-h-80 overflow-y-auto">
                <button
                    v-for="n in recent"
                    :key="n.id"
                    type="button"
                    class="block w-full border-b border-slate-50 px-4 py-3 text-left hover:bg-slate-50"
                    :class="!n.read_at ? 'bg-blue-50/40' : ''"
                    @click="openNotification(n.id)"
                >
                    <p class="text-sm font-semibold text-slate-900">{{ n.title }}</p>
                    <p class="mt-0.5 text-xs text-slate-600">{{ n.message }}</p>
                    <p class="mt-1 text-[11px] text-slate-400">{{ formatWhen(n.created_at) }}</p>
                </button>
            </div>

            <div class="border-t border-slate-100 px-4 py-2">
                <Link :href="route('notifications.index')" class="block text-center text-xs font-semibold text-blue-700 hover:text-blue-900">
                    View all notifications
                </Link>
            </div>
        </template>
    </Dropdown>
</template>
