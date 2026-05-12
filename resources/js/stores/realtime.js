import { defineStore } from 'pinia';
import { toast } from 'vue-sonner';
import { disconnectEcho, getEcho } from '../lib/echo';
import { useAuthStore } from './auth';
import { useCustomerBookingsStore } from './customer/bookings';
import { useNotificationsStore } from './notifications';
import { useWorkerBookingRequestsStore } from './worker/bookingRequests';
import { useWorkerBookingsStore } from './worker/bookings';

function currentPath() {
    return window.location.pathname;
}

export const useRealtimeStore = defineStore('realtime', {
    state: () => ({
        connectedUserId: null,
        adminDashboardRefreshKey: 0,
    }),

    actions: {
        sync() {
            const authStore = useAuthStore();

            if (! authStore.isAuthenticated || ! authStore.user?.id) {
                this.disconnect();
                return;
            }

            if (this.connectedUserId === authStore.user.id) {
                return;
            }

            this.connect(authStore.user.id, authStore.role);
        },

        connect(userId, role) {
            this.disconnect();

            const echo = getEcho();
            const notificationsStore = useNotificationsStore();
            const customerBookingsStore = useCustomerBookingsStore();
            const workerBookingRequestsStore = useWorkerBookingRequestsStore();
            const workerBookingsStore = useWorkerBookingsStore();
            const authStore = useAuthStore();

            // One private user channel handles personal notifications and workflow updates.
            echo.private(`users.${userId}`)
                .notification((notification) => {
                    notificationsStore.addRealtimeNotification(notification);
                    this.handleNotification(notification, role);
                })
                .listen('.booking.created', () => {
                    this.refreshBookingViews(role, {
                        customerBookingsStore,
                        workerBookingRequestsStore,
                        workerBookingsStore,
                    });
                })
                .listen('.booking.status.changed', () => {
                    this.refreshBookingViews(role, {
                        customerBookingsStore,
                        workerBookingRequestsStore,
                        workerBookingsStore,
                    });
                })
                .listen('.verification.status.updated', async (event) => {
                    authStore.setUser(event.user);
                    toast.success('Your verification status was updated.');
                });

            if (role === 'admin') {
                echo.private('admin.dashboard')
                    .listen('.admin.dashboard.updated', () => {
                        this.adminDashboardRefreshKey += 1;
                    });
            }

            this.connectedUserId = userId;
        },

        disconnect() {
            this.connectedUserId = null;
            disconnectEcho();
        },

        handleNotification(notification, role) {
            const eventName = notification.event || notification.data?.event;

            if (! eventName) {
                return;
            }

            const toastMessage = notification.message || notification.data?.message;

            if (toastMessage) {
                toast.success(toastMessage);
            }

            if (role === 'worker' && ['service_request_received', 'service_request_cancelled'].includes(eventName)) {
                const workerBookingRequestsStore = useWorkerBookingRequestsStore();

                if (currentPath().startsWith('/worker/booking-requests')) {
                    workerBookingRequestsStore.fetch();
                }
            }

            if (role === 'customer' && [
                'service_request_accepted',
                'service_request_rejected',
                'service_request_awaiting_reschedule',
                'booking_accepted',
                'booking_rejected',
                'booking_cancelled',
                'work_started',
                'work_completed',
            ].includes(eventName)) {
                const customerBookingsStore = useCustomerBookingsStore();

                if (currentPath().startsWith('/customer/bookings')) {
                    customerBookingsStore.fetch();

                    const serviceRequestId = notification.service_request_id || notification.data?.service_request_id;

                    if (serviceRequestId && currentPath() === `/customer/bookings/${serviceRequestId}`) {
                        customerBookingsStore.fetchOne(serviceRequestId);
                    }
                }
            }
        },

        refreshBookingViews(role, stores) {
            if (role === 'worker') {
                if (currentPath().startsWith('/worker/bookings')) {
                    stores.workerBookingsStore.fetch();
                }

                if (currentPath().startsWith('/worker/booking-requests')) {
                    stores.workerBookingRequestsStore.fetch();
                }
            }

            if (role === 'customer' && currentPath().startsWith('/customer/bookings')) {
                stores.customerBookingsStore.fetch();
            }
        },
    },
});
