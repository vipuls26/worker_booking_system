import http from '../http';

export function getWorkerProfile() {
    return http.get('/worker/profile');
}

export function updateWorkerProfile(payload) {
    return http.post('/worker/profile', payload);
}
