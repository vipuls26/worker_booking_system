import { defineStore } from 'pinia';
import * as bookingsApi from '../../api/worker/bookings';

export const useWorkerBookingsStore = defineStore('workerBookings', {
    state: () => ({
        bookings: [],
        meta: {},
        loading: false,
        saving: false,
        activeStatusKey: null,
        filters: {
            search: '',
            status: '',
            per_page: 10,
        },
    }),

    actions: {
        async fetch(page = 1) {
            this.loading = true;

            try {
                const response = await bookingsApi.listBookings({
                    search: this.filters.search || undefined,
                    status: this.filters.status || undefined,
                    per_page: this.filters.per_page,
                    page,
                });
                this.bookings = response.data.data.bookings;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async updateStatus(id, payload) {
            this.activeStatusKey = `${id}:${payload.status}`;
            this.saving = true;

            try {
                const response = await bookingsApi.updateBookingStatus(id, payload);
                this.bookings = this.bookings.map((booking) => (
                    booking.id === response.data.data.booking.id ? response.data.data.booking : booking
                ));

                return response.data;
            } finally {
                this.saving = false;
                this.activeStatusKey = null;
            }
        },

        async submitCustomerReview(id, payload) {
            this.saving = true;

            try {
                const response = await bookingsApi.submitCustomerReview(id, payload);

                this.bookings = this.bookings.map((booking) => (
                    booking.id === Number(id)
                        ? { ...booking, worker_review: response.data.data.review }
                        : booking
                ));

                return response.data;
            } finally {
                this.saving = false;
            }
        },
    },
});
