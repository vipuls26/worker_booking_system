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
        <p class="text-sm text-slate-600 dark:text-slate-400">
            Showing {{ props.meta.from }} to {{ props.meta.to }} of {{ props.meta.total }}
        </p>
        <div class="flex gap-2">
            <button
                type="button"
                :disabled="meta.current_page <= 1"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-100 disabled:opacity-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-800"
                @click="emit('change', meta.current_page - 1)"
            >
                Previous
            </button>
            <button
                type="button"
                :disabled="meta.current_page >= meta.last_page"
                class="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-100 disabled:opacity-50 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-800"
                @click="emit('change', meta.current_page + 1)"
            >
                Next
            </button>
        </div>
    </div>
</template>
