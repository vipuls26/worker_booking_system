<script setup>
import { onMounted, ref } from 'vue';
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
            <DashboardCard v-for="card in stats.cards" :key="card.label" :eyebrow="card.label" :title="card.label === 'Revenue' ? `₹${card.value}` : String(card.value)" description="Platform analytics" />
        </div>

        <div v-if="loading" class="mt-6 grid gap-5 lg:grid-cols-2">
            <SkeletonCard v-for="item in 4" :key="item" :lines="5" :avatar="false" />
        </div>

        <div v-else class="mt-6 grid gap-5 lg:grid-cols-2">
            <AnalyticsBarChart title="Monthly revenue" :items="stats.revenue_reports.monthly" value-prefix="₹" />
            <AnalyticsBarChart title="Booking statuses" :items="stats.booking_statuses" />
            <AnalyticsBarChart title="Revenue by status" :items="stats.revenue_reports.by_status" value-prefix="₹" />
            <AnalyticsTable title="Popular services" :rows="stats.popular_services" :columns="serviceColumns" />
        </div>
    </AdminLayout>
</template>
