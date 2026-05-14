<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../components/common/AppButton.vue';
import FormTextarea from '../components/forms/FormTextarea.vue';
import ThemeToggle from '../components/common/ThemeToggle.vue';
import { useApiErrors } from '../composables/useApiErrors';
import { useYupValidation } from '../composables/useYupValidation';
import { useAuthStore } from '../stores/auth';
import { unblockRequestSchema } from '../validation/profileSchemas';

const router = useRouter();
const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(unblockRequestSchema);
const loading = ref(false);
const saving = ref(false);
const latestRequest = ref(null);
const restrictionContent = computed(() => {
    if (authStore.accountStatus === 'partially_blocked') {
        return {
            title: 'Account partially blocked',
            message: authStore.role === 'worker'
                ? 'You can still access your dashboard, profile, and booking history. New work is paused until admin reviews your unblock request.'
                : 'You can still access your dashboard, profile, and booking history. New bookings are paused until admin reviews your unblock request.',
        };
    }

    return {
        title: 'Account fully blocked',
        message: 'Your account is under a full restriction. Protected platform actions are paused until admin review is complete.',
    };
});
const canSubmitUnblockRequest = computed(() => {
    if (! latestRequest.value) {
        return true;
    }

    if (latestRequest.value.status === 'rejected') {
        return true;
    }

    if (latestRequest.value.status === 'approved' && authStore.isRestricted) {
        return true;
    }

    return false;
});
const unblockFormHeading = computed(() => {
    if (canSubmitUnblockRequest.value) {
        return 'Request account review';
    }

    return 'Unblock request status';
});
const unblockFormHelpText = computed(() => {
    if (! latestRequest.value) {
        return 'Explain why admin should remove this restriction so your account can be reviewed.';
    }

    if (latestRequest.value.status === 'rejected') {
        return 'Your last request was rejected. Update the reason below and submit a new review request.';
    }

    if (latestRequest.value.status === 'approved' && authStore.isRestricted) {
        return 'A previous unblock request was approved in the past, but this account is currently restricted again. Submit a new request for this new restriction.';
    }

    if (latestRequest.value.status === 'pending') {
        return 'Your request has already been submitted. Admin will review it and update this page once a decision is made.';
    }

    return 'This request has already been reviewed. Follow the status details below for the next step.';
});

const form = reactive({
    reason: '',
});

async function loadRequest() {
    loading.value = true;

    try {
        const response = await authStore.fetchUnblockRequest();
        latestRequest.value = response.data.unblock_request;

        if (latestRequest.value?.status === 'approved') {
            await authStore.refreshUser();

            if (! authStore.isRestricted) {
                toast.success('Your unblock request was approved');
                await router.replace(authStore.dashboardPath);
                return;
            }
        }

        form.reason = canSubmitUnblockRequest.value ? (latestRequest.value?.reason || '') : '';
    } catch {
        toast.error('Unable to load unblock request status');
    } finally {
        loading.value = false;
    }
}

async function submit() {
    if (! canSubmitUnblockRequest.value) {
        return;
    }

    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please explain why the account should be restored.');

        return;
    }

    saving.value = true;

    try {
        const response = await authStore.submitUnblockRequest(form);
        latestRequest.value = response.data.unblock_request;
        form.reason = '';
        toast.success(response.message || 'Unblock request submitted');
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to submit request');
    } finally {
        saving.value = false;
    }
}

async function logout() {
    await authStore.logout();
    await router.push('/login');
}

onMounted(async () => {
    await authStore.refreshUser();

    if (! authStore.isRestricted) {
        await router.replace(authStore.dashboardPath);
        return;
    }

    await loadRequest();
});

watch(() => form.reason, () => clearValidationErrors('reason'));
</script>

<template>
    <main class="min-h-screen bg-gray-50 px-4 py-8 text-gray-900 dark:bg-gray-950 dark:text-white" data-testid="blocked-account-page">
        <div class="mx-auto flex max-w-2xl justify-end">
            <ThemeToggle />
        </div>

        <section class="mx-auto mt-8 max-w-2xl rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex size-12 items-center justify-center rounded-lg bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300">
                <i class="pi pi-ban text-xl" aria-hidden="true"></i>
            </div>

            <h1 class="mt-5 text-2xl font-semibold">{{ restrictionContent.title }}</h1>
            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ restrictionContent.message }}
            </p>

            <div v-if="latestRequest" class="mt-5 rounded-md border border-gray-200 p-4 text-sm dark:border-white/10" data-testid="blocked-account-latest-request">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-gray-900 dark:text-white">Latest request</p>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold capitalize text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ latestRequest.status.replace('_', ' ') }}
                    </span>
                </div>
                <p class="mt-2 text-gray-600 dark:text-gray-300">{{ latestRequest.reason }}</p>
                <p v-if="latestRequest.admin_note" class="mt-2 text-gray-500 dark:text-gray-400">Admin note: {{ latestRequest.admin_note }}</p>
            </div>

            <section class="mt-6 space-y-4" data-testid="blocked-account-unblock-request-section">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ unblockFormHeading }}</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ unblockFormHelpText }}</p>
                </div>

                <p v-if="latestRequest?.status === 'pending'" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    Your unblock request is pending admin review.
                </p>

                <form v-if="canSubmitUnblockRequest" class="space-y-4" data-testid="blocked-account-unblock-form" @submit.prevent="submit">
                <div v-if="errors.request?.length || errors.account?.length" class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-200">
                    {{ errors.request?.[0] || errors.account?.[0] }}
                </div>
                <FormTextarea id="unblock_reason" v-model="form.reason" label="Why should admin remove this restriction?" :error="validationErrors.reason || errors.reason || []" data-testid="blocked-account-reason" :disabled="saving" />
                <AppButton type="submit" icon="pi-send" :loading="saving" data-testid="blocked-account-submit">
                    {{ saving ? 'Submitting...' : 'Submit unblock request' }}
                </AppButton>
                </form>

                <div v-else class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-100">
                    <p class="font-semibold">Unblock request already submitted</p>
                    <p class="mt-1">
                        Admin is already reviewing your latest appeal. You cannot send another request until this one is rejected or approved.
                    </p>
                </div>
            </section>
            <button type="button" data-testid="blocked-account-logout" class="mt-6 text-sm font-medium text-gray-600 underline dark:text-gray-300" @click="logout">
                Logout
            </button>
        </section>
    </main>
</template>
