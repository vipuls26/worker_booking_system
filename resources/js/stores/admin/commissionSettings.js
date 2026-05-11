import { defineStore } from 'pinia';
import { adminCommissionSetting, updateAdminCommissionSetting } from '../../api/admin';

export const useAdminCommissionSettingsStore = defineStore('adminCommissionSettings', {
    state: () => ({
        setting: null,
        loading: false,
        saving: false,
    }),

    getters: {
        currentRate: (state) => state.setting?.commission_rate ?? '10.00',
    },

    actions: {
        async fetch() {
            this.loading = true;

            try {
                const response = await adminCommissionSetting();
                this.setting = response.data.data.commission_setting;

                return response.data;
            } finally {
                this.loading = false;
            }
        },

        async update(payload) {
            this.saving = true;

            try {
                const response = await updateAdminCommissionSetting(payload);
                this.setting = response.data.data.commission_setting;

                return response.data;
            } finally {
                this.saving = false;
            }
        },
    },
});
