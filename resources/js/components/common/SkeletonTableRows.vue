<script setup>
import SkeletonBlock from './SkeletonBlock.vue';

defineProps({
    rows: {
        type: Number,
        default: 5,
    },
    columns: {
        type: Number,
        default: 4,
    },
    actions: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <template v-for="row in rows" :key="row">
        <tr>
            <td v-for="column in columns" :key="column" class="px-4 py-3">
                <div class="space-y-2">
                    <SkeletonBlock class="h-4" :class="column === 1 ? 'w-32' : 'w-24'" />
                    <SkeletonBlock v-if="column === 1" class="h-3 w-20" />
                </div>
            </td>
            <td v-if="actions" class="px-4 py-3">
                <div class="flex justify-end gap-2">
                    <SkeletonBlock class="size-9" />
                    <SkeletonBlock class="size-9" />
                </div>
            </td>
        </tr>
    </template>
</template>
