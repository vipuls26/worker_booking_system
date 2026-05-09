<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import { workerDashboard } from '../api/worker/dashboard';
import AnalyticsBarChart from '../components/common/AnalyticsBarChart.vue';
import AnalyticsTable from '../components/common/AnalyticsTable.vue';
import AppPanel from '../components/common/AppPanel.vue';
import DashboardCard from '../components/common/DashboardCard.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
import SkeletonCard from '../components/common/SkeletonCard.vue';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const loading = ref(true);
const analytics = ref({
    cards: [],
    earnings_chart: [],
    booking_statuses: [],
    top_services: [],
    recent_reviews: [],
    approved_services_count: 0,
    availability: {
        configured_days: 0,
        working_windows: 0,
        off_days: 0,
        today_windows: [],
        next_off_day: null,
    },
});

const earningsSummary = computed(() => [
    { label: 'Paid by customers', value: analytics.value.earnings || 0, icon: 'pi-indian-rupee' },
    { label: 'Available for payout', value: analytics.value.pending_payout || 0, icon: 'pi-wallet' },
    { label: 'Completed jobs', value: analytics.value.completed_bookings || 0, icon: 'pi-check-circle' },
]);

const serviceColumns = [
    { key: 'name', label: 'Service' },
    { key: 'bookings_count', label: 'Bookings' },
    { key: 'earnings', label: 'Earnings', format: (row) => `₹${row.earnings}` },
];

const reviewColumns = [
    { key: 'customer', label: 'Customer' },
    { key: 'rating', label: 'Rating', format: (row) => `${row.rating} ★` },
    { key: 'review', label: 'Review', format: (row) => row.review || 'No written review' },
];

const setupSteps = [
    {
        title: 'Profile verification',
        description: 'Keep proof and profile details ready for admin approval.',
        icon: 'pi-shield',
        to: '/worker/profile',
        done: () => authStore.isEmailVerified && authStore.isPlatformVerified,
    },
    {
        title: 'Service approval',
        description: 'Apply for categories and wait for admin approval before customers can book.',
        icon: 'pi-briefcase',
        to: '/worker/services',
        done: () => analytics.value.approved_services_count > 0,
    },
    {
        title: 'Weekly availability',
        description: 'Set working windows and off days so requests match your real schedule.',
        icon: 'pi-clock',
        to: '/worker/availability',
        done: () => analytics.value.availability.configured_days === 7,
    },
];

const workActions = [
    {
        title: 'New requests',
        description: 'Accept or reject customer requests sent to multiple workers.',
        icon: 'pi-inbox',
        to: '/worker/booking-requests',
    },
    {
        title: 'Active bookings',
        description: 'Move jobs through accepted, in progress, and completed states.',
        icon: 'pi-calendar',
        to: '/worker/bookings',
    },
    {
        title: 'Reviews',
        description: 'Read customer feedback and monitor your rating.',
        icon: 'pi-star',
        to: '/worker/reviews',
    },
];

