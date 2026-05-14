import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const ForgotPasswordPage = () => import('../pages/ForgotPasswordPage.vue');
const LoginPage = () => import('../pages/LoginPage.vue');
const RegisterPage = () => import('../pages/RegisterPage.vue');
const ResetPasswordPage = () => import('../pages/ResetPasswordPage.vue');
const BlockedAccountPage = () => import('../pages/BlockedAccountPage.vue');
const EmailVerificationNoticePage = () => import('../pages/EmailVerificationNoticePage.vue');
const EmailVerificationSuccessPage = () => import('../pages/EmailVerificationSuccessPage.vue');
const NotificationsPage = () => import('../pages/NotificationsPage.vue');
const WorkerDashboard = () => import('../pages/WorkerDashboard.vue');
const CustomerDashboard = () => import('../pages/CustomerDashboard.vue');
const WorkerAvailabilityPage = () => import('../pages/worker/AvailabilityPage.vue');
const WorkerBookingRequestsPage = () => import('../pages/worker/BookingRequestsPage.vue');
const WorkerBookingsPage = () => import('../pages/worker/BookingsPage.vue');
const WorkerProfilePage = () => import('../pages/worker/ProfilePage.vue');
const WorkerReviewsPage = () => import('../pages/worker/ReviewsPage.vue');
const WorkerServicesPage = () => import('../pages/worker/ServicesPage.vue');
const CustomerBookingDetailPage = () => import('../pages/customer/BookingDetailPage.vue');
const CustomerBookingsPage = () => import('../pages/customer/BookingsPage.vue');
const CustomerDisputesPage = () => import('../pages/customer/DisputesPage.vue');
const CustomerProfilePage = () => import('../pages/customer/ProfilePage.vue');
const CustomerWorkerDetailPage = () => import('../pages/customer/WorkerDetailPage.vue');
const CustomerWorkerListingPage = () => import('../pages/customer/WorkerListingPage.vue');
const AdminDashboardPage = () => import('../pages/admin/DashboardPage.vue');
const AdminProfilePage = () => import('../pages/admin/ProfilePage.vue');
const AdminServicesPage = () => import('../pages/admin/ServicesPage.vue');
const AdminWorkerServiceRequestsPage = () => import('../pages/admin/WorkerServiceRequestsPage.vue');
const AdminUsersPage = () => import('../pages/admin/UsersPage.vue');
const AdminWorkerVerificationsPage = () => import('../pages/admin/WorkerVerificationsPage.vue');
const AdminUnblockRequestsPage = () => import('../pages/admin/UnblockRequestsPage.vue');
const AdminDisputesPage = () => import('../pages/admin/DisputesPage.vue');
const AdminAuditLogsPage = () => import('../pages/admin/AuditLogsPage.vue');
const AdminCommissionSettingsPage = () => import('../pages/admin/CommissionSettingsPage.vue');
const WorkerAccountPage = () => import('../pages/worker/AccountPage.vue');

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
        path: '/admin/profile',
        name: 'admin.profile',
        component: AdminProfilePage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/services',
        name: 'admin.services',
        component: AdminServicesPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/commission-settings',
        name: 'admin.commission-settings',
        component: AdminCommissionSettingsPage,
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
    {
        path: '/customer/disputes',
        name: 'customer.disputes',
        component: CustomerDisputesPage,
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

    if (
        authStore.isAuthenticated
        && authStore.isBlocked
        && ! authStore.isEmailVerified
        && ! ['email.verify.notice', 'email.verify.success'].includes(to.name)
    ) {
        return '/email/verify';
    }

    if (authStore.isAuthenticated && authStore.isBlocked && ! to.meta.allowsBlocked) {
        return '/account/blocked';
    }

    if (to.name === 'account.blocked' && authStore.isAuthenticated && ! authStore.isRestricted) {
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
