<script setup>
import { useRouter } from 'vue-router';
import ThemeToggle from '../components/common/ThemeToggle.vue';
import { useAuthStore } from '../stores/auth';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
});

const router = useRouter();
const authStore = useAuthStore();

async function handleLogout() {
    await authStore.logout();
    await router.push('/login');
}
</script>

<template>
    <main class="min-h-screen bg-gray-50 dark:bg-gray-950">
        <header class="border-b border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">{{ authStore.role }}</p>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">{{ props.title }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
                        @click="handleLogout"
                    >
                        <i class="pi pi-sign-out" aria-hidden="true"></i>
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <section class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </section>
    </main>
</template>
