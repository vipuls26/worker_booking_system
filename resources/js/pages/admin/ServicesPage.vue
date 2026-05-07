<script setup>
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import ServiceFormModal from '../../components/admin/ServiceFormModal.vue';
import ServicesTable from '../../components/admin/ServicesTable.vue';
import AppPanel from '../../components/common/AppPanel.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { useAdminServicesStore } from '../../stores/admin/services';

const servicesStore = useAdminServicesStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();

const saving = ref(false);
const modalOpen = ref(false);
const editing = ref(null);
const deleting = ref(null);

async function load(page = 1) {
    try {
        await servicesStore.fetch(page);
    } catch {
        toast.error('Unable to load service categories');
    }
}

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
        const response = await servicesStore.delete(deleting.value.id);
        toast.success(response.message || 'Service deleted');
        deleting.value = null;
        await load(servicesStore.meta.current_page || 1);
    } catch {
        toast.error('Unable to delete service');
    }
}

onMounted(() => load());
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
                        class="block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                        @change="load()"
                    >
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </AppPanel>

            <ServicesTable
                :services="servicesStore.services"
                :loading="servicesStore.loading"
                @edit="openEditModal"
                @delete="deleting = $event"
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
            title="Delete service category"
            :message="`This will soft delete ${deleting?.name || 'this service category'}.`"
            @cancel="deleting = null"
            @confirm="confirmDelete"
        />
    </AdminLayout>
</template>
