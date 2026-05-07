import http from '../http';

export function listServices(params = {}) {
    return http.get('/admin/services', { params });
}

export function createService(payload) {
    return http.post('/admin/services', payload);
}

export function getService(id) {
    return http.get(`/admin/services/${id}`);
}

export function updateService(id, payload) {
    return http.put(`/admin/services/${id}`, payload);
}

export function toggleServiceStatus(id) {
    return http.patch(`/admin/services/${id}/toggle-status`);
}

export function deleteService(id) {
    return http.delete(`/admin/services/${id}`);
}
