<script setup>
import { computed, reactive, watch } from 'vue';
import { toast } from 'vue-sonner';
import PasswordUpdatePanel from '../../components/account/PasswordUpdatePanel.vue';
import AppButton from '../../components/common/AppButton.vue';
import AppPanel from '../../components/common/AppPanel.vue';
import FormInput from '../../components/forms/FormInput.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useAuthStore } from '../../stores/auth';

const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const form = reactive({
    name: '',
    email: '',
    phone: '',
});
const isEmailVerified = computed(() => Boolean(authStore.user?.email_verified_at));

/**
 * Copy the current authenticated user values into the admin profile form.
 */
function fillForm() {
    form.name = authStore.user?.name || '';
    form.email = authStore.user?.email || '';
    form.phone = authStore.user?.phone || '';
}

/**
 * Save the admin's account identity details from the dashboard profile page.
 */
async function submit() {
    clearApiErrors();

    try {
        const response = await authStore.updateProfile(form);
        toast.success(response.message || 'Profile updated successfully');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to update profile');
    }
}

watch(() => authStore.user, fillForm, { immediate: true });
</script>

<template>
    <AdminLayout title="Profile">
        <div class="grid gap-5 xl:grid-cols-[1.15fr_0.85fr]">
            <AppPanel>
                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Admin account</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Keep your identity details accurate for
                            audit and platform notifications.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInput id="admin_name" v-model="form.name" label="Name" autocomplete="name"
                            :error="errors.name" />
                        <FormInput id="admin_phone" v-model="form.phone" label="Phone" autocomplete="tel"
                            :error="errors.phone" />
                    </div>

                    <FormInput id="admin_email" v-model="form.email" type="email" label="Email" autocomplete="email"
                        :error="errors.email" />

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <div class="flex items-center gap-2 text-sm font-medium"
                            :class="isEmailVerified ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                            <i :class="['pi', isEmailVerified ? 'pi-verified' : 'pi-exclamation-triangle']"
                                aria-hidden="true"></i>
                            {{ isEmailVerified ? 'Email verified' : 'Email verification pending' }}
                        </div>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Changing your email will require email verification again.
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-gray-200 pt-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-end">

                        <AppButton type="submit" icon="pi-save" :full-width="false">
                            Save changes
                        </AppButton>
                    </div>
                </form>
            </AppPanel>

            <PasswordUpdatePanel />
        </div>
    </AdminLayout>
</template>
