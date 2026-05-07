<script setup>
defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Search',
    },
});

defineEmits(['update:modelValue', 'search']);
</script>

<template>
    <form class="flex gap-2" @submit.prevent="$emit('search')">
        <input
            :value="modelValue"
            :placeholder="placeholder"
            class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <button type="submit" class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-white dark:text-gray-950">
            <i class="pi pi-search" aria-hidden="true"></i>
            Search
        </button>
    </form>
</template>
