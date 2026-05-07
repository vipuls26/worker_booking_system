<script setup>
import { onMounted } from 'vue';
import { toast } from 'vue-sonner';
import PaginationControls from '../../components/common/PaginationControls.vue';
import RatingStars from '../../components/common/RatingStars.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useWorkerReviewsStore } from '../../stores/worker/reviews';

const reviewsStore = useWorkerReviewsStore();

const ratingOptions = [
    { label: 'All ratings', value: '' },
    { label: '5 stars', value: 5 },
    { label: '4 stars', value: 4 },
    { label: '3 stars', value: 3 },
    { label: '2 stars', value: 2 },
    { label: '1 star', value: 1 },
];

const sortOptions = [
    { label: 'Latest', value: 'latest' },
    { label: 'Rating high', value: 'rating_high' },
    { label: 'Rating low', value: 'rating_low' },
];

async function load(page = 1) {
    try {
        await reviewsStore.fetch(page);
    } catch {
        toast.error('Unable to load reviews');
    }
}

onMounted(load);
</script>

<template>
    <DashboardLayout title="My Reviews">
        <div class="space-y-5">
            <section class="grid gap-4 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 md:grid-cols-[1fr_220px_220px] md:items-end">
                <div>
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Average rating</p>
                    <div class="mt-2 flex items-center gap-3">
                        <p class="text-3xl font-semibold text-gray-900 dark:text-white">{{ reviewsStore.summary.average.toFixed(1) }}</p>
                        <div>
                            <RatingStars :model-value="Math.round(reviewsStore.summary.average)" readonly />
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ reviewsStore.summary.count }} reviews</p>
                        </div>
                    </div>
                </div>
                <FormSelect id="review_rating" v-model="reviewsStore.filters.rating" label="Rating" :options="ratingOptions" option-label="label" option-value="value" @update:model-value="load()" />
                <FormSelect id="review_sort" v-model="reviewsStore.filters.sort" label="Sort" :options="sortOptions" option-label="label" option-value="value" @update:model-value="load()" />
            </section>

            <div v-if="reviewsStore.loading">
                <SkeletonList :count="4" />
            </div>

            <div v-else-if="reviewsStore.reviews.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                No reviews yet.
            </div>

            <div v-else class="space-y-3">
                <article v-for="review in reviewsStore.reviews" :key="review.id" class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ review.customer?.name || 'Customer' }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ review.booking?.service?.name || `Booking #${review.booking_id}` }}</p>
                        </div>
                        <RatingStars :model-value="review.rating" readonly />
                    </div>
                    <p v-if="review.review" class="mt-4 text-sm text-gray-700 dark:text-gray-300">{{ review.review }}</p>
                </article>
            </div>

            <PaginationControls :meta="reviewsStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
