<script setup>
import { computed, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import { useAuthStore } from '../../stores/auth';

const authStore = useAuthStore();
const sending = ref(false);

const content = computed(() => {
    if (authStore.role === 'admin') {
        return null;
    }

    if (! authStore.isEmailVerified) {
        return {
            icon: 'pi-envelope',
            title: 'Email verification required',
            message: 'Verify your email address before using protected platform features.',
            action: 'Verify email',
            to: '/email/verify',
            tone: 'warning',
        };
    }

    if (! authStore.isPlatformVerified && authStore.role === 'worker') {
        return {
            icon: 'pi-shield',
            title: 'Worker profile verification pending',
            message: 'Submit your worker proof and wait for admin approval before managing services or bookings.',
            action: 'Update profile',
            to: '/worker/profile',
            tone: 'warning',
        };
    }

    if (! authStore.isPlatformVerified) {
        return {
            icon: 'pi-shield',
            title: 'Admin approval pending',
            message: 'Your email is verified. An admin must approve your account before bookings are enabled.',
            tone: 'warning',
        };
    }

    return null;
});

const toneClasses = computed(() => {
    return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100';
});

async function sendVerificationEmail() {
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
</script>

<template>
    <section v-if="content" :class="['mb-5 rounded-lg border p-4', toneClasses]">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-md bg-white/70 dark:bg-white/10">
                    <i :class="['pi', content.icon]" aria-hidden="true"></i>
                </span>
                <div>
                    <h2 class="text-sm font-semibold">{{ content.title }}</h2>
                    <p class="mt-1 text-sm opacity-80">{{ content.message }}</p>
                </div>
            </div>

            <RouterLink
                v-if="content.to"
                :to="content.to"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
            >
                {{ content.action }}
                <i class="pi pi-arrow-right text-xs" aria-hidden="true"></i>
            </RouterLink>

            <button
                v-else-if="content.action"
                type="button"
                :disabled="sending"
                class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                @click="sendVerificationEmail"
            >
                <i class="pi pi-send text-xs" aria-hidden="true"></i>
                {{ sending ? 'Sending...' : content.action }}
            </button>
        </div>
    </section>
</template>
