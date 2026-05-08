<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import ConfirmDialog from '../components/common/ConfirmDialog.vue';
import PaginationControls from '../components/common/PaginationControls.vue';
import SkeletonList from '../components/common/SkeletonList.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import { useNotificationsStore } from '../stores/notifications';

const router = useRouter();
const notificationsStore = useNotificationsStore();
const isClearAllOpen = ref(false);
const totalNotifications = computed(() => notificationsStore.meta?.total || notificationsStore.notifications.length);

function formatDate(value) {
    if (! value) {
        return '';
    }

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

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
    };

    return icons[notification.event] || 'pi-bell';
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
        isClearAllOpen.value = false;
        toast.success('Notifications cleared');
    } catch {
        toast.error('Unable to clear notifications');
    }
}

onMounted(load);
</script>

<template>
    <DashboardLayout title="Notifications">
        <div class="space-y-5">
            <section class="flex flex-col gap-3 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950">
                        <i class="pi pi-bell" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">Notification center</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ notificationsStore.unreadCount }} unread · {{ totalNotifications }} total
                        </p>
                    </div>
                </div>
                <div class="grid gap-2 sm:w-auto sm:grid-cols-2">
                    <AppButton icon="pi-check" :disabled="notificationsStore.unreadCount === 0" @click="markAll">Mark read</AppButton>
                    <AppButton icon="pi-trash" variant="danger" :disabled="totalNotifications === 0" @click="isClearAllOpen = true">Clear all</AppButton>
                </div>
            </section>

            <div v-if="notificationsStore.loading" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <SkeletonList :count="5" />
            </div>

            <div v-else-if="notificationsStore.notifications.length === 0" class="rounded-lg bg-white p-10 text-center ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="mx-auto flex size-12 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300">
                    <i class="pi pi-inbox" aria-hidden="true"></i>
                </div>
                <h2 class="mt-4 font-semibold text-gray-900 dark:text-white">No notifications yet</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Booking updates, payout alerts, and account messages will appear here.</p>
            </div>

            <div v-else class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div
                    v-for="notification in notificationsStore.notifications"
                    :key="notification.id"
                    class="flex w-full items-start gap-4 border-b border-gray-100 px-5 py-4 text-left transition last:border-b-0 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                    :class="notification.is_read ? '' : 'bg-blue-50/60 dark:bg-blue-500/10'"
                >
                    <button type="button" class="flex min-w-0 flex-1 items-start gap-4 text-left" @click="openNotification(notification)">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-md" :class="notification.is_read ? 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' : 'bg-blue-600 text-white'">
                            <i :class="['pi', notificationIcon(notification)]" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <p class="font-medium text-gray-900 dark:text-white">{{ notification.title }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(notification.created_at) }}</p>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ notification.message }}</p>
                            <p v-if="notification.url" class="mt-2 text-sm font-semibold text-blue-600 dark:text-blue-300">Open related item</p>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-9 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10 dark:hover:text-red-300"
                        aria-label="Clear notification"
                        @click="removeNotification(notification)"
                    >
                        <i class="pi pi-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <PaginationControls :meta="notificationsStore.meta" @change="load" />
        </div>

        <ConfirmDialog
            :open="isClearAllOpen"
            title="Clear all notifications"
            message="This permanently deletes every notification in your account."
            @cancel="isClearAllOpen = false"
            @confirm="clearAll"
        />
    </DashboardLayout>
</template>
