<script setup>
import { reactive, ref, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import FormInput from '../components/forms/FormInput.vue';
import { useApiErrors } from '../composables/useApiErrors';
import { useYupValidation } from '../composables/useYupValidation';
import AuthLayout from '../layouts/AuthLayout.vue';
import { useAuthStore } from '../stores/auth';
import { forgotPasswordSchema } from '../validation/authSchemas';

const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(forgotPasswordSchema);
const loading = ref(false);
const sent = ref(false);

const form = reactive({
    email: '',
});

async function submit() {
    if (loading.value) {
        return;
    }

    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please enter a valid email address.');

        return;
    }

    loading.value = true;

    try {
        const response = await authStore.forgotPassword(form);
        sent.value = true;
        toast.success(response.message || 'Password reset link sent');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Could not send reset link');
    } finally {
        loading.value = false;
    }
}

watch(() => form.email, () => clearValidationErrors('email'));
</script>

<template>
    <AuthLayout>
        <form class="space-y-5" data-testid="forgot-password-form" @submit.prevent="submit">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Forgot password</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Enter your email and we will send a secure reset link.
                </p>
            </div>

            <div
                v-if="sent"
                data-testid="forgot-password-success"
                class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200"
            >
                Reset link sent. Check your inbox for the next step.
            </div>

            <FormInput id="email" v-model="form.email" label="Email" type="email" autocomplete="email" :error="validationErrors.email || errors.email || []" data-testid="forgot-password-email" />

            <AppButton type="submit" icon="pi-send" :loading="loading" data-testid="forgot-password-submit">{{ loading ? 'Sending...' : 'Send reset link' }}</AppButton>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                Remembered it?
                <RouterLink to="/login" class="font-medium text-gray-900 underline dark:text-white">Back to login</RouterLink>
            </p>
        </form>
    </AuthLayout>
</template>
