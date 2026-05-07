import http from '../http';

export function workerDashboard() {
    return http.get('/worker/dashboard');
}
