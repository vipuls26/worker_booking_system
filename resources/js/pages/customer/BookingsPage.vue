<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import AppButton from '../../components/common/AppButton.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';

const bookingsStore = useCustomerBookingsStore();

const statuses = [
    { label: 'All statuses', value: '' },
    { label: 'Open requests', value: 'open' },
    { label: 'Worker selected', value: 'worker_selected' },
    { label: 'Cancelled', value: 'cancelled' },
];
const bookingSummary = computed(() => {
    const summary = {
        active: 0,
        unavailable: 0,
        history: 0,
    };

    bookingsStore.bookings.forEach((booking) => {
        const displayStatus = bookingDisplayStatus(booking);

        if (['open', 'worker_selected', 'awaiting_reschedule'].includes(displayStatus)) {
            summary.active += 1;
        } else if (displayStatus === 'unavailable') {
            summary.unavailable += 1;
        } else {
            summary.history += 1;
        }
    });

    return summary;
});
const emptyStateMessage = computed(() => {
    if (bookingsStore.filters.status === 'cancelled') {
        return 'No cancelled requests yet. Closed requests and refund-related decisions will appear here.';
    }

    if (bookingsStore.filters.status === 'worker_selected') {
        return 'No confirmed worker bookings yet. Accept a worker offer first and the official booking will appear here.';
    }

    if (bookingsStore.filters.status === 'open') {
        return 'No open requests right now. Create a new request and we will track worker responses here.';
    }

    return 'No service requests yet. Start with Find Workers and send your first booking request.';
});

async function load(page = 1) {
    try {
        await bookingsStore.fetch(page);
    } catch {
        toast.error('Unable to load bookings');
    }
}

useDebouncedWatch(
    () => bookingsStore.filters.status,
    () => load(),
);

onMounted(() => load());

function bookingDisplayStatus(booking) {
    if (booking.worker_requests?.some((request) => request.status === 'awaiting_reschedule')) {
        return 'awaiting_reschedule';
    }

    if (
        booking.status === 'open'
        && booking.worker_requests?.length
        && booking.worker_requests.every((request) => ['rejected', 'cancelled', 'expired'].includes(request.status))
    ) {
        return 'unavailable';
    }

    return booking.status;
}
</script>

<template>
    <DashboardLayout title="My Requests">
        <div class="space-y-5">
            <Transition appear enter-active-class="fade-up-enter-active" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
                <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.07)] dark:border-white/10 dark:bg-slate-900 dark:shadow-none">
                    <div class="border-b border-slate-200 bg-slate-50 px-4 py-4 dark:border-white/10 dark:bg-slate-950 sm:px-5">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Requests overview</p>
                        <h2 class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">Track every booking request in one place.</h2>
                    </div>
                    <div class="p-4 sm:p-5">
                        <FormSelect id="booking_status" v-model="bookingsStore.filters.status" label="Status" :options="statuses" option-label="label" option-value="value" />
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-slate-950">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Active</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ bookingSummary.active }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Open, accepted, or waiting for reschedule.</p>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-500/10">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">Unavailable</p>
                                <p class="mt-2 text-2xl font-semibold text-amber-900 dark:text-amber-100">{{ bookingSummary.unavailable }}</p>
                                <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Every invited worker declined this slot.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-slate-900">
                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">History</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-white">{{ bookingSummary.history }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Cancelled or completed requests from this page of results.</p>
                            </div>
                        </div>
                    </div>
                </section>
            </Transition>

            <Transition appear enter-active-class="fade-up-enter-active delay-100" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
                <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.07)] dark:border-white/10 dark:bg-slate-900 dark:shadow-none">
                    <div v-if="bookingsStore.loading" class="p-4">
                        <SkeletonList :count="4" />
                    </div>
                    <div v-else-if="bookingsStore.bookings.length === 0" class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        <p class="font-semibold text-slate-900 dark:text-white">{{ emptyStateMessage }}</p>
                        <div class="mt-4 inline-flex">
                            <RouterLink to="/customer/workers">
                                <AppButton type="button" icon="pi-search" :full-width="false">Find workers</AppButton>
                            </RouterLink>
                        </div>
                    </div>
                    <div v-else class="divide-y divide-slate-100 dark:divide-white/10">
                        <RouterLink v-for="booking in bookingsStore.bookings" :key="booking.id" :to="`/customer/bookings/${booking.id}`" class="block px-5 py-5 transition hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-slate-900 dark:text-white">{{ booking.service?.name }}</p>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-700 dark:bg-white/10 dark:text-slate-200">
                                            Request #SR-{{ String(booking.id).padStart(6, '0') }}
                                        </span>
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                                            :class="bookingDisplayStatus(booking) === 'awaiting_reschedule'
                                                ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                                                : bookingDisplayStatus(booking) === 'unavailable'
                                                    ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'
                                                    : 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200'"
                                        >
                                            {{ bookingDisplayStatus(booking).replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ booking.worker?.name || 'Awaiting final worker' }}</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ booking.booking_date }} at {{ booking.start_time }}</p>
                                    <p v-if="booking.booking?.id" class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">Booking #BK-{{ String(booking.booking.id).padStart(6, '0') }}</p>
                                </div>
                                <div class="text-sm sm:text-right">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Budget</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">₹{{ booking.total_amount }}</p>
                                </div>
                            </div>
                        </RouterLink>
                    </div>
                </div>
            </Transition>

            <PaginationControls :meta="bookingsStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
