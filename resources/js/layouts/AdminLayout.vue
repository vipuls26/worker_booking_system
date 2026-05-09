<script setup>
import { useRouter } from 'vue-router';
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
const authStore = useAuthStore();

const navigation = [
    { label: 'Dashboard', path: '/admin/dashboard', icon: 'pi-chart-line' },
    { label: 'Services', path: '/admin/services', icon: 'pi-briefcase' },
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
</script>

<template>
    <main class="min-h-screen bg-blue-50/50 text-gray-900 dark:bg-gray-950 dark:text-white">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-blue-100 bg-white p-4 dark:border-white/10 dark:bg-gray-900 lg:block">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">
                    <i class="pi pi-briefcase" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Local Worker</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Admin Panel</p>
                </div>
            </div>

            <nav class="mt-8 space-y-1">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.path"
                    :to="item.path"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                    active-class="bg-blue-600 text-white hover:bg-blue-600 dark:bg-blue-500 dark:text-white dark:hover:bg-blue-500"
                >
                    <i :class="['pi', item.icon]" aria-hidden="true"></i>
                    {{ item.label }}
                </RouterLink>
            </nav>
        </aside>

        <section class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-blue-100 bg-white/95 backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
                <div class="flex items-start justify-between gap-3 px-4 py-3 sm:items-center sm:gap-4 sm:px-6 sm:py-4 lg:px-8">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Admin</p>
                        <h1 class="truncate text-xl font-semibold text-gray-900 dark:text-white">{{ title }}</h1>
                    </div>
                    <div class="flex shrink-0 items-center justify-end gap-2 sm:gap-3">
                        <RouterLink
                            to="/admin/dashboard"
                            class="hidden h-10 items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700 shadow-[0_3px_0_#bfdbfe,0_8px_16px_rgba(37,99,235,0.12)] transition-all duration-150 hover:-translate-y-0.5 hover:bg-blue-100 active:translate-y-0.5 active:shadow-[0_1px_0_#bfdbfe,0_5px_10px_rgba(37,99,235,0.12)] dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:shadow-[0_3px_0_rgba(59,130,246,0.18)] dark:hover:bg-white/10 lg:inline-flex"
                            title="Dashboard"
                        >
                            <i class="pi pi-home" aria-hidden="true"></i>
                            <span class="hidden md:inline">Dashboard</span>
                        </RouterLink>
                        <NotificationDropdown />
                        <ThemeToggle />
                        <button type="button" class="inline-flex h-10 items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700 shadow-[0_3px_0_#bfdbfe,0_8px_16px_rgba(37,99,235,0.12)] transition-all duration-150 hover:-translate-y-0.5 hover:bg-blue-100 active:translate-y-0.5 active:shadow-[0_1px_0_#bfdbfe,0_5px_10px_rgba(37,99,235,0.12)] dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:shadow-[0_3px_0_rgba(59,130,246,0.18)] dark:hover:bg-white/10" @click="logout">
                            <i class="pi pi-sign-out" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </div>
                </div>
                <nav class="flex gap-2 overflow-x-auto px-4 pb-3 sm:px-6 lg:hidden" aria-label="Admin navigation">
                    <RouterLink v-for="item in navigation" :key="item.path" :to="item.path" class="inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:hover:bg-white/10" active-class="bg-blue-600 text-white hover:bg-blue-600 dark:bg-blue-500 dark:text-white dark:hover:bg-blue-500">
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
