import { defineStore } from 'pinia';
import * as bookingsApi from '../../api/customer/bookings';

export const useCustomerBookingsStore = defineStore('customerBookings', {
    state: () => ({
        bookings: [],
        booking: null,
        meta: {},
        loading: false,
        saving: false,
        filters: {
            status: '',
            per_page: 10,
        },
    }),

    actions: {
        async fetch(page = 1) {
            this.loading = true;

            try {
                const response = await bookingsApi.listBookings({ ...this.filters, page });
                this.bookings = response.data.data.bookings;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            this.saving = true;

            try {
                const response = await bookingsApi.createBooking(payload);

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async prepareBookAgain(id) {
            this.saving = true;

            try {
                const response = await bookingsApi.prepareBookAgain(id);

                return response.data;
            } finally {
                this.saving = false;
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

        async cancel(id, payload = {}) {
            this.saving = true;

            try {
                const response = await bookingsApi.cancelBooking(id, payload);
                this.booking = response.data.data.booking;

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async reschedule(id, payload) {
            this.saving = true;

            try {
                const response = await bookingsApi.rescheduleBooking(id, payload);
                this.booking = response.data.data.booking;

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async selectWorker(id, payload) {
            this.saving = true;

            try {
                const response = await bookingsApi.selectBookingWorker(id, payload);
                this.booking = response.data.data.booking;

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async pay(id, payload = {}) {
            this.saving = true;

            try {
                const response = await bookingsApi.payBooking(id, payload);
                this.booking = response.data.data.booking;

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async submitReview(id, payload) {
            this.saving = true;

            try {
                const response = await bookingsApi.submitBookingReview(id, payload);

                if (this.booking?.id === Number(id)) {
                    this.booking = {
                        ...this.booking,
                        review: response.data.data.review,
                    };
                }

                return response.data;
            } finally {
                this.saving = false;
            }
        },
    },
});
