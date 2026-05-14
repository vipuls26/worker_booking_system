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
            search: '',
            status: '',
            per_page: 10,
        },
    }),

    actions: {
        async fetch(page = 1) {
            this.loading = true;

            try {
                const response = await bookingRequestsApi.listBookingRequests({
                    search: this.filters.search || undefined,
                    status: this.filters.status || undefined,
                    per_page: this.filters.per_page,
                    page,
                });
                this.bookingRequests = response.data.data.worker_requests;
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
                this.bookingRequest = response.data.data.worker_request;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async respond(id, payload) {
            this.saving = true;

            try {
                const response = await bookingRequestsApi.respondToBookingRequest(id, payload);
                const updatedRequest = response.data.data.worker_request;
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
