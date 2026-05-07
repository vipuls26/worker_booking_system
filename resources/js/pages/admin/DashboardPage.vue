<script setup>
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminDashboard } from '../../api/admin';
import DashboardCard from '../../components/common/DashboardCard.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(true);
const stats = ref({
    total_users: 0,
    total_workers: 0,
    total_bookings: 0,
    total_revenue: 0,
    popular_services: [],
});

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
        <div class="grid gap-4 md:grid-cols-4">
            <DashboardCard eyebrow="Users" :title="String(stats.total_users)" description="Registered accounts" />
            <DashboardCard eyebrow="Workers" :title="String(stats.total_workers)" description="Worker accounts" />
            <DashboardCard eyebrow="Bookings" :title="String(stats.total_bookings)" description="Total bookings" />
            <DashboardCard eyebrow="Revenue" :title="`₹${stats.total_revenue}`" description="Completed booking revenue" />
        </div>

        <section class="mt-6 rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Popular services</h2>
            <p v-if="loading" class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading...</p>
            <div v-else class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="service in stats.popular_services" :key="service.id" class="rounded-md border border-gray-200 p-4 dark:border-white/10">
                    <p class="font-medium text-gray-900 dark:text-white">{{ service.name }}</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ service.description || 'No description' }}</p>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
