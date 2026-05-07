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
        { label: 'Notifications', path: '/notifications', icon: 'pi-bell' },
        { label: 'Profile', path: '/customer/profile', icon: 'pi-user' },
    ],
    worker: [
        { label: 'Dashboard', path: '/worker/dashboard', icon: 'pi-home' },
        { label: 'Requests', path: '/worker/booking-requests', icon: 'pi-inbox', requiresVerified: true },
        { label: 'Bookings', path: '/worker/bookings', icon: 'pi-calendar', requiresVerified: true },
        { label: 'Services', path: '/worker/services', icon: 'pi-briefcase', requiresVerified: true },
        { label: 'Availability', path: '/worker/availability', icon: 'pi-clock', requiresVerified: true },
        { label: 'Reviews', path: '/worker/reviews', icon: 'pi-star', requiresVerified: true },
        { label: 'Profile', path: '/worker/profile', icon: 'pi-user' },
    ],
};

const navigation = computed(() => navigationByRole[authStore.role] || []);
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
    <main class="min-h-screen bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-white">
        <aside class="fixed inset-y-0 left-0 hidden w-64 border-r border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 lg:block">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-950">
                    <i class="pi pi-briefcase" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">Local Worker</p>
                    <p class="text-xs capitalize text-gray-500 dark:text-gray-400">{{ authStore.role }} workspace</p>
                </div>
            </div>

            <div
                class="mt-5 inline-flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium"
                :class="canAccessProtectedFeatures ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
            >
                <i :class="['pi', verificationIcon]" aria-hidden="true"></i>
                {{ verificationLabel }}
            </div>

            <nav class="mt-8 space-y-1">
                <RouterLink
                    v-for="item in navigation"
                    :key="item.path"
                    :to="item.path"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                    :class="item.requiresVerified && !canAccessProtectedFeatures ? 'opacity-60' : ''"
                    active-class="bg-gray-900 text-white hover:bg-gray-900 dark:bg-white dark:text-gray-950 dark:hover:bg-white"
                >
                    <i :class="['pi', item.icon]" aria-hidden="true"></i>
                    <span class="flex-1">{{ item.label }}</span>
                    <i v-if="item.requiresVerified && !canAccessProtectedFeatures" class="pi pi-lock text-xs" aria-hidden="true"></i>
                </RouterLink>
            </nav>
        </aside>

        <section class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-white/10 dark:bg-gray-900/95">
                <div class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ authStore.role }}</p>
                            <span
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="canAccessProtectedFeatures ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                            >
                                <i :class="['pi', verificationIcon]" aria-hidden="true"></i>
                                {{ verificationLabel }}
                            </span>
                        </div>
                        <h1 class="truncate text-xl font-semibold text-gray-900 dark:text-white">{{ props.title }}</h1>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                        <NotificationDropdown />
                        <ThemeToggle />
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
                            @click="handleLogout"
                        >
                            <i class="pi pi-sign-out" aria-hidden="true"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </button>
                    </div>
                </div>

                <nav class="flex gap-2 overflow-x-auto px-4 pb-4 sm:px-6 lg:hidden">
                    <RouterLink
                        v-for="item in navigation"
                        :key="item.path"
                        :to="item.path"
                        class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 dark:border-white/10 dark:text-gray-200"
                        :class="item.requiresVerified && !canAccessProtectedFeatures ? 'opacity-60' : ''"
                        active-class="bg-gray-900 text-white dark:bg-white dark:text-gray-950"
                    >
                        <i :class="['pi', item.icon]" aria-hidden="true"></i>
                        {{ item.label }}
                        <i v-if="item.requiresVerified && !canAccessProtectedFeatures" class="pi pi-lock text-xs" aria-hidden="true"></i>
                    </RouterLink>
                </nav>
            </header>

            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <VerificationBanner v-if="authStore.role !== 'admin'" />
                <slot />
            </div>
        </section>
    </main>
</template>
