<script setup>
import { onMounted } from 'vue';
import { RouterView } from 'vue-router';
import { Toaster } from 'vue-sonner';
import { initializeTheme } from './composables/useTheme';
import { useAuthStore } from './stores/auth';

const authStore = useAuthStore();

onMounted(() => {
    initializeTheme();
    authStore.bootstrap();
});
</script>

<template>
    <RouterView v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.fullPath" />
        </Transition>
    </RouterView>
    <Toaster rich-colors position="top-right" />
</template>
