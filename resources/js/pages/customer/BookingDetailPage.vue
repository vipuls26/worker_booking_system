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
const acceptedRequests = computed(() => booking.value?.requests?.filter((request) => request.status === 'accepted') || []);
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

async function submitReview() {
    try {
        await bookingsStore.submitReview(route.params.id, reviewForm);
        toast.success('Review submitted');
        reviewForm.rating = 0;
        reviewForm.review = '';
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to submit review');
    }
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
                    <BookingStatusTracker :status="booking.status" />
                </div>
            </section>

            <section v-if="booking.requests?.length" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Worker responses</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose an accepted worker to lock the booking.</p>
                    </div>
                    <span v-if="booking.status === 'requested'" class="rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                        {{ acceptedRequests.length }} accepted
                    </span>
                </div>

                <div class="mt-4 divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 dark:divide-white/10 dark:border-white/10">
                    <div v-for="request in booking.requests" :key="request.id" class="flex flex-col gap-3 bg-white p-4 dark:bg-gray-950 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ request.worker?.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ request.worker?.phone || request.worker?.email }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="{
                                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300': ['accepted', 'selected'].includes(request.status),
                                'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300': ['rejected', 'cancelled'].includes(request.status),
                                'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300': request.status === 'pending',
                            }">
                                {{ request.status.replace('_', ' ') }}
                            </span>
                            <button
                                v-if="booking.status === 'requested' && request.status === 'accepted'"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                                @click="selectWorker(request)"
                            >
                                <i class="pi pi-check" aria-hidden="true"></i>
                                Select
                            </button>
                        </div>
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

                <form v-if="['requested', 'pending'].includes(booking.status)" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="cancel">
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

                    <form v-else-if="booking.status === 'completed'" class="mt-4 space-y-4" @submit.prevent="submitReview">
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