onMounted(async () => {
    try {
        const response = await workerDashboard();
        analytics.value = response.data.data.analytics;
    } catch {
        toast.error('Unable to load dashboard analytics');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <DashboardLayout title="Worker Dashboard">
        <div v-if="loading" class="grid gap-4 md:grid-cols-4">
            <SkeletonCard v-for="item in 4" :key="item" :lines="2" :avatar="false" />
        </div>

        <div v-else class="grid gap-4 md:grid-cols-4">
            <DashboardCard
                v-for="card in analytics.cards"
                :key="card.label"
                :eyebrow="card.label"
                :title="['Paid by customers', 'Available for payout'].includes(card.label) ? `₹${card.value}` : String(card.value)"
                description="Worker analytics"
            />
        </div>

        <SkeletonCard v-if="loading" class="mt-6" :lines="4" :avatar="false" />

        <section v-else class="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Earnings</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Paid earning graph</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Track monthly worker earnings after customer payment.</p>
                </div>
                <RouterLink
                    to="/worker/bookings"
                    class="inline-flex w-fit items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    <i class="pi pi-calendar" aria-hidden="true"></i>
                    View bookings
                </RouterLink>
            </div>

            <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_340px]">
                <AnalyticsBarChart title="Monthly paid earnings" subtitle="Only paid bookings are counted here." :items="analytics.earnings_chart" value-prefix="₹" variant="line" />
                <div class="grid gap-3">
                    <div v-for="item in earningsSummary" :key="item.label" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ item.label }}</p>
                            <span class="inline-flex size-9 items-center justify-center rounded-md bg-white text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">
                                <i :class="['pi', item.icon]" aria-hidden="true"></i>
                            </span>
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ item.label === 'Completed jobs' ? item.value : `₹${item.value}` }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <AppPanel v-if="!loading" class="mt-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Availability</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Today’s working windows</h2>
                    <div v-if="analytics.availability.today_windows.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="window in analytics.availability.today_windows"
                            :key="`${window.start}-${window.end}`"
                            class="rounded-md bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                        >
                            {{ window.start }} - {{ window.end }}
                        </span>
                    </div>
                    <p v-else class="mt-2 text-sm text-gray-600 dark:text-gray-400">No working windows configured for today.</p>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center sm:min-w-[360px]">
                    <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Days</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ analytics.availability.configured_days }}/7</p>
                    </div>
                    <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Windows</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ analytics.availability.working_windows }}</p>
                    </div>
                    <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Next off</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ analytics.availability.next_off_day || 'None' }}</p>
                    </div>
                </div>

                <RouterLink
                    to="/worker/availability"
                    class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                >
                    <i class="pi pi-calendar" aria-hidden="true"></i>
                    Manage
                </RouterLink>
            </div>
        </AppPanel>

        <div v-if="loading" class="mt-6 grid gap-5 lg:grid-cols-2">
            <SkeletonCard v-for="item in 4" :key="item" :lines="5" :avatar="false" />
        </div>

        <div v-else class="mt-6 grid gap-5 lg:grid-cols-2">
            <AnalyticsBarChart title="Booking statuses" subtitle="Your active and completed booking split." :items="analytics.booking_statuses" />
            <AnalyticsTable title="Top services" :rows="analytics.top_services" :columns="serviceColumns" />
            <AnalyticsTable title="Recent reviews" :rows="analytics.recent_reviews" :columns="reviewColumns" />
        </div>

        <div class="mt-6 grid gap-5 xl:grid-cols-[1fr_420px]">
            <AppPanel>
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Setup Flow</p>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Become bookable</h2>
                    </div>
                    <RouterLink to="/worker/profile" class="text-sm font-semibold text-gray-700 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white">Profile</RouterLink>
                </div>

                <div class="mt-4 grid gap-3">
                    <RouterLink v-for="step in setupSteps" :key="step.title" :to="step.to" class="flex gap-3 rounded-lg border border-gray-200 p-4 transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            <i :class="['pi', step.done() ? 'pi-check' : step.icon]" aria-hidden="true"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ step.title }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="step.done() ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                                >
                                    {{ step.done() ? 'Done' : 'Needs setup' }}
                                </span>
                            </span>
                            <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">{{ step.description }}</span>
                        </span>
                    </RouterLink>
                </div>
            </AppPanel>

            <AppPanel>
                <div>
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Daily Work</p>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Run bookings</h2>
                </div>

                <div class="mt-4 grid gap-3">
                    <RouterLink v-for="action in workActions" :key="action.title" :to="action.to" class="rounded-lg border border-gray-200 p-4 transition hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-9 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                <i :class="['pi', action.icon]" aria-hidden="true"></i>
                            </span>
                            <span>
                                <span class="block font-semibold text-gray-900 dark:text-white">{{ action.title }}</span>
                                <span class="mt-1 block text-sm text-gray-600 dark:text-gray-400">{{ action.description }}</span>
                            </span>
                        </div>
                    </RouterLink>
                </div>
            </AppPanel>
        </div>
    </DashboardLayout>
</template>
