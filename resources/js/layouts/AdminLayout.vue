<script setup>
import { useRouter } from 'vue-router';
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
    { label: 'Users', path: '/admin/users', icon: 'pi-users' },
    { label: 'Verifications', path: '/admin/worker-verifications', icon: 'pi-verified' },
    { label: 'Bookings', path: '/admin/bookings', icon: 'pi-calendar' },
];

async function logout() {
    await authStore.logout();
    await router.push('/login');
}
</script>

<template>
    <main class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 lg:block">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950">
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
                    active-class="bg-gray-900 text-white hover:bg-gray-900 dark:bg-white dark:text-gray-950 dark:hover:bg-white"
                >
                    <i :class="['pi', item.icon]" aria-hidden="true"></i>
                    {{ item.label }}
                </RouterLink>
            </nav>
        </aside>

        <section class="lg:pl-64">
            <header class="border-b border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                    <div>
                        <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Admin</p>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ title }}</h1>
                    </div>
                    <div class="flex items-center gap-3">
                        <ThemeToggle />
                        <button type="button" class="inline-flex h-10 items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200" @click="logout">
                            <i class="pi pi-sign-out" aria-hidden="true"></i>
                            Logout
                        </button>
                    </div>
                </div>
                <nav class="flex gap-2 overflow-x-auto px-4 pb-4 lg:hidden">
                    <RouterLink v-for="item in navigation" :key="item.path" :to="item.path" class="whitespace-nowrap rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200" active-class="bg-gray-900 text-white dark:bg-white dark:text-gray-950">
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
