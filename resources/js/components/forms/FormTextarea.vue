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
        type: String,
        default: '',
    },
    rows: {
        type: [String, Number],
        default: 4,
    },
    placeholder: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
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
        <textarea
            :id="id"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            :required="required"
            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-white dark:focus:ring-white"
            @input="$emit('update:modelValue', $event.target.value)"
        ></textarea>
        <FormError :error="error" />
    </div>
</template>
