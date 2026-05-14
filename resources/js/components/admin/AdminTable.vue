<script setup>
import SkeletonTableRows from '../common/SkeletonTableRows.vue';

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
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

function cellSlotName(columnKey) {
    return `cell-${columnKey}`;
}
</script>

<template>
    <div class="app-surface overflow-hidden">
        <div class="overflow-x-auto overscroll-x-contain">
            <table class="min-w-[720px] divide-y divide-slate-200 dark:divide-white/10 sm:min-w-full">
                <thead class="bg-slate-50 dark:bg-slate-950">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600 dark:text-slate-400"
                        >
                            {{ column.label }}
                        </th>
                        <th v-if="$slots.actions" class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-600 dark:text-slate-400">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                    <SkeletonTableRows v-if="loading" :columns="columns.length + ($slots.actions ? 1 : 0)" />

                    <template v-else-if="hasRecords">
                        <tr v-for="(row, rowIndex) in rows" :key="row.id || row.name || row.label || rowIndex">
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-4 py-3 align-middle text-sm text-slate-700 dark:text-slate-200"
                            >
                                <slot
                                    v-if="$slots[cellSlotName(column.key)]"
                                    :name="cellSlotName(column.key)"
                                    :row="row"
                                    :value="typeof column.accessor === 'function' ? column.accessor(row) : row[column.key]"
                                />
                                <template v-else>
                                    {{ typeof column.accessor === 'function' ? column.accessor(row) : row[column.key] }}
                                </template>
                            </td>
                            <td v-if="$slots.actions" class="px-4 py-3 text-right align-middle">
                                <slot name="actions" :row="row" />
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <div v-if="!loading && !hasRecords" class="border-t border-slate-100 px-4 py-3 text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
            <slot name="empty">{{ emptyMessage }}</slot>
        </div>
    </div>
</template>
