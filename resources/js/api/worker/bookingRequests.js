import http from '../http';

export function listBookingRequests(params = {}) {
    return http.get('/worker/booking-requests', { params });
}

export function getBookingRequest(id) {
    return http.get(`/worker/booking-requests/${id}`);
}

export function respondToBookingRequest(id, payload) {
    return http.patch(`/worker/booking-requests/${id}/respond`, payload);
}
