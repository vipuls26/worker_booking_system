<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AppButton from '../components/common/AppButton.vue';
import ThemeToggle from '../components/common/ThemeToggle.vue';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const route = useRoute();
const refreshed = ref(false);
const isExpired = route.query.status === 'expired';

onMounted(async () => {
    if (isExpired || ! authStore.token) {
        refreshed.value = true;
        return;
    }

    try {
        await authStore.refreshUser();
    } finally {
        refreshed.value = true;
    }
});
</script>

<template>
    <main class="min-h-screen bg-gray-50 px-4 py-8 text-gray-900 dark:bg-gray-950 dark:text-white">
        <div class="mx-auto flex max-w-2xl justify-end">
            <ThemeToggle />
        </div>

        <section class="mx-auto mt-8 max-w-2xl rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div
                class="flex size-12 items-center justify-center rounded-lg"
                :class="isExpired ? 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'"
            >
                <i :class="['pi text-xl', isExpired ? 'pi-exclamation-triangle' : 'pi-check']" aria-hidden="true"></i>
            </div>

            <h1 class="mt-5 text-2xl font-semibold">{{ isExpired ? 'Verification link expired' : 'Email verified' }}</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ isExpired ? 'Request a new email verification link from your account and try again.' : 'Your email ownership is confirmed. Admin profile approval is handled separately and may still be pending.' }}
            </p>

            <div class="mt-6">
                <RouterLink v-if="isExpired && authStore.isAuthenticated" to="/email/verify">
                    <AppButton type="button" icon="pi-send">
                        Request new link
                    </AppButton>
                </RouterLink>

                <RouterLink v-else-if="authStore.isAuthenticated" :to="authStore.dashboardPath">
                    <AppButton type="button" icon="pi-arrow-right" :disabled="!refreshed">
                        Continue to dashboard
                    </AppButton>
                </RouterLink>

                <RouterLink
                    v-else
                    to="/login"
                    class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                >
                    Login
                </RouterLink>
            </div>
        </section>
    </main>
</template>
