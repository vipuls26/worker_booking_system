<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    rows: {
        type: Array,
        default: () => [],
    },
    columns: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <section class="app-surface overflow-hidden">
        <div class="border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-white/10 dark:bg-slate-950">
            <h2 class="font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h2>
        </div>

        <div v-if="rows.length === 0" class="p-5 text-sm text-slate-500 dark:text-slate-400">
            No records available yet.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-white/10">
                <thead class="bg-slate-50 dark:bg-slate-950">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-600 dark:text-slate-400">
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-white/10">
                    <tr v-for="(row, index) in rows" :key="row.id || row.name || row.label || index">
                        <td v-for="column in columns" :key="column.key" class="px-5 py-4 text-sm text-slate-700 dark:text-slate-200">
                            {{ column.format ? column.format(row) : row[column.key] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
