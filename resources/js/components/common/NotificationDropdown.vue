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

async function load() {
    try {
        await notificationsStore.fetch(1, 5);
    } catch {
        toast.error('Unable to load notifications');
    }
}

async function openNotification(notification) {
    if (! notification.is_read) {
        await notificationsStore.markAsRead(notification.id);
    }

    isOpen.value = false;

    if (notification.url) {
        await router.push(notification.url);
    }
}

async function markAll() {
    try {
        await notificationsStore.markAllAsRead();
    } catch {
        toast.error('Unable to mark notifications as read');
    }
}

onMounted(load);
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative inline-flex size-10 items-center justify-center rounded-md border border-gray-300 text-gray-700 transition hover:bg-gray-100 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
            aria-label="Notifications"
            @click="isOpen = !isOpen"
        >
            <i class="pi pi-bell" aria-hidden="true"></i>
            <span v-if="notificationsStore.unreadCount" class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                {{ notificationsStore.unreadCount > 9 ? '9+' : notificationsStore.unreadCount }}
            </span>
        </button>

        <div v-if="isOpen" class="fixed inset-x-4 top-16 z-30 overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:absolute sm:inset-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-80">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</h2>
                <button type="button" class="text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white" @click="markAll">
                    Mark all read
                </button>
            </div>

            <div v-if="notificationsStore.loading" class="p-4">
                <SkeletonList :count="3" />
            </div>

            <div v-else-if="recentNotifications.length === 0" class="p-4 text-sm text-gray-500 dark:text-gray-400">
                No notifications yet.
            </div>

            <div v-else class="max-h-[70vh] divide-y divide-gray-100 overflow-y-auto dark:divide-white/10 sm:max-h-96">
                <button
                    v-for="notification in recentNotifications"
                    :key="notification.id"
                    type="button"
                    class="block w-full px-4 py-3 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
                    :class="notification.is_read ? '' : 'bg-blue-50/60 dark:bg-blue-500/10'"
                    @click="openNotification(notification)"
                >
                    <div class="flex items-start gap-3">
                        <span class="mt-1 size-2 rounded-full" :class="notification.is_read ? 'bg-gray-300 dark:bg-gray-700' : 'bg-blue-600'"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ notification.title }}</p>
                            <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ notification.message }}</p>
                        </div>
                    </div>
                </button>
            </div>

            <button type="button" class="block w-full border-t border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5" @click="router.push('/notifications'); isOpen = false">
                View all
            </button>
        </div>
    </div>
</template>
