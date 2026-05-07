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
    <div v-if="meta?.last_page > 1" class="flex flex-col gap-3 rounded-lg bg-white px-4 py-3 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Showing {{ props.meta.from }} to {{ props.meta.to }} of {{ props.meta.total }}
        </p>

        <div class="flex items-center gap-2">
            <button
                type="button"
                :disabled="meta.current_page <= 1"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                @click="emit('change', meta.current_page - 1)"
            >
                <i class="pi pi-angle-left text-xs" aria-hidden="true"></i>
                Previous
            </button>
            <span class="min-w-20 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ meta.current_page }} / {{ meta.last_page }}
            </span>
            <button
                type="button"
                :disabled="meta.current_page >= meta.last_page"
                class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                @click="emit('change', meta.current_page + 1)"
            >
                Next
                <i class="pi pi-angle-right text-xs" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</template>
