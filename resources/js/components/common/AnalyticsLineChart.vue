<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useTheme } from '../../composables/useTheme';

let chartConstructorPromise = null;

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
    chartType: {
        type: String,
        default: 'line',
        validator: (value) => ['bar', 'line', 'pie'].includes(value),
    },
});

const { isDark } = useTheme();
const canvas = ref(null);
let chart = null;

async function loadChartConstructor() {
    if (! chartConstructorPromise) {
        chartConstructorPromise = import('chart.js').then(({ Chart, registerables }) => {
            Chart.register(...registerables);

            return Chart;
        });
    }

    return chartConstructorPromise;
}

const chartItems = computed(() => props.items.map((item) => ({
    label: String(item.label).replace('_', ' '),
    value: Number(item.value) || 0,
})));

const totalValue = computed(() => chartItems.value.reduce((total, item) => total + item.value, 0));
const hasItems = computed(() => chartItems.value.length > 0);

function formattedValue(value) {
    return `${props.valuePrefix}${value}${props.valueSuffix}`;
}

function chartColors() {
    return {
        border: isDark.value ? '#f9fafb' : '#111827',
        fill: isDark.value ? 'rgba(249, 250, 251, 0.12)' : 'rgba(17, 24, 39, 0.08)',
        grid: isDark.value ? 'rgba(255, 255, 255, 0.1)' : 'rgba(229, 231, 235, 1)',
        pieBackgrounds: isDark.value
            ? ['#f9fafb', '#9ca3af', '#52525b', '#a3e635', '#38bdf8']
            : ['#111827', '#6b7280', '#d1d5db', '#65a30d', '#0284c7'],
        pieBorders: isDark.value ? '#030712' : '#ffffff',
        text: isDark.value ? '#d1d5db' : '#4b5563',
        tooltipBackground: isDark.value ? '#111827' : '#ffffff',
        tooltipBorder: isDark.value ? 'rgba(255, 255, 255, 0.12)' : 'rgba(229, 231, 235, 1)',
        tooltipText: isDark.value ? '#f9fafb' : '#111827',
    };
}

function chartData() {
    const colors = chartColors();
    const isLine = props.chartType === 'line';
    const isPie = props.chartType === 'pie';

    return {
        labels: chartItems.value.map((item) => item.label),
        datasets: [
            {
                label: props.title,
                data: chartItems.value.map((item) => item.value),
                borderColor: isPie ? colors.pieBorders : colors.border,
                backgroundColor: isPie ? colors.pieBackgrounds : (isLine ? colors.fill : colors.border),
                borderRadius: isLine ? 0 : 6,
                borderSkipped: false,
                pointBackgroundColor: colors.tooltipBackground,
                pointBorderColor: colors.border,
                pointHoverBackgroundColor: colors.border,
                pointHoverBorderColor: colors.tooltipBackground,
                borderWidth: isPie ? 2 : (isLine ? 2.5 : 0),
                pointRadius: isLine ? 4 : 0,
                pointHoverRadius: isLine ? 5 : 0,
                fill: isLine,
                tension: isLine ? 0.35 : 0,
            },
        ],
    };
}

function chartOptions() {
    const colors = chartColors();
    const isPie = props.chartType === 'pie';

    return {
        responsive: true,
        maintainAspectRatio: false,
        cutout: isPie ? '0%' : undefined,
        interaction: {
            intersect: false,
            mode: 'index',
        },
        plugins: {
            legend: {
                display: isPie,
                position: 'bottom',
                labels: {
                    boxHeight: 10,
                    boxWidth: 10,
                    color: colors.text,
                    padding: 18,
                    usePointStyle: true,
                },
            },
            tooltip: {
                backgroundColor: colors.tooltipBackground,
                borderColor: colors.tooltipBorder,
                borderWidth: 1,
                bodyColor: colors.tooltipText,
                displayColors: false,
                padding: 12,
                titleColor: colors.tooltipText,
                callbacks: {
                    label: (context) => {
                        const value = typeof context.parsed === 'number' ? context.parsed : context.parsed.y;

                        return formattedValue(value);
                    },
                },
            },
        },
        scales: {
            x: {
                display: !isPie,
                border: {
                    display: false,
                },
                grid: {
                    display: false,
                },
                ticks: {
                    color: colors.text,
                    maxRotation: 0,
                    autoSkipPadding: 16,
                    callback(value) {
                        return this.getLabelForValue(value);
                    },
                },
            },
            y: {
                display: !isPie,
                beginAtZero: true,
                border: {
                    display: false,
                },
                grid: {
                    color: colors.grid,
                },
                ticks: {
                    color: colors.text,
                    callback: (value) => formattedValue(value),
                },
            },
        },
    };
}

async function renderChart() {
    await nextTick();

    if (! canvas.value || ! hasItems.value) {
        chart?.destroy();
        chart = null;

        return;
    }

    if (chart) {
        chart.data = chartData();
        chart.options = chartOptions();
        chart.update();

        return;
    }

    const Chart = await loadChartConstructor();

    chart = new Chart(canvas.value, {
        type: props.chartType,
        data: chartData(),
        options: chartOptions(),
    });
}

onMounted(renderChart);

watch([chartItems, isDark], renderChart, { deep: true });

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
            </div>
            <div v-if="hasItems" class="rounded-md bg-gray-50 px-3 py-2 text-right dark:bg-gray-950">
                <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total</p>
                <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">{{ formattedValue(totalValue) }}</p>
            </div>
        </div>

        <div v-if="!hasItems" class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
            No analytics available yet.
        </div>

        <div v-else class="mt-5 h-72 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
            <canvas ref="canvas" :aria-label="title" role="img"></canvas>
        </div>
    </section>
</template>
