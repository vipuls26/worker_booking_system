<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import BookingTimeline from '../../components/common/BookingTimeline.vue';
import RatingStars from '../../components/common/RatingStars.vue';
import SkeletonCard from '../../components/common/SkeletonCard.vue';
import BookingStatusTracker from '../../components/customer/BookingStatusTracker.vue';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';

const route = useRoute();
const bookingsStore = useCustomerBookingsStore();
const cancelReason = ref('');
const booking = computed(() => bookingsStore.booking);
const officialBooking = computed(() => booking.value?.booking || null);
const canPayBooking = computed(() => officialBooking.value?.status === 'completed' && officialBooking.value?.payment_status !== 'paid');
const paymentButtonLabel = computed(() => {
    if (officialBooking.value?.payment_status === 'paid') {
        return 'Paid';
    }

    if (officialBooking.value?.status !== 'completed') {
        return 'Available after completion';
    }

    return 'Pay now';
});
const acceptedRequests = computed(() => booking.value?.requests?.filter((request) => request.status === 'accepted') || []);
const selectedRequests = computed(() => booking.value?.requests?.filter((request) => request.status === 'selected') || []);
const comparisonRequests = computed(() => [...selectedRequests.value, ...acceptedRequests.value]);
const otherRequests = computed(() => booking.value?.requests?.filter((request) => !['accepted', 'selected'].includes(request.status)) || []);
const reviewForm = reactive({
    rating: 0,
    review: '',
});

async function load() {
    try {
        await bookingsStore.fetchOne(route.params.id);
    } catch {
        toast.error('Unable to load booking');
    }
}

async function cancel() {
    try {
        await bookingsStore.cancel(route.params.id, { cancelled_reason: cancelReason.value });
        toast.success('Booking cancelled');
        cancelReason.value = '';
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to cancel booking');
    }
}

async function selectWorker(bookingRequest) {
    try {
        await bookingsStore.selectWorker(route.params.id, { booking_request_id: bookingRequest.id });
        toast.success('Worker selected');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to select worker');
    }
}

async function payBooking() {
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

onMounted(load);
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

        <div v-else-if="booking" class="space-y-5">
            <RouterLink to="/customer/bookings" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                <i class="pi pi-arrow-left text-xs" aria-hidden="true"></i>
                Back to bookings
            </RouterLink>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ booking.service?.name }}</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ booking.worker?.name || 'Awaiting final worker' }} · {{ booking.booking_date }} {{ booking.start_time }} - {{ booking.end_time }}</p>
                    </div>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">₹{{ booking.total_amount }}</p>
                </div>

                <div class="mt-6">
                    <BookingStatusTracker :status="booking.booking?.status || booking.status" />
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

            <section v-if="booking.requests?.length" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ booking.requests.length > 1 ? 'Compare accepted workers' : 'Worker response' }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ booking.requests.length > 1 ? 'Choose one accepted worker to create the official booking.' : 'This request was sent to one worker. Confirm them after they accept.' }}
                        </p>
                    </div>
                    <span v-if="booking.status === 'open'" class="rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                        {{ acceptedRequests.length }} accepted
                    </span>
                </div>

                <div v-if="comparisonRequests.length" class="mt-4 grid gap-4 lg:grid-cols-2">
                    <article v-for="request in comparisonRequests" :key="request.id" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-gray-950">
                        <div class="flex gap-4">
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

                                <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
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

                                <button
                                    v-if="booking.status === 'open' && request.status === 'accepted'"
                                    type="button"
                                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200 sm:w-auto"
                                    @click="selectWorker(request)"
                                >
                                    <i class="pi pi-check" aria-hidden="true"></i>
                                    {{ booking.requests.length > 1 ? 'Select worker' : 'Confirm this worker' }}
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-4 rounded-lg border border-dashed border-gray-200 p-5 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    No workers have accepted this request yet.
                </div>

                <div v-if="otherRequests.length" class="mt-5 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                    <div v-for="request in otherRequests" :key="request.id" class="flex flex-col gap-3 border-b border-gray-200 bg-white p-4 last:border-b-0 dark:border-white/10 dark:bg-gray-950 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ request.worker?.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ request.worker?.phone || request.worker?.email }}</p>
                        </div>
                        <span class="w-fit rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="{
                            'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300': ['rejected', 'cancelled', 'auto_cancelled', 'not_selected'].includes(request.status),
                            'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300': request.status === 'pending',
                        }">
                            {{ request.status.replace('_', ' ') }}
                        </span>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 lg:grid-cols-[1fr_360px]">
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Request details</h3>
                    <dl class="mt-4 space-y-3 text-sm">
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

                <form v-if="booking.status === 'open'" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="cancel">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Cancel booking</h3>
                    <textarea v-model="cancelReason" rows="4" class="mt-4 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white" placeholder="Reason"></textarea>
                    <div class="mt-4">
                        <AppButton type="submit" icon="pi-times">Cancel booking</AppButton>
                    </div>
                </form>
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

                    <form v-else-if="booking.booking?.status === 'completed'" class="mt-4 space-y-4" @submit.prevent="submitReview">
                        <RatingStars v-model="reviewForm.rating" />
                        <textarea
                            v-model="reviewForm.review"
                            rows="4"
                            class="block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                            placeholder="Write your review"
                        ></textarea>
                        <div class="sm:w-48">
                            <AppButton type="submit" icon="pi-star" :loading="bookingsStore.saving" :disabled="reviewForm.rating === 0">Submit review</AppButton>
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
