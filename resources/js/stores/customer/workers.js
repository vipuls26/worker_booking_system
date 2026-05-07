import { defineStore } from 'pinia';
import * as workersApi from '../../api/customer/workers';

export const useCustomerWorkersStore = defineStore('customerWorkers', {
    state: () => ({
        workers: [],
        worker: null,
        reviews: [],
        reviewSummary: { average: 0, count: 0 },
        availability: [],
        serviceOptions: [],
        meta: {},
        loading: false,
        detailLoading: false,
        availabilityLoading: false,
        filters: {
            service_id: '',
            min_rating: '',
            max_price: '',
            city: '',
            available_date: '',
            available_time: '',
            sort: 'relevance',
            per_page: 12,
        },
    }),

    actions: {
        async fetch(page = 1) {
            this.loading = true;

            try {
                const response = await workersApi.listWorkers({
                    ...this.filters,
                    page,
                });

                this.workers = response.data.data.workers;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async fetchOptions() {
            const response = await workersApi.workerSearchOptions();
            this.serviceOptions = response.data.data.services;

            return response.data;
        },

        async fetchWorker(id, params = {}) {
            this.detailLoading = true;

            try {
                const response = await workersApi.getWorker(id, params);
                this.worker = response.data.data.worker;
                this.availability = response.data.data.availability || [];

                return response.data;
            } finally {
                this.detailLoading = false;
            }
        },

        async fetchWorkerReviews(id, params = {}) {
            const response = await workersApi.listWorkerReviews(id, params);
            this.reviews = response.data.data.reviews;
            this.reviewSummary = response.data.data.summary;

            return response.data;
        },

        async fetchAvailability(id, params = {}) {
            this.availabilityLoading = true;

            try {
                const response = await workersApi.getWorker(id, params);
                this.availability = response.data.data.availability || [];

                return response.data;
            } finally {
                this.availabilityLoading = false;
            }
        },
    },
});
