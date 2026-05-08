<script setup>
defineProps({
    as: {
        type: String,
        default: 'section',
    },
    padded: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <component
        :is="as"
        :class="[
            'rounded-lg bg-white shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10',
            padded ? 'p-4 sm:p-5' : '',
        ]"
    >
        <slot />
    </component>
</template>
