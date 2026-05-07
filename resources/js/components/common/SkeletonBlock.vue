<script setup>
defineProps({
    rounded: {
        type: String,
        default: 'rounded-md',
    },
});
</script>

<template>
    <span
        aria-hidden="true"
        :class="[
            'block animate-pulse bg-gray-200 motion-reduce:animate-none dark:bg-gray-800',
            rounded,
        ]"
    ></span>
</template>
