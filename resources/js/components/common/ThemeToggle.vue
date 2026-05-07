<script setup>
import { useTheme } from '../../composables/useTheme';

const { isDark, icon, label, toggleTheme } = useTheme();
</script>

<template>
    <button
        type="button"
        :aria-label="label"
        class="relative inline-flex h-10 w-16 shrink-0 items-center rounded-md border border-gray-300 bg-white px-1 text-gray-700 shadow-sm transition-colors duration-300 hover:bg-gray-50 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-white/10"
        @click="toggleTheme"
    >
        <span
            class="flex size-8 items-center justify-center rounded-md bg-gray-900 text-white shadow-sm transition-transform duration-300 ease-in-out dark:bg-white dark:text-gray-950"
            :class="isDark ? 'translate-x-6' : 'translate-x-0'"
        >
            <i :class="['pi text-sm', icon]" aria-hidden="true"></i>
        </span>
    </button>
</template>
