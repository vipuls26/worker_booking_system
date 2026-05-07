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

export function adminUsers(params = {}) {
    return http.get('/admin/users', { params });
}

export function blockAdminUser(id) {
    return http.patch(`/admin/users/${id}/block`);
}

export function unblockAdminUser(id) {
    return http.patch(`/admin/users/${id}/unblock`);
}

export function deleteAdminUser(id) {
    return http.delete(`/admin/users/${id}`);
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

export function adminBookings(params = {}) {
    return http.get('/admin/bookings', { params });
}

export function cancelAdminBooking(id, cancelled_reason) {
    return http.patch(`/admin/bookings/${id}/cancel`, { cancelled_reason });
}
