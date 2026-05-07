import http from '../http';

export function getWorkerVerification() {
    return http.get('/worker/verification');
}

export function submitWorkerVerification(payload) {
    return http.post('/worker/verification', payload);
}
