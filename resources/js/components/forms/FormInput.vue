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
                    'block min-h-10 w-full min-w-0 rounded-md border-blue-100 bg-white text-gray-900 shadow-sm [color-scheme:light] placeholder:text-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark] dark:placeholder:text-gray-500 dark:focus:border-blue-400 dark:focus:ring-blue-400',
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
