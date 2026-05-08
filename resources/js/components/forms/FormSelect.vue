<script setup>
import FormError from './FormError.vue';
import FormLabel from './FormLabel.vue';

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
</script>

<template>
    <div>
        <FormLabel :for-id="id">{{ label }}</FormLabel>
        <div class="relative mt-1">
            <select
                :id="id"
                :value="modelValue"
                class="block w-full appearance-none rounded-md border-blue-100 bg-white py-2 pl-3 pr-10 text-gray-900 shadow-sm [color-scheme:light] focus:border-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark] dark:focus:border-blue-400 dark:focus:ring-blue-400"
                @change="$emit('update:modelValue', Number($event.target.value) || $event.target.value)"
            >
                <option value="" disabled class="bg-white text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                    {{ placeholder }}
                </option>
                <option
                    v-for="option in options"
                    :key="option[optionValue]"
                    :value="option[optionValue]"
                    class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white"
                >
                    {{ option[optionLabel] }}
                </option>
            </select>

            <span
                class="pointer-events-none absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-500 dark:text-gray-400"
            >
                <i class="pi pi-chevron-down text-xs" aria-hidden="true"></i>
            </span>
        </div>
        <FormError :error="error" />
    </div>
</template>
