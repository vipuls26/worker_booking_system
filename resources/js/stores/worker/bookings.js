import { defineStore } from 'pinia';
import * as bookingsApi from '../../api/worker/bookings';

export const useWorkerBookingsStore = defineStore('workerBookings', {
    state: () => ({
        bookings: [],
        booking: null,
        meta: {},
        loading: false,
        saving: false,
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

        async fetchOne(id) {
            this.loading = true;

            try {
                const response = await bookingsApi.getBooking(id);
                this.booking = response.data.data.booking;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async updateStatus(id, payload) {
            this.saving = true;

            try {
                const response = await bookingsApi.updateBookingStatus(id, payload);
                this.booking = response.data.data.booking;
                this.bookings = this.bookings.map((booking) => (
                    booking.id === this.booking.id ? this.booking : booking
                ));

                return response.data;
            } finally {
                this.saving = false;
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

                if (this.booking?.id === Number(id)) {
                    this.booking = {
                        ...this.booking,
                        worker_review: response.data.data.review,
                    };
                }

                return response.data;
            } finally {
                this.saving = false;
            }
        },
    },
});
