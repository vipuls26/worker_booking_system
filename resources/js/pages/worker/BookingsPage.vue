<script setup>
import { onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import BookingTimeline from '../../components/common/BookingTimeline.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import RatingStars from '../../components/common/RatingStars.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useWorkerBookingsStore } from '../../stores/worker/bookings';

const bookingsStore = useWorkerBookingsStore();
const expandedBookingId = ref(null);
const reviewingBookingId = ref(null);
const rejectionReason = ref('');
const cancellationReasons = reactive({});
const reviewForm = reactive({
    rating: 0,
    review: '',
});

const statusOptions = [
    { label: 'All bookings', value: '' },
    { label: 'Confirmed', value: 'confirmed' },
    { label: 'In progress', value: 'in_progress' },
    { label: 'Completed', value: 'completed' },
    { label: 'Rejected', value: 'rejected' },
    { label: 'Cancelled', value: 'cancelled' },
];

function actionsFor(status) {
    if (status === 'pending') {
        return [
            { label: 'Accept', status: 'accepted', icon: 'pi-check' },
            { label: 'Reject', status: 'rejected', icon: 'pi-times', danger: true },
            { label: 'Cancel', status: 'cancelled', icon: 'pi-ban', danger: true },
        ];
    }

    if (['accepted', 'confirmed'].includes(status)) {
        return [
            { label: 'Start work', status: 'in_progress', icon: 'pi-play' },
            { label: 'Cancel', status: 'cancelled', icon: 'pi-ban', danger: true },
        ];
    }

    if (status === 'in_progress') {
        return [
            { label: 'Complete', status: 'completed', icon: 'pi-flag' },
            { label: 'Cancel', status: 'cancelled', icon: 'pi-ban', danger: true },
        ];
    }

    return [];
}

function canCancel(status) {
    return ['pending', 'accepted', 'confirmed', 'in_progress'].includes(status);
}

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

async function updateStatus(booking, status) {
    try {
        const payload = { status };

        if (status === 'rejected') {
            payload.rejection_reason = rejectionReason.value || 'Worker rejected the booking.';
        }

        if (status === 'cancelled') {
            const reason = (cancellationReasons[booking.id] || '').trim();

            if (! reason) {
                toast.error('Please add a cancellation reason');
                return;
            }

            payload.cancelled_reason = reason;
        }

        await bookingsStore.updateStatus(booking.id, payload);
        toast.success('Booking updated');
        rejectionReason.value = '';
        delete cancellationReasons[booking.id];
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to update booking');
    }
}

function toggleTimeline(booking) {
    expandedBookingId.value = expandedBookingId.value === booking.id ? null : booking.id;
}

function openReview(booking) {
    reviewingBookingId.value = booking.id;
    reviewForm.rating = 0;
    reviewForm.review = '';
}

async function submitCustomerReview(booking) {
    try {
        await bookingsStore.submitCustomerReview(booking.id, reviewForm);
        toast.success('Customer feedback submitted');
        reviewingBookingId.value = null;
        reviewForm.rating = 0;
        reviewForm.review = '';
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to submit feedback');
    }
}

onMounted(load);
</script>

