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
    <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-white/10">
            <h2 class="font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
        </div>

        <div v-if="rows.length === 0" class="p-5 text-sm text-gray-500 dark:text-gray-400">
            No records available yet.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th v-for="column in columns" :key="column.key" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    <tr v-for="(row, index) in rows" :key="row.id || row.name || row.label || index">
                        <td v-for="column in columns" :key="column.key" class="px-5 py-4 text-sm text-gray-700 dark:text-gray-200">
                            {{ column.format ? column.format(row) : row[column.key] }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
