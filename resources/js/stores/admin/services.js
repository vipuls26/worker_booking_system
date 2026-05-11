import { defineStore } from 'pinia';
import * as serviceApi from '../../api/admin/services';

function activeParam(value) {
    if (value === 'active') {
        return 1;
    }

    if (value === 'inactive') {
        return 0;
    }

    return undefined;
}

export const useAdminServicesStore = defineStore('adminServices', {
    state: () => ({
        services: [],
        meta: {},
        loading: false,
        filters: {
            search: '',
            status: 'all',
            per_page: 10,
        },
    }),

    actions: {
        async fetch(page = 1) {
            this.loading = true;

            try {
                const response = await serviceApi.listServices({
                    search: this.filters.search || undefined,
                    is_active: activeParam(this.filters.status),
                    per_page: this.filters.per_page,
                    page,
                });

                this.services = response.data.data.services;
                this.meta = response.data.data.meta;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            const response = await serviceApi.createService(payload);

            return response.data;
        },

        async update(id, payload) {
            const response = await serviceApi.updateService(id, payload);

            return response.data;
        },

        async toggleStatus(id) {
            const response = await serviceApi.toggleServiceStatus(id);

            return response.data;
        },

        async delete(id, force = false) {
            const response = await serviceApi.deleteService(id, force);

            return response.data;
        },
    },
});
