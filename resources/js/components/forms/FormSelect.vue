<script setup>
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
        <label :for="id" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ label }}</label>
        <select
            :id="id"
            :value="modelValue"
            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
            @change="$emit('update:modelValue', Number($event.target.value) || $event.target.value)"
        >
            <option value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option[optionValue]" :value="option[optionValue]">
                {{ option[optionLabel] }}
            </option>
        </select>
        <p v-if="error.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ error[0] }}</p>
    </div>
</template>
