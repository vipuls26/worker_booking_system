import http from '../http';

export function listReviews(params = {}) {
    return http.get('/worker/reviews', { params });
}
