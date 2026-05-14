<script setup>
import { useRoute, useRouter } from 'vue-router';
import NotificationDropdown from '../components/common/NotificationDropdown.vue';
import ThemeToggle from '../components/common/ThemeToggle.vue';
import { useAuthStore } from '../stores/auth';

defineProps({
    title: {
        type: String,
        required: true,
    },
});

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

const navigation = [
    { label: 'Dashboard', path: '/admin/dashboard', icon: 'pi-chart-line' },
    { label: 'Profile', path: '/admin/profile', icon: 'pi-user' },
    { label: 'Services', path: '/admin/services', icon: 'pi-briefcase' },
    { label: 'Commission', path: '/admin/commission-settings', icon: 'pi-percentage' },
    { label: 'Service Requests', path: '/admin/worker-service-requests', icon: 'pi-send' },
    { label: 'Users', path: '/admin/users', icon: 'pi-users' },
    { label: 'Unblock Requests', path: '/admin/unblock-requests', icon: 'pi-unlock' },
    { label: 'Verifications', path: '/admin/worker-verifications', icon: 'pi-verified' },
    { label: 'Disputes', path: '/admin/disputes', icon: 'pi-exclamation-circle' },
    { label: 'Audit Logs', path: '/admin/audit-logs', icon: 'pi-history' },
];

async function logout() {
    await authStore.logout();
    await router.push('/login');
}

function isActivePath(path) {
    return route.path === path;
}

function desktopSidebarLinkClass(path) {
    if (isActivePath(path)) {
        return 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition bg-blue-600 text-white dark:bg-blue-500 dark:text-white';
    }

    return 'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white';
}

function mobileSidebarLinkClass(path) {
    if (isActivePath(path)) {
        return 'inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-blue-600 bg-blue-600 px-3 py-2 text-sm font-medium text-white transition dark:border-blue-500 dark:bg-blue-500 dark:text-white';
    }

    return 'inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
}
</script>

<template>
    <main class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100" data-testid="admin-layout">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900 lg:block">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">
                    <i class="pi pi-briefcase" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Local Worker</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Admin Panel</p>
                </div>
            </div>

            <nav class="mt-8 space-y-1" data-testid="admin-desktop-sidebar-nav">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.path"
                    :to="item.path"
                    :data-testid="`admin-desktop-nav-${item.label.toLowerCase().replaceAll(' ', '-')}`"
                    :class="desktopSidebarLinkClass(item.path)"
                >
                    <i :class="['pi', item.icon]" aria-hidden="true"></i>
                    {{ item.label }}
                </RouterLink>
            </nav>
        </aside>

        <section class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-white/10 dark:bg-slate-900/95">
                <div class="flex items-start justify-between gap-3 px-4 py-3 sm:items-center sm:gap-4 sm:px-6 sm:py-4 lg:px-8">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Admin</p>
                        <h1 class="truncate text-xl font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h1>
                    </div>
                    <div class="flex shrink-0 items-center justify-end gap-2 sm:gap-3">
                        <RouterLink
                            to="/admin/dashboard"
                            class="app-toolbar-button app-accent-button hidden h-10 lg:inline-flex"
                            title="Dashboard"
                        >
                            <i class="pi pi-home" aria-hidden="true"></i>
                            <span class="hidden md:inline">Dashboard</span>
                        </RouterLink>
                        <NotificationDropdown />
                        <ThemeToggle />
                        <button type="button" data-testid="admin-logout-button" class="app-toolbar-button h-10" @click="logout">
                            <i class="pi pi-sign-out" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </div>
                </div>
                <nav class="flex gap-2 overflow-x-auto px-4 pb-3 sm:px-6 lg:hidden" aria-label="Admin navigation" data-testid="admin-mobile-navbar">
                    <RouterLink v-for="item in navigation" :key="item.path" :to="item.path" :data-testid="`admin-mobile-nav-${item.label.toLowerCase().replaceAll(' ', '-')}`" :class="mobileSidebarLinkClass(item.path)">
                        <i :class="['pi', item.icon]" aria-hidden="true"></i>
                        {{ item.label }}
                    </RouterLink>
                </nav>
            </header>

            <div class="px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </div>
        </section>
    </main>
</template>
