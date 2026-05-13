<script setup>
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import NotificationDropdown from '../components/common/NotificationDropdown.vue';
import ThemeToggle from '../components/common/ThemeToggle.vue';
import VerificationBanner from '../components/common/VerificationBanner.vue';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
});

const router = useRouter();
const authStore = useAuthStore();

const navigationByRole = {
    customer: [
        { label: 'Dashboard', path: '/customer/dashboard', icon: 'pi-home' },
        { label: 'Find Workers', path: '/customer/workers', icon: 'pi-search', requiresVerified: true },
        { label: 'Bookings', path: '/customer/bookings', icon: 'pi-calendar', requiresVerified: true },
        { label: 'Disputes', path: '/customer/disputes', icon: 'pi-exclamation-circle', requiresVerified: true },
        { label: 'Notifications', path: '/notifications', icon: 'pi-bell' },
        { label: 'Profile', path: '/customer/profile', icon: 'pi-user' },
    ],
    worker: [
        { label: 'Dashboard', path: '/worker/dashboard', icon: 'pi-home' },
        { label: 'Requests', path: '/worker/booking-requests', icon: 'pi-inbox', requiresVerified: true },
        { label: 'Bookings', path: '/worker/bookings', icon: 'pi-calendar', requiresVerified: true },
        { label: 'Services', path: '/worker/services', icon: 'pi-briefcase', requiresVerified: true },
        { label: 'Availability', path: '/worker/availability', icon: 'pi-clock', requiresVerified: true },
        { label: 'Account', path: '/worker/account', icon: 'pi-wallet', requiresVerified: true },
        { label: 'Reviews', path: '/worker/reviews', icon: 'pi-star', requiresVerified: true },
        { label: 'Profile', path: '/worker/profile', icon: 'pi-user' },
    ],
};

const navigation = computed(() => navigationByRole[authStore.role] || []);
const dashboardPath = computed(() => authStore.dashboardPath || `/${authStore.role}/dashboard`);
const canAccessProtectedFeatures = computed(() => authStore.isEmailVerified && authStore.isPlatformVerified);
const verificationLabel = computed(() => {
    if (! authStore.isEmailVerified) {
        return 'Email pending';
    }

    return authStore.isPlatformVerified ? 'Approved' : 'Approval pending';
});
const verificationIcon = computed(() => (canAccessProtectedFeatures.value ? 'pi-verified' : 'pi-exclamation-triangle'));

async function handleLogout() {
    await authStore.logout();
    await router.push('/login');
}

onMounted(() => {
    authStore.refreshUser().catch(() => {});
});
</script>

<template>
    <main class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-950 dark:text-slate-100" data-testid="dashboard-layout">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-slate-900 lg:block">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">
                    <i class="pi pi-briefcase" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Local Worker</p>
                    <p class="text-xs capitalize text-slate-500 dark:text-slate-400">{{ authStore.role }} workspace</p>
                </div>
            </div>

            <div
                class="mt-5 inline-flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                :class="canAccessProtectedFeatures ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
            >
                <i :class="['pi', verificationIcon]" aria-hidden="true"></i>
                {{ verificationLabel }}
            </div>

            <nav class="mt-8 space-y-1" data-testid="desktop-sidebar-nav">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.path"
                    :to="item.path"
                    :data-testid="`desktop-nav-${item.label.toLowerCase().replaceAll(' ', '-')}`"
                    class="flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    :class="item.requiresVerified && !canAccessProtectedFeatures ? 'opacity-60' : ''"
                    active-class="bg-blue-600 text-white hover:bg-blue-600 dark:bg-blue-500 dark:text-white dark:hover:bg-blue-500"
                >
                    <i :class="['pi', item.icon]" aria-hidden="true"></i>
                    <span class="flex-1">{{ item.label }}</span>
                    <i v-if="item.requiresVerified && !canAccessProtectedFeatures" class="pi pi-lock text-xs" aria-hidden="true"></i>
                </RouterLink>
            </nav>
        </aside>

        <section class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-white/10 dark:bg-slate-900/95">
                <div class="flex items-start justify-between gap-3 px-4 py-3 sm:items-center sm:gap-4 sm:px-6 sm:py-4 lg:px-8">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ authStore.role }}</p>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="canAccessProtectedFeatures ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                            >
                                <i :class="['pi', verificationIcon]" aria-hidden="true"></i>
                                {{ verificationLabel }}
                            </span>
                        </div>
                        <h1 class="truncate text-lg font-semibold text-slate-900 dark:text-slate-100 sm:text-xl">{{ props.title }}</h1>
                    </div>

                    <div class="flex shrink-0 items-center justify-end gap-2 sm:gap-3">
                        <RouterLink
                            :to="dashboardPath"
                            class="app-toolbar-button app-accent-button hidden h-10 lg:inline-flex"
                            title="Dashboard"
                        >
                            <i class="pi pi-home" aria-hidden="true"></i>
                            <span class="hidden md:inline">Dashboard</span>
                        </RouterLink>
                        <NotificationDropdown />
                        <ThemeToggle />
                        <button
                            type="button"
                            data-testid="dashboard-logout-button"
                            class="app-toolbar-button h-10"
                            @click="handleLogout"
                        >
                            <i class="pi pi-sign-out" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </div>
                </div>

                <nav class="flex gap-2 overflow-x-auto px-4 pb-3 sm:px-6 lg:hidden" aria-label="Section navigation" data-testid="mobile-navbar">
                    <RouterLink
                        v-for="item in navigation"
                        :key="item.path"
                        :to="item.path"
                        :data-testid="`mobile-nav-${item.label.toLowerCase().replaceAll(' ', '-')}`"
                        class="inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        :class="item.requiresVerified && !canAccessProtectedFeatures ? 'opacity-60' : ''"
                        active-class="bg-blue-600 text-white dark:bg-blue-500 dark:text-white"
                    >
                        <i :class="['pi', item.icon]" aria-hidden="true"></i>
                        {{ item.label }}
                        <i v-if="item.requiresVerified && !canAccessProtectedFeatures" class="pi pi-lock text-xs" aria-hidden="true"></i>
                    </RouterLink>
                </nav>
            </header>

            <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                <VerificationBanner v-if="authStore.role !== 'admin'" />
                <slot />
            </div>
        </section>
    </main>
</template>
