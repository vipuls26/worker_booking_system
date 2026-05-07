import http from './http';

export function adminDashboard() {
    return http.get('/admin/dashboard');
}

export function adminServices(params = {}) {
    return http.get('/admin/services', { params });
}

export function createAdminService(payload) {
    return http.post('/admin/services', payload);
}

export function updateAdminService(id, payload) {
    return http.put(`/admin/services/${id}`, payload);
}

export function deleteAdminService(id) {
    return http.delete(`/admin/services/${id}`);
}

export function adminWorkerServiceRequests(params = {}) {
    return http.get('/admin/worker-service-requests', { params });
}

export function approveWorkerServiceRequest(id) {
    return http.patch(`/admin/worker-service-requests/${id}/approve`);
}

export function rejectWorkerServiceRequest(id, rejection_reason) {
    return http.patch(`/admin/worker-service-requests/${id}/reject`, { rejection_reason });
}

export function adminUsers(params = {}) {
    return http.get('/admin/users', { params });
}

export function blockAdminUser(id) {
    return http.patch(`/admin/users/${id}/block`);
}

export function unblockAdminUser(id) {
    return http.patch(`/admin/users/${id}/unblock`);
}

export function verifyAdminUser(id) {
    return http.patch(`/admin/users/${id}/verify`);
}

export function unverifyAdminUser(id) {
    return http.patch(`/admin/users/${id}/unverify`);
}

export function deleteAdminUser(id) {
    return http.delete(`/admin/users/${id}`);
}

export function adminUnblockRequests(params = {}) {
    return http.get('/admin/unblock-requests', { params });
}

export function approveUnblockRequest(id, admin_note = '') {
    return http.patch(`/admin/unblock-requests/${id}/approve`, { admin_note });
}

export function rejectUnblockRequest(id, admin_note = '') {
    return http.patch(`/admin/unblock-requests/${id}/reject`, { admin_note });
}

export function adminWorkerVerifications(params = {}) {
    return http.get('/admin/worker-verifications', { params });
}

export function approveWorkerVerification(id) {
    return http.patch(`/admin/worker-verifications/${id}/approve`);
}

export function rejectWorkerVerification(id, rejection_reason) {
    return http.patch(`/admin/worker-verifications/${id}/reject`, { rejection_reason });
}

export function requestWorkerVerificationResubmission(id, rejection_reason) {
    return http.patch(`/admin/worker-verifications/${id}/request-resubmission`, { rejection_reason });
}

export function adminBookings(params = {}) {
    return http.get('/admin/bookings', { params });
}

export function cancelAdminBooking(id, cancelled_reason) {
    return http.patch(`/admin/bookings/${id}/cancel`, { cancelled_reason });
}
