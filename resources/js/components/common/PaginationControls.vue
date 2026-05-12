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
    <div v-if="meta?.last_page > 1" class="app-surface flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-center text-sm text-slate-600 dark:text-slate-400 sm:text-left">
            Showing {{ props.meta.from }} to {{ props.meta.to }} of {{ props.meta.total }}
        </p>

        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:flex">
            <button
                type="button"
                :disabled="meta.current_page <= 1"
                class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-800"
                @click="emit('change', meta.current_page - 1)"
            >
                <i class="pi pi-angle-left text-xs" aria-hidden="true"></i>
                <span class="hidden sm:inline">Previous</span>
            </button>
            <span class="min-w-20 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ meta.current_page }} / {{ meta.last_page }}
            </span>
            <button
                type="button"
                :disabled="meta.current_page >= meta.last_page"
                class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-800"
                @click="emit('change', meta.current_page + 1)"
            >
                <span class="hidden sm:inline">Next</span>
                <i class="pi pi-angle-right text-xs" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</template>
