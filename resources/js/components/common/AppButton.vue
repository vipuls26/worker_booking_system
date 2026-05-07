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
    disabled: {
        type: Boolean,
        default: false,
    },
    variant: {
        type: String,
        default: 'primary',
    },
    size: {
        type: String,
        default: 'md',
    },
    fullWidth: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <button
        :type="type"
        :disabled="loading || disabled"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-md text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-60',
            fullWidth ? 'w-full' : '',
            size === 'sm' ? 'px-3 py-1.5' : 'px-4 py-2',
            variant === 'primary' ? 'bg-gray-900 text-white shadow-sm hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200' : '',
            variant === 'secondary' ? 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5' : '',
            variant === 'danger' ? 'bg-red-600 text-white hover:bg-red-700' : '',
            variant === 'ghost' ? 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10' : '',
        ]"
    >
        <i v-if="icon" :class="['pi', icon]" aria-hidden="true"></i>
        <slot>{{ loading ? 'Please wait...' : 'Submit' }}</slot>
    </button>
</template>
