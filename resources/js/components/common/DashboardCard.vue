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
});
</script>

<template>
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ eyebrow }}</h2>
        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ title }}</p>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ description }}</p>
    </section>
</template>
