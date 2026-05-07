<script setup>
import { onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';

const bookingsStore = useCustomerBookingsStore();

const statuses = [
    { label: 'All statuses', value: '' },
    { label: 'Requested', value: 'requested' },
    { label: 'Pending', value: 'pending' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'In progress', value: 'in_progress' },
    { label: 'Completed', value: 'completed' },
    { label: 'Cancelled', value: 'cancelled' },
];

async function load(page = 1) {
    try {
        await bookingsStore.fetch(page);
    } catch {
        toast.error('Unable to load bookings');
    }
}

onMounted(() => load());
</script>

<template>
    <DashboardLayout title="My Bookings">
        <div class="space-y-5">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <FormSelect id="booking_status" v-model="bookingsStore.filters.status" label="Status" :options="statuses" option-label="label" option-value="value" @update:model-value="load()" />
            </section>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div v-if="bookingsStore.loading" class="p-4">
                    <SkeletonList :count="4" />
                </div>
                <div v-else-if="bookingsStore.bookings.length === 0" class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">No bookings yet.</div>
                <div v-else class="divide-y divide-gray-100 dark:divide-white/10">
                    <RouterLink v-for="booking in bookingsStore.bookings" :key="booking.id" :to="`/customer/bookings/${booking.id}`" class="block px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ booking.service?.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ booking.worker?.name || 'Awaiting final worker' }} · {{ booking.booking_date }} {{ booking.start_time }}</p>
                            </div>
                            <div class="text-sm sm:text-right">
                                <p class="font-semibold text-gray-900 dark:text-white">₹{{ booking.total_amount }}</p>
                                <p class="capitalize text-gray-500 dark:text-gray-400">{{ booking.status.replace('_', ' ') }}</p>
                            </div>
                        </div>
                    </RouterLink>
                </div>
            </div>

            <PaginationControls :meta="bookingsStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
