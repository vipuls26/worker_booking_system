<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Confirm action',
    },
    message: {
        type: String,
        default: 'Are you sure?',
    },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-3 py-3 sm:items-center sm:px-4 sm:py-6" data-testid="confirm-dialog">
        <section class="max-h-[calc(100vh-1.5rem)] w-full max-w-sm overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl dark:bg-gray-900 sm:max-h-[calc(100vh-3rem)] sm:rounded-2xl">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ message }}</p>
            <div class="mt-5 grid gap-2 sm:flex sm:justify-end">
                <button type="button" data-testid="confirm-dialog-cancel" class="min-h-11 rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200" @click="$emit('cancel')">Cancel</button>
                <button type="button" data-testid="confirm-dialog-confirm" class="min-h-11 rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white" @click="$emit('confirm')">Confirm</button>
            </div>
        </section>
    </div>
</template>
