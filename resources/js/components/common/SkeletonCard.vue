<script setup>
import SkeletonBlock from './SkeletonBlock.vue';

defineProps({
    lines: {
        type: Number,
        default: 3,
    },
    avatar: {
        type: Boolean,
        default: true,
    },
    actions: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <article class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex gap-3">
            <SkeletonBlock v-if="avatar" rounded="rounded-md" class="size-10 shrink-0" />

            <div class="min-w-0 flex-1 space-y-3">
                <SkeletonBlock class="h-4 w-2/3" />
                <SkeletonBlock
                    v-for="line in lines"
                    :key="line"
                    class="h-3"
                    :class="line === lines ? 'w-1/2' : 'w-full'"
                />
            </div>
        </div>

        <div v-if="actions" class="mt-4 grid grid-cols-2 gap-2">
            <SkeletonBlock class="h-9" />
            <SkeletonBlock class="h-9" />
        </div>
    </article>
</template>
