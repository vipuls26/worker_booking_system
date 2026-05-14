<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { roles as fetchRoles } from '../api/auth';
import AppButton from '../components/common/AppButton.vue';
import FormInput from '../components/forms/FormInput.vue';
import FormSelect from '../components/forms/FormSelect.vue';
import { useApiErrors } from '../composables/useApiErrors';
import { useYupValidation } from '../composables/useYupValidation';
import AuthLayout from '../layouts/AuthLayout.vue';
import { useAuthStore } from '../stores/auth';
import { registerSchema } from '../validation/authSchemas';

const router = useRouter();
const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(registerSchema);
const loading = ref(false);

const roles = ref([]);

const form = reactive({
    role_id: '',
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

onMounted(async () => {
    try {
        const response = await fetchRoles();
        roles.value = response.data.data.roles.filter((role) => role.slug !== 'admin');
        form.role_id = roles.value.find((role) => role.slug === 'customer')?.id || roles.value[0]?.id || '';
    } catch {
        toast.error('Unable to load roles');
    }
});

async function submit() {
    if (loading.value) {
        return;
    }

    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please fix the highlighted registration fields.');

        return;
    }

    loading.value = true;

    try {
        await authStore.register(form);
        toast.success('Registration successful');
        await router.push(authStore.dashboardPath);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Registration failed');
    } finally {
        loading.value = false;
    }
}

watch(() => form.role_id, () => clearValidationErrors('role_id'));
watch(() => form.name, () => clearValidationErrors('name'));
watch(() => form.email, () => clearValidationErrors('email'));
watch(() => form.phone, () => clearValidationErrors('phone'));
watch(() => form.password, () => clearValidationErrors(['password', 'password_confirmation']));
watch(() => form.password_confirmation, () => clearValidationErrors('password_confirmation'));
</script>

<template>
    <AuthLayout>
        <form class="space-y-5" data-testid="register-form" @submit.prevent="submit">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Create account</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Choose the role that matches how you will use
                    the platform.</p>
            </div>

            <FormInput id="name" v-model="form.name" label="Name" autocomplete="name" :error="validationErrors.name || errors.name || []" data-testid="register-name" />
            <FormInput id="email" v-model="form.email" label="Email" type="email" autocomplete="email"
                data-testid="register-email"
                :error="validationErrors.email || errors.email || []" />
            <FormInput id="phone" v-model="form.phone" label="Phone" autocomplete="tel" :error="validationErrors.phone || errors.phone || []" data-testid="register-phone" />
            <FormInput id="password" v-model="form.password" label="Password" type="password"
                autocomplete="new-password" :error="validationErrors.password || errors.password || []" data-testid="register-password" />
            <FormInput id="password_confirmation" v-model="form.password_confirmation" label="Confirm password"
                type="password" autocomplete="new-password" :error="validationErrors.password_confirmation || errors.password_confirmation || []" data-testid="register-password-confirmation" />

            <FormSelect id="role_id" v-model="form.role_id" label="Role" placeholder="Choose your role" :options="roles" data-testid="register-role"
                :error="validationErrors.role_id || errors.role_id || []" />
            <AppButton type="submit" icon="pi-user-plus" :loading="loading" data-testid="register-submit">{{ loading ? 'Creating account...' : 'Register' }}</AppButton>

            <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                Already registered?
                <RouterLink to="/login" class="font-medium text-gray-900 underline dark:text-white">Sign in</RouterLink>
            </p>
        </form>
    </AuthLayout>
</template>
