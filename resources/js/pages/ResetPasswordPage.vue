<script setup>
import { reactive, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import FormInput from '../components/forms/FormInput.vue';
import { useApiErrors } from '../composables/useApiErrors';
import { useYupValidation } from '../composables/useYupValidation';
import AuthLayout from '../layouts/AuthLayout.vue';
import { useAuthStore } from '../stores/auth';
import { resetPasswordSchema } from '../validation/authSchemas';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(resetPasswordSchema);
const loading = ref(false);

function queryString(value) {
    return Array.isArray(value) ? value[0] : value || '';
}

const form = reactive({
    token: queryString(route.query.token),
    email: queryString(route.query.email),
    password: '',
    password_confirmation: '',
});

async function submit() {
    if (loading.value) {
        return;
    }

    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please fix the highlighted password reset fields.');

        return;
    }

    loading.value = true;

    try {
        const response = await authStore.resetPassword(form);
        toast.success(response.message || 'Password reset successful');
        await router.push('/login');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Password reset failed');
    } finally {
        loading.value = false;
    }
}

watch(() => form.email, () => clearValidationErrors('email'));
watch(() => form.password, () => clearValidationErrors(['password', 'password_confirmation']));
watch(() => form.password_confirmation, () => clearValidationErrors('password_confirmation'));
</script>

<template>
    <AuthLayout>
        <form class="space-y-5" data-testid="reset-password-form" @submit.prevent="submit">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Reset password</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Create a new password for your account.
                </p>
            </div>

            <div
                v-if="!form.token"
                data-testid="reset-password-missing-token"
                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                Reset token is missing. Open the link from your reset email.
            </div>

            <FormInput id="email" v-model="form.email" label="Email" type="email" autocomplete="email" :error="validationErrors.email || errors.email || []" data-testid="reset-password-email" />
            <FormInput id="password" v-model="form.password" label="New password" type="password" autocomplete="new-password" :error="validationErrors.password || errors.password || []" data-testid="reset-password-new-password" />
            <FormInput
                id="password_confirmation"
                v-model="form.password_confirmation"
                label="Confirm password"
                type="password"
                autocomplete="new-password"
                :error="validationErrors.password_confirmation || errors.password_confirmation || []"
                data-testid="reset-password-confirm-password"
            />

            <AppButton type="submit" icon="pi-lock" :loading="loading" data-testid="reset-password-submit">{{ loading ? 'Resetting...' : 'Reset password' }}</AppButton>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                <RouterLink to="/login" class="font-medium text-gray-900 underline dark:text-white">Back to login</RouterLink>
            </p>
        </form>
    </AuthLayout>
</template>
