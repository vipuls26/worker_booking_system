<script setup>
import DashboardCard from '../components/common/DashboardCard.vue';
import DashboardLayout from '../layouts/DashboardLayout.vue';
</script>

<template>
    <DashboardLayout title="Worker Dashboard">
        <div class="grid gap-4 md:grid-cols-3">
            <DashboardCard eyebrow="Jobs" title="Available work" description="Manage service requests and upcoming bookings." />
            <DashboardCard eyebrow="Access" title="Worker only" description="Protected by Sanctum and role middleware." />
        </div>
    </DashboardLayout>
</template>
