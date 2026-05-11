import http from '../http';

export function listBookings(params = {}) {
    return http.get('/customer/bookings', { params });
}

export function createBooking(payload) {
    return http.post('/customer/bookings', payload);
}

export function prepareBookAgain(id) {
    return http.post(`/customer/bookings/${id}/book-again`);
}

export function getBooking(id) {
    return http.get(`/customer/bookings/${id}`);
}

export function cancelBooking(id, payload = {}) {
    return http.patch(`/customer/bookings/${id}/cancel`, payload);
}

export function selectBookingWorker(id, payload) {
    return http.patch(`/customer/bookings/${id}/select-worker`, payload);
}

export function payBooking(id, payload = {}) {
    return http.post(`/customer/bookings/${id}/pay`, payload);
}

export function submitBookingReview(id, payload) {
    return http.post(`/customer/bookings/${id}/review`, payload);
}
