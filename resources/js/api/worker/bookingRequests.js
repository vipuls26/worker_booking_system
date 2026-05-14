import http from '../http';
import { withIdempotencyKey } from '../../lib/idempotency';

export function listBookingRequests(params = {}) {
    return http.get('/worker/booking-requests', { params });
}

export function respondToBookingRequest(id, payload) {
    return http.patch(`/worker/booking-requests/${id}/respond`, payload, withIdempotencyKey(`worker-booking-request-respond:${id}`));
}
