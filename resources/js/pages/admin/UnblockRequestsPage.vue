<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { adminUnblockRequests, approveUnblockRequest, rejectUnblockRequest } from '../../api/admin';
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
import { adminUnblockReviewSchema } from '../../validation/adminSchemas';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const requests = ref([]);
const meta = ref({});
const search = ref('');
const status = ref('pending');
const filtersReady = ref(false);
const reviewing = ref(null);
const action = ref('');
const adminNote = ref('');
const reviewSaving = ref(false);
const reviewError = ref('');
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(adminUnblockReviewSchema);

const statusOptions = [
    { id: '', name: 'All statuses' },
    { id: 'pending', name: 'Pending' },
    { id: 'approved', name: 'Approved' },
    { id: 'rejected', name: 'Rejected' },
];
const chipBase = 'inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-semibold transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0.5';
const successChip = `${chipBase} bg-emerald-50 text-emerald-700 shadow-[0_2px_0_#bbf7d0,0_6px_12px_rgba(5,150,105,0.12)] hover:bg-emerald-100 active:shadow-[0_1px_0_#bbf7d0,0_4px_8px_rgba(5,150,105,0.12)] dark:bg-emerald-500/10 dark:text-emerald-300 dark:shadow-[0_2px_0_rgba(52,211,153,0.18)]`;
const dangerChip = `${chipBase} bg-red-50 text-red-700 shadow-[0_2px_0_#fecaca,0_6px_12px_rgba(220,38,38,0.12)] hover:bg-red-100 active:shadow-[0_1px_0_#fecaca,0_4px_8px_rgba(220,38,38,0.12)] dark:bg-red-500/10 dark:text-red-300 dark:shadow-[0_2px_0_rgba(248,113,113,0.18)]`;

async function load(page = 1) {
    loading.value = true;

    try {
        const response = await adminUnblockRequests({
            search: search.value || undefined,
            status: status.value || undefined,
            page,
        });
        requests.value = response.data.data.unblock_requests;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load unblock requests');
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

function openReview(item, nextAction) {
    reviewing.value = item;
    action.value = nextAction;
    adminNote.value = '';
    reviewError.value = '';
    clearValidationErrors();
}

async function submitReview() {
    clearValidationErrors();
    const isValid = await validateWithSchema({
        admin_note: adminNote.value,
    });

    if (! isValid) {
        toast.error('Please fix the admin note field.');

        return;
    }

    reviewSaving.value = true;
    reviewError.value = '';

    try {
        if (action.value === 'approve') {
            await approveUnblockRequest(reviewing.value.id, adminNote.value);
            toast.success('User unblocked and notified');
        } else {
            await rejectUnblockRequest(reviewing.value.id, adminNote.value);
            toast.success('Unblock request rejected and user notified');
        }

        reviewing.value = null;
        action.value = '';
        adminNote.value = '';
        await load(meta.value.current_page || 1);
    } catch (error) {
        reviewError.value = error.response?.data?.errors?.request?.[0] || error.response?.data?.message || 'Unable to review unblock request';
        toast.error(reviewError.value);
    } finally {
        reviewSaving.value = false;
    }
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

watch(adminNote, () => clearValidationErrors('admin_note'));
</script>

<template>
    <AdminLayout title="Unblock Requests">
        <div class="space-y-4" data-testid="admin-unblock-requests-page">
            <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_200px]">
                <SearchFilter v-model="search" placeholder="Search user, email, or appeal note" @search="load()" />
                <FormSelect id="unblock_status" v-model="status" label="Status" :options="statusOptions" data-testid="admin-unblock-status-filter" />
            </div>

            <div class="space-y-3 md:hidden">
                <div v-if="loading" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Loading appeals...</p>
                </div>

                <div v-else-if="requests.length === 0" class="rounded-lg bg-white p-6 text-center text-sm text-gray-500 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                    No unblock requests found. Try changing the search or status filter.
                </div>

                <article v-else v-for="item in requests" :key="item.id" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white">{{ item.user?.name }}</p>
                            <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ item.user?.email }}</p>
                        </div>
                        <StatusBadge :value="item.status" />
                    </div>

                    <div class="mt-3">
                        <StatusBadge :value="item.account_status || item.user?.account_status || 'active'" />
                    </div>

                    <p class="mt-4 text-sm text-gray-700 dark:text-gray-200">{{ item.reason }}</p>
                    <p v-if="item.admin_note" class="mt-2 text-xs text-gray-500 dark:text-gray-400">Admin note: {{ item.admin_note }}</p>

                    <div v-if="item.status === 'pending'" class="mt-4 flex flex-wrap gap-2">
                        <button :class="successChip" :data-testid="`admin-unblock-request-approve-${item.id}`" @click="openReview(item, 'approve')">Approve</button>
                        <button :class="dangerChip" :data-testid="`admin-unblock-request-reject-${item.id}`" @click="openReview(item, 'reject')">Reject</button>
                    </div>
                </article>
            </div>

            <div class="hidden md:block">
                <AdminTable :columns="[{ key: 'user', label: 'User' }, { key: 'reason', label: 'Reason' }, { key: 'status', label: 'Status' }]" :loading="loading" :has-records="requests.length > 0">
                    <tr v-for="item in requests" :key="item.id" :data-testid="`admin-unblock-request-row-${item.id}`">
                        <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                            <p class="font-medium text-gray-900 dark:text-white">{{ item.user?.name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ item.user?.email }}</p>
                            <div class="mt-2">
                                <StatusBadge :value="item.account_status || item.user?.account_status || 'active'" />
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-sm text-gray-700 dark:text-gray-200 lg:px-4 lg:py-3">
                            <p class="max-w-xl">{{ item.reason }}</p>
                            <p v-if="item.admin_note" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Admin note: {{ item.admin_note }}</p>
                        </td>
                        <td class="px-3 py-2.5 lg:px-4 lg:py-3"><StatusBadge :value="item.status" /></td>
                        <td class="px-3 py-2.5 text-right lg:px-4 lg:py-3">
                            <template v-if="item.status === 'pending'">
                                <div class="flex flex-wrap justify-end gap-1.5 lg:gap-2">
                                <button :class="successChip" :data-testid="`admin-unblock-request-approve-${item.id}`" @click="openReview(item, 'approve')">Approve</button>
                                <button :class="dangerChip" :data-testid="`admin-unblock-request-reject-${item.id}`" @click="openReview(item, 'reject')">Reject</button>
                                </div>
                            </template>
                        </td>
                    </tr>
                </AdminTable>
            </div>

            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="reviewing" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 py-4 sm:items-center" data-testid="admin-unblock-review-modal">
            <form class="w-full max-w-md space-y-4 rounded-lg bg-white p-5 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="submitReview">
                <h2 class="font-semibold capitalize text-gray-900 dark:text-white">{{ action }} unblock request</h2>
                <p v-if="reviewError" class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-200">{{ reviewError }}</p>
                <FormTextarea id="unblock_admin_note" v-model="adminNote" label="Admin note optional" :error="validationErrors.admin_note || []" data-testid="admin-unblock-admin-note" />
                <AppButton type="submit" :loading="reviewSaving" :icon="action === 'approve' ? 'pi-check' : 'pi-times'" data-testid="admin-unblock-review-submit">{{ action === 'approve' ? 'Approve' : 'Reject' }}</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="reviewing = null">Cancel</button>
            </form>
        </div>
    </AdminLayout>
</template>
