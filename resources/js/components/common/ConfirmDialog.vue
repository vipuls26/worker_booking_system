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
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 py-4 sm:items-center">
        <section class="w-full max-w-sm rounded-lg bg-white p-5 shadow-xl dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ message }}</p>
            <div class="mt-5 grid gap-2 sm:flex sm:justify-end">
                <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200" @click="$emit('cancel')">Cancel</button>
                <button type="button" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white" @click="$emit('confirm')">Confirm</button>
            </div>
        </section>
    </div>
</template>
