import http from '../http';

export function listWorkers(params = {}) {
    return http.get('/customer/workers', { params });
}

export function getWorker(id, params = {}) {
    return http.get(`/customer/workers/${id}`, { params });
}

export function workerSearchOptions() {
    return http.get('/customer/worker-search-options');
}

export function listWorkerReviews(id, params = {}) {
    return http.get(`/customer/workers/${id}/reviews`, { params });
}
