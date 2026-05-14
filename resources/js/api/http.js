import axios from 'axios';
import { clearStoredAuthSession, getStoredAuthToken, setStoredAuthNotice } from '../lib/authStorage';

const http = axios.create({
    baseURL: '/api',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
    },
});

http.interceptors.request.use((config) => {
    const token = getStoredAuthToken();

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }

    config.headers['X-Client-Platform'] = 'web-spa';

    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
    }

    return config;
});

http.interceptors.response.use(
    (response) => response,
    (error) => {
        const statusCode = error.response?.status;
        const requestUrl = error.config?.url || '';
        const hasToken = Boolean(getStoredAuthToken());
        const isAuthRecoveryRequest = [
            '/auth/login',
            '/auth/register',
            '/auth/forgot-password',
            '/auth/reset-password',
        ].includes(requestUrl);

        if (statusCode === 401 && hasToken && ! isAuthRecoveryRequest) {
            clearStoredAuthSession();
            setStoredAuthNotice('Your session expired. Please sign in again.');

            if (window.location.pathname !== '/login') {
                window.location.assign('/login');
            }
        }

        return Promise.reject(error);
    },
);

export default http;
