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
            'inline-flex min-h-10 min-w-0 items-center justify-center gap-2 rounded-md text-center text-sm font-semibold leading-5 transition disabled:cursor-not-allowed disabled:opacity-60',
            fullWidth ? 'w-full' : '',
            size === 'sm' ? 'px-3 py-1.5' : 'px-4 py-2',
            variant === 'primary' ? 'bg-blue-600 text-white shadow-sm shadow-blue-600/20 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400' : '',
            variant === 'secondary' ? 'border border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300 dark:hover:bg-blue-500/20' : '',
            variant === 'danger' ? 'bg-red-600 text-white shadow-sm shadow-red-600/20 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-400' : '',
            variant === 'ghost' ? 'text-slate-600 hover:bg-blue-50 hover:text-blue-700 dark:text-slate-300 dark:hover:bg-blue-500/10 dark:hover:text-blue-300' : '',
        ]"
    >
        <i v-if="icon" :class="['pi', icon]" aria-hidden="true"></i>
        <slot>{{ loading ? 'Please wait...' : 'Submit' }}</slot>
    </button>
</template>
