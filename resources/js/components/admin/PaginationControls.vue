<script setup>
const props = defineProps({
    meta: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['change']);
</script>

<template>
    <div v-if="meta?.last_page > 1" class="flex items-center justify-between gap-3">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Showing {{ props.meta.from }} to {{ props.meta.to }} of {{ props.meta.total }}
        </p>
        <div class="flex gap-2">
            <button
                type="button"
                :disabled="meta.current_page <= 1"
                class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 disabled:opacity-50 dark:border-white/10 dark:text-gray-200"
                @click="emit('change', meta.current_page - 1)"
            >
                Previous
            </button>
            <button
                type="button"
                :disabled="meta.current_page >= meta.last_page"
                class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 disabled:opacity-50 dark:border-white/10 dark:text-gray-200"
                @click="emit('change', meta.current_page + 1)"
            >
                Next
            </button>
        </div>
    </div>
</template>
