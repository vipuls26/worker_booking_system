 <script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, RouterLink, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import BookingTimeline from '../../components/common/BookingTimeline.vue';
import RatingStars from '../../components/common/RatingStars.vue';
import SkeletonCard from '../../components/common/SkeletonCard.vue';
import BookingStatusTracker from '../../components/customer/BookingStatusTracker.vue';
import FormInput from '../../components/forms/FormInput.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useYupValidation } from '../../composables/useYupValidation';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { createDispute } from '../../api/disputes';
import { getWorker as getCustomerWorker } from '../../api/customer/workers';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';
import { disputeSchema, rescheduleSchema } from '../../validation/bookingSchemas';
import { currentLocalLatestBookingDateString } from '../../validation/shared';

function localDateString() {
    const today = new Date();
    const timezoneOffsetInMilliseconds = today.getTimezoneOffset() * 60 * 1000;

    return new Date(today.getTime() - timezoneOffsetInMilliseconds).toISOString().slice(0, 10);
}

function addDaysToLocalDate(dateString, daysToAdd) {
    const date = new Date(`${dateString}T00:00:00`);
    date.setDate(date.getDate() + daysToAdd);

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const route = useRoute();
const router = useRouter();
const bookingsStore = useCustomerBookingsStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const {
    validationErrors: rescheduleValidationErrors,
    clearValidationErrors: clearRescheduleValidationErrors,
    validateWithSchema: validateRescheduleWithSchema,
} = useYupValidation(rescheduleSchema);
const {
    validationErrors: disputeValidationErrors,
    clearValidationErrors: clearDisputeValidationErrors,
    validateWithSchema: validateDisputeWithSchema,
} = useYupValidation(disputeSchema);
const cancelReason = ref('');
const disputeSaving = ref(false);
const bookAgainPrefill = ref(null);
const checkingBookAgainAvailability = ref(false);
const booking = computed(() => bookingsStore.booking);
const officialBooking = computed(() => booking.value?.booking || null);
const activeDisputes = computed(() => officialBooking.value?.disputes?.filter((dispute) => ['open', 'under_review'].includes(dispute.status)) || []);
const canOpenDispute = computed(() => {
    if (! officialBooking.value) {
        return false;
    }

    return ! ['pending', 'rejected', 'cancelled'].includes(officialBooking.value.status)
        && activeDisputes.value.length === 0;
});
const canPayBooking = computed(() => officialBooking.value?.status === 'completed' && officialBooking.value?.payment_status !== 'paid');
const canBookAgain = computed(() => officialBooking.value?.status === 'completed' && bookAgainPrefill.value);
const lockedCommissionRate = computed(() => officialBooking.value?.quote?.commission_rate || officialBooking.value?.commission_rate);
const paidCommissionRate = computed(() => officialBooking.value?.latest_payment?.commission_rate || lockedCommissionRate.value);
const paymentButtonLabel = computed(() => {
    if (officialBooking.value?.payment_status === 'paid') {
        return 'Paid';
    }

    if (officialBooking.value?.status !== 'completed') {
        return 'Available after completion';
    }

    return 'Pay now';
});
const serviceRequestReference = computed(() => `SR-${String(booking.value?.id || 0).padStart(6, '0')}`);
const officialBookingReference = computed(() => (
    officialBooking.value?.id ? `BK-${String(officialBooking.value.id).padStart(6, '0')}` : null
));
const acceptedRequests = computed(() => booking.value?.worker_requests?.filter((request) => request.status === 'accepted') || []);
const selectedRequests = computed(() => booking.value?.worker_requests?.filter((request) => request.status === 'selected') || []);
const comparisonRequests = computed(() => [...selectedRequests.value, ...acceptedRequests.value]);
const awaitingRescheduleRequests = computed(() => booking.value?.worker_requests?.filter((request) => request.status === 'awaiting_reschedule') || []);
const assignedRescheduleWorkerId = computed(() => awaitingRescheduleRequests.value[0]?.worker_id || null);
const needsReschedule = computed(() => booking.value?.status === 'open' && awaitingRescheduleRequests.value.length > 0 && !officialBooking.value);
const hasNoWorkerOptions = computed(() => (
    booking.value?.status === 'open'
    && !officialBooking.value
    && Boolean(booking.value?.worker_requests?.length)
    && booking.value.worker_requests.every((request) => ['rejected', 'cancelled', 'expired'].includes(request.status))
));
const hasAcceptedWorkerChoices = computed(() => comparisonRequests.value.length > 0);
const showWaitingForWorkerAcceptance = computed(() => (
    booking.value?.status === 'open'
    && !officialBooking.value
    && !needsReschedule.value
    && !hasNoWorkerOptions.value
    && !hasAcceptedWorkerChoices.value
    && Boolean(booking.value?.worker_requests?.length)
));
const showWorkerResponseSection = computed(() => (
    hasAcceptedWorkerChoices.value
    || showWaitingForWorkerAcceptance.value
    || hasNoWorkerOptions.value
));
const bookingDisplayStatus = computed(() => {
    if (needsReschedule.value) {
        return 'awaiting_reschedule';
    }

    if (hasNoWorkerOptions.value) {
        return 'unavailable';
    }

    return booking.value?.booking?.status || booking.value?.status || 'open';
});
const bookingDisplayWorkerName = computed(() => awaitingRescheduleRequests.value[0]?.worker?.name || booking.value?.worker?.name || 'Awaiting final worker');
const quickRescheduleSlots = ref([]);
const quickRescheduleLoading = ref(false);
const reviewForm = reactive({
    rating: 0,
    review: '',
});
const rescheduleForm = reactive({
    booking_date: '',
    start_time: '',
    end_time: '',
    duration_minutes: 60,
});
const disputeForm = reactive({
    category: '',
    title: '',
    description: '',
});
const disputeCategories = [
    { label: 'Service issue', value: 'service_issue' },
    { label: 'Payment issue', value: 'payment_issue' },
    { label: 'Worker no show', value: 'worker_no_show' },
    { label: 'Customer issue', value: 'customer_issue' },
    { label: 'Other', value: 'other' },
];
const minimumRescheduleDate = computed(() => localDateString());
const maximumRescheduleDate = computed(() => currentLocalLatestBookingDateString());
const minimumRescheduleTime = computed(() => rescheduleForm.booking_date === minimumRescheduleDate.value ? roundTimeUpToFiveMinutes(localTimeString()) : '');

async function load() {
    try {
        await bookingsStore.fetchOne(route.params.id);
        syncRescheduleForm();
        await loadQuickRescheduleSlots();
        await refreshBookAgainAvailability();
    } catch {
        toast.error('Unable to load booking');
    }
}

async function cancel() {
    if (bookingsStore.saving) {
        return;
    }

    try {
        await bookingsStore.cancel(route.params.id, { cancelled_reason: cancelReason.value });
        toast.success('Booking cancelled');
        cancelReason.value = '';
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to cancel booking');
    }
}

async function selectWorker(bookingRequest) {
    if (bookingsStore.saving) {
        return;
    }

    try {
        await bookingsStore.selectWorker(route.params.id, { worker_request_id: bookingRequest.id });
        toast.success('Worker selected');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to select worker');
    }
}

async function reschedule() {
    if (bookingsStore.saving) {
        return;
    }

    clearApiErrors();
    clearRescheduleValidationErrors();

    if (! await validateRescheduleSchedule()) {
        toast.error('Please fix the highlighted reschedule fields.');

        return;
    }

    try {
        await bookingsStore.reschedule(route.params.id, rescheduleForm);
        syncRescheduleForm();
        await loadQuickRescheduleSlots();
        toast.success('Booking request rescheduled');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to reschedule booking');
    }
}

function clearFrontendErrors() {
    clearRescheduleValidationErrors();
}

async function payBooking() {
    if (bookingsStore.saving) {
        return;
    }

    if (! canPayBooking.value) {
        toast.info('Payment is available after the worker completes the service.');
        return;
    }

    try {
        await bookingsStore.pay(route.params.id, { provider: 'manual' });
        toast.success('Payment successful');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to process payment');
    }
}

async function bookAgain() {
    if (! canBookAgain.value) {
        return;
    }

    try {
        const prefill = await prepareBookAgainPrefill();

        sessionStorage.setItem(`book-again:${prefill.source_booking_id}`, JSON.stringify(prefill));
        toast.success('Booking details prefilled');
        await router.push({
            name: 'customer.workers.show',
            params: { id: prefill.worker_id },
            query: {
                book_again_from: prefill.source_booking_id,
            },
        });
    } catch (error) {
        toast.error(error.response?.data?.message || firstErrorFromResponse(error) || 'Unable to book again');
    }
}

async function refreshBookAgainAvailability() {
    bookAgainPrefill.value = null;

    if (officialBooking.value?.status !== 'completed') {
        return;
    }

    checkingBookAgainAvailability.value = true;

    try {
        bookAgainPrefill.value = await prepareBookAgainPrefill();
    } catch {
        bookAgainPrefill.value = null;
    } finally {
        checkingBookAgainAvailability.value = false;
    }
}

async function prepareBookAgainPrefill() {
    const response = await bookingsStore.prepareBookAgain(route.params.id);

    return response.data.prefill;
}

async function submitReview() {
    try {
        await bookingsStore.submitReview(booking.value.booking_id, reviewForm);
        toast.success('Review submitted');
        reviewForm.rating = 0;
        reviewForm.review = '';
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to submit review');
    }
}

async function submitDispute() {
    if (! officialBooking.value?.id) {
        toast.error('Booking is not ready for dispute yet.');
        return;
    }

    clearApiErrors();
    clearDisputeValidationErrors();
    disputeSaving.value = true;

    const isValid = await validateDisputeWithSchema(disputeForm);

    if (! isValid) {
        disputeSaving.value = false;
        toast.error('Please fix the highlighted dispute fields.');

        return;
    }

    try {
        await createDispute({
            booking_id: officialBooking.value.id,
            ...disputeForm,
        });
        toast.success('Dispute opened');
        disputeForm.category = '';
        disputeForm.title = '';
        disputeForm.description = '';
        await load();
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to open dispute');
    } finally {
        disputeSaving.value = false;
    }
}

function matchingService(request) {
    const serviceId = Number(booking.value?.service?.id);

    return request.worker?.services?.find((service) => Number(service.service_id) === serviceId);
}

function priceLabel(request) {
    if (request.quoted_price) {
        return request.pricing_type === 'hourly' ? `₹${request.quoted_price}/hr` : `₹${request.quoted_price} fixed`;
    }

    const service = matchingService(request);

    if (! service) {
        return 'Price not shared';
    }

    if (service.pricing_type === 'hourly') {
        return `₹${service.price}/hr`;
    }

    return `₹${service.price} fixed`;
}

function minimumLabel(request) {
    if (request.pricing_type === 'hourly') {
        return `Min ${request.minimum_hours || 1}h`;
    }

    const service = matchingService(request);

    if (! service || service.pricing_type !== 'hourly') {
        return null;
    }

    return `Min ${service.minimum_hours || 1}h`;
}

function ratingLabel(worker) {
    return Number(worker?.rating_average || 0).toFixed(1);
}

function reviewLabel(worker) {
    const count = Number(worker?.reviews_count || 0);

    return `${count} ${count === 1 ? 'review' : 'reviews'}`;
}

async function loadQuickRescheduleSlots() {
    quickRescheduleSlots.value = [];

    if (! needsReschedule.value || ! assignedRescheduleWorkerId.value || ! booking.value?.service?.id) {
        return;
    }

    quickRescheduleLoading.value = true;

    try {
        const desiredDurationMinutes = calculateDurationMinutes(
            booking.value.start_time,
            booking.value.end_time,
        );

        const searchStartDate = booking.value.booking_date && booking.value.booking_date >= localDateString()
            ? booking.value.booking_date
            : localDateString();

        let cursorDate = searchStartDate;
        const latestBookingDate = currentLocalLatestBookingDateString();
        const suggestedSlots = [];

        // Check a few upcoming days so customers can quickly pick the next workable slot.
        for (let dayIndex = 0; dayIndex < 7 && suggestedSlots.length < 6 && cursorDate <= latestBookingDate; dayIndex++) {
            const response = await getCustomerWorker(assignedRescheduleWorkerId.value, {
                available_date: cursorDate,
                slot_minutes: desiredDurationMinutes,
                service_id: booking.value.service.id,
            });

            const availableSlots = (response.data.data.availability || [])
                .filter((slot) => slot.available)
                .slice(0, Math.max(0, 6 - suggestedSlots.length))
                .map((slot) => ({
                    booking_date: cursorDate,
                    start_time: slot.start_time,
                    end_time: slot.end_time,
                    label: `${cursorDate} · ${slot.start_time} - ${slot.end_time}`,
                }));

            suggestedSlots.push(...availableSlots);

            cursorDate = addDaysToLocalDate(cursorDate, 1);
        }

        quickRescheduleSlots.value = suggestedSlots;
    } finally {
        quickRescheduleLoading.value = false;
    }
}

function applyQuickRescheduleSlot(slot) {
    rescheduleForm.booking_date = slot.booking_date;
    rescheduleForm.start_time = slot.start_time;
    rescheduleForm.end_time = slot.end_time;
    rescheduleForm.duration_minutes = calculateDurationMinutes(slot.start_time, slot.end_time);
}

function isQuickRescheduleSlotSelected(slot) {
    return rescheduleForm.booking_date === slot.booking_date
        && rescheduleForm.start_time === slot.start_time
        && rescheduleForm.end_time === slot.end_time;
}

function firstErrorFromResponse(error) {
    const responseErrors = error.response?.data?.errors || {};
    const firstField = Object.keys(responseErrors)[0];

    return firstField && Array.isArray(responseErrors[firstField]) ? responseErrors[firstField][0] : '';
}

function syncRescheduleForm() {
    if (! booking.value) {
        return;
    }

    rescheduleForm.booking_date = booking.value.booking_date || '';
    rescheduleForm.start_time = booking.value.start_time || '';
    rescheduleForm.end_time = booking.value.end_time || '';
    rescheduleForm.duration_minutes = calculateDurationMinutes(rescheduleForm.start_time, rescheduleForm.end_time);
}

function validateRescheduleSchedule() {
    clearRescheduleValidationErrors();

    return validateRescheduleWithSchema(rescheduleForm);
}

function calculateDurationMinutes(startTime, endTime) {
    if (!startTime || !endTime) {
        return 60;
    }

    const [startHour, startMinute] = startTime.split(':').map(Number);
    const [endHour, endMinute] = endTime.split(':').map(Number);

    return ((endHour * 60) + endMinute) - ((startHour * 60) + startMinute);
}

function localTimeString() {
    const now = new Date();

    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
}

function roundTimeUpToFiveMinutes(time) {
    const [hours, minutes] = time.split(':').map(Number);
    const roundedMinutes = Math.ceil(minutes / 5) * 5;
    const roundedDate = new Date();

    roundedDate.setHours(hours, roundedMinutes, 0, 0);

    return `${String(roundedDate.getHours()).padStart(2, '0')}:${String(roundedDate.getMinutes()).padStart(2, '0')}`;
}

onMounted(load);

watch(
    () => [
        needsReschedule.value,
        assignedRescheduleWorkerId.value,
        booking.value?.service?.id || null,
        booking.value?.booking_date || null,
        booking.value?.start_time || null,
        booking.value?.end_time || null,
    ],
    () => {
        void loadQuickRescheduleSlots();
    },
);

watch(
    () => rescheduleForm.booking_date,
    (bookingDate) => {
        clearRescheduleValidationErrors('booking_date');

        if (bookingDate === minimumRescheduleDate.value && rescheduleForm.start_time && rescheduleForm.start_time < minimumRescheduleTime.value) {
            rescheduleForm.start_time = '';
            clearRescheduleValidationErrors();
            validateRescheduleWithSchema(rescheduleForm);
        }
    },
);

watch(
    () => rescheduleForm.start_time,
    (startTime) => {
        if (! startTime) {
            clearRescheduleValidationErrors('start_time');

            return;
        }

        if (rescheduleForm.booking_date === minimumRescheduleDate.value && startTime < minimumRescheduleTime.value) {
            rescheduleForm.start_time = '';
            clearRescheduleValidationErrors();
            validateRescheduleWithSchema(rescheduleForm);

            return;
        }

        clearRescheduleValidationErrors('start_time');
    },
);

watch(() => disputeForm.category, () => clearDisputeValidationErrors('category'));
watch(() => disputeForm.title, () => clearDisputeValidationErrors('title'));
watch(() => disputeForm.description, () => clearDisputeValidationErrors('description'));
</script>

<template>
    <DashboardLayout title="Booking Details">
        <div v-if="bookingsStore.loading" class="space-y-5">
            <SkeletonCard :lines="4" :avatar="false" />
            <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
                <SkeletonCard :lines="6" :avatar="false" />
                <SkeletonCard :lines="5" />
            </div>
        </div>

        <div v-else-if="booking" class="space-y-5" data-testid="booking-detail-page">
            <RouterLink to="/customer/bookings" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                <i class="pi pi-arrow-left text-xs" aria-hidden="true"></i>
                Back to bookings
            </RouterLink>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ booking.service?.name }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ bookingDisplayWorkerName }} · {{ booking.booking_date }} {{ booking.start_time }} - {{ booking.end_time }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-700 dark:bg-white/10 dark:text-slate-200">
                                Request #{{ serviceRequestReference }}
                            </span>
                            <span
                                v-if="officialBookingReference"
                                class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                            >
                                Booking #{{ officialBookingReference }}
                            </span>
                        </div>
                    </div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">₹{{ booking.total_amount }}</p>
                </div>

                <div class="mt-6">
                    <BookingStatusTracker :status="bookingDisplayStatus" />
                </div>

                <div v-if="needsReschedule" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Worker availability changed</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                        {{ awaitingRescheduleRequests[0]?.worker?.name || 'The worker' }} is no longer available for this time slot. Please choose a new time or cancel this request.
                    </p>
                    <div class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700 dark:text-amber-300">Next available slots</p>
                        <div v-if="quickRescheduleLoading" class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                            Finding the next available slots...
                        </div>
                        <div v-else-if="quickRescheduleSlots.length" class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="slot in quickRescheduleSlots"
                                :key="slot.label"
                                type="button"
                                :class="[
                                    'rounded-md border px-3 py-2 text-sm font-medium transition',
                                    isQuickRescheduleSlotSelected(slot)
                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-800 ring-2 ring-emerald-200 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/20'
                                        : 'border-amber-300 bg-white text-amber-800 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-slate-900 dark:text-amber-200 dark:hover:bg-amber-500/10',
                                ]"
                                @click="applyQuickRescheduleSlot(slot)"
                            >
                                {{ slot.label }}
                            </button>
                        </div>
                        <p v-else class="mt-3 text-sm text-amber-700 dark:text-amber-300">
                            No quick slots found yet. You can still choose another time manually or cancel this request.
                        </p>
                    </div>
                </div>

                <div v-if="canBookAgain" class="mt-5 flex justify-end">
                    <AppButton type="button" icon="pi-refresh" :loading="bookingsStore.saving || checkingBookAgainAvailability" data-testid="booking-book-again-button" @click="bookAgain">
                        Book Again
                    </AppButton>
                </div>
            </section>

            <section v-if="officialBooking" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Payment</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                            {{ officialBooking.payment_status === 'paid' ? 'Payment completed' : 'Pay after service completion' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Platform commission: ₹{{ officialBooking.platform_commission }} · Worker earning: ₹{{ officialBooking.worker_earning }}
                        </p>
                        <p class="mt-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                            Commission rate locked at booking: {{ lockedCommissionRate }}%
                            <template v-if="officialBooking.latest_payment">
                                · Paid at {{ paidCommissionRate }}%
                            </template>
                        </p>
                        <p v-if="officialBooking.payment_status !== 'paid' && officialBooking.status !== 'completed'" class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300">
                            Payment unlocks when the worker marks this booking as completed.
                        </p>
                        <p v-if="officialBooking.latest_payment" class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Transaction {{ officialBooking.latest_payment.transaction_reference }}
                        </p>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-4 text-left dark:bg-gray-950 sm:min-w-64">
                        <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Amount</p>
                        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">₹{{ officialBooking.total_amount }}</p>
                        <div class="mt-4">
                            <AppButton
                                v-if="officialBooking.payment_status !== 'paid'"
                                type="button"
                                icon="pi-credit-card"
                                :loading="bookingsStore.saving"
                                :disabled="!canPayBooking"
                                data-testid="booking-pay-button"
                                @click="payBooking"
                            >
                                {{ paymentButtonLabel }}
                            </AppButton>
                            <span v-else class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <i class="pi pi-check-circle" aria-hidden="true"></i>
                                Paid
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="officialBooking" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Dispute</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Raise a booking issue</h3>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Open a dispute when this booking needs admin review.</p>
                    </div>
                    <RouterLink to="/customer/disputes" class="inline-flex w-fit items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                        <i class="pi pi-list" aria-hidden="true"></i>
                        My disputes
                    </RouterLink>
                </div>

                <div v-if="activeDisputes.length" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/20 dark:bg-amber-500/10">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Active dispute already open</p>
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">{{ activeDisputes[0].title }} · {{ activeDisputes[0].status.replace('_', ' ') }}</p>
                </div>

                <form v-else-if="canOpenDispute" class="mt-5 grid gap-4 lg:grid-cols-2" data-testid="booking-dispute-form" @submit.prevent="submitDispute">
                    <FormSelect id="dispute_category" v-model="disputeForm.category" label="Category" :options="disputeCategories" option-label="label" option-value="value" :error="disputeValidationErrors.category || errors.category || []" data-testid="booking-dispute-category" />
                    <FormInput id="dispute_title" v-model="disputeForm.title" label="Title" :error="disputeValidationErrors.title || errors.title || []" data-testid="booking-dispute-title" />
                    <FormTextarea id="dispute_description" v-model="disputeForm.description" class="lg:col-span-2" label="Description" rows="5" placeholder="Explain what happened and what support you need." :error="disputeValidationErrors.description || errors.description || []" data-testid="booking-dispute-description" />
                    <div class="lg:col-span-2 sm:w-48">
                        <AppButton type="submit" icon="pi-exclamation-circle" :loading="disputeSaving" data-testid="booking-dispute-submit">Open dispute</AppButton>
                    </div>
                </form>

                <p v-else class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                    Disputes open after a worker is selected and the booking moves beyond request stage.
                </p>
            </section>

            <section v-if="showWorkerResponseSection" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            {{ hasAcceptedWorkerChoices ? (booking.worker_requests.length > 1 ? 'Compare accepted workers' : 'Worker response') : 'Worker availability' }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ hasAcceptedWorkerChoices ? (booking.worker_requests.length > 1 ? 'Choose one accepted worker to create the official booking.' : 'Your worker has accepted. Confirm them to create the official booking.') : 'We will show the worker here after someone accepts your booking request.' }}
                        </p>
                    </div>
                    <span v-if="hasAcceptedWorkerChoices && booking.status === 'open'" class="rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                        {{ acceptedRequests.length }} accepted
                    </span>
                </div>

                <div v-if="hasAcceptedWorkerChoices" class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article v-for="request in comparisonRequests" :key="request.id" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-gray-950">
                        <div class="flex flex-col gap-4 min-[420px]:flex-row">
                            <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-900 dark:text-gray-500">
                                <img v-if="request.worker?.profile?.profile_photo_url" :src="request.worker.profile.profile_photo_url" :alt="request.worker.name" class="size-full object-cover">
                                <i v-else class="pi pi-user text-xl" aria-hidden="true"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h4 class="truncate font-semibold text-gray-900 dark:text-white">{{ request.worker?.name }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ request.worker?.profile?.city || 'City not set' }}</p>
                                    </div>
                                    <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="request.status === 'selected' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-950' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'">
                                        {{ request.status.replace('_', ' ') }}
                                    </span>
                                </div>

                                <div class="mt-4 grid grid-cols-1 gap-2 text-sm min-[420px]:grid-cols-2">
                                    <div class="rounded-md bg-gray-50 p-3 dark:bg-white/5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Price</p>
                                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ priceLabel(request) }}</p>
                                        <p v-if="minimumLabel(request)" class="text-xs text-gray-500 dark:text-gray-400">{{ minimumLabel(request) }}</p>
                                    </div>
                                    <div class="rounded-md bg-gray-50 p-3 dark:bg-white/5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Rating</p>
                                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ ratingLabel(request.worker) }} ★</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ reviewLabel(request.worker) }}</p>
                                    </div>
                                    <div class="rounded-md bg-gray-50 p-3 dark:bg-white/5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Experience</p>
                                        <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ request.worker?.profile?.experience_years || 0 }} years</p>
                                    </div>
                                    <div class="rounded-md bg-gray-50 p-3 dark:bg-white/5">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Contact</p>
                                        <p class="mt-1 truncate font-semibold text-gray-900 dark:text-white">{{ request.worker?.phone || request.worker?.email || 'Not shared' }}</p>
                                    </div>
                                </div>

                                <p v-if="request.worker?.profile?.bio" class="mt-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ request.worker.profile.bio }}</p>

                                <AppButton
                                    v-if="booking.status === 'open' && request.status === 'accepted'"
                                    type="button"
                                    :data-testid="`booking-select-worker-${request.id}`"
                                    class="mt-4 sm:w-auto"
                                    icon="pi-check"
                                    variant="primary"
                                    :full-width="false"
                                    :loading="bookingsStore.saving"
                                    :disabled="bookingsStore.saving"
                                    @click="selectWorker(request)"
                                >
                                    {{ booking.worker_requests.length > 1 ? 'Select worker' : 'Confirm this worker' }}
                                </AppButton>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else-if="showWaitingForWorkerAcceptance" class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-5 dark:border-sky-400/20 dark:bg-sky-500/10">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                        <div class="flex size-11 items-center justify-center rounded-full bg-white text-sky-600 shadow-sm dark:bg-slate-950 dark:text-sky-300">
                            <i class="pi pi-send text-base" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-sky-900 dark:text-sky-100">Request sent to available workers</p>
                            <p class="mt-1 text-sm leading-6 text-sky-800 dark:text-sky-200">
                                Your booking request is being reviewed right now. We will show the accepted worker here as soon as someone confirms availability.
                            </p>
                        </div>
                    </div>
                </div>

                <div v-if="hasNoWorkerOptions" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300">
                    All invited workers declined this request. Try another time slot, choose a different worker, or create a new request.
                </div>
            </section>

            <section class="grid gap-5 lg:grid-cols-[1fr_360px]">
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Request details</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Request reference</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ serviceRequestReference }}</dd>
                        </div>
                        <div v-if="officialBookingReference">
                            <dt class="text-gray-500 dark:text-gray-400">Booking reference</dt>
                            <dd class="font-semibold text-gray-900 dark:text-white">{{ officialBookingReference }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Address</dt>
                            <dd class="text-gray-900 dark:text-white">{{ booking.address }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Issue description</dt>
                            <dd class="text-gray-900 dark:text-white">{{ booking.issue_description }}</dd>
                        </div>
                        <div v-if="booking.rejection_reason">
                            <dt class="text-gray-500 dark:text-gray-400">Rejection reason</dt>
                            <dd class="text-red-600 dark:text-red-300">{{ booking.rejection_reason }}</dd>
                        </div>
                        <div v-if="booking.cancelled_reason">
                            <dt class="text-gray-500 dark:text-gray-400">Cancellation reason</dt>
                            <dd class="text-red-600 dark:text-red-300">{{ booking.cancelled_reason }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="space-y-5">
                    <form
                        v-if="needsReschedule"
                        novalidate
                        class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10"
                        data-testid="booking-reschedule-form"
                        @submit.prevent="reschedule"
                    >
                        <h3 class="font-semibold text-gray-900 dark:text-white">Reschedule booking</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose a new date and time for this same worker request.</p>
                        <div class="mt-4 grid gap-4">
                            <FormInput id="reschedule_booking_date" v-model="rescheduleForm.booking_date" type="date" label="Booking date" data-testid="booking-reschedule-date" :min="minimumRescheduleDate" :max="maximumRescheduleDate" :error="rescheduleValidationErrors.booking_date?.length ? rescheduleValidationErrors.booking_date : (errors.booking_date || [])" />
                            <FormInput id="reschedule_start_time" v-model="rescheduleForm.start_time" type="time" label="Start time" data-testid="booking-reschedule-start-time" :min="minimumRescheduleTime" :error="rescheduleValidationErrors.start_time?.length ? rescheduleValidationErrors.start_time : (errors.start_time || [])" />
                            <FormInput id="reschedule_end_time" v-model="rescheduleForm.end_time" type="time" label="End time" data-testid="booking-reschedule-end-time" :error="errors.end_time" />
                        </div>
                        <div class="mt-4">
                            <AppButton type="submit" icon="pi-calendar" data-testid="booking-reschedule-submit" :loading="bookingsStore.saving">Reschedule booking</AppButton>
                        </div>
                    </form>

                    <form v-if="booking.status === 'open'" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" data-testid="booking-cancel-form" @submit.prevent="cancel">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Cancel booking</h3>
                        <textarea v-model="cancelReason" rows="4" data-testid="booking-cancel-reason" class="mt-4 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white" placeholder="Reason"></textarea>
                        <div class="mt-4">
                            <AppButton type="submit" icon="pi-times" data-testid="booking-cancel-submit">Cancel booking</AppButton>
                        </div>
                    </form>
                    <div v-else class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Cancel booking</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Cancellation is available only while this request is still open.</p>
                    </div>

                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/10">
                        <h3 class="font-semibold text-amber-900 dark:text-amber-100">Cancellation and refund policy</h3>
                        <ul class="mt-3 space-y-2 text-sm text-amber-800 dark:text-amber-200">
                            <li>Open requests can be cancelled before you confirm a final worker.</li>
                            <li>If a worker availability change forces a reschedule, you can pick a new slot or close the request.</li>
                            <li>Completed and paid bookings may require admin review before any refund decision is final.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <BookingTimeline :timeline="booking.timeline" />

            <section class="grid gap-5 lg:grid-cols-2">
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Your review for worker</h3>

                    <div v-if="booking.review" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <RatingStars :model-value="booking.review.rating" readonly />
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ booking.review.customer?.name || 'You' }}</p>
                        </div>
                        <p v-if="booking.review.review" class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ booking.review.review }}</p>
                    </div>

                    <form v-else-if="booking.booking?.status === 'completed'" class="mt-4 space-y-4" data-testid="booking-review-form" @submit.prevent="submitReview">
                        <RatingStars v-model="reviewForm.rating" />
                        <textarea
                            v-model="reviewForm.review"
                            rows="4"
                            data-testid="booking-review-text"
                            class="block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                            placeholder="Write your review"
                        ></textarea>
                        <div class="sm:w-48">
                            <AppButton type="submit" icon="pi-star" :loading="bookingsStore.saving" :disabled="reviewForm.rating === 0" data-testid="booking-review-submit">Submit review</AppButton>
                        </div>
                    </form>

                    <p v-else class="mt-3 text-sm text-gray-500 dark:text-gray-400">Reviews open after the booking is completed.</p>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Worker feedback for you</h3>

                    <div v-if="booking.worker_review" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <RatingStars :model-value="booking.worker_review.rating" readonly />
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ booking.worker_review.worker?.name || 'Worker' }}</p>
                        </div>
                        <p v-if="booking.worker_review.review" class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ booking.worker_review.review }}</p>
                    </div>

                    <p v-else class="mt-3 text-sm text-gray-500 dark:text-gray-400">Worker has not shared feedback yet.</p>
                </div>
            </section>

        </div>
    </DashboardLayout>
</template>
