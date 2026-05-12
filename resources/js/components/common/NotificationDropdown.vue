<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { useNotificationsStore } from '../../stores/notifications';
import SkeletonList from './SkeletonList.vue';

const router = useRouter();
const notificationsStore = useNotificationsStore();
const isOpen = ref(false);
const recentNotifications = computed(() => notificationsStore.notifications.slice(0, 5));

function notificationIcon(notification) {
    const icons = {
        booking_confirmed: 'pi-check-circle',
        booking_received: 'pi-inbox',
        booking_accepted: 'pi-thumbs-up',
        booking_rejected: 'pi-times-circle',
        booking_cancelled: 'pi-ban',
        work_started: 'pi-play',
        work_completed: 'pi-verified',
        review_received: 'pi-star-fill',
        unblock_request_approved: 'pi-lock-open',
        unblock_request_rejected: 'pi-lock',
    };

    return icons[notification.event] || 'pi-bell';
}

function notificationUrl(notification) {
    if (['unblock_request_approved', 'unblock_request_rejected'].includes(notification.event)) {
        return '/account/blocked';
    }

    return notification.url;
}

async function load() {
    try {
        await notificationsStore.fetch(1, 5);
    } catch {
        toast.error('Unable to load notifications');
    }
}

async function openNotification(notification) {
    if (!notification.is_read) {
        await notificationsStore.markAsRead(notification.id);
    }

    isOpen.value = false;

    const targetUrl = notificationUrl(notification);

    if (targetUrl) {
        await router.push(targetUrl);
    }
}

async function markAll() {
    try {
        await notificationsStore.markAllAsRead();
    } catch {
        toast.error('Unable to mark notifications as read');
    }
}

async function removeNotification(notification) {
    try {
        await notificationsStore.remove(notification.id);
        toast.success('Notification cleared');
    } catch {
        toast.error('Unable to clear notification');
    }
}

async function clearAll() {
    try {
        await notificationsStore.clearAll();
        isOpen.value = false;
        toast.success('Notifications cleared');
    } catch {
        toast.error('Unable to clear notifications');
    }
}

onMounted(load);
</script>

<template>
    <div class="relative">
        <button type="button"
            class="relative inline-flex size-10 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-700 shadow-sm transition-colors hover:border-slate-400 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            aria-label="Notifications" @click="isOpen = !isOpen">
            <i class="pi pi-bell" aria-hidden="true"></i>
            <span v-if="notificationsStore.unreadCount"
                class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                {{ notificationsStore.unreadCount > 9 ? '9+' : notificationsStore.unreadCount }}
            </span>
        </button>

        <div v-if="isOpen"
            class="app-surface fixed inset-x-4 top-16 z-30 overflow-hidden shadow-lg sm:absolute sm:inset-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-80">
            <div
                class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-950">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Notifications</h2>
                <div class="flex items-center gap-3">
                    <button type="button"
                        class="text-xs font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                        @click="markAll">
                        Mark read
                    </button>
                    <button type="button"
                        class="text-xs font-medium text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                        @click="clearAll">
                        Clear
                    </button>
                </div>
            </div>

            <div v-if="notificationsStore.loading" class="p-4">
                <SkeletonList :count="3" />
            </div>

            <div v-else-if="recentNotifications.length === 0" class="p-4 text-sm text-slate-500 dark:text-slate-400">
                No notifications yet.
            </div>

            <div v-else class="max-h-[70vh] divide-y divide-slate-100 overflow-y-auto dark:divide-white/10 sm:max-h-96">
                <div v-for="notification in recentNotifications" :key="notification.id"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/80"
                    :class="notification.is_read ? '' : 'bg-blue-50 dark:bg-blue-500/10'">
                    <button type="button" class="flex min-w-0 flex-1 items-start gap-3 text-left"
                        @click="openNotification(notification)">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-md"
                            :class="notification.is_read ? 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-400' : 'bg-blue-600 text-white'">
                            <i :class="['pi', notificationIcon(notification), 'text-sm']" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{
                                notification.title }}</p>
                            <p class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{
                                notification.message }}</p>
                            <p v-if="notificationUrl(notification)"
                                class="mt-1 text-xs font-semibold text-blue-600 dark:text-blue-300">Open related item
                            </p>
                        </span>
                    </button>
                    <button type="button"
                        class="inline-flex size-8 shrink-0 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                        aria-label="Clear notification" @click="removeNotification(notification)">
                        <i class="pi pi-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <button type="button"
                class="block w-full border-t border-slate-200 px-4 py-3 text-center text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-800"
                @click="router.push('/notifications'); isOpen = false">
                View all
            </button>
        </div>
    </div>
</template>
