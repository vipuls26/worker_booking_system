<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    items: {
        type: Array,
        default: () => [],
    },
    valuePrefix: {
        type: String,
        default: '',
    },
    valueSuffix: {
        type: String,
        default: '',
    },
    variant: {
        type: String,
        default: 'bars',
        validator: (value) => ['bars', 'columns', 'line'].includes(value),
    },
});

const maxValue = computed(() => Math.max(...props.items.map((item) => Number(item.value) || 0), 1));
const totalValue = computed(() => props.items.reduce((total, item) => total + (Number(item.value) || 0), 0));
const chartItems = computed(() => props.items.map((item) => ({
    ...item,
    displayLabel: String(item.label).replace('_', ' '),
    value: Number(item.value) || 0,
    percentage: Math.max(((Number(item.value) || 0) / maxValue.value) * 100, props.variant === 'columns' ? 8 : 4),
})));
const linePoints = computed(() => chartItems.value.map((item, index) => {
    const x = chartItems.value.length === 1 ? 50 : (index / (chartItems.value.length - 1)) * 100;
    const y = 54 - ((item.value / maxValue.value) * 44);

    return {
        ...item,
        x: Number(x.toFixed(2)),
        y: Number(y.toFixed(2)),
    };
}));
const linePath = computed(() => linePoints.value.map((point) => `${point.x},${point.y}`).join(' '));
const areaPath = computed(() => (linePoints.value.length ? `0,58 ${linePath.value} 100,58` : ''));

function widthFor(value) {
    return `${Math.max((Number(value) / maxValue.value) * 100, 4)}%`;
}

function formattedValue(value) {
    return `${props.valuePrefix}${value}${props.valueSuffix}`;
}
</script>

<template>
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
            </div>
            <div v-if="items.length" class="rounded-md bg-gray-50 px-3 py-2 text-right dark:bg-gray-950">
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total</p>
                <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ formattedValue(totalValue) }}</p>
            </div>
        </div>

        <div v-if="items.length === 0" class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
            No analytics available yet.
        </div>

        <div v-else-if="variant === 'columns'" class="mt-5">
            <div class="flex h-48 items-end gap-2 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                <div v-for="item in chartItems" :key="item.label" class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ formattedValue(item.value) }}</span>
                    <div class="flex h-32 w-full items-end">
                        <div
                            class="w-full rounded-t-md bg-gray-900 transition-all dark:bg-white"
                            :style="{ height: `${item.percentage}%` }"
                        ></div>
                    </div>
                    <span class="w-full truncate text-center text-xs capitalize text-gray-500 dark:text-gray-400">{{ item.displayLabel }}</span>
                </div>
            </div>
        </div>

        <div v-else-if="variant === 'line'" class="mt-5">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                <svg viewBox="0 0 100 64" role="img" :aria-label="title" class="h-56 w-full overflow-visible text-gray-900 dark:text-white">
                    <line x1="0" y1="58" x2="100" y2="58" class="stroke-gray-200 dark:stroke-white/10" stroke-width="0.8" />
                    <line x1="0" y1="36" x2="100" y2="36" class="stroke-gray-200/80 dark:stroke-white/10" stroke-width="0.5" stroke-dasharray="2 2" />
                    <line x1="0" y1="14" x2="100" y2="14" class="stroke-gray-200/80 dark:stroke-white/10" stroke-width="0.5" stroke-dasharray="2 2" />
                    <polygon :points="areaPath" class="fill-gray-900/5 dark:fill-white/10" />
                    <polyline :points="linePath" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                    <g v-for="point in linePoints" :key="point.label">
                        <circle :cx="point.x" :cy="point.y" r="2.2" class="fill-white stroke-gray-900 dark:fill-gray-950 dark:stroke-white" stroke-width="1.5" />
                    </g>
                </svg>

                <div class="mt-3 flex items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                    <span class="max-w-32 truncate capitalize">{{ chartItems[0]?.displayLabel }}</span>
                    <span class="font-semibold text-gray-700 dark:text-gray-200">Peak {{ formattedValue(maxValue) }}</span>
                    <span class="max-w-32 truncate text-right capitalize">{{ chartItems[chartItems.length - 1]?.displayLabel }}</span>
                </div>
            </div>
        </div>

        <div v-else class="mt-5 space-y-4">
            <div v-for="item in chartItems" :key="item.label" class="space-y-2">
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium capitalize text-gray-700 dark:text-gray-200">{{ item.displayLabel }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ formattedValue(item.value) }}</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-950">
                    <div class="h-full rounded-full bg-gray-900 transition-all dark:bg-white" :style="{ width: widthFor(item.value) }"></div>
                </div>
            </div>
        </div>
    </section>
</template>
