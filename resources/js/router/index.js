import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import ForgotPasswordPage from '../pages/ForgotPasswordPage.vue';
import LoginPage from '../pages/LoginPage.vue';
import RegisterPage from '../pages/RegisterPage.vue';
import ResetPasswordPage from '../pages/ResetPasswordPage.vue';
import WorkerDashboard from '../pages/WorkerDashboard.vue';
import CustomerDashboard from '../pages/CustomerDashboard.vue';
import AdminDashboardPage from '../pages/admin/DashboardPage.vue';
import AdminServicesPage from '../pages/admin/ServicesPage.vue';
import AdminUsersPage from '../pages/admin/UsersPage.vue';
import AdminWorkerVerificationsPage from '../pages/admin/WorkerVerificationsPage.vue';
import AdminBookingsPage from '../pages/admin/BookingsPage.vue';

const routes = [
    { path: '/', redirect: '/login' },
    { path: '/login', name: 'login', component: LoginPage, meta: { guest: true } },
    { path: '/register', name: 'register', component: RegisterPage, meta: { guest: true } },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordPage, meta: { guest: true } },
    { path: '/reset-password', name: 'reset-password', component: ResetPasswordPage, meta: { guest: true } },
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
        path: '/admin/users',
        name: 'admin.users',
        component: AdminUsersPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/worker-verifications',
        name: 'admin.worker-verifications',
        component: AdminWorkerVerificationsPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/admin/bookings',
        name: 'admin.bookings',
        component: AdminBookingsPage,
        meta: { requiresAuth: true, role: 'admin' },
    },
    {
        path: '/worker/dashboard',
        name: 'worker.dashboard',
        component: WorkerDashboard,
        meta: { requiresAuth: true, role: 'worker' },
    },
    {
        path: '/customer/dashboard',
        name: 'customer.dashboard',
        component: CustomerDashboard,
        meta: { requiresAuth: true, role: 'customer' },
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

    if (to.meta.role && authStore.role !== to.meta.role) {
        return authStore.dashboardPath;
    }

    return true;
});

export default router;
