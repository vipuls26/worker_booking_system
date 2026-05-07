import { defineStore } from 'pinia';
import * as workerServicesApi from '../../api/worker/services';

function activeParam(value) {
    if (value === 'active') {
        return true;
    }

    if (value === 'inactive') {
        return false;
    }

    return undefined;
}

export const useWorkerServicesStore = defineStore('workerServices', {
    state: () => ({
        workerServices: [],
        serviceOptions: [],
        meta: {},
        loading: false,
        saving: false,
        filters: {
            search: '',
            pricing_type: '',
            status: 'all',
            approval_status: '',
            per_page: 10,
        },
    }),

    actions: {
        async fetch(page = 1, options = {}) {
            const silent = options.silent ?? false;

            if (! silent) {
                this.loading = true;
            }

            try {
                const response = await workerServicesApi.listWorkerServices({
                    search: this.filters.search || undefined,
                    pricing_type: this.filters.pricing_type || undefined,
                    is_active: activeParam(this.filters.status),
                    approval_status: this.filters.approval_status || undefined,
                    per_page: this.filters.per_page,
                    page,
                });

                this.workerServices = response.data.data.worker_services;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                if (! silent) {
                    this.loading = false;
                }
            }
        },

        async fetchOptions() {
            const response = await workerServicesApi.listServiceOptions();
            this.serviceOptions = response.data.data.services;

            return response.data;
        },

        async create(payload) {
            this.saving = true;

            try {
                const response = await workerServicesApi.createWorkerService(payload);

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async update(id, payload) {
            this.saving = true;

            try {
                const response = await workerServicesApi.updateWorkerService(id, payload);

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async delete(id) {
            const response = await workerServicesApi.deleteWorkerService(id);

            return response.data;
        },
    },
});
