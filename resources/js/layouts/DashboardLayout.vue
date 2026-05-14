<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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
const route = useRoute();
const authStore = useAuthStore();
const isMobileNavigationOpen = ref(false);

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

function isActivePath(path) {
    return route.path === path;
}

function desktopSidebarLinkClass(item) {
    const activeClasses = 'bg-blue-600 text-white dark:bg-blue-500 dark:text-white';
    const inactiveClasses = 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white';
    const disabledClasses = item.requiresVerified && !canAccessProtectedFeatures.value ? 'opacity-60' : '';

    return [
        'app-sidebar-link',
        isActivePath(item.path) ? 'app-sidebar-link-active' : inactiveClasses,
        disabledClasses,
    ];
}

function mobileSidebarLinkClass(item) {
    const activeClasses = 'border-blue-600 bg-blue-600 text-white dark:border-blue-500 dark:bg-blue-500 dark:text-white';
    const inactiveClasses = 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
    const disabledClasses = item.requiresVerified && !canAccessProtectedFeatures.value ? 'opacity-60' : '';

    return [
        'inline-flex min-h-10 shrink-0 items-center gap-2 whitespace-nowrap rounded-full px-3 py-2 text-sm font-medium transition-all duration-150',
        isActivePath(item.path) ? activeClasses : inactiveClasses,
        disabledClasses,
    ];
}

function openMobileNavigation() {
    isMobileNavigationOpen.value = true;
}

function closeMobileNavigation() {
    isMobileNavigationOpen.value = false;
}

onMounted(() => {
    authStore.refreshUser().catch(() => {});
});

watch(() => route.path, () => {
    closeMobileNavigation();
});
</script>

<template>
    <main class="app-shell text-slate-900 dark:text-slate-100" data-testid="dashboard-layout">
        <aside class="app-sidebar fixed inset-y-0 left-0 hidden w-64 p-4 lg:block">
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
                class="app-status-chip mt-5 w-full justify-center sm:justify-start"
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
                    :class="desktopSidebarLinkClass(item)"
                >
                    <i :class="['pi', item.icon]" aria-hidden="true"></i>
                    <span class="flex-1">{{ item.label }}</span>
                    <i v-if="item.requiresVerified && !canAccessProtectedFeatures" class="pi pi-lock text-xs" aria-hidden="true"></i>
                </RouterLink>
            </nav>
        </aside>

        <section class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/90 shadow-[0_12px_30px_rgba(15,23,42,0.04)] backdrop-blur dark:border-white/10 dark:bg-slate-900/90 dark:shadow-none">
                <div class="flex items-start justify-between gap-3 px-4 py-3 sm:items-center sm:gap-4 sm:px-6 sm:py-4 lg:px-8">
                    <div class="min-w-0 flex-1">
                        <button
                            type="button"
                            class="app-toolbar-button mb-3 h-10 lg:hidden"
                            data-testid="dashboard-mobile-menu-button"
                            @click="openMobileNavigation"
                        >
                            <i class="pi pi-bars" aria-hidden="true"></i>
                            <span>Menu</span>
                        </button>
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

                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-2 sm:gap-3">
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
            </header>

            <div class="mx-auto w-full max-w-[1560px] px-4 py-5 sm:px-6 sm:py-6 xl:px-8 xl:py-8">
                <VerificationBanner v-if="authStore.role !== 'admin'" />
                <slot />
            </div>
        </section>

        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-200"
            enter-from-class="motion-safe:opacity-0"
            enter-to-class="motion-safe:opacity-100"
            leave-active-class="motion-safe:transition motion-safe:duration-150"
            leave-from-class="motion-safe:opacity-100"
            leave-to-class="motion-safe:opacity-0"
        >
            <div
                v-if="isMobileNavigationOpen"
                class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
                data-testid="dashboard-mobile-drawer-overlay"
                @click="closeMobileNavigation"
            ></div>
        </Transition>

        <Transition
            enter-active-class="motion-safe:transition motion-safe:duration-200"
            enter-from-class="motion-safe:-translate-x-full"
            enter-to-class="motion-safe:translate-x-0"
            leave-active-class="motion-safe:transition motion-safe:duration-150"
            leave-from-class="motion-safe:translate-x-0"
            leave-to-class="motion-safe:-translate-x-full"
        >
            <aside
                v-if="isMobileNavigationOpen"
                class="app-sidebar fixed inset-y-0 left-0 z-50 flex w-[min(88vw,22rem)] flex-col p-4 lg:hidden"
                data-testid="dashboard-mobile-drawer"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">
                            <i class="pi pi-briefcase" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Local Worker</p>
                            <p class="text-xs capitalize text-slate-500 dark:text-slate-400">{{ authStore.role }} workspace</p>
                        </div>
                    </div>

                    <button type="button" class="app-toolbar-button h-10 px-3" data-testid="dashboard-mobile-menu-close" @click="closeMobileNavigation">
                        <i class="pi pi-times" aria-hidden="true"></i>
                        <span class="sr-only">Close menu</span>
                    </button>
                </div>

                <div
                    class="app-status-chip mt-5 w-full justify-center sm:justify-start"
                    :class="canAccessProtectedFeatures ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                >
                    <i :class="['pi', verificationIcon]" aria-hidden="true"></i>
                    {{ verificationLabel }}
                </div>

                <nav class="mt-6 flex-1 space-y-2 overflow-y-auto pr-1" aria-label="Mobile section navigation" data-testid="mobile-navbar">
                    <RouterLink
                        v-for="item in navigation"
                        :key="item.path"
                        :to="item.path"
                        :data-testid="`mobile-nav-${item.label.toLowerCase().replaceAll(' ', '-')}`"
                        :class="[mobileSidebarLinkClass(item), 'flex w-full justify-between whitespace-normal rounded-2xl px-4 py-3 text-left']"
                    >
                        <span class="flex items-center gap-2">
                            <i :class="['pi', item.icon]" aria-hidden="true"></i>
                            <span>{{ item.label }}</span>
                        </span>
                        <i v-if="item.requiresVerified && !canAccessProtectedFeatures" class="pi pi-lock text-xs" aria-hidden="true"></i>
                    </RouterLink>
                </nav>

                <div class="mt-4 grid gap-2 border-t border-slate-200 pt-4 dark:border-white/10">
                    <RouterLink :to="dashboardPath" class="app-toolbar-button h-11 justify-center" @click="closeMobileNavigation">
                        <i class="pi pi-home" aria-hidden="true"></i>
                        Dashboard
                    </RouterLink>
                    <button type="button" class="app-toolbar-button h-11 justify-center" data-testid="dashboard-mobile-logout-button" @click="handleLogout">
                        <i class="pi pi-sign-out" aria-hidden="true"></i>
                        Logout
                    </button>
                </div>
            </aside>
        </Transition>
    </main>
</template>
