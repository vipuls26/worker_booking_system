<script setup>
defineProps({
    eyebrow: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
    icon: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 transition hover:shadow-md dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ eyebrow }}</h2>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ title }}</p>
            </div>
            <div v-if="icon" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                <i :class="['pi', icon]" aria-hidden="true"></i>
            </div>
        </div>
        <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ description }}</p>
    </section>
</template>
