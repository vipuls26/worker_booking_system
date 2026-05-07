import { defineStore } from 'pinia';
import * as scheduleApi from '../../api/worker/schedules';

export const useWorkerSchedulesStore = defineStore('workerSchedules', {
    state: () => ({
        schedules: [],
        slots: [],
        loading: false,
        saving: false,
        checking: false,
    }),

    actions: {
        async fetch() {
            this.loading = true;

            try {
                const response = await scheduleApi.listWorkerSchedules();
                this.schedules = response.data.data.schedules;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async create(payload) {
            this.saving = true;

            try {
                const response = await scheduleApi.createWorkerSchedule(payload);

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async update(id, payload) {
            this.saving = true;

            try {
                const response = await scheduleApi.updateWorkerSchedule(id, payload);

                return response.data;
            } finally {
                this.saving = false;
            }
        },

        async delete(id) {
            const response = await scheduleApi.deleteWorkerSchedule(id);

            return response.data;
        },

        async availability(params) {
            this.checking = true;

            try {
                const response = await scheduleApi.getWorkerAvailability(params);
                this.slots = response.data.data.slots;

                return response.data;
            } finally {
                this.checking = false;
            }
        },
    },
});
