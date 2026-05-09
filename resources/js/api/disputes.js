import http from './http';

export function listDisputes(params = {}) {
    return http.get('/disputes', { params });
}

export function getDispute(id) {
    return http.get(`/disputes/${id}`);
}

export function createDispute(payload) {
    return http.post('/disputes', payload);
}
