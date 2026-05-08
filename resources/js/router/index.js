import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import ForgotPasswordPage from '../pages/ForgotPasswordPage.vue';
import LoginPage from '../pages/LoginPage.vue';
import RegisterPage from '../pages/RegisterPage.vue';
import ResetPasswordPage from '../pages/ResetPasswordPage.vue';
import BlockedAccountPage from '../pages/BlockedAccountPage.vue';
import EmailVerificationNoticePage from '../pages/EmailVerificationNoticePage.vue';
import EmailVerificationSuccessPage from '../pages/EmailVerificationSuccessPage.vue';
import NotificationsPage from '../pages/NotificationsPage.vue';
import WorkerDashboard from '../pages/WorkerDashboard.vue';
import CustomerDashboard from '../pages/CustomerDashboard.vue';
import WorkerAvailabilityPage from '../pages/worker/AvailabilityPage.vue';
import WorkerBookingRequestsPage from '../pages/worker/BookingRequestsPage.vue';
import WorkerBookingsPage from '../pages/worker/BookingsPage.vue';
import WorkerProfilePage from '../pages/worker/ProfilePage.vue';
import WorkerReviewsPage from '../pages/worker/ReviewsPage.vue';
import WorkerServicesPage from '../pages/worker/ServicesPage.vue';
import CustomerBookingDetailPage from '../pages/customer/BookingDetailPage.vue';
import CustomerBookingsPage from '../pages/customer/BookingsPage.vue';
import CustomerProfilePage from '../pages/customer/ProfilePage.vue';
import CustomerWorkerDetailPage from '../pages/customer/WorkerDetailPage.vue';
import CustomerWorkerListingPage from '../pages/customer/WorkerListingPage.vue';
import AdminDashboardPage from '../pages/admin/DashboardPage.vue';
import AdminServicesPage from '../pages/admin/ServicesPage.vue';
import AdminWorkerServiceRequestsPage from '../pages/admin/WorkerServiceRequestsPage.vue';
import AdminUsersPage from '../pages/admin/UsersPage.vue';
import AdminWorkerVerificationsPage from '../pages/admin/WorkerVerificationsPage.vue';
import AdminUnblockRequestsPage from '../pages/admin/UnblockRequestsPage.vue';
import AdminDisputesPage from '../pages/admin/DisputesPage.vue';
import AdminAuditLogsPage from '../pages/admin/AuditLogsPage.vue';
import WorkerAccountPage from '../pages/worker/AccountPage.vue';

const routes = [
    { path: '/', redirect: '/login' },
    { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
    { path: '/register', name: 'register', component: RegisterPage, meta: { guest: true } },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordPage, meta: { guest: true } },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordPage, meta: { guest: true } },
    { path: '/account/blocked', name: 'account.blocked', component: BlockedAccountPage, meta: { requiresAuth: true, allowsBlocked: true } },
    { path: '/email/verify', name: 'email.verify.notice', component: EmailVerificationNoticePage, meta: { requiresAuth: true } },
    { path: '/email/verified', name: 'email.verify.success', component: EmailVerificationSuccessPage },
    {
        path: '/notifications',
        name: 'notifications',
        component: NotificationsPage,
        meta: { requiresAuth: true },
    },
    {
        path: '/admin/dashboard',
        name: 'admin.dashboard',
        component: AdminDashboardPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/services',
        name: 'admin.services',
        component: AdminServicesPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/worker-service-requests',
        name: 'admin.worker-service-requests',
        component: AdminWorkerServiceRequestsPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/users',
        name: 'admin.users',
        component: AdminUsersPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/unblock-requests',
        name: 'admin.unblock-requests',
        component: AdminUnblockRequestsPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/worker-verifications',
        name: 'admin.worker-verifications',
        component: AdminWorkerVerificationsPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/disputes',
        name: 'admin.disputes',
        component: AdminDisputesPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/audit-logs',
        name: 'admin.audit-logs',
        component: AdminAuditLogsPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/worker/dashboard',
        name: 'worker.dashboard',
        component: WorkerDashboard,
        meta: { requiresAuth: true, role: 'worker' },
    },
    {
        path: '/worker/profile',
        name: 'worker.profile',
        component: WorkerProfilePage,
        meta: { requiresAuth: true, role: 'worker' },
    },
    {
        path: '/worker/services',
        name: 'worker.services',
        component: WorkerServicesPage,
        meta: { requiresAuth: true, role: 'worker', requiresVerified: true },
    },
    {
        path: '/worker/availability',
        name: 'worker.availability',
        component: WorkerAvailabilityPage,
        meta: { requiresAuth: true, role: 'worker', requiresVerified: true },
    },
    {
        path: '/worker/booking-requests',
        name: 'worker.booking-requests',
        component: WorkerBookingRequestsPage,
        meta: { requiresAuth: true, role: 'worker', requiresVerified: true },
    },
    {
        path: '/worker/bookings',
        name: 'worker.bookings',
        component: WorkerBookingsPage,
        meta: { requiresAuth: true, role: 'worker', requiresVerified: true },
    },
    {
        path: '/worker/reviews',
        name: 'worker.reviews',
        component: WorkerReviewsPage,
        meta: { requiresAuth: true, role: 'worker', requiresVerified: true },
    },
    {
        path: '/worker/account',
        name: 'worker.account',
        component: WorkerAccountPage,
        meta: { requiresAuth: true, role: 'worker', requiresVerified: true },
    },
    {
        path: '/customer/dashboard',
        name: 'customer.dashboard',
        component: CustomerDashboard,
        meta: { requiresAuth: true, role: 'customer' },
    },
    {
        path: '/customer/profile',
        name: 'customer.profile',
        component: CustomerProfilePage,
        meta: { requiresAuth: true, role: 'customer' },
    },
    {
        path: '/customer/workers',
        name: 'customer.workers',
        component: CustomerWorkerListingPage,
        meta: { requiresAuth: true, role: 'customer', requiresVerified: true },
    },
    {
        path: '/customer/workers/:id',
        name: 'customer.workers.show',
        component: CustomerWorkerDetailPage,
        meta: { requiresAuth: true, role: 'customer', requiresVerified: true },
    },
    {
        path: '/customer/bookings',
        name: 'customer.bookings',
        component: CustomerBookingsPage,
        meta: { requiresAuth: true, role: 'customer', requiresVerified: true },
    },
    {
        path: '/customer/bookings/:id',
        name: 'customer.bookings.show',
        component: CustomerBookingDetailPage,
        meta: { requiresAuth: true, role: 'customer', requiresVerified: true },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const authStore = useAuthStore();

    if (! authStore.isBootstrapped) {
        await authStore.bootstrap();
    }

    if (to.meta.guest && authStore.isAuthenticated) {
        return authStore.dashboardPath;
    }

    if (to.meta.requiresAuth && ! authStore.isAuthenticated) {
        return '/login';
    }

    if (authStore.isAuthenticated && authStore.isBlocked && ! to.meta.allowsBlocked) {
        return '/account/blocked';
    }

    if (to.name === 'account.blocked' && authStore.isAuthenticated && ! authStore.isBlocked) {
        return authStore.dashboardPath;
    }

    if (to.meta.role && authStore.role !== to.meta.role) {
        return authStore.dashboardPath;
    }

    if (to.meta.requiresVerified && ! authStore.isEmailVerified) {
        return authStore.role === 'worker' ? '/worker/profile' : authStore.dashboardPath;
    }

    if (to.meta.requiresVerified && ! authStore.isPlatformVerified) {
        return authStore.role === 'worker' ? '/worker/profile' : authStore.dashboardPath;
    }

    return true;
});

export default router;
