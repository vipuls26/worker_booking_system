<script setup>
import { reactive, watch } from 'vue';
import AppButton from '../common/AppButton.vue';
import FormInput from '../forms/FormInput.vue';
import FormSelect from '../forms/FormSelect.vue';
import { useYupValidation } from '../../composables/useYupValidation';
import { workerScheduleSchema } from '../../validation/workerSchemas';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    schedule: {
        type: Object,
        default: null,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
    initialDay: {
        type: [Number, String],
        default: '',
    },
});

const emit = defineEmits(['close', 'submit']);
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(workerScheduleSchema);

const dayOptions = [
    { label: 'Sunday', value: 0 },
    { label: 'Monday', value: 1 },
    { label: 'Tuesday', value: 2 },
    { label: 'Wednesday', value: 3 },
    { label: 'Thursday', value: 4 },
    { label: 'Friday', value: 5 },
    { label: 'Saturday', value: 6 },
];

const form = reactive({
    day_of_week: '',
    start_time: '09:00',
    end_time: '18:00',
    is_off_day: false,
});

watch(
    () => [props.open, props.schedule, props.initialDay],
    () => {
        clearValidationErrors();
        Object.assign(form, {
            day_of_week: props.schedule?.day_of_week ?? props.initialDay,
            start_time: props.schedule?.start_time || '09:00',
            end_time: props.schedule?.end_time || '18:00',
            is_off_day: props.schedule?.is_off_day ?? false,
        });
    },
    { immediate: true },
);

async function submit() {
    clearValidationErrors();
    const isValid = await validateWithSchema(form);

    if (! isValid) {
        return;
    }

    emit('submit', { ...form });
}

watch(() => form.day_of_week, () => clearValidationErrors('day_of_week'));
watch(() => form.is_off_day, () => clearValidationErrors(['is_off_day', 'start_time', 'end_time']));
watch(() => form.start_time, () => clearValidationErrors('start_time'));
watch(() => form.end_time, () => clearValidationErrors('end_time'));
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-3 py-3 sm:items-center sm:px-4 sm:py-6" data-testid="worker-schedule-form-modal">
        <section class="max-h-[calc(100vh-1.5rem)] w-full max-w-lg overflow-hidden rounded-t-2xl bg-white shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:max-h-[calc(100vh-3rem)] sm:rounded-2xl">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ schedule ? 'Edit availability' : 'Add availability window' }}</h2>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white" @click="$emit('close')">
                    <i class="pi pi-times" aria-hidden="true"></i>
                </button>
            </div>

            <form class="max-h-[calc(100vh-6rem)] space-y-4 overflow-y-auto p-4 sm:p-5" @submit.prevent="submit">
                <FormSelect
                    id="schedule_day"
                    v-model="form.day_of_week"
                    label="Day"
                    :options="dayOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Select day"
                    :error="validationErrors.day_of_week || errors.day_of_week || []"
                    data-testid="worker-schedule-form-day"
                />

                <label class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200">
                    <span>Mark this full day off</span>
                    <input v-model="form.is_off_day" data-testid="worker-schedule-form-off-day" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:focus:ring-white">
                </label>

                <div v-if="!form.is_off_day" class="grid gap-4 sm:grid-cols-2">
                    <FormInput id="schedule_start" v-model="form.start_time" label="Start time" type="time" :error="validationErrors.start_time || errors.start_time || []" data-testid="worker-schedule-form-start-time" />
                    <FormInput id="schedule_end" v-model="form.end_time" label="End time" type="time" :error="validationErrors.end_time || errors.end_time || []" data-testid="worker-schedule-form-end-time" />
                </div>

                <div class="grid gap-2 pt-2 sm:flex sm:justify-end">
                    <AppButton type="button" variant="secondary" :full-width="false" data-testid="worker-schedule-form-cancel" @click="$emit('close')">Cancel</AppButton>
                    <div class="sm:w-auto">
                        <AppButton type="submit" icon="pi-save" :loading="loading" data-testid="worker-schedule-form-submit">{{ loading ? 'Saving...' : 'Save availability' }}</AppButton>
                    </div>
                </div>
            </form>
        </section>
    </div>
</template>
