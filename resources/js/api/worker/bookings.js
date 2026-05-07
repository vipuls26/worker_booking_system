import http from '../http';

export function listBookings(params = {}) {
    return http.get('/worker/bookings', { params });
}

export function getBooking(id) {
    return http.get(`/worker/bookings/${id}`);
}

export function updateBookingStatus(id, payload) {
    return http.patch(`/worker/bookings/${id}/status`, payload);
}

export function submitCustomerReview(id, payload) {
    return http.post(`/worker/bookings/${id}/review-customer`, payload);
}
