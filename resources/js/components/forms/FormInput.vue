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
    disabled: {
        type: Boolean,
        default: false,
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
                :disabled="disabled"
                :class="[
                    'block min-h-10 w-full min-w-0 rounded-md border-slate-300 bg-white text-slate-900 shadow-sm [color-scheme:light] placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 dark:[color-scheme:dark] dark:placeholder:text-slate-500 dark:focus:border-blue-400 dark:focus:ring-blue-400 dark:disabled:bg-slate-900 dark:disabled:text-slate-500',
                    isPassword ? 'pr-10' : '',
                ]"
                @input="$emit('update:modelValue', $event.target.value)"
            >
            <button
                v-if="isPassword"
                type="button"
                class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                :aria-label="isPasswordVisible ? 'Hide password' : 'Show password'"
                @click="isPasswordVisible = !isPasswordVisible"
            >
                <i :class="['pi', isPasswordVisible ? 'pi-eye-slash' : 'pi-eye']" aria-hidden="true"></i>
            </button>
        </div>
        <FormError :error="error" />
    </div>
</template>
