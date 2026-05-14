<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { adminWorkerVerifications, approveWorkerVerification, rejectWorkerVerification, requestWorkerVerificationResubmission } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import AppButton from '../../components/common/AppButton.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import { useYupValidation } from '../../composables/useYupValidation';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { adminWorkerVerificationRejectionSchema, adminWorkerVerificationResubmissionSchema } from '../../validation/adminSchemas';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const verifications = ref([]);
const meta = ref({});
const search = ref('');
const status = ref('');
const filtersReady = ref(false);
const rejecting = ref(null);
const resubmitting = ref(null);
const rejectionReason = ref('');
const resubmissionReason = ref('');
const {
    validationErrors: rejectionErrors,
    clearValidationErrors: clearRejectionErrors,
    validateWithSchema: validateRejection,
} = useYupValidation(adminWorkerVerificationRejectionSchema);
const {
    validationErrors: resubmissionErrors,
    clearValidationErrors: clearResubmissionErrors,
    validateWithSchema: validateResubmission,
} = useYupValidation(adminWorkerVerificationResubmissionSchema);
const statusOptions = [
    { id: '', name: 'All statuses' },
    { id: 'pending', name: 'Pending' },
    { id: 'approved', name: 'Approved' },
    { id: 'rejected', name: 'Rejected' },
    { id: 'resubmission_requested', name: 'Resubmission requested' },
];
const chipBase = 'inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-semibold transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0.5';
const successChip = `${chipBase} bg-emerald-50 text-emerald-700 shadow-[0_2px_0_#bbf7d0,0_6px_12px_rgba(5,150,105,0.12)] hover:bg-emerald-100 active:shadow-[0_1px_0_#bbf7d0,0_4px_8px_rgba(5,150,105,0.12)] dark:bg-emerald-500/10 dark:text-emerald-300 dark:shadow-[0_2px_0_rgba(52,211,153,0.18)]`;
const warningChip = `${chipBase} bg-amber-50 text-amber-700 shadow-[0_2px_0_#fde68a,0_6px_12px_rgba(217,119,6,0.12)] hover:bg-amber-100 active:shadow-[0_1px_0_#fde68a,0_4px_8px_rgba(217,119,6,0.12)] dark:bg-amber-500/10 dark:text-amber-300 dark:shadow-[0_2px_0_rgba(251,191,36,0.18)]`;
const dangerChip = `${chipBase} bg-red-50 text-red-700 shadow-[0_2px_0_#fecaca,0_6px_12px_rgba(220,38,38,0.12)] hover:bg-red-100 active:shadow-[0_1px_0_#fecaca,0_4px_8px_rgba(220,38,38,0.12)] dark:bg-red-500/10 dark:text-red-300 dark:shadow-[0_2px_0_rgba(248,113,113,0.18)]`;
const rejectionWarning = computed(() => activeBookingWarning(rejecting.value));
const resubmissionWarning = computed(() => activeBookingWarning(resubmitting.value));

function activeBookingWarning(item) {
    const activeBookingsCount = item?.active_worker_bookings_count || 0;

    if (activeBookingsCount === 0) {
        return '';
    }

    return `This worker has ${activeBookingsCount} active booking${activeBookingsCount === 1 ? '' : 's'}. Existing bookings will stay active, customers will be notified, and new bookings will be blocked until verification is approved again.`;
}

async function load(page = 1) {
    loading.value = true;
    try {
        const response = await adminWorkerVerifications({
            search: search.value || undefined,
            status: status.value || undefined,
            page,
        });
        verifications.value = response.data.data.verifications;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load verifications');
    } finally {
        loading.value = false;
    }
}

useDebouncedWatch(
    () => [search.value, status.value],
    () => {
        if (! filtersReady.value) {
            return;
        }

        syncFiltersToRoute();
        load();
    },
);

async function approve(item) {
    await approveWorkerVerification(item.id);
    toast.success('Worker approved');
    await load();
}

async function reject() {
    clearRejectionErrors();
    const isValid = await validateRejection({
        rejection_reason: rejectionReason.value,
    });

    if (! isValid) {
        toast.error('Please add a rejection reason.');

        return;
    }

    await rejectWorkerVerification(rejecting.value.id, rejectionReason.value);
    toast.success('Worker rejected');
    rejecting.value = null;
    rejectionReason.value = '';
    await load();
}

async function requestResubmission() {
    clearResubmissionErrors();
    const isValid = await validateResubmission({
        resubmission_reason: resubmissionReason.value,
    });

    if (! isValid) {
        toast.error('Please explain what the worker should fix.');

        return;
    }

    await requestWorkerVerificationResubmission(resubmitting.value.id, resubmissionReason.value);
    toast.success('Resubmission requested');
    resubmitting.value = null;
    resubmissionReason.value = '';
    await load();
}

function openRejectModal(item) {
    rejecting.value = item;
    rejectionReason.value = '';
    clearRejectionErrors();
}

function openResubmissionModal(item) {
    resubmitting.value = item;
    resubmissionReason.value = '';
    clearResubmissionErrors();
}

function applyRouteFilters() {
    if (route.query.search !== undefined) {
        search.value = String(route.query.search);
    }

    if (route.query.status !== undefined) {
        status.value = String(route.query.status);
    }
}

function syncFiltersToRoute() {
    router.replace({
        path: route.path,
        query: {
            ...(search.value ? { search: search.value } : {}),
            ...(status.value ? { status: status.value } : {}),
        },
    });
}

onMounted(() => {
    applyRouteFilters();
    filtersReady.value = true;
    load();
});

