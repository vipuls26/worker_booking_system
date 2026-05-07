<script setup>
import DashboardCard from '../components/common/DashboardCard.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
</script>

<template>
    <DashboardLayout title="Customer Dashboard">
        <div class="grid gap-4 md:grid-cols-3">
            <DashboardCard eyebrow="Bookings" title="Find workers" description="Book trusted local workers for everyday services." />
            <DashboardCard eyebrow="Access" title="Customer only" description="Protected by Sanctum and role middleware." />
        </div>
    </DashboardLayout>
</template>
