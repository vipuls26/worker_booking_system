<script setup>
import { computed, ref } from 'vue';
import FormError from './FormError.vue';
import FormLabel from './FormLabel.vue';

const props = defineProps({
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
    type: {
        type: String,
        default: 'text',
    },
    error: {
        type: Array,
        default: () => [],
    },
    autocomplete: {
        type: String,
        default: '',
    },
    min: {
        type: [String, Number],
        default: null,
    },
    max: {
        type: [String, Number],
        default: null,
    },
    step: {
        type: [String, Number],
        default: null,
    },
});

defineEmits(['update:modelValue']);

const isPasswordVisible = ref(false);
const isPassword = computed(() => props.type === 'password');
const inputType = computed(() => (isPassword.value && isPasswordVisible.value ? 'text' : props.type));
</script>

<template>
    <div>
        <FormLabel :for-id="id">{{ label }}</FormLabel>
        <div class="relative mt-1">
            <input
                :id="id"
                :type="inputType"
                :value="modelValue"
                :autocomplete="autocomplete"
                :min="min"
                :max="max"
                :step="step"
                :class="[
                    'block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:placeholder:text-gray-500 dark:focus:border-white dark:focus:ring-white',
                    isPassword ? 'pr-10' : '',
                ]"
                @input="$emit('update:modelValue', $event.target.value)"
            >
            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                :aria-label="isPasswordVisible ? 'Hide password' : 'Show password'"
                @click="isPasswordVisible = !isPasswordVisible"
            >
                <i :class="['pi', isPasswordVisible ? 'pi-eye-slash' : 'pi-eye']" aria-hidden="true"></i>
            </button>
        </div>
        <FormError :error="error" />
    </div>
</template>
