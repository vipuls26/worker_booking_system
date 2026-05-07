<script setup>
defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'button',
    },
    icon: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <button
        :type="type"
        :disabled="loading"
        class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
    >
        <i v-if="icon" :class="['pi', icon]" aria-hidden="true"></i>
        <slot>{{ loading ? 'Please wait...' : 'Submit' }}</slot>
    </button>
</template>
