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
