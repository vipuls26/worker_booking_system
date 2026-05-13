<script setup>
import { onMounted, reactive } from 'vue';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useWorkerBookingRequestsStore } from '../../stores/worker/bookingRequests';

const bookingRequestsStore = useWorkerBookingRequestsStore();
const cancellationReasons = reactive({});

const statusOptions = [
    { label: 'All requests', value: '' },
    { label: 'Pending', value: 'pending' },
    { label: 'Accepted', value: 'accepted' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Cancelled', value: 'cancelled' },
    { label: 'Selected', value: 'selected' },
    { label: 'Not selected', value: 'not_selected' },
];

function statusClass(status) {
    return {
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300': status === 'pending',
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300': ['accepted', 'selected'].includes(status),
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300': ['rejected', 'cancelled', 'auto_cancelled', 'not_selected'].includes(status),
    };
}

async function load(page = 1) {
    try {
        await bookingRequestsStore.fetch(page);
    } catch {
        toast.error('Unable to load booking requests');
    }
}

useDebouncedWatch(
    () => bookingRequestsStore.filters.status,
    () => load(),
);

async function respond(bookingRequest, status) {
    try {
        const payload = { status };

        if (status === 'cancelled') {
            const reason = (cancellationReasons[bookingRequest.id] || '').trim();

            if (! reason) {
                toast.error('Please add a cancellation reason');
                return;
            }

            payload.response_reason = reason;
        }

        await bookingRequestsStore.respond(bookingRequest.id, payload);
        delete cancellationReasons[bookingRequest.id];
        toast.success(`Request ${status}`);
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to update request');
    }
}

onMounted(load);
</script>

<template>
    <DashboardLayout title="Booking Requests">
        <div class="space-y-5" data-testid="worker-booking-requests-page">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="max-w-sm">
                    <FormSelect id="worker_request_status" v-model="bookingRequestsStore.filters.status" label="Status" :options="statusOptions" option-label="label" option-value="value" />
                </div>
            </section>

            <div v-if="bookingRequestsStore.loading">
                <SkeletonList :count="4" actions />
            </div>

            <div v-else-if="bookingRequestsStore.bookingRequests.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                No customer requests found.
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="bookingRequest in bookingRequestsStore.bookingRequests"
                    :key="bookingRequest.id"
                    :data-testid="`worker-booking-request-card-${bookingRequest.id}`"
                    class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-gray-900 dark:text-white">{{ bookingRequest.service_request?.service?.name }}</h2>
                                <span :data-testid="`worker-booking-request-status-${bookingRequest.id}`" class="rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(bookingRequest.status)">
                                    {{ bookingRequest.status.replace('_', ' ') }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ bookingRequest.service_request?.customer?.name }} · {{ bookingRequest.service_request?.booking_date }}
                                {{ bookingRequest.service_request?.start_time }} - {{ bookingRequest.service_request?.end_time }}
                            </p>
                            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ bookingRequest.service_request?.issue_description }}</p>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ bookingRequest.service_request?.address }}</p>
                            <p class="mt-3 inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-200">
                                <i class="pi pi-info-circle" aria-hidden="true"></i>
                                Accept to join the customer's shortlist. Final booking starts only if the customer selects you.
                            </p>
                        </div>

                        <div v-if="bookingRequest.status === 'pending'" class="grid w-full gap-2 sm:w-80">
                            <FormTextarea
                                :id="`worker_request_cancel_reason_${bookingRequest.id}`"
                                v-model="cancellationReasons[bookingRequest.id]"
                                label="Cancellation reason"
                                rows="3"
                                placeholder="Tell the customer why you cannot take this request"
                                :data-testid="`worker-booking-request-cancel-reason-${bookingRequest.id}`"
                            />
                            <div class="grid grid-cols-2 gap-2">
                                <AppButton icon="pi-check" size="sm" :loading="bookingRequestsStore.saving" :data-testid="`worker-booking-request-accept-${bookingRequest.id}`" @click="respond(bookingRequest, 'accepted')">Accept</AppButton>
                                <button
                                    type="button"
                                    class="inline-flex w-full items-center justify-center gap-1.5 rounded-md border border-red-200 px-3 py-1.5 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                    :data-testid="`worker-booking-request-cancel-${bookingRequest.id}`"
                                    :disabled="bookingRequestsStore.saving"
                                    @click="respond(bookingRequest, 'cancelled')"
                                >
                                    <i class="pi pi-ban" aria-hidden="true"></i>
                                    Cancel
                                </button>
                            </div>
                        </div>

                        <div v-else-if="bookingRequest.response_reason" class="w-full rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300 lg:max-w-sm">
                            {{ bookingRequest.response_reason }}
                        </div>
                    </div>
                </article>
            </div>

            <PaginationControls :meta="bookingRequestsStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
