<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    items: {
        type: Array,
        default: () => [],
    },
    valuePrefix: {
        type: String,
        default: '',
    },
});

const maxValue = computed(() => Math.max(...props.items.map((item) => Number(item.value) || 0), 1));

function widthFor(value) {
    return `${Math.max((Number(value) / maxValue.value) * 100, 4)}%`;
}
</script>

<template>
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="font-semibold text-gray-900 dark:text-white">{{ title }}</h2>

        <div v-if="items.length === 0" class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
            No analytics available yet.
        </div>

        <div v-else class="mt-5 space-y-4">
            <div v-for="item in items" :key="item.label" class="space-y-2">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium capitalize text-gray-700 dark:text-gray-200">{{ String(item.label).replace('_', ' ') }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ valuePrefix }}{{ item.value }}</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-950">
                    <div class="h-full rounded-full bg-gray-900 transition-all dark:bg-white" :style="{ width: widthFor(item.value) }"></div>
                </div>
            </div>
        </div>
    </section>
</template>
