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
    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-blue-100 dark:divide-white/10">
                <thead class="bg-blue-50/70 dark:bg-gray-950">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="px-4 py-3 text-left text-xs font-semibold uppercase text-blue-700 dark:text-gray-400">
                            {{ column.label }}
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-blue-700 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-blue-50 dark:divide-white/10">
                    <SkeletonTableRows v-if="loading" :columns="columns.length" />
                    <slot v-else />
                </tbody>
            </table>
        </div>
        <div v-if="!loading && !hasRecords" class="border-t border-blue-50 px-4 py-3 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            <slot name="empty">{{ emptyMessage }}</slot>
        </div>
    </div>
</template>
