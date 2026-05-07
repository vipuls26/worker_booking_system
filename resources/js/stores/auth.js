import { defineStore } from 'pinia';
import * as authApi from '../api/auth';

const dashboardByRole = {
    admin: '/admin/dashboard',
    worker: '/worker/dashboard',
    customer: '/customer/dashboard',
};

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('auth_user') || 'null'),
        token: localStorage.getItem('auth_token'),
        isBootstrapped: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token && state.user),
        role: (state) => state.user?.role?.slug,
        dashboardPath: (state) => dashboardByRole[state.user?.role?.slug] || '/login',
    },

    actions: {
        setSession({ token, user }) {
            this.token = token;
            this.user = user;
            localStorage.setItem('auth_token', token);
            localStorage.setItem('auth_user', JSON.stringify(user));
        },

        clearSession() {
            this.token = null;
            this.user = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
        },

        async bootstrap() {
            if (! this.token) {
                this.isBootstrapped = true;
                return;
            }

            try {
                const response = await authApi.me();
                this.user = response.data.data.user;
                localStorage.setItem('auth_user', JSON.stringify(this.user));
            } catch {
                this.clearSession();
            } finally {
                this.isBootstrapped = true;
            }
        },

        async register(payload) {
            const response = await authApi.register(payload);
            this.setSession(response.data.data);

            return response.data;
        },

        async login(payload) {
            const response = await authApi.login(payload);
            this.setSession(response.data.data);

            return response.data;
        },

        async forgotPassword(payload) {
            const response = await authApi.forgotPassword(payload);

            return response.data;
        },

        async resetPassword(payload) {
            const response = await authApi.resetPassword(payload);

            return response.data;
        },

        async logout() {
            try {
                await authApi.logout();
            } finally {
                this.clearSession();
            }
        },
    },
});
