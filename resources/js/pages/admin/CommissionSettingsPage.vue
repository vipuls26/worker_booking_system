<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import AppPanel from '../../components/common/AppPanel.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import FormInput from '../../components/forms/FormInput.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminCommissionSettingsStore } from '../../stores/admin/commissionSettings';

const commissionSettingsStore = useAdminCommissionSettingsStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();

const form = ref({
    commission_rate: '',
});
const confirmationOpen = ref(false);

const currentRate = computed(() => Number(commissionSettingsStore.currentRate).toFixed(2));
const previewRate = computed(() => Number(form.value.commission_rate || 0));
const previewAmount = computed(() => 1000);
const previewCommission = computed(() => (previewAmount.value * (previewRate.value / 100)).toFixed(2));
const previewWorkerEarning = computed(() => (previewAmount.value - Number(previewCommission.value)).toFixed(2));

async function loadSetting() {
    try {
        await commissionSettingsStore.fetch();
        form.value.commission_rate = currentRate.value;
    } catch {
        toast.error('Unable to load commission settings');
    }
}

function openConfirmation() {
    clearApiErrors();

    if (form.value.commission_rate === '') {
        errors.value = { commission_rate: ['Please provide commission rate.'] };

        return;
    }

    confirmationOpen.value = true;
}

function cancelConfirmation() {
    confirmationOpen.value = false;
}

async function saveSetting() {
    clearApiErrors();

    try {
        const response = await commissionSettingsStore.update({
            commission_rate: form.value.commission_rate,
        });

        form.value.commission_rate = Number(response.data.commission_setting.commission_rate).toFixed(2);
        confirmationOpen.value = false;
        toast.success(response.message || 'Commission rate updated');
    } catch (error) {
        confirmationOpen.value = false;
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to update commission rate');
    }
}

onMounted(() => loadSetting());
</script>

<template>
    <AdminLayout title="Commission Settings">
        <div class="space-y-5" data-testid="admin-commission-settings-page">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Global commission rate</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">New bookings lock the active rate when the customer selects a worker.</p>
                </div>
                <div class="rounded-md border border-blue-100 bg-white px-4 py-3 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Current rate</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ currentRate }}%</p>
                </div>
            </div>

            <AppPanel>
                <div v-if="commissionSettingsStore.loading" class="space-y-3">
                    <div class="h-5 w-48 animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>
                    <div class="h-10 w-full animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>
                    <div class="h-24 w-full animate-pulse rounded bg-gray-200 dark:bg-white/10"></div>
                </div>

                <form v-else class="space-y-5" @submit.prevent="openConfirmation">
                    <FormInput
                        id="commission_rate"
                        v-model="form.commission_rate"
                        label="Commission percentage"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                        :error="errors.commission_rate"
                        data-testid="commission-rate-input"
                    />

                    <div class="grid gap-3 rounded-lg border border-blue-100 bg-blue-50 p-4 dark:border-white/10 dark:bg-white/5 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Example booking</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">₹{{ previewAmount.toFixed(2) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Platform commission</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">₹{{ previewCommission }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Worker earning</p>
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">₹{{ previewWorkerEarning }}</p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <AppButton
                            type="submit"
                            icon="pi-save"
                            :loading="commissionSettingsStore.saving"
                            :full-width="false"
                            data-testid="commission-save-button"
                        >
                            {{ commissionSettingsStore.saving ? 'Saving...' : 'Save rate' }}
                        </AppButton>
                    </div>
                </form>
            </AppPanel>
        </div>

        <ConfirmDialog
            :open="confirmationOpen"
            title="Update commission rate"
            :message="`New bookings will use ${form.commission_rate}% after this update. Existing bookings and payments keep their locked quoted values.`"
            @cancel="cancelConfirmation"
            @confirm="saveSetting"
        />
    </AdminLayout>
</template>
