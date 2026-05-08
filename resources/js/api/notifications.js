import http from './http';

export function listNotifications(params = {}) {
    return http.get('/notifications', { params });
}

export function unreadNotificationCount() {
    return http.get('/notifications/unread-count');
}

export function markNotificationAsRead(id) {
    return http.patch(`/notifications/${id}/read`);
}

export function markAllNotificationsAsRead() {
    return http.patch('/notifications/read-all');
}

export function deleteNotification(id) {
    return http.delete(`/notifications/${id}`);
}

export function clearAllNotifications() {
    return http.delete('/notifications/clear-all');
}
