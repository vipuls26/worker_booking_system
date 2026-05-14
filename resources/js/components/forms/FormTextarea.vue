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

const textareaAttributes = useAttrs();
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
            v-bind="textareaAttributes"
            class="mt-1 block w-full min-w-0 rounded-md border-blue-100 bg-white text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-400"
            @input="$emit('update:modelValue', $event.target.value)"
        ></textarea>
        <FormError :error="error" />
    </div>
</template>
