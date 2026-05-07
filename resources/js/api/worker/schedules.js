import http from '../http';

export function listWorkerSchedules() {
    return http.get('/worker/schedules');
}

export function createWorkerSchedule(payload) {
    return http.post('/worker/schedules', payload);
}

export function updateWorkerSchedule(id, payload) {
    return http.put(`/worker/schedules/${id}`, payload);
}

export function deleteWorkerSchedule(id) {
    return http.delete(`/worker/schedules/${id}`);
}

export function getWorkerAvailability(params) {
    return http.get('/worker/availability', { params });
}
