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
            class="block w-full rounded-md border-slate-300 bg-white text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:ring-blue-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-blue-400 dark:focus:ring-blue-400"
            @input="update($event.target.value)"
        >
        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400">
            <i class="pi pi-search" aria-hidden="true"></i>
            Search
        </button>
    </form>
</template>
