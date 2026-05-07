import { defineStore } from 'pinia';
import * as bookingRequestsApi from '../../api/worker/bookingRequests';

export const useWorkerBookingRequestsStore = defineStore('workerBookingRequests', {
    state: () => ({
        bookingRequests: [],
        bookingRequest: null,
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
                const response = await bookingRequestsApi.listBookingRequests({ ...this.filters, page });
                this.bookingRequests = response.data.data.booking_requests;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async fetchOne(id) {
            this.loading = true;

            try {
                const response = await bookingRequestsApi.getBookingRequest(id);
                this.bookingRequest = response.data.data.booking_request;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async respond(id, status) {
            this.saving = true;

            try {
                const response = await bookingRequestsApi.respondToBookingRequest(id, { status });
                const updatedRequest = response.data.data.booking_request;
                this.bookingRequest = updatedRequest;
                this.bookingRequests = this.bookingRequests.map((bookingRequest) => (
                    bookingRequest.id === updatedRequest.id ? updatedRequest : bookingRequest
                ));

                return response.data;
            } finally {
                this.saving = false;
            }
        },
    },
});