watch(rejectionReason, () => clearRejectionErrors('rejection_reason'));
watch(resubmissionReason, () => clearResubmissionErrors('resubmission_reason'));
</script>

<template>
    <AdminLayout title="Worker Verification">
        <div class="space-y-4" data-testid="admin-worker-verifications-page">
            <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_200px]">
                <SearchFilter v-model="search" placeholder="Search worker, email, or review note" @search="load()" />
                <FormSelect id="verification_status" v-model="status" label="Status" :options="statusOptions" />
            </div>

            <div class="space-y-3 md:hidden">
                <div v-if="loading" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Loading verification queue...</p>
                </div>

                <div v-else-if="verifications.length === 0" class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                    No worker verifications found. Try changing the status filter or search term.
                </div>

                <article v-else v-for="item in verifications" :key="item.id" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white">{{ item.worker?.name }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ item.experience_years }} years experience</p>
                        </div>
                        <StatusBadge :value="item.status" />
                    </div>

                    <div class="mt-4 space-y-2 text-sm">
                        <a :href="item.id_proof_url" target="_blank" class="inline-flex items-center gap-2 text-blue-600 dark:text-blue-300">
                            <i class="pi pi-id-card" aria-hidden="true"></i>
                            View ID proof
                        </a>

                        <div v-if="item.certificates?.length" class="flex flex-wrap gap-2">
                            <a v-for="(certificate, index) in item.certificates" :key="certificate.path" :href="certificate.url" target="_blank" class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                Certificate {{ index + 1 }}
                            </a>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button :data-testid="`admin-worker-verification-approve-${item.id}`" :class="successChip" @click="approve(item)">Approve</button>
                        <button :data-testid="`admin-worker-verification-resubmit-${item.id}`" :class="warningChip" @click="openResubmissionModal(item)">Resubmit</button>
                        <button :data-testid="`admin-worker-verification-reject-${item.id}`" :class="dangerChip" @click="openRejectModal(item)">Reject</button>
                    </div>
                </article>
            </div>

            <div class="hidden md:block">
                <AdminTable :columns="[{ key: 'worker', label: 'Worker' }, { key: 'experience', label: 'Experience' }, { key: 'status', label: 'Status' }]" :loading="loading" :has-records="verifications.length > 0">
                    <tr v-for="item in verifications" :key="item.id" :data-testid="`admin-worker-verification-row-${item.id}`">
                        <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                            <p class="font-medium text-gray-900 dark:text-white">{{ item.worker?.name }}</p>
                            <a :href="item.id_proof_url" target="_blank" class="text-sm text-blue-600 dark:text-blue-300">View ID proof</a>
                            <div v-if="item.certificates?.length" class="mt-1 flex flex-wrap gap-2">
                                <a v-for="(certificate, index) in item.certificates" :key="certificate.path" :href="certificate.url" target="_blank" class="text-xs text-gray-600 underline dark:text-gray-300">
                                    Certificate {{ index + 1 }}
                                </a>
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-sm text-gray-700 dark:text-gray-200 lg:px-4 lg:py-3">{{ item.experience_years }} years</td>
                        <td class="px-3 py-2.5 lg:px-4 lg:py-3"><StatusBadge :value="item.status" /></td>
                        <td class="px-3 py-2.5 text-right lg:px-4 lg:py-3">
                            <div class="flex flex-wrap justify-end gap-1.5 lg:gap-2">
                            <button :data-testid="`admin-worker-verification-approve-${item.id}`" :class="successChip" @click="approve(item)">Approve</button>
                            <button :data-testid="`admin-worker-verification-resubmit-${item.id}`" :class="warningChip" @click="openResubmissionModal(item)">Resubmit</button>
                            <button :data-testid="`admin-worker-verification-reject-${item.id}`" :class="dangerChip" @click="openRejectModal(item)">Reject</button>
                            </div>
                        </td>
                    </tr>
                </AdminTable>
            </div>
            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 py-4 sm:items-center" data-testid="admin-worker-verification-reject-modal">
            <form class="w-full max-w-md space-y-4 rounded-lg bg-white p-5 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="reject">
                <h2 class="font-semibold text-gray-900 dark:text-white">Reject worker</h2>
                <p v-if="rejectionWarning" class="rounded-md bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ rejectionWarning }}
                </p>
                <FormTextarea id="worker_rejection_reason" v-model="rejectionReason" label="Reason" required :error="rejectionErrors.rejection_reason || []" data-testid="worker-verification-rejection-reason" />
                <AppButton type="submit" icon="pi-times" data-testid="worker-verification-reject-submit">Reject</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="rejecting = null; clearRejectionErrors()">Cancel</button>
            </form>
        </div>

        <div v-if="resubmitting" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 py-4 sm:items-center" data-testid="admin-worker-verification-resubmit-modal">
            <form class="w-full max-w-md space-y-4 rounded-lg bg-white p-5 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="requestResubmission">
                <h2 class="font-semibold text-gray-900 dark:text-white">Request resubmission</h2>
                <p v-if="resubmissionWarning" class="rounded-md bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ resubmissionWarning }}
                </p>
                <FormTextarea id="worker_resubmission_reason" v-model="resubmissionReason" label="What should the worker fix?" required :error="resubmissionErrors.resubmission_reason || []" data-testid="worker-verification-resubmission-reason" />
                <AppButton type="submit" icon="pi-refresh" data-testid="worker-verification-resubmit-submit">Request resubmission</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="resubmitting = null; clearResubmissionErrors()">Cancel</button>
            </form>
        </div>
    </AdminLayout>
</template>
