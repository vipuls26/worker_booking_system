<script setup>
import { onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search',
    },
    debounce: {
        type: Number,
        default: 400,
    },
});

const emit = defineEmits(['update:modelValue', 'search']);
const timeout = ref(null);

function submit() {
    clearTimeout(timeout.value);
    emit('search');
}

function update(value) {
    emit('update:modelValue', value);
    clearTimeout(timeout.value);
    timeout.value = setTimeout(() => emit('search'), props.debounce);
}

onBeforeUnmount(() => {
    clearTimeout(timeout.value);
});
</script>

<template>
    <form class="grid gap-2 sm:grid-cols-[1fr_auto]" @submit.prevent="submit">
        <input
            :value="modelValue"
            :placeholder="placeholder"
            class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
            @input="update($event.target.value)"
        >
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-gray-950">
            <i class="pi pi-search" aria-hidden="true"></i>
            Search
        </button>
    </form>
</template>
