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
    <article class="overflow-hidden rounded-[26px] border border-slate-200 bg-white p-5 shadow-[0_18px_40px_rgba(15,23,42,0.07)] transition hover:-translate-y-1 hover:border-slate-300 hover:shadow-[0_24px_56px_rgba(15,23,42,0.12)] dark:border-white/10 dark:bg-slate-900 dark:shadow-none dark:hover:border-white/20" :class="selected ? 'border-slate-950 ring-2 ring-slate-950/10 dark:border-white dark:ring-white/20' : ''">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-950 dark:text-slate-500">
                <img v-if="worker.profile?.profile_photo_url" :src="worker.profile.profile_photo_url" :alt="worker.name" class="size-full object-cover">
                <i v-else class="pi pi-user text-2xl" aria-hidden="true"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold text-slate-900 dark:text-white">{{ worker.name }}</h2>
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                Available
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ worker.profile?.city || 'Location not set' }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            v-if="selectable"
                            type="button"
                            class="inline-flex size-9 items-center justify-center rounded-md border text-sm transition"
                            :class="selected ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950' : 'border-slate-300 text-slate-500 hover:bg-slate-100 dark:border-white/10 dark:text-slate-300 dark:hover:bg-white/10'"
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

                <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ worker.profile?.bio || 'No bio added yet.' }}</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="service in (worker.services || []).slice(0, 3)" :key="service.id" class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200">
                        {{ service.service?.name }} · ₹{{ service.price }}
                    </span>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">Starting price</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">From ₹{{ formatPrice(worker.min_service_price) }}</p>
                    </div>
                    <RouterLink :to="`/customer/workers/${worker.id}`" class="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                        View
                        <i class="pi pi-arrow-right text-xs" aria-hidden="true"></i>
                    </RouterLink>
                </div>
            </div>
        </div>
    </article>
</template>