<template>
    <DashboardLayout title="Manage Bookings">
        <div class="space-y-5">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="max-w-sm">
                    <FormSelect id="worker_booking_status" v-model="bookingsStore.filters.status" label="Status" :options="statusOptions" option-label="label" option-value="value" />
                </div>
            </section>

            <div v-if="bookingsStore.loading">
                <SkeletonList :count="4" actions />
            </div>

            <div v-else-if="bookingsStore.bookings.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                No bookings found.
            </div>

            <div v-else class="space-y-4">
                <article v-for="booking in bookingsStore.bookings" :key="booking.id" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" :data-testid="`worker-booking-card-${booking.id}`">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold text-gray-900 dark:text-white">{{ booking.service?.name }}</h2>
                                <StatusBadge :value="booking.status" />
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ booking.customer?.name }} · {{ booking.booking_date }} {{ booking.start_time }} - {{ booking.end_time }}
                            </p>
                            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ booking.issue_description }}</p>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ booking.address }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                    Earning ₹{{ booking.worker_earning }}
                                </span>
                                <span
                                    class="rounded-full px-2.5 py-1 capitalize"
                                    :class="booking.payment_status === 'paid'
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                                        : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'"
                                >
                                    {{ booking.payment_status || 'unpaid' }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2 sm:w-72">
                            <input
                                v-if="booking.status === 'pending'"
                                v-model="rejectionReason"
                                type="text"
                                :data-testid="`worker-booking-rejection-reason-${booking.id}`"
                                class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                                placeholder="Reject reason"
                            >
                            <FormTextarea
                                v-if="canCancel(booking.status)"
                                :id="`worker_booking_cancel_reason_${booking.id}`"
                                v-model="cancellationReasons[booking.id]"
                                label="Cancellation reason"
                                rows="3"
                                placeholder="Tell the customer why you need to cancel"
                                required
                            />
                            <div v-for="action in actionsFor(booking.status)" :key="action.status">
                                <button
                                    v-if="action.danger"
                                    type="button"
                                    :data-testid="`worker-booking-action-${action.status}-${booking.id}`"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                    :disabled="bookingsStore.saving"
                                    @click="updateStatus(booking, action.status)"
                                >
                                    <i :class="['pi', action.icon]" aria-hidden="true"></i>
                                    {{ action.label }}
                                </button>
                                <AppButton v-else :icon="action.icon" :loading="bookingsStore.saving" :data-testid="`worker-booking-action-${action.status}-${booking.id}`" @click="updateStatus(booking, action.status)">
                                    {{ action.label }}
                                </AppButton>
                            </div>
                            <button type="button" class="inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10" @click="toggleTimeline(booking)">
                                <i class="pi pi-history" aria-hidden="true"></i>
                                Timeline
                            </button>
                            <button
                                v-if="booking.status === 'completed' && !booking.worker_review"
                                type="button"
                                :data-testid="`worker-booking-open-review-${booking.id}`"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                @click="openReview(booking)"
                            >
                                <i class="pi pi-star" aria-hidden="true"></i>
                                Review customer
                            </button>
                        </div>
                    </div>

                    <div v-if="booking.review || booking.worker_review || reviewingBookingId === booking.id" class="mt-5 grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Customer feedback for you</p>
                            <div v-if="booking.review" class="mt-3">
                                <RatingStars :model-value="booking.review.rating" readonly />
                                <p v-if="booking.review.review" class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ booking.review.review }}</p>
                            </div>
                            <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-400">Customer has not reviewed yet.</p>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Your feedback for customer</p>
                            <div v-if="booking.worker_review" class="mt-3">
                                <RatingStars :model-value="booking.worker_review.rating" readonly />
                                <p v-if="booking.worker_review.review" class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ booking.worker_review.review }}</p>
                            </div>
                            <form v-else-if="reviewingBookingId === booking.id" class="mt-3 space-y-3" :data-testid="`worker-booking-review-form-${booking.id}`" @submit.prevent="submitCustomerReview(booking)">
                                <RatingStars v-model="reviewForm.rating" :testid-prefix="`worker-booking-review-rating-${booking.id}`" />
                                <textarea
                                    v-model="reviewForm.review"
                                    rows="3"
                                    :data-testid="`worker-booking-review-text-${booking.id}`"
                                    class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white dark:focus:border-white dark:focus:ring-white"
                                    placeholder="Share feedback about the customer"
                                ></textarea>
                                <AppButton type="submit" icon="pi-star" :loading="bookingsStore.saving" :disabled="reviewForm.rating === 0" :data-testid="`worker-booking-review-submit-${booking.id}`">Submit feedback</AppButton>
                            </form>
                            <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-400">You have not reviewed this customer yet.</p>
                        </div>
                    </div>

                    <div v-if="expandedBookingId === booking.id" class="mt-5">
                        <BookingTimeline :timeline="booking.timeline" />
                    </div>

                </article>
            </div>

            <PaginationControls :meta="bookingsStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
