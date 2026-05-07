<script setup>
defineProps({
    error: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <p v-if="error.length" class="mt-1 text-sm text-red-600 dark:text-red-400">
        {{ error[0] }}
    </p>
</template>
