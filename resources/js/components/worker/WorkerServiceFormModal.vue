<script setup>
import { computed, reactive, watch } from 'vue';
import AppButton from '../common/AppButton.vue';
import FormInput from '../forms/FormInput.vue';
import FormSelect from '../forms/FormSelect.vue';
import FormTextarea from '../forms/FormTextarea.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    workerService: {
        type: Object,
        default: null,
    },
    serviceOptions: {
        type: Array,
        default: () => [],
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'submit']);
const isRejectedReapply = computed(() => props.workerService?.approval_status === 'rejected');
const title = computed(() => {
    if (isRejectedReapply.value) {
        return 'Reapply for service';
    }

    return props.workerService ? 'Edit service request' : 'Apply for service';
});
const submitLabel = computed(() => {
    if (props.loading) {
        return 'Submitting...';
    }

    return isRejectedReapply.value ? 'Resubmit for approval' : 'Submit for approval';
});

const pricingTypes = [
    { label: 'Fixed Price', value: 'fixed' },
    { label: 'Hourly Price', value: 'hourly' },
];

const form = reactive({
    service_id: '',
    pricing_type: 'fixed',
    price: '',
    minimum_hours: '',
    description: '',
    is_active: true,
});

watch(
    () => [props.open, props.workerService],
    () => {
        Object.assign(form, {
            service_id: props.workerService?.service_id || '',
            pricing_type: props.workerService?.pricing_type || 'fixed',
            price: props.workerService?.price || '',
            minimum_hours: props.workerService?.minimum_hours || '',
            description: props.workerService?.description || '',
            is_active: props.workerService?.is_active ?? true,
        });
    },
    { immediate: true },
);

function submit() {
    emit('submit', {
        ...form,
        minimum_hours: form.pricing_type === 'hourly' ? form.minimum_hours : null,
    });
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-3 py-3 sm:items-center sm:px-4 sm:py-6" data-testid="worker-service-form-modal">
        <section class="max-h-[calc(100vh-1.5rem)] w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:max-h-[calc(100vh-3rem)]">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white" @click="$emit('close')">
                    <i class="pi pi-times" aria-hidden="true"></i>
                </button>
            </div>

            <form class="max-h-[calc(100vh-6rem)] space-y-4 overflow-y-auto p-4 sm:p-5" @submit.prevent="submit">
                <FormSelect
                    id="worker_service_service"
                    v-model="form.service_id"
                    label="Service"
                    :options="serviceOptions"
                    option-label="name"
                    option-value="id"
                    placeholder="Select a service"
                    :error="errors.service_id"
                    data-testid="worker-service-form-service"
                />

                <FormSelect
                    id="worker_service_pricing_type"
                    v-model="form.pricing_type"
                    label="Pricing type"
                    :options="pricingTypes"
                    option-label="label"
                    option-value="value"
                    :error="errors.pricing_type"
                    data-testid="worker-service-form-pricing-type"
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormInput id="worker_service_price" v-model="form.price" label="Price" type="number" min="1" step="0.01" :error="errors.price" data-testid="worker-service-form-price" />
                    <FormInput
                        v-if="form.pricing_type === 'hourly'"
                        id="worker_service_minimum_hours"
                        v-model="form.minimum_hours"
                        label="Minimum hours"
                        type="number"
                        min="1"
                        max="24"
                        step="1"
                        :error="errors.minimum_hours"
                        data-testid="worker-service-form-minimum-hours"
                    />
                </div>

                <div
                    v-if="isRejectedReapply && workerService?.rejection_reason"
                    class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200"
                >
                    Previous rejection: {{ workerService.rejection_reason }}
                </div>

                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                    Service requests stay hidden from customers until an admin approves them. Reapplying updates the rejected request and sends it back for approval.
                </div>

                <FormTextarea id="worker_service_description" v-model="form.description" label="Description" :error="errors.description" data-testid="worker-service-form-description" />

                <div class="grid gap-2 pt-2 sm:flex sm:justify-end">
                    <button type="button" data-testid="worker-service-form-cancel" class="rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 shadow-[0_3px_0_#bfdbfe,0_8px_16px_rgba(37,99,235,0.12)] transition-all duration-150 hover:-translate-y-0.5 hover:bg-blue-100 active:translate-y-0.5 active:shadow-[0_1px_0_#bfdbfe,0_5px_10px_rgba(37,99,235,0.12)] dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:shadow-[0_3px_0_rgba(59,130,246,0.18)] dark:hover:bg-white/10" @click="$emit('close')">
                        Cancel
                    </button>
                    <div class="sm:w-auto">
                        <AppButton type="submit" icon="pi-send" :loading="loading" data-testid="worker-service-form-submit">{{ submitLabel }}</AppButton>
                    </div>
                </div>
            </form>
        </section>
    </div>
</template>
