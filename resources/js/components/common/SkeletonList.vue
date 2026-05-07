<script setup>
import SkeletonCard from './SkeletonCard.vue';

defineProps({
    count: {
        type: Number,
        default: 4,
    },
    actions: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="grid gap-3">
        <SkeletonCard v-for="item in count" :key="item" :actions="actions" />
    </div>
</template>
