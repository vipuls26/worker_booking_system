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
    tone: {
        type: String,
        default: 'default',
    },
});
</script>

<template>
    <component
        :is="as"
        :class="[
            'app-surface',
            tone === 'brand' ? 'app-surface-brand' : '',
            tone === 'success' ? 'app-surface-success' : '',
            tone === 'warning' ? 'app-surface-warning' : '',
            padded ? 'p-4 sm:p-5' : '',
        ]"
    >
        <slot />
    </component>
</template>
