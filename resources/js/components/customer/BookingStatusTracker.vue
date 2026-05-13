<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const steps = ['open', 'worker_selected', 'in_progress', 'completed'];
const currentStatus = computed(() => (props.status === 'confirmed' ? 'worker_selected' : props.status));

function stepState(step) {
    if (props.status === 'cancelled' || props.status === 'rejected') {
        return 'muted';
    }

    return steps.indexOf(step) <= steps.indexOf(currentStatus.value) ? 'done' : 'pending';
}
</script>

<template>
    <div data-testid="booking-status-tracker" :data-status="status">
        <div v-if="status === 'cancelled' || status === 'rejected'" data-testid="booking-status-banner" class="rounded-md bg-red-50 px-4 py-3 text-sm font-medium text-red-700 dark:bg-red-500/10 dark:text-red-300">
            Request {{ status.replace('_', ' ') }}
        </div>
        <div v-else-if="status === 'awaiting_reschedule'" data-testid="booking-status-banner" class="rounded-md bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
            Request awaiting reschedule
        </div>
        <div v-else class="grid grid-cols-4 gap-2">
            <div v-for="step in steps" :key="step" class="text-center">
                <div
                    :class="[
                        'mx-auto flex size-8 items-center justify-center rounded-full text-xs font-semibold',
                        stepState(step) === 'done'
                            ? 'bg-emerald-600 text-white'
                            : 'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400',
                    ]"
                >
                    <i v-if="stepState(step) === 'done'" class="pi pi-check text-xs" aria-hidden="true"></i>
                </div>
                <p class="mt-2 text-xs capitalize text-gray-600 dark:text-gray-400">{{ step.replace('_', ' ') }}</p>
            </div>
        </div>
    </div>
</template>
