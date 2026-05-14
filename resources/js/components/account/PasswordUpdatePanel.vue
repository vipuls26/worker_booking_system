<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppButton from '../common/AppButton.vue';
import AppPanel from '../common/AppPanel.vue';
import FormInput from '../forms/FormInput.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useYupValidation } from '../../composables/useYupValidation';
import { useAuthStore } from '../../stores/auth';
import { passwordUpdateSchema } from '../../validation/authSchemas';

const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(passwordUpdateSchema);
const saving = ref(false);
const sendingResetLink = ref(false);
const form = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});
const resetEmailAddress = computed(() => authStore.user?.email || '');

/**
 * Clear the password form after a successful update or manual reset.
 */
function resetForm() {
    form.current_password = '';
    form.password = '';
    form.password_confirmation = '';
}

/**
 * Submit the authenticated password change request for the signed-in user.
 */
async function submit() {
    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please fix the highlighted password fields.');

        return;
    }

    saving.value = true;

    try {
        const response = await authStore.updatePassword(form);
        toast.success(response.message || 'Password updated successfully');
        resetForm();
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to update password');
    } finally {
        saving.value = false;
    }
}

/**
 * Email the signed-in user a password reset link when they cannot remember the current password.
 */
async function sendResetLink() {
    if (! resetEmailAddress.value) {
        toast.error('No account email is available for password reset.');
        return;
    }

    clearApiErrors();
    sendingResetLink.value = true;

    try {
        const response = await authStore.forgotPassword({
            email: resetEmailAddress.value,
        });

        toast.success(response.message || 'Password reset link sent');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to send reset link');
    } finally {
        sendingResetLink.value = false;
    }
}

watch(() => form.current_password, () => clearValidationErrors(['current_password', 'password']));
watch(() => form.password, () => clearValidationErrors(['password', 'password_confirmation']));
watch(() => form.password_confirmation, () => clearValidationErrors('password_confirmation'));
</script>

<template>
    <AppPanel>
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Password & security</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Change your password without leaving the dashboard.</p>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">Forgot your current password?</p>
                        <p class="mt-1 text-sm text-amber-800 dark:text-amber-100">
                            Send a reset link to <span class="font-medium">{{ resetEmailAddress || 'your account email' }}</span>.
                        </p>
                    </div>
                    <AppButton type="button" variant="secondary" icon="pi-send" :loading="sendingResetLink" :full-width="false" @click="sendResetLink">
                        Send reset link
                    </AppButton>
                </div>
            </div>

            <div class="grid gap-4">
                <FormInput id="current_password" v-model="form.current_password" type="password" label="Current password" autocomplete="current-password" :error="validationErrors.current_password || errors.current_password || []" />
                <FormInput id="new_password" v-model="form.password" type="password" label="New password" autocomplete="new-password" :error="validationErrors.password || errors.password || []" />
                <FormInput id="new_password_confirmation" v-model="form.password_confirmation" type="password" label="Confirm new password" autocomplete="new-password" :error="validationErrors.password_confirmation || errors.password_confirmation || []" />
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 pt-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-end">
               
                <AppButton type="submit" icon="pi-lock" :loading="saving" :full-width="false">
                    Update password
                </AppButton>
            </div>
        </form>
    </AppPanel>
</template>
