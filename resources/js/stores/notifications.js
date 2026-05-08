import { defineStore } from 'pinia';
import * as notificationsApi from '../api/notifications';

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        notifications: [],
        unreadCount: 0,
        meta: {},
        loading: false,
    }),

    actions: {
        async fetch(page = 1, perPage = 10) {
            this.loading = true;

            try {
                const response = await notificationsApi.listNotifications({ page, per_page: perPage });
                this.notifications = response.data.data.notifications;
                this.unreadCount = response.data.data.unread_count;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async fetchUnreadCount() {
            const response = await notificationsApi.unreadNotificationCount();
            this.unreadCount = response.data.data.unread_count;

            return response.data;
        },

        async markAsRead(id) {
            const response = await notificationsApi.markNotificationAsRead(id);
            const notification = response.data.data.notification;
            this.unreadCount = response.data.data.unread_count;
            this.notifications = this.notifications.map((item) => (item.id === id ? notification : item));

            return response.data;
        },

        async markAllAsRead() {
            const response = await notificationsApi.markAllNotificationsAsRead();
            this.unreadCount = response.data.data.unread_count;
            this.notifications = this.notifications.map((item) => ({
                ...item,
                is_read: true,
                read_at: item.read_at || new Date().toISOString(),
            }));

            return response.data;
        },

        async remove(id) {
            const response = await notificationsApi.deleteNotification(id);
            this.unreadCount = response.data.data.unread_count;
            this.notifications = this.notifications.filter((item) => item.id !== id);

            return response.data;
        },

        async clearAll() {
            const response = await notificationsApi.clearAllNotifications();
            this.unreadCount = response.data.data.unread_count;
            this.notifications = [];
            this.meta = {};

            return response.data;
        },
    },
});
