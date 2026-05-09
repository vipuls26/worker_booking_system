<script setup>
import { RouterLink } from 'vue-router';

defineProps({
    worker: {
        type: Object,
        required: true,
    },
    selectable: {
        type: Boolean,
        default: false,
    },
    selected: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle-select']);

function formatRating(rating) {
    const numericRating = Number(rating);

    return Number.isFinite(numericRating) ? numericRating.toFixed(1) : '0.0';
}

function formatPrice(price) {
    const numericPrice = Number(price);

    return Number.isFinite(numericPrice) && numericPrice > 0 ? numericPrice : 'N/A';
}
</script>

<template>
    <article class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:-translate-y-0.5 hover:shadow-md dark:bg-gray-900 dark:ring-white/10" :class="selected ? 'ring-gray-900 dark:ring-white' : ''">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-950 dark:text-gray-500">
                <img v-if="worker.profile?.profile_photo_url" :src="worker.profile.profile_photo_url" :alt="worker.name" class="size-full object-cover">
                <i v-else class="pi pi-user text-2xl" aria-hidden="true"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ worker.name }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ worker.profile?.city || 'Location not set' }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            v-if="selectable"
                            type="button"
                            class="inline-flex size-9 items-center justify-center rounded-md border text-sm transition"
                            :class="selected ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-950' : 'border-gray-300 text-gray-500 hover:bg-gray-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/10'"
                            :aria-pressed="selected"
                            @click="emit('toggle-select', worker)"
                        >
                            <i :class="['pi', selected ? 'pi-check' : 'pi-plus']" aria-hidden="true"></i>
                        </button>
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                            <i class="pi pi-star-fill text-[10px]" aria-hidden="true"></i>
                            {{ formatRating(worker.rating_average) }}
                        </span>
                    </div>
                </div>

                <p class="mt-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ worker.profile?.bio || 'No bio added yet.' }}</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="service in (worker.services || []).slice(0, 3)" :key="service.id" class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ service.service?.name }} · ₹{{ service.price }}
                    </span>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                        From ₹{{ formatPrice(worker.min_service_price) }}
                    </p>
                    <RouterLink :to="`/customer/workers/${worker.id}`" class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">
                        View
                        <i class="pi pi-arrow-right text-xs" aria-hidden="true"></i>
                    </RouterLink>
                </div>
            </div>
        </div>
    </article>
</template>
