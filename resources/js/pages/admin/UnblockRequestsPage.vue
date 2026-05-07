<script setup>
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminUnblockRequests, approveUnblockRequest, rejectUnblockRequest } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/admin/PaginationControls.vue';
import AppButton from '../../components/common/AppButton.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(false);
const requests = ref([]);
const meta = ref({});
const status = ref('pending');
const reviewing = ref(null);
const action = ref('');
const adminNote = ref('');

const statusOptions = [
    { id: '', name: 'All statuses' },
    { id: 'pending', name: 'Pending' },
    { id: 'approved', name: 'Approved' },
    { id: 'rejected', name: 'Rejected' },
];

async function load(page = 1) {
    loading.value = true;

    try {
        const response = await adminUnblockRequests({ status: status.value, page });
        requests.value = response.data.data.unblock_requests;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load unblock requests');
    } finally {
        loading.value = false;
    }
}

function openReview(item, nextAction) {
    reviewing.value = item;
    action.value = nextAction;
    adminNote.value = '';
}

async function submitReview() {
    if (action.value === 'approve') {
        await approveUnblockRequest(reviewing.value.id, adminNote.value);
        toast.success('User unblocked');
    } else {
        await rejectUnblockRequest(reviewing.value.id, adminNote.value);
        toast.success('Unblock request rejected');
    }

    reviewing.value = null;
    action.value = '';
    adminNote.value = '';
    await load(meta.value.current_page || 1);
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Unblock Requests">
        <div class="space-y-4">
            <div class="max-w-xs">
                <FormSelect id="unblock_status" v-model="status" label="Status" :options="statusOptions" @update:model-value="load()" />
            </div>

            <AdminTable :columns="[{ key: 'user', label: 'User' }, { key: 'reason', label: 'Reason' }, { key: 'status', label: 'Status' }]" :loading="loading" :has-records="requests.length > 0">
                <tr v-for="item in requests" :key="item.id">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900 dark:text-white">{{ item.user?.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ item.user?.email }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                        <p class="max-w-xl">{{ item.reason }}</p>
                        <p v-if="item.admin_note" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Admin note: {{ item.admin_note }}</p>
                    </td>
                    <td class="px-4 py-3"><StatusBadge :value="item.status" /></td>
                    <td class="px-4 py-3 text-right">
                        <template v-if="item.status === 'pending'">
                            <button class="text-sm font-medium text-emerald-600" @click="openReview(item, 'approve')">Approve</button>
                            <button class="ml-3 text-sm font-medium text-red-600" @click="openReview(item, 'reject')">Reject</button>
                        </template>
                    </td>
                </tr>
            </AdminTable>

            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="reviewing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <form class="w-full max-w-sm space-y-4 rounded-lg bg-white p-5 dark:bg-gray-900" @submit.prevent="submitReview">
                <h2 class="font-semibold capitalize text-gray-900 dark:text-white">{{ action }} unblock request</h2>
                <FormTextarea id="unblock_admin_note" v-model="adminNote" label="Admin note optional" />
                <AppButton type="submit" :icon="action === 'approve' ? 'pi-check' : 'pi-times'">{{ action === 'approve' ? 'Approve' : 'Reject' }}</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="reviewing = null">Cancel</button>
            </form>
        </div>
    </AdminLayout>
</template>
