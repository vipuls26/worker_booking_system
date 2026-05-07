<script setup>
defineProps({
    forId: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <label :for="forId || null" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
        <slot />
    </label>
</template>
