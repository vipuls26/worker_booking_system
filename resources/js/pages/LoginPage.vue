<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import FormInput from '../components/forms/FormInput.vue';
import { pullStoredAuthNotice } from '../lib/authStorage';
import { useApiErrors } from '../composables/useApiErrors';
import { useYupValidation } from '../composables/useYupValidation';
import AuthLayout from '../layouts/AuthLayout.vue';
import { useAuthStore } from '../stores/auth';
import { loginSchema } from '../validation/authSchemas';

const router = useRouter();
const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(loginSchema);
const loading = ref(false);

const form = reactive({
    email: '',
    password: '',
    remember: false,
});

async function submit() {
    if (loading.value) {
        return;
    }

    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please fix the highlighted login fields.');

        return;
    }

    loading.value = true;

    try {
        await authStore.login(form);
        toast.success('Login successful');

        if (authStore.isBlocked && ! authStore.isEmailVerified) {
            await router.push('/email/verify');
            return;
        }

        await router.push(authStore.isBlocked ? '/account/blocked' : authStore.dashboardPath);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Login failed');
    } finally {
        loading.value = false;
    }
}

watch(() => form.email, () => clearValidationErrors('email'));
watch(() => form.password, () => clearValidationErrors('password'));

onMounted(() => {
    const authNotice = pullStoredAuthNotice();

    if (authNotice) {
        toast.error(authNotice);
    }
});
</script>

<template>
    <AuthLayout>
        <form class="space-y-5" data-testid="login-form" @submit.prevent="submit">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Sign in</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Use your platform account to continue.</p>
            </div>

            <FormInput id="email" v-model="form.email" label="Email" type="email" autocomplete="email" :error="validationErrors.email || errors.email || []" data-testid="login-email" />
            <div class="space-y-2">
                <FormInput id="password" v-model="form.password" label="Password" type="password" autocomplete="current-password" :error="validationErrors.password || errors.password || []" data-testid="login-password" />
                <div class="flex items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <input v-model="form.remember" type="checkbox" class="size-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-white/10 dark:bg-slate-950 dark:text-blue-400 dark:focus:ring-blue-400">
                        <span>Remember me</span>
                    </label>
                    <RouterLink to="/forgot-password" class="text-sm font-medium text-gray-700 underline transition hover:text-gray-950 dark:text-gray-300 dark:hover:text-white">
                        Forgot password?
                    </RouterLink>
                </div>
            </div>

            <AppButton type="submit" icon="pi-sign-in" :loading="loading" data-testid="login-submit">{{ loading ? 'Signing in...' : 'Login' }}</AppButton>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                New here?
                <RouterLink to="/register" class="font-medium text-gray-900 underline dark:text-white">Create an account</RouterLink>
            </p>
        </form>
    </AuthLayout>
</template>
