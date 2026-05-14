import { defineStore } from 'pinia';
import * as authApi from '../api/auth';
import {
    clearStoredAuthSession,
    getStoredAuthToken,
    getStoredAuthUser,
    setStoredAuthSession,
    setStoredAuthUser,
} from '../lib/authStorage';

const dashboardByRole = {
    admin: '/admin/dashboard',
    worker: '/worker/dashboard',
    customer: '/customer/dashboard',
};

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: getStoredAuthUser(),
        token: getStoredAuthToken(),
        isBootstrapped: false,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token && state.user),
        role: (state) => state.user?.role?.slug,
        accountStatus: (state) => state.user?.account_status || 'active',
        isBlocked: (state) => Boolean(state.user?.is_fully_blocked),
        isRestricted: (state) => Boolean(state.user?.is_restricted),
        isPartiallyBlocked: (state) => Boolean(state.user?.is_partially_blocked),
        isEmailVerified: (state) => Boolean(state.user?.email_verified_at),
        isPlatformVerified: (state) => Boolean(state.user?.is_admin_verified),
        isVerified: (state) => Boolean(state.user?.email_verified_at && state.user?.is_admin_verified),
        verificationStatus: (state) => state.user?.verification_status || 'pending',
        dashboardPath: (state) => dashboardByRole[state.user?.role?.slug] || '/login',
    },

    actions: {
        setSession({ token, user, remember = false }) {
            this.token = token;
            this.user = user;
            setStoredAuthSession(token, user, remember);
        },

        clearSession() {
            this.token = null;
            this.user = null;
            clearStoredAuthSession();
        },

        async bootstrap() {
            if (! this.token) {
                this.isBootstrapped = true;
                return;
            }

            try {
                await this.refreshUser();
            } catch {
                this.clearSession();
            } finally {
                this.isBootstrapped = true;
            }
        },

        setUser(user) {
            this.user = user;
            setStoredAuthUser(user);
        },

        async refreshUser() {
            const response = await authApi.me();
            this.setUser(response.data.data.user);

            return response.data;
        },

        async updateProfile(payload) {
            const response = await authApi.updateProfile(payload);
            this.setUser(response.data.data.user);

            return response.data;
        },

        async updatePassword(payload) {
            const response = await authApi.updatePassword(payload);
            this.setUser(response.data.data.user);

            return response.data;
        },

        async register(payload) {
            const response = await authApi.register(payload);
            this.setSession({ ...response.data.data, remember: false });

            return response.data;
        },

        async login(payload) {
            const response = await authApi.login(payload);
            this.setSession({ ...response.data.data, remember: Boolean(payload.remember) });

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

        async sendVerificationEmail() {
            const response = await authApi.sendVerificationEmail();

            return response.data;
        },

        async fetchUnblockRequest() {
            const response = await authApi.unblockRequest();

            return response.data;
        },

        async submitUnblockRequest(payload) {
            const response = await authApi.submitUnblockRequest(payload);

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
