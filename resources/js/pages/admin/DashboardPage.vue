<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminDashboard } from '../../api/admin';
import AnalyticsBarChart from '../../components/common/AnalyticsBarChart.vue';
import AnalyticsTable from '../../components/common/AnalyticsTable.vue';
import DashboardCard from '../../components/common/DashboardCard.vue';
import SkeletonCard from '../../components/common/SkeletonCard.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(true);
const stats = ref({
    total_users: 0,
    total_workers: 0,
    total_bookings: 0,
    total_revenue: 0,
    cards: [],
    revenue_reports: { monthly: [], by_status: [] },
    booking_statuses: [],
    popular_services: [],
});

const serviceColumns = [
    { key: 'name', label: 'Service', format: (row) => row.service?.name || 'Service' },
    { key: 'bookings_count', label: 'Bookings' },
    { key: 'revenue', label: 'Revenue', format: (row) => `₹${row.revenue}` },
];

const revenueSummary = computed(() => [
    { label: 'Gross booking value', value: stats.value.gross_booking_value || 0, icon: 'pi-wallet' },
    { label: 'Platform commission', value: stats.value.total_revenue || 0, icon: 'pi-percentage' },
    { label: 'Worker earnings', value: stats.value.worker_payouts || 0, icon: 'pi-briefcase' },
]);

onMounted(async () => {
    try {
        const response = await adminDashboard();
        stats.value = response.data.data;
    } catch {
        toast.error('Unable to load dashboard');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AdminLayout title="Dashboard">
        <div v-if="loading" class="grid gap-4 md:grid-cols-4">
            <SkeletonCard v-for="item in 4" :key="item" :lines="2" :avatar="false" />
        </div>

        <div v-else class="grid gap-4 md:grid-cols-4">
            <DashboardCard v-for="card in stats.cards" :key="card.label" :eyebrow="card.label" :title="card.label.toLowerCase().includes('revenue') ? `₹${card.value}` : String(card.value)" description="Platform analytics" />
        </div>

        <section v-if="!loading" class="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Money Flow</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Commission and payout split</h2>
                </div>
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                    <i class="pi pi-chart-line" aria-hidden="true"></i>
                    Paid payments
                </span>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div v-for="item in revenueSummary" :key="item.label" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ item.label }}</p>
                        <span class="inline-flex size-9 items-center justify-center rounded-md bg-white text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">
                            <i :class="['pi', item.icon]" aria-hidden="true"></i>
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">₹{{ item.value }}</p>
                </div>
            </div>
        </section>

        <div v-if="loading" class="mt-6 grid gap-5 lg:grid-cols-2">
            <SkeletonCard v-for="item in 4" :key="item" :lines="5" :avatar="false" />
        </div>

        <div v-else class="mt-6 grid gap-5 lg:grid-cols-2">
            <AnalyticsBarChart class="lg:col-span-2" title="Monthly commission graph" subtitle="Platform commission from paid bookings." :items="stats.revenue_reports.monthly" value-prefix="₹" variant="line" />
            <AnalyticsBarChart title="Booking status statistics" subtitle="Current booking workflow distribution." :items="stats.booking_statuses" />
            <AnalyticsBarChart title="Revenue by payment status" subtitle="Commission grouped by payment state." :items="stats.revenue_reports.by_status" value-prefix="₹" />
            <AnalyticsTable title="Popular services" :rows="stats.popular_services" :columns="serviceColumns" />
        </div>
    </AdminLayout>
</template>
