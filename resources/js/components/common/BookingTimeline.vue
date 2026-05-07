<script setup>
import StatusBadge from './StatusBadge.vue';

defineProps({
    timeline: {
        type: Array,
        default: () => [],
    },
});

function eventLabel(event) {
    return event.replaceAll('_', ' ');
}

function formatDate(value) {
    if (! value) {
        return '';
    }

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">Booking timeline</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Status activity is stored for live updates later.</p>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                <i class="pi pi-bolt text-[10px]" aria-hidden="true"></i>
                Ready
            </span>
        </div>

        <div v-if="timeline.length === 0" class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
            No activity recorded yet.
        </div>

        <ol v-else class="mt-5 space-y-4">
            <li v-for="activity in timeline" :key="activity.id" class="relative pl-9">
                <span class="absolute left-0 top-1 flex size-6 items-center justify-center rounded-full bg-gray-900 text-white dark:bg-white dark:text-gray-950">
                    <i class="pi pi-check text-[10px]" aria-hidden="true"></i>
                </span>
                <div class="flex flex-col gap-2 rounded-md border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium capitalize text-gray-900 dark:text-white">{{ eventLabel(activity.event) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ activity.actor?.name || 'System' }}
                            <span v-if="activity.created_at"> · {{ formatDate(activity.created_at) }}</span>
                        </p>
                        <p v-if="activity.note" class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ activity.note }}</p>
                    </div>
                    <StatusBadge :value="activity.to_status" />
                </div>
            </li>
        </ol>
    </section>
</template>
