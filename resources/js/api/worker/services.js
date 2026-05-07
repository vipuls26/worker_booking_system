import http from '../http';

export function listWorkerServices(params = {}) {
    return http.get('/worker/services', { params });
}

export function listServiceOptions() {
    return http.get('/worker/service-options');
}

export function createWorkerService(payload) {
    return http.post('/worker/services', payload);
}

export function updateWorkerService(id, payload) {
    return http.put(`/worker/services/${id}`, payload);
}

export function deleteWorkerService(id) {
    return http.delete(`/worker/services/${id}`);
}
