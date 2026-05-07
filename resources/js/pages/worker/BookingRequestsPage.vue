<script setup>
import { onMounted } from 'vue';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useWorkerBookingRequestsStore } from '../../stores/worker/bookingRequests';

const bookingRequestsStore = useWorkerBookingRequestsStore();

const statusOptions = [
    { label: 'All requests', value: '' },
    { label: 'Pending', value: 'pending' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Selected', value: 'selected' },
    { label: 'Cancelled', value: 'cancelled' },
];

function statusClass(status) {
    return {
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300': status === 'pending',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300': ['accepted', 'selected'].includes(status),
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300': ['rejected', 'cancelled'].includes(status),
    };
}

async function load(page = 1) {
    try {
        await bookingRequestsStore.fetch(page);
    } catch {
        toast.error('Unable to load booking requests');
    }
}

async function respond(bookingRequest, status) {
    try {
        await bookingRequestsStore.respond(bookingRequest.id, status);
        toast.success(`Request ${status}`);
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to update request');
    }
}

onMounted(load);
</script>

<template>
    <DashboardLayout title="Booking Requests">
        <div class="space-y-5">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="grid gap-4 sm:grid-cols-[240px_1fr] sm:items-end">
                    <FormSelect id="worker_request_status" v-model="bookingRequestsStore.filters.status" label="Status" :options="statusOptions" option-label="label" option-value="value" />
                    <div class="sm:w-36">
                        <AppButton icon="pi-filter" @click="load()">Filter</AppButton>
                    </div>
                </div>
            </section>

            <div v-if="bookingRequestsStore.loading">
                <SkeletonList :count="4" actions />
            </div>

            <div v-else-if="bookingRequestsStore.bookingRequests.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                No booking requests found.
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="bookingRequest in bookingRequestsStore.bookingRequests"
                    :key="bookingRequest.id"
                    class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-gray-900 dark:text-white">{{ bookingRequest.booking?.service?.name }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(bookingRequest.status)">
                                    {{ bookingRequest.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ bookingRequest.booking?.customer?.name }} · {{ bookingRequest.booking?.booking_date }}
                                {{ bookingRequest.booking?.start_time }} - {{ bookingRequest.booking?.end_time }}
                            </p>
                            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ bookingRequest.booking?.issue_description }}</p>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ bookingRequest.booking?.address }}</p>
                        </div>

                        <div v-if="bookingRequest.status === 'pending'" class="flex gap-2 sm:w-64">
                            <AppButton icon="pi-check" :loading="bookingRequestsStore.saving" @click="respond(bookingRequest, 'accepted')">Accept</AppButton>
                            <button
                                type="button"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                :disabled="bookingRequestsStore.saving"
                                @click="respond(bookingRequest, 'rejected')"
                            >
                                <i class="pi pi-times" aria-hidden="true"></i>
                                Reject
                            </button>
                        </div>
                    </div>
                </article>
            </div>

            <PaginationControls :meta="bookingRequestsStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
