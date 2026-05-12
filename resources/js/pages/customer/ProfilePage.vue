<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import PasswordUpdatePanel from '../../components/account/PasswordUpdatePanel.vue';
import AppButton from '../../components/common/AppButton.vue';
import AppPanel from '../../components/common/AppPanel.vue';
import FormInput from '../../components/forms/FormInput.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useAuthStore } from '../../stores/auth';

const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const saving = ref(false);
const form = reactive({
    name: '',
    email: '',
    phone: '',
    address: '',
});
const isEmailVerified = computed(() => Boolean(authStore.user?.email_verified_at));

function fillForm() {
    form.name = authStore.user?.name || '';
    form.email = authStore.user?.email || '';
    form.phone = authStore.user?.phone || '';
    form.address = authStore.user?.address || '';
}

async function submit() {
    clearApiErrors();
    saving.value = true;

    try {
        const response = await authStore.updateProfile(form);
        toast.success(response.message || 'Profile updated successfully');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to update profile');
    } finally {
        saving.value = false;
    }
}

watch(() => authStore.user, fillForm, { immediate: true });
</script>

<template>
    <DashboardLayout title="Customer Profile">
        <div class="grid gap-5 lg:grid-cols-[320px_1fr]">
            <AppPanel>
                <div class="flex items-start gap-4">
                    <div class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-gray-900 text-lg font-semibold text-white dark:bg-white dark:text-gray-950">
                        {{ form.name.charAt(0).toUpperCase() || 'C' }}
                    </div>
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ form.name || 'Customer' }}</h2>
                        <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ form.email }}</p>
                    </div>
                </div>

                <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                    <div class="flex items-center gap-2 text-sm font-medium" :class="isEmailVerified ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'">
                        <i :class="['pi', isEmailVerified ? 'pi-verified' : 'pi-exclamation-triangle']" aria-hidden="true"></i>
                        {{ isEmailVerified ? 'Email verified' : 'Email verification pending' }}
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Changing your email will require email verification again.
                    </p>
                </div>
            </AppPanel>

            <AppPanel>
                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Personal information</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update the details used for bookings and worker communication.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormInput id="customer_name" v-model="form.name" label="Name" autocomplete="name" :error="errors.name" />
                        <FormInput id="customer_phone" v-model="form.phone" label="Phone" autocomplete="tel" :error="errors.phone" />
                    </div>

                    <FormInput id="customer_email" v-model="form.email" type="email" label="Email" autocomplete="email" :error="errors.email" />

                    <FormTextarea
                        id="customer_address"
                        v-model="form.address"
                        label="Address"
                        rows="4"
                        placeholder="House number, street, area, city"
                        :error="errors.address"
                    />

                    <div class="flex flex-col gap-3 border-t border-gray-200 pt-5 dark:border-white/10 sm:flex-row sm:items-center sm:justify-end">
                        <AppButton type="button" variant="secondary" :full-width="false" @click="fillForm">
                            Reset
                        </AppButton>
                        <AppButton type="submit" icon="pi-save" :loading="saving" :full-width="false">
                            Save changes
                        </AppButton>
                    </div>
                </form>
            </AppPanel>
        </div>

        <div class="mt-5 xl:max-w-3xl">
            <PasswordUpdatePanel />
        </div>
    </DashboardLayout>
</template>
