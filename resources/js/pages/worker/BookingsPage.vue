<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import BookingTimeline from '../../components/common/BookingTimeline.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import RatingStars from '../../components/common/RatingStars.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormInput from '../../components/forms/FormInput.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import { useYupValidation } from '../../composables/useYupValidation';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useWorkerBookingsStore } from '../../stores/worker/bookings';
import { workerBookingStatusSchema, workerCustomerReviewSchema } from '../../validation/bookingSchemas';

const route = useRoute();
const router = useRouter();
const bookingsStore = useWorkerBookingsStore();
const expandedBookingId = ref(null);
const reviewingBookingId = ref(null);
const rejectionReason = ref('');
const cancellationReasons = reactive({});
const filtersReady = ref(false);
const reviewForm = reactive({
    rating: 0,
    review: '',
});
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(workerBookingStatusSchema);
const {
    validationErrors: reviewValidationErrors,
    clearValidationErrors: clearReviewValidationErrors,
    validateWithSchema: validateReviewWithSchema,
} = useYupValidation(workerCustomerReviewSchema);

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

function isActionSaving(booking, status) {
    return bookingsStore.activeStatusKey === `${booking.id}:${status}`;
}

async function load(page = 1) {
    try {
        await bookingsStore.fetch(page);
    } catch {
        toast.error('Unable to load bookings');
    }
}

useDebouncedWatch(
    () => [bookingsStore.filters.search, bookingsStore.filters.status],
    () => {
        if (! filtersReady.value) {
            return;
        }

        syncFiltersToRoute();
        load();
    },
);

async function updateStatus(booking, status) {
    if (bookingsStore.saving) {
        return;
    }

    try {
        const payload = { status };
        const trimmedRejectionReason = rejectionReason.value.trim();
        const trimmedCancellationReason = (cancellationReasons[booking.id] || '').trim();

        if (status === 'rejected') {
            payload.rejection_reason = trimmedRejectionReason;
        }

        if (status === 'cancelled') {
            payload.cancelled_reason = trimmedCancellationReason;
        }

        clearValidationErrors([
            `rejection_reason_${booking.id}`,
            `cancelled_reason_${booking.id}`,
        ]);

        const isValid = await validateWithSchema({
            status,
            rejection_reason: trimmedRejectionReason,
            cancelled_reason: trimmedCancellationReason,
        });

        if (! isValid) {
            if (validationErrors.value.rejection_reason) {
                validationErrors.value[`rejection_reason_${booking.id}`] = validationErrors.value.rejection_reason;
            }

            if (validationErrors.value.cancelled_reason) {
                validationErrors.value[`cancelled_reason_${booking.id}`] = validationErrors.value.cancelled_reason;
            }

            clearValidationErrors(['rejection_reason', 'cancelled_reason']);
            toast.error('Please fix the booking action fields.');

            return;
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
    clearReviewValidationErrors();
}

async function submitCustomerReview(booking) {
    clearReviewValidationErrors();
    const isValid = await validateReviewWithSchema(reviewForm);

    if (! isValid) {
        toast.error('Please complete the customer review.');

        return;
    }

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

function applyRouteFilters() {
    if (route.query.search !== undefined) {
        bookingsStore.filters.search = String(route.query.search);
    }

    if (route.query.status !== undefined) {
        bookingsStore.filters.status = String(route.query.status);
    }
}

function syncFiltersToRoute() {
    router.replace({
        path: route.path,
        query: {
            ...(bookingsStore.filters.search ? { search: bookingsStore.filters.search } : {}),
            ...(bookingsStore.filters.status ? { status: bookingsStore.filters.status } : {}),
        },
    });
}

onMounted(() => {
    applyRouteFilters();
    filtersReady.value = true;
    load();
});
</script>

<template>
    <DashboardLayout title="Manage Bookings">
        <div class="space-y-5">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_200px]">
                    <SearchFilter v-model="bookingsStore.filters.search" placeholder="Search customer, service, address, or issue" @search="load()" />
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
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
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

                        <div class="flex flex-col gap-2 xl:w-72">
                            <FormInput
                                v-if="booking.status === 'pending'"
                                v-model="rejectionReason"
                                :id="`worker_booking_rejection_reason_${booking.id}`"
                                label="Reject reason"
                                :data-testid="`worker-booking-rejection-reason-${booking.id}`"
                                placeholder="Tell the customer why you are rejecting this booking"
                                :error="validationErrors[`rejection_reason_${booking.id}`] || []"
                                :disabled="bookingsStore.saving"
                            />
                            <FormTextarea
                                v-if="canCancel(booking.status)"
                                :id="`worker_booking_cancel_reason_${booking.id}`"
                                v-model="cancellationReasons[booking.id]"
                                label="Cancellation reason"
                                rows="3"
                                placeholder="Tell the customer why you need to cancel"
                                :error="validationErrors[`cancelled_reason_${booking.id}`] || []"
                                :disabled="bookingsStore.saving"
                            />
                            <div v-for="action in actionsFor(booking.status)" :key="action.status">
                                <AppButton
                                    :icon="action.icon"
                                    :variant="action.danger ? 'danger' : 'primary'"
                                    :loading="isActionSaving(booking, action.status)"
                                    :disabled="bookingsStore.saving"
                                    :data-testid="`worker-booking-action-${action.status}-${booking.id}`"
                                    @click="updateStatus(booking, action.status)"
                                >
                                    {{ action.label }}
                                </AppButton>
                            </div>
                            <button type="button" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10" @click="toggleTimeline(booking)">
                                <i class="pi pi-history" aria-hidden="true"></i>
                                Timeline
                            </button>
                            <button
                                v-if="booking.status === 'completed' && !booking.worker_review"
                                type="button"
                                :data-testid="`worker-booking-open-review-${booking.id}`"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
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
                                <p v-if="reviewValidationErrors.rating?.length" class="text-sm text-red-600 dark:text-red-300">
                                    {{ reviewValidationErrors.rating[0] }}
                                </p>
                                <FormTextarea
                                    :id="`worker_booking_review_text_${booking.id}`"
                                    v-model="reviewForm.review"
                                    rows="3"
                                    label="Feedback"
                                    :data-testid="`worker-booking-review-text-${booking.id}`"
                                    placeholder="Share feedback about the customer"
                                    :error="reviewValidationErrors.review || []"
                                />
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
