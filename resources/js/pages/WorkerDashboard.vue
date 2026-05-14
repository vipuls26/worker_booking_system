<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import { workerDashboard } from '../api/worker/dashboard';
import AnalyticsBarChart from '../components/common/AnalyticsBarChart.vue';
import AnalyticsLineChart from '../components/common/AnalyticsLineChart.vue';
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
    earnings_periods: [],
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
const pendingRequestCount = computed(() => Number(analytics.value.pending_request_count || 0));
const upcomingBookings = computed(() => analytics.value.upcoming_bookings || []);
const availabilityConfigured = computed(() => analytics.value.availability.configured_days === 7);
const recentReviewPreview = computed(() => (analytics.value.recent_reviews || []).slice(0, 3));

const cardTones = ['blue', 'emerald', 'amber', 'violet'];

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

        <Transition appear enter-active-class="fade-up-enter-active" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
            <div v-if="!loading" class="grid gap-4 md:grid-cols-4">
                <DashboardCard
                    v-for="(card, index) in analytics.cards"
                    :key="card.label"
                    :eyebrow="card.label"
                    :title="['Paid by customers', 'Available for payout'].includes(card.label) ? `₹${card.value}` : String(card.value)"
                    description="Worker analytics"
                    :icon="['pi-wallet', 'pi-clock', 'pi-calendar', 'pi-star'][index] || 'pi-chart-bar'"
                    :tone="cardTones[index % cardTones.length]"
                />
            </div>
        </Transition>

        <Transition appear enter-active-class="fade-up-enter-active delay-50" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
            <section v-if="!loading" class="mt-6 grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                <AppPanel class="p-5">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Upcoming bookings</p>
                            <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Your next jobs</h2>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Use this view to know what is coming next before you open the full bookings page.</p>
                        </div>
                        <RouterLink to="/worker/bookings" class="inline-flex w-fit items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                            <i class="pi pi-arrow-right" aria-hidden="true"></i>
                            Manage bookings
                        </RouterLink>
                    </div>

                    <div v-if="upcomingBookings.length" class="mt-4 space-y-3">
                        <article v-for="booking in upcomingBookings" :key="booking.id" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ booking.service }}</p>
                                        <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                                            Booking #BK-{{ String(booking.id).padStart(6, '0') }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ booking.customer }} · {{ booking.booking_date }} · {{ booking.start_time }} - {{ booking.end_time }}</p>
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ booking.address }}</p>
                                </div>
                                <span class="inline-flex w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold capitalize text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    {{ booking.status.replace('_', ' ') }}
                                </span>
                            </div>
                        </article>
                    </div>
                    <div v-else class="mt-4 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                        No upcoming bookings yet. Accept new requests to fill your work queue.
                    </div>
                </AppPanel>

                <AppPanel class="p-5">
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Booking request notifications</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">What needs attention</h2>

                    <div class="mt-4 rounded-xl border p-4" :class="pendingRequestCount > 0 ? 'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/10' : 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/10'">
                        <p class="text-3xl font-semibold" :class="pendingRequestCount > 0 ? 'text-amber-900 dark:text-amber-100' : 'text-emerald-900 dark:text-emerald-100'">
                            {{ pendingRequestCount }}
                        </p>
                        <p class="mt-2 text-sm" :class="pendingRequestCount > 0 ? 'text-amber-800 dark:text-amber-200' : 'text-emerald-800 dark:text-emerald-200'">
                            {{ pendingRequestCount > 0 ? 'Pending customer request notifications are waiting for your response.' : 'No pending request notifications right now.' }}
                        </p>
                    </div>

                    <div class="mt-4 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                        <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-950">
                            <p class="font-semibold text-gray-900 dark:text-white">Availability status</p>
                            <p class="mt-1">
                                {{ availabilityConfigured ? 'Your weekly availability is configured for all seven days.' : 'Some weekdays are still missing working hours or off-day settings.' }}
                            </p>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2">
                            <RouterLink to="/worker/booking-requests" class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">
                                <i class="pi pi-inbox" aria-hidden="true"></i>
                                Review requests
                            </RouterLink>
                            <RouterLink to="/worker/availability" class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                                <i class="pi pi-calendar" aria-hidden="true"></i>
                                Update hours
                            </RouterLink>
                        </div>
                    </div>
                </AppPanel>
            </section>
        </Transition>

        <SkeletonCard v-if="loading" class="mt-6" :lines="4" :avatar="false" />

        <Transition appear enter-active-class="fade-up-enter-active delay-75" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
            <section v-if="!loading" class="app-surface app-surface-success mt-6 p-5">
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
                <AnalyticsLineChart title="Daily, weekly, monthly earnings" subtitle="Only paid bookings are counted here." :items="analytics.earnings_periods" value-prefix="₹" chart-type="pie" />
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
        </Transition>

        <Transition appear enter-active-class="fade-up-enter-active delay-100" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
            <AppPanel v-if="!loading" tone="brand" class="mt-6">
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
        </Transition>

        <div v-if="loading" class="mt-6 grid gap-5 lg:grid-cols-2">
            <SkeletonCard v-for="item in 4" :key="item" :lines="5" :avatar="false" />
        </div>

        <Transition appear enter-active-class="fade-up-enter-active delay-150" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
            <div v-if="!loading" class="mt-6 grid gap-5 lg:grid-cols-2">
                <AnalyticsBarChart title="Booking statuses" subtitle="Your active and completed booking split." :items="analytics.booking_statuses" />
                <AnalyticsTable title="Top services" :rows="analytics.top_services" :columns="serviceColumns" />
                <AnalyticsTable title="Recent reviews" :rows="recentReviewPreview" :columns="reviewColumns" />
            </div>
        </Transition>

        <Transition appear enter-active-class="fade-up-enter-active delay-200" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
            <div v-if="!loading" class="mt-6 grid gap-5 xl:grid-cols-[1fr_420px]">
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
        </Transition>
    </DashboardLayout>
</template>
