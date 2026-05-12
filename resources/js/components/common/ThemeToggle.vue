<script setup>
import { computed } from 'vue';
import { useThemeStore } from '../../stores/themeStore';

const themeStore = useThemeStore();

const iconClass = computed(() => (themeStore.darkMode ? 'pi pi-sun' : 'pi pi-moon'));
</script>

<template>
    <button
        type="button"
        :aria-label="themeStore.themeLabel"
        class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-gray-300 bg-white text-gray-700 shadow-sm transition-all duration-500 ease-in-out hover:scale-110 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:text-yellow-300"
        @click="themeStore.toggleTheme"
    >
        <Transition
            mode="out-in"
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-300 ease-in absolute"
            enter-from-class="opacity-0 rotate-90 scale-50"
            enter-to-class="opacity-100 rotate-0 scale-100"
            leave-from-class="opacity-100 rotate-0 scale-100"
            leave-to-class="opacity-0 -rotate-90 scale-50"
        >
            <i :key="iconClass" :class="iconClass" class="text-lg" aria-hidden="true"></i>
        </Transition>
    </button>
</template>



