import http from '../http';
import { withIdempotencyKey } from '../../lib/idempotency';

export function listBookings(params = {}) {
    return http.get('/worker/bookings', { params });
}

export function updateBookingStatus(id, payload) {
    return http.patch(`/worker/bookings/${id}/status`, payload, withIdempotencyKey(`worker-booking-status:${id}`));
}

export function submitCustomerReview(id, payload) {
    return http.post(`/worker/bookings/${id}/review-customer`, payload);
}
