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
    tone: {
        type: String,
        default: 'blue',
    },
});
</script>

<template>
    <section
        class="dashboard-card app-surface p-5 transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md dark:hover:border-white/20"
        :class="`dashboard-card--${tone}`"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="dashboard-card__eyebrow text-sm font-medium">{{ eyebrow }}</h2>
                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ title }}</p>
            </div>
            <div v-if="icon" class="dashboard-card__icon flex size-10 shrink-0 items-center justify-center rounded-lg ring-1">
                <i :class="['pi', icon]" aria-hidden="true"></i>
            </div>
        </div>
        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ description }}</p>
    </section>
</template>
