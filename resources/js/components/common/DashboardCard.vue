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
    <section class="app-surface p-5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:hover:border-white/20">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-sm font-medium text-blue-700 dark:text-blue-300">{{ eyebrow }}</h2>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ title }}</p>
            </div>
            <div v-if="icon" class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-800 ring-1 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20">
                <i :class="['pi', icon]" aria-hidden="true"></i>
            </div>
        </div>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ description }}</p>
    </section>
</template>
