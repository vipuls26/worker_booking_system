import axios from 'axios';
import { getStoredAuthToken } from '../lib/authStorage';

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

export default http;
