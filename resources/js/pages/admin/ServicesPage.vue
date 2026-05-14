<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import ServiceFormModal from '../../components/admin/ServiceFormModal.vue';
import ServicesTable from '../../components/admin/ServicesTable.vue';
import AppPanel from '../../components/common/AppPanel.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminServicesStore } from '../../stores/admin/services';

const route = useRoute();
const router = useRouter();
const servicesStore = useAdminServicesStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();

const saving = ref(false);
const modalOpen = ref(false);
const editing = ref(null);
const deleting = ref(null);
const forceDeleting = ref(false);
const filtersReady = ref(false);

async function load(page = 1) {
    try {
        await servicesStore.fetch(page);
    } catch {
        toast.error('Unable to load service categories');
    }
}

useDebouncedWatch(
    () => [servicesStore.filters.search, servicesStore.filters.status],
    () => {
        if (! filtersReady.value) {
            return;
        }

        syncFiltersToRoute();
        load();
    },
);

function openCreateModal() {
    clearApiErrors();
    editing.value = null;
    modalOpen.value = true;
}

function openEditModal(service) {
    clearApiErrors();
    editing.value = service;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    editing.value = null;
    clearApiErrors();
}

function openDeleteDialog(service) {
    deleting.value = service;
    forceDeleting.value = false;
}

async function saveService(payload) {
    saving.value = true;
    clearApiErrors();

    try {
        const response = editing.value
            ? await servicesStore.update(editing.value.id, payload)
            : await servicesStore.create(payload);

        toast.success(response.message || 'Service saved');
        closeModal();
        await load(servicesStore.meta.current_page || 1);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to save service');
    } finally {
        saving.value = false;
    }
}

async function toggleStatus(service) {
    try {
        const response = await servicesStore.toggleStatus(service.id);
        toast.success(response.message || 'Service status updated');
        await load(servicesStore.meta.current_page || 1);
    } catch {
        toast.error('Unable to update service status');
    }
}

async function confirmDelete() {
    try {
        const response = await servicesStore.delete(deleting.value.id, forceDeleting.value);
        toast.success(response.message || 'Service deleted');
        deleting.value = null;
        forceDeleting.value = false;
        await load(servicesStore.meta.current_page || 1);
    } catch (error) {
        const serviceErrors = error.response?.data?.errors?.service || [];

        if (serviceErrors.length > 0) {
            forceDeleting.value = true;
            toast.error(serviceErrors[0]);

            return;
        }

        toast.error(error.response?.data?.message || 'Unable to delete service');
    }
}

function cancelDelete() {
    deleting.value = null;
    forceDeleting.value = false;
}

function applyRouteFilters() {
    if (route.query.search !== undefined) {
        servicesStore.filters.search = String(route.query.search);
    }

    if (route.query.status !== undefined) {
        servicesStore.filters.status = String(route.query.status);
    }
}

function syncFiltersToRoute() {
    router.replace({
        path: route.path,
        query: {
            ...(servicesStore.filters.search ? { search: servicesStore.filters.search } : {}),
            ...(servicesStore.filters.status && servicesStore.filters.status !== 'all' ? { status: servicesStore.filters.status } : {}),
        },
    });
}

onMounted(() => {
    applyRouteFilters();
    filtersReady.value = true;
    load();
});
</script>

<template>
    <AdminLayout title="Services Management">
        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Service categories</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage the master services available for customer bookings.</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200"
                    @click="openCreateModal"
                >
                    <i class="pi pi-plus" aria-hidden="true"></i>
                    Add service
                </button>
            </div>

            <AppPanel>
                <div class="grid gap-3 lg:grid-cols-[1fr_220px]">
                    <SearchFilter v-model="servicesStore.filters.search" placeholder="Search by name, slug, or description" @search="load()" />
                    <select
                        v-model="servicesStore.filters.status"
                        class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm [color-scheme:light] focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark] dark:focus:border-white dark:focus:ring-white"
                    >
                        <option value="all" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">All statuses</option>
                        <option value="active" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Active</option>
                        <option value="inactive" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Inactive</option>
                    </select>
                </div>
            </AppPanel>

            <ServicesTable
                :services="servicesStore.services"
                :loading="servicesStore.loading"
                @edit="openEditModal"
                @delete="openDeleteDialog"
                @toggle="toggleStatus"
            />

            <PaginationControls :meta="servicesStore.meta" @change="load" />
        </div>

        <ServiceFormModal
            :open="modalOpen"
            :service="editing"
            :errors="errors"
            :loading="saving"
            @close="closeModal"
            @submit="saveService"
        />

        <ConfirmDialog
            :open="Boolean(deleting)"
            :title="forceDeleting ? 'Force delete service category' : 'Delete service category'"
            :message="forceDeleting
                ? `This service has active bookings. Force soft delete ${deleting?.name || 'this service category'}? Existing bookings will continue, new bookings will be blocked, and each active booking will receive a timeline note.`
                : `This will soft delete ${deleting?.name || 'this service category'}.`"
            @cancel="cancelDelete"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
