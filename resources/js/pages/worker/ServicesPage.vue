<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import WorkerServiceFormModal from '../../components/worker/WorkerServiceFormModal.vue';
import WorkerServicesTable from '../../components/worker/WorkerServicesTable.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useWorkerServicesStore } from '../../stores/worker/services';

const workerServicesStore = useWorkerServicesStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const modalOpen = ref(false);
const editing = ref(null);
const deleting = ref(null);
let refreshTimer = null;

async function load(page = 1, options = {}) {
    try {
        await workerServicesStore.fetch(page, options);
    } catch {
        if (! options.silent) {
            toast.error('Unable to load worker services');
        }
    }
}

async function silentRefresh() {
    if (modalOpen.value || deleting.value) {
        return;
    }

    await load(workerServicesStore.meta.current_page || 1, { silent: true });
}

useDebouncedWatch(
    () => [
        workerServicesStore.filters.pricing_type,
        workerServicesStore.filters.status,
        workerServicesStore.filters.approval_status,
    ],
    () => load(),
);

function refreshWhenVisible() {
    if (document.visibilityState === 'visible') {
        silentRefresh();
    }
}

function openCreateModal() {
    clearApiErrors();
    editing.value = null;
    modalOpen.value = true;
}

function openEditModal(workerService) {
    clearApiErrors();
    editing.value = workerService;
    modalOpen.value = true;
}

function closeModal() {
    clearApiErrors();
    modalOpen.value = false;
    editing.value = null;
}

async function saveWorkerService(payload) {
    clearApiErrors();

    try {
        const response = editing.value
            ? await workerServicesStore.update(editing.value.id, payload)
            : await workerServicesStore.create(payload);

        toast.success(response.message || 'Worker service saved');
        closeModal();
        await load(workerServicesStore.meta.current_page || 1);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to save worker service');
    }
}

async function confirmDelete() {
    try {
        const response = await workerServicesStore.delete(deleting.value.id);
        toast.success(response.message || 'Worker service deleted');
        deleting.value = null;
        await load(workerServicesStore.meta.current_page || 1);
    } catch {
        toast.error('Unable to delete worker service');
    }
}

onMounted(async () => {
    await Promise.all([
        workerServicesStore.fetchOptions(),
        load(),
    ]);

    refreshTimer = window.setInterval(silentRefresh, 10000);
    window.addEventListener('focus', silentRefresh);
    document.addEventListener('visibilitychange', refreshWhenVisible);
});

onBeforeUnmount(() => {
    if (refreshTimer) {
        window.clearInterval(refreshTimer);
    }

    window.removeEventListener('focus', silentRefresh);
    document.removeEventListener('visibilitychange', refreshWhenVisible);
});
</script>

<template>
    <DashboardLayout title="Worker Services">
        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">My services</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Apply for service categories and manage approved pricing.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                    @click="openCreateModal"
                >
                    <i class="pi pi-plus" aria-hidden="true"></i>
                    Apply for service
                </button>
            </div>

            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="grid gap-3 lg:grid-cols-[1fr_180px_180px]">
                    <SearchFilter v-model="workerServicesStore.filters.search" placeholder="Search services" @search="load()" />
                    <select
                        v-model="workerServicesStore.filters.pricing_type"
                        class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm [color-scheme:light] focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark] dark:focus:border-white dark:focus:ring-white"
                    >
                        <option value="" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">All pricing</option>
                        <option value="fixed" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Fixed price</option>
                        <option value="hourly" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Hourly price</option>
                    </select>
                    <select
                        v-model="workerServicesStore.filters.status"
                        class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm [color-scheme:light] focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark] dark:focus:border-white dark:focus:ring-white"
                    >
                        <option value="all" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">All statuses</option>
                        <option value="active" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Active</option>
                        <option value="inactive" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Inactive</option>
                    </select>
                </div>
                <div class="mt-3 max-w-xs">
                    <select
                        v-model="workerServicesStore.filters.approval_status"
                        class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm [color-scheme:light] focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark] dark:focus:border-white dark:focus:ring-white"
                    >
                        <option value="" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">All approvals</option>
                        <option value="pending" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Pending approval</option>
                        <option value="approved" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Approved</option>
                        <option value="rejected" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Rejected</option>
                    </select>
                </div>
            </section>

            <WorkerServicesTable
                :worker-services="workerServicesStore.workerServices"
                :loading="workerServicesStore.loading"
                @edit="openEditModal"
                @delete="deleting = $event"
            />

            <PaginationControls :meta="workerServicesStore.meta" @change="load" />
        </div>

        <WorkerServiceFormModal
            :open="modalOpen"
            :worker-service="editing"
            :service-options="workerServicesStore.serviceOptions"
            :errors="errors"
            :loading="workerServicesStore.saving"
            @close="closeModal"
            @submit="saveWorkerService"
        />

        <ConfirmDialog
            :open="Boolean(deleting)"
            title="Delete worker service"
            :message="`Remove ${deleting?.service?.name || 'this service'} from your offered services?`"
            @cancel="deleting = null"
            @confirm="confirmDelete"
        />
    </DashboardLayout>
</template>
