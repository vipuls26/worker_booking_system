<script setup>
import { computed } from 'vue';
import AppButton from '../common/AppButton.vue';
import SkeletonCard from '../common/SkeletonCard.vue';

const props = defineProps({
    schedules: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['edit', 'delete', 'add-day', 'mark-off-day']);

const days = [
    { label: 'Sunday', value: 0 },
    { label: 'Monday', value: 1 },
    { label: 'Tuesday', value: 2 },
    { label: 'Wednesday', value: 3 },
    { label: 'Thursday', value: 4 },
    { label: 'Friday', value: 5 },
    { label: 'Saturday', value: 6 },
];

const groupedDays = computed(() => days.map((day) => {
    const items = props.schedules.filter((schedule) => schedule.day_of_week === day.value);

    return {
        ...day,
        items,
        offDay: items.find((schedule) => schedule.is_off_day),
        windows: items.filter((schedule) => !schedule.is_off_day),
    };
}));
</script>

<template>
    <section class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">Weekly schedule</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Choose working windows or mark a complete weekly off day.</p>
        </div>

        <div v-if="loading" class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
            <SkeletonCard v-for="item in 6" :key="item" :lines="2" :avatar="false" actions />
        </div>

        <div v-else class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-3">
            <article v-for="day in groupedDays" :key="day.value" class="rounded-lg border border-gray-200 p-4 dark:border-white/10" :data-testid="`worker-schedule-day-${day.value}`">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ day.label }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ day.offDay ? 'Full day off' : `${day.windows.length} working window${day.windows.length === 1 ? '' : 's'}` }}
                        </p>
                    </div>
                    <div v-if="!day.offDay" class="flex items-center gap-1">
                        <button
                            type="button"
                            :data-testid="`worker-schedule-add-day-${day.value}`"
                            title="Add working window"
                            class="inline-flex size-8 items-center justify-center rounded-md bg-gray-900 text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                            @click="$emit('add-day', day.value)"
                        >
                            <i class="pi pi-plus text-xs" aria-hidden="true"></i>
                        </button>
                        <button
                            v-if="day.windows.length === 0"
                            type="button"
                            :data-testid="`worker-schedule-mark-off-day-${day.value}`"
                            title="Mark off day"
                            class="inline-flex size-8 items-center justify-center rounded-md border border-gray-200 text-gray-600 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/10"
                            @click="$emit('mark-off-day', day.value)"
                        >
                            <i class="pi pi-ban text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div v-if="day.offDay" class="mt-4 flex items-center justify-between gap-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    <span>Weekly off</span>
                    <div class="flex gap-1">
                        <button type="button" :data-testid="`worker-schedule-edit-${day.offDay.id}`" class="inline-flex size-8 items-center justify-center rounded-md hover:bg-amber-100 dark:hover:bg-amber-500/20" @click="$emit('edit', day.offDay)">
                            <i class="pi pi-pencil text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" :data-testid="`worker-schedule-delete-${day.offDay.id}`" class="inline-flex size-8 items-center justify-center rounded-md text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10" @click="$emit('delete', day.offDay)">
                            <i class="pi pi-trash text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <div v-else-if="day.windows.length" class="mt-4 space-y-2">
                    <div v-for="schedule in day.windows" :key="schedule.id" class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-950" :data-testid="`worker-schedule-window-${schedule.id}`">
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ schedule.start_time }} - {{ schedule.end_time }}</span>
                        <div class="flex gap-1">
                            <button type="button" :data-testid="`worker-schedule-edit-${schedule.id}`" class="inline-flex size-8 items-center justify-center rounded-md text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10" @click="$emit('edit', schedule)">
                                <i class="pi pi-pencil text-xs" aria-hidden="true"></i>
                            </button>
                            <button type="button" :data-testid="`worker-schedule-delete-${schedule.id}`" class="inline-flex size-8 items-center justify-center rounded-md text-red-600 hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10" @click="$emit('delete', schedule)">
                                <i class="pi pi-trash text-xs" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-4 rounded-md border border-dashed border-gray-300 p-3 text-center dark:border-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No hours set</p>
                    <div class="mt-3 flex flex-col justify-center gap-2 min-[360px]:flex-row">
                        <AppButton type="button" icon="pi-plus" size="sm" :full-width="false" :data-testid="`worker-schedule-add-hours-${day.value}`" @click="$emit('add-day', day.value)">
                            Add hours
                        </AppButton>
                        <AppButton type="button" icon="pi-ban" size="sm" variant="secondary" :full-width="false" :data-testid="`worker-schedule-mark-off-${day.value}`" @click="$emit('mark-off-day', day.value)">
                            Mark off
                        </AppButton>
                    </div>
                </div>
            </article>
        </div>
    </section>
</template>
