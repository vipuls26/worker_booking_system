<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { adminWorkerServiceRequests, approveWorkerServiceRequest, rejectWorkerServiceRequest } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import AppButton from '../../components/common/AppButton.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import { useYupValidation } from '../../composables/useYupValidation';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { adminWorkerServiceRejectionSchema } from '../../validation/adminSchemas';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const workerServices = ref([]);
const meta = ref({});
const search = ref('');
const status = ref('pending');
const filtersReady = ref(false);
const rejecting = ref(null);
const rejectionReason = ref('');
const processingId = ref(null);
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(adminWorkerServiceRejectionSchema);

const statusOptions = [
    { id: '', name: 'All approvals' },
    { id: 'pending', name: 'Pending' },
    { id: 'approved', name: 'Approved' },
    { id: 'rejected', name: 'Rejected' },
];

async function load(page = 1) {
    loading.value = true;

    try {
        const response = await adminWorkerServiceRequests({
            search: search.value || undefined,
            status: status.value || undefined,
            page,
        });

        workerServices.value = response.data.data.worker_services;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load service requests');
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

async function approve(workerService) {
    processingId.value = workerService.id;

    try {
        const response = await approveWorkerServiceRequest(workerService.id);
        syncWorkerService(response.data.data.worker_service);
        toast.success('Worker service approved');
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to approve service');
    } finally {
        processingId.value = null;
    }
}

async function reject() {
    clearValidationErrors();
    const isValid = await validateWithSchema({
        rejection_reason: rejectionReason.value,
    });

    if (! isValid) {
        toast.error('Please add a rejection reason.');

        return;
    }

    processingId.value = rejecting.value.id;

    try {
        const response = await rejectWorkerServiceRequest(rejecting.value.id, rejectionReason.value);
        syncWorkerService(response.data.data.worker_service);
        toast.success('Worker service rejected');
        rejecting.value = null;
        rejectionReason.value = '';
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to reject service');
    } finally {
        processingId.value = null;
    }
}

function openRejectModal(workerService) {
    rejecting.value = workerService;
    rejectionReason.value = '';
    clearValidationErrors();
}

function syncWorkerService(updatedWorkerService) {
    if (status.value && updatedWorkerService.approval_status !== status.value) {
        workerServices.value = workerServices.value.filter((workerService) => workerService.id !== updatedWorkerService.id);
        return;
    }

    workerServices.value = workerServices.value.map((workerService) => (
        workerService.id === updatedWorkerService.id ? updatedWorkerService : workerService
    ));
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

watch(rejectionReason, () => clearValidationErrors('rejection_reason'));
</script>

<template>
    <AdminLayout title="Worker Service Requests">
        <div class="space-y-4" data-testid="admin-worker-service-requests-page">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_200px]">
                    <SearchFilter v-model="search" placeholder="Search worker, service, or description" @search="load()" />
                    <FormSelect id="worker_service_request_status" v-model="status" label="Approval status" :options="statusOptions" />
                </div>
            </section>

            <AdminTable
                class="hidden md:block"
                :columns="[
                    { key: 'worker', label: 'Worker' },
                    { key: 'service', label: 'Service' },
                    { key: 'pricing', label: 'Pricing' },
                    { key: 'status', label: 'Status' },
                ]"
                :loading="loading"
                :has-records="workerServices.length > 0"
                empty-message="No worker service requests found."
            >
                <tr v-for="workerService in workerServices" :key="workerService.id" :data-testid="`admin-worker-service-row-${workerService.id}`">
                    <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                        <p class="font-medium text-gray-900 dark:text-white">{{ workerService.worker?.name || 'Worker' }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ workerService.worker?.email }}</p>
                    </td>
                    <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                <i :class="['pi', workerService.service?.icon || 'pi-briefcase']" aria-hidden="true"></i>
                            </span>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ workerService.service?.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ workerService.description || workerService.service?.slug }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-2.5 text-sm text-gray-700 dark:text-gray-200 lg:px-4 lg:py-3">
                        <p class="font-medium">₹{{ workerService.price }}</p>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ workerService.pricing_type === 'hourly' ? `Per hour, min ${workerService.minimum_hours}h` : 'Fixed price' }}
                        </p>
                    </td>
                    <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                        <StatusBadge :value="workerService.approval_status" />
                        <p v-if="workerService.rejection_reason" class="mt-2 max-w-xs text-xs text-red-600 dark:text-red-300">{{ workerService.rejection_reason }}</p>
                    </td>
                    <td class="px-3 py-2.5 text-right lg:px-4 lg:py-3">
                        <div class="flex justify-end gap-1.5 lg:gap-2">
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-md border border-emerald-200 text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                                title="Approve"
                                :data-testid="`admin-worker-service-approve-${workerService.id}`"
                                :disabled="processingId === workerService.id || workerService.approval_status === 'approved'"
                                @click="approve(workerService)"
                            >
                                <i :class="['pi', processingId === workerService.id ? 'pi-spin pi-spinner' : 'pi-check']" aria-hidden="true"></i>
                            </button>
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded-md border border-red-200 text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                title="Reject"
                                :data-testid="`admin-worker-service-reject-${workerService.id}`"
                                :disabled="processingId === workerService.id || workerService.approval_status === 'rejected'"
                                @click="openRejectModal(workerService)"
                            >
                                <i class="pi pi-times" aria-hidden="true"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </AdminTable>

            <div class="space-y-3 md:hidden">
                <div v-if="loading" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Loading service requests...</p>
                </div>

                <div v-else-if="workerServices.length === 0" class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                    No worker service requests found. Try changing the approval filter or search term.
                </div>

                <article v-else v-for="workerService in workerServices" :key="workerService.id" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white">{{ workerService.worker?.name || 'Worker' }}</p>
                            <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ workerService.worker?.email }}</p>
                        </div>
                        <StatusBadge :value="workerService.approval_status" />
                    </div>

                    <div class="mt-4 flex items-start gap-3">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            <i :class="['pi', workerService.service?.icon || 'pi-briefcase']" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white">{{ workerService.service?.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ workerService.description || workerService.service?.slug }}</p>
                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">₹{{ workerService.price }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ workerService.pricing_type === 'hourly' ? `Per hour, min ${workerService.minimum_hours}h` : 'Fixed price' }}
                            </p>
                            <p v-if="workerService.rejection_reason" class="mt-2 text-xs text-red-600 dark:text-red-300">{{ workerService.rejection_reason }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-500/30 dark:text-emerald-300 dark:hover:bg-emerald-500/10"
                            :data-testid="`admin-worker-service-approve-${workerService.id}`"
                            :disabled="processingId === workerService.id || workerService.approval_status === 'approved'"
                            @click="approve(workerService)"
                        >
                            <i :class="['pi', processingId === workerService.id ? 'pi-spin pi-spinner' : 'pi-check']" aria-hidden="true"></i>
                            Approve
                        </button>
                        <button
                            type="button"
                            class="inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                            :data-testid="`admin-worker-service-reject-${workerService.id}`"
                            :disabled="processingId === workerService.id || workerService.approval_status === 'rejected'"
                            @click="openRejectModal(workerService)"
                        >
                            <i class="pi pi-times" aria-hidden="true"></i>
                            Reject
                        </button>
                    </div>
                </article>
            </div>

            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 py-4 sm:items-center" data-testid="admin-worker-service-reject-modal">
            <form class="w-full max-w-md space-y-4 rounded-lg bg-white p-5 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="reject">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Reject service request</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ rejecting.worker?.name }} · {{ rejecting.service?.name }}</p>
                </div>
                <FormTextarea id="worker_service_rejection_reason" v-model="rejectionReason" label="Reason" required :error="validationErrors.rejection_reason || []" data-testid="worker-service-rejection-reason" />
                <AppButton type="submit" icon="pi-times" :loading="processingId === rejecting.id" data-testid="worker-service-reject-submit">Reject request</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="rejecting = null; rejectionReason = ''; clearValidationErrors()">Cancel</button>
            </form>
        </div>
    </AdminLayout>
</template>
