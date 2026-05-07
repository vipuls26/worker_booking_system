<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import PaginationControls from '../components/common/PaginationControls.vue';
import SkeletonList from '../components/common/SkeletonList.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import { useNotificationsStore } from '../stores/notifications';

const router = useRouter();
const notificationsStore = useNotificationsStore();

function formatDate(value) {
    if (! value) {
        return '';
    }

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

async function load(page = 1) {
    try {
        await notificationsStore.fetch(page, 12);
    } catch {
        toast.error('Unable to load notifications');
    }
}

async function openNotification(notification) {
    if (! notification.is_read) {
        await notificationsStore.markAsRead(notification.id);
    }

    if (notification.url) {
        await router.push(notification.url);
    }
}

async function markAll() {
    try {
        await notificationsStore.markAllAsRead();
        toast.success('Notifications marked as read');
    } catch {
        toast.error('Unable to mark notifications as read');
    }
}

onMounted(load);
</script>

<template>
    <DashboardLayout title="Notifications">
        <div class="space-y-5">
            <section class="flex flex-col gap-3 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Notification center</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ notificationsStore.unreadCount }} unread notifications</p>
                </div>
                <div class="sm:w-44">
                    <AppButton icon="pi-check" @click="markAll">Mark all read</AppButton>
                </div>
            </section>

            <div v-if="notificationsStore.loading" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <SkeletonList :count="5" />
            </div>

            <div v-else-if="notificationsStore.notifications.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                No notifications yet.
            </div>

            <div v-else class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <button
                    v-for="notification in notificationsStore.notifications"
                    :key="notification.id"
                    type="button"
                    class="block w-full border-b border-gray-100 px-5 py-4 text-left transition last:border-b-0 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                    :class="notification.is_read ? '' : 'bg-blue-50/60 dark:bg-blue-500/10'"
                    @click="openNotification(notification)"
                >
                    <div class="flex items-start gap-4">
                        <span class="mt-2 size-2 rounded-full" :class="notification.is_read ? 'bg-gray-300 dark:bg-gray-700' : 'bg-blue-600'"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <p class="font-medium text-gray-900 dark:text-white">{{ notification.title }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(notification.created_at) }}</p>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ notification.message }}</p>
                        </div>
                    </div>
                </button>
            </div>

            <PaginationControls :meta="notificationsStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
