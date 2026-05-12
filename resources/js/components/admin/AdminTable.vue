<script setup>
import SkeletonTableRows from '../common/SkeletonTableRows.vue';

defineProps({
    columns: {
        type: Array,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    emptyMessage: {
        type: String,
        default: 'No records found.',
    },
    hasRecords: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <div class="app-surface overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
                <thead class="bg-slate-50 dark:bg-slate-950">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600 dark:text-slate-400">
                            {{ column.label }}
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-600 dark:text-slate-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                    <SkeletonTableRows v-if="loading" :columns="columns.length" />
                    <slot v-else />
                </tbody>
            </table>
        </div>
        <div v-if="!loading && !hasRecords" class="border-t border-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
            <slot name="empty">{{ emptyMessage }}</slot>
        </div>
    </div>
</template>
