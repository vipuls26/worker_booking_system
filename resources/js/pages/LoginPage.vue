<script setup>
import { reactive, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import FormInput from '../components/forms/FormInput.vue';
import { useApiErrors } from '../composables/useApiErrors';
import AuthLayout from '../layouts/AuthLayout.vue';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const loading = ref(false);

const form = reactive({
    email: '',
    password: '',
});

async function submit() {
    loading.value = true;
    clearApiErrors();

    try {
        await authStore.login(form);
        toast.success('Login successful');
        await router.push(authStore.dashboardPath);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Login failed');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <AuthLayout>
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sign in</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Use your platform account to continue.</p>
            </div>

            <FormInput id="email" v-model="form.email" label="Email" type="email" autocomplete="email" :error="errors.email" />
            <div class="space-y-2">
                <FormInput id="password" v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="errors.password" />
                <div class="flex justify-end">
                    <RouterLink to="/forgot-password" class="text-sm font-medium text-gray-700 underline transition hover:text-gray-950 dark:text-gray-300 dark:hover:text-white">
                        Forgot password?
                    </RouterLink>
                </div>
            </div>

            <AppButton type="submit" icon="pi-sign-in" :loading="loading">{{ loading ? 'Signing in...' : 'Login' }}</AppButton>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                New here?
                <RouterLink to="/register" class="font-medium text-gray-900 underline dark:text-white">Create an account</RouterLink>
            </p>
        </form>
    </AuthLayout>
</template>
