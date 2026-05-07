import http from './http';

export function register(payload) {
    return http.post('/auth/register', payload);
}

export function login(payload) {
    return http.post('/auth/login', payload);
}

export function forgotPassword(payload) {
    return http.post('/auth/forgot-password', payload);
}

export function resetPassword(payload) {
    return http.post('/auth/reset-password', payload);
}

export function logout() {
    return http.post('/auth/logout');
}

export function me() {
    return http.get('/auth/me');
}

export function dashboardFor(role) {
    return http.get(`/${role}/dashboard`);
}

export function roles() {
    return http.get('/roles');
}
