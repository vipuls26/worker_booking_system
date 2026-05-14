<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import ThemeToggle from '../components/common/ThemeToggle.vue';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
const router = useRouter();
const sending = ref(false);
const checkingVerification = ref(false);
let verificationPollingWindow = null;

async function resend() {
    sending.value = true;

    try {
        const response = await authStore.sendVerificationEmail();
        toast.success(response.message || 'Verification link sent');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to send verification email');
    } finally {
        sending.value = false;
    }
}

async function checkVerificationStatus() {
    if (! authStore.isAuthenticated || checkingVerification.value || authStore.isEmailVerified) {
        return;
    }

    checkingVerification.value = true;

    try {
        await authStore.refreshUser();

        if (authStore.isEmailVerified) {
            stopVerificationPolling();
            toast.success('Email verified successfully');
            await router.replace(authStore.dashboardPath);
        }
    } catch {
        // Ignore temporary refresh failures and let the next poll try again.
    } finally {
        checkingVerification.value = false;
    }
}

function startVerificationPolling() {
    if (! authStore.isAuthenticated || authStore.isEmailVerified || verificationPollingWindow !== null) {
        return;
    }

    verificationPollingWindow = window.setInterval(() => {
        void checkVerificationStatus();
    }, 4000);
}

function stopVerificationPolling() {
    if (verificationPollingWindow === null) {
        return;
    }

    window.clearInterval(verificationPollingWindow);
    verificationPollingWindow = null;
}

onMounted(() => {
    if (authStore.isEmailVerified) {
        void router.replace(authStore.dashboardPath);
        return;
    }

    startVerificationPolling();
    void checkVerificationStatus();
});

onUnmounted(() => {
    stopVerificationPolling();
});
</script>

<template>
    <main class="min-h-screen bg-gray-50 px-4 py-8 text-gray-900 dark:bg-gray-950 dark:text-white">
        <div class="mx-auto flex max-w-2xl justify-end">
            <ThemeToggle />
        </div>

        <section class="mx-auto mt-8 max-w-2xl rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex size-12 items-center justify-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                <i class="pi pi-envelope text-xl" aria-hidden="true"></i>
            </div>

            <h1 class="mt-5 text-2xl font-semibold">Verify your email</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                We sent a verification link to your email address. You can log in, but protected booking features stay locked until this email check is complete.
            </p>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <AppButton type="button" icon="pi-send" :loading="sending" @click="resend">
                    {{ sending ? 'Sending...' : 'Resend verification email' }}
                </AppButton>

                <RouterLink
                    :to="authStore.dashboardPath"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
                >
                    Go to dashboard
                </RouterLink>
            </div>
        </section>
    </main>
</template>
