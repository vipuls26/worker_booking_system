import http from '../http';

export function workerEarnings() {
    return http.get('/worker/earnings');
}
