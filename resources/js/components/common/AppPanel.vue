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
            'app-surface',
            padded ? 'p-4 sm:p-5' : '',
        ]"
    >
        <slot />
    </component>
</template>
