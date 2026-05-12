<script setup>
import { onMounted, watch } from 'vue';
import { RouterView } from 'vue-router';
import { Toaster } from 'vue-sonner';
import { initializeTheme } from './composables/useTheme';
import { useAuthStore } from './stores/auth';
import { useRealtimeStore } from './stores/realtime';

const authStore = useAuthStore();
const realtimeStore = useRealtimeStore();

onMounted(async () => {
    initializeTheme();
    await authStore.bootstrap();
    realtimeStore.sync();
});

watch(
    () => [authStore.isAuthenticated, authStore.user?.id],
    () => {
        realtimeStore.sync();
    },
);
</script>

<template>
    <RouterView v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.fullPath" />
        </Transition>
    </RouterView>
    <Toaster rich-colors position="top-right" />
</template>
