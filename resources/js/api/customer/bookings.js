import http from '../http';
import { withIdempotencyKey } from '../../lib/idempotency';

export function listBookings(params = {}) {
    return http.get('/customer/bookings', { params });
}

export function createBooking(payload) {
    return http.post('/customer/bookings', payload, withIdempotencyKey('customer-booking-create'));
}

export function prepareBookAgain(id) {
    return http.post(`/customer/bookings/${id}/book-again`);
}

export function getBooking(id) {
    return http.get(`/customer/bookings/${id}`);
}

export function cancelBooking(id, payload = {}) {
    return http.patch(`/customer/bookings/${id}/cancel`, payload, withIdempotencyKey(`customer-booking-cancel:${id}`));
}

export function rescheduleBooking(id, payload) {
    return http.patch(`/customer/bookings/${id}/reschedule`, payload, withIdempotencyKey(`customer-booking-reschedule:${id}`));
}

export function selectBookingWorker(id, payload) {
    return http.patch(`/customer/bookings/${id}/select-worker`, payload, withIdempotencyKey(`customer-booking-select:${id}`));
}

export function payBooking(id, payload = {}) {
    return http.post(`/customer/bookings/${id}/pay`, payload, withIdempotencyKey(`customer-booking-pay:${id}`));
}

export function submitBookingReview(id, payload) {
    return http.post(`/customer/bookings/${id}/review`, payload);
}
