<script setup>
import { useAttrs } from 'vue';
import FormError from './FormError.vue';
import FormLabel from './FormLabel.vue';

defineOptions({
    inheritAttrs: false,
});

defineProps({
    id: {
        type: String,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    modelValue: {
        type: [String, Number],
        default: '',
    },
    options: {
        type: Array,
        required: true,
    },
    optionLabel: {
        type: String,
        default: 'name',
    },
    optionValue: {
        type: String,
        default: 'id',
    },
    placeholder: {
        type: String,
        default: 'Select an option',
    },
    error: {
        type: Array,
        default: () => [],
    },
});

defineEmits(['update:modelValue']);

const selectAttributes = useAttrs();
</script>

<template>
    <div>
        <FormLabel :for-id="id">{{ label }}</FormLabel>
        <div class="relative mt-1">
            <select
                :id="id"
                :value="modelValue"
                v-bind="selectAttributes"
                class="block min-h-10 w-full min-w-0 appearance-none rounded-md border-slate-300 bg-white py-2 pl-3 pr-10 text-slate-900 shadow-sm [color-scheme:light] focus:border-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 dark:[color-scheme:dark] dark:focus:border-blue-400 dark:focus:ring-blue-400"
                @change="$emit('update:modelValue', Number($event.target.value) || $event.target.value)"
            >
                <option value="" disabled class="bg-white text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                    {{ placeholder }}
                </option>
                <option
                    v-for="option in options"
                    :key="option[optionValue]"
                    :value="option[optionValue]"
                    class="bg-white text-slate-900 dark:bg-slate-950 dark:text-slate-100"
                >
                    {{ option[optionLabel] }}
                </option>
            </select>

            <span
                class="pointer-events-none absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-500 dark:text-slate-400"
            >
                <i class="pi pi-chevron-down text-xs" aria-hidden="true"></i>
            </span>
        </div>
        <FormError :error="error" />
    </div>
</template>
