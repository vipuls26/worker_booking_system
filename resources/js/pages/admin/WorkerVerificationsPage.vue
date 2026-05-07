<script setup>
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminWorkerVerifications, approveWorkerVerification, rejectWorkerVerification } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/admin/PaginationControls.vue';
import AppButton from '../../components/common/AppButton.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(false);
const verifications = ref([]);
const meta = ref({});
const status = ref('');
const rejecting = ref(null);
const rejectionReason = ref('');
const statusOptions = [
    { id: '', name: 'All statuses' },
    { id: 'pending', name: 'Pending' },
    { id: 'approved', name: 'Approved' },
    { id: 'rejected', name: 'Rejected' },
];

async function load(page = 1) {
    loading.value = true;
    try {
        const response = await adminWorkerVerifications({ status: status.value, page });
        verifications.value = response.data.data.verifications;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load verifications');
    } finally {
        loading.value = false;
    }
}

async function approve(item) {
    await approveWorkerVerification(item.id);
    toast.success('Worker approved');
    await load();
}

async function reject() {
    await rejectWorkerVerification(rejecting.value.id, rejectionReason.value);
    toast.success('Worker rejected');
    rejecting.value = null;
    rejectionReason.value = '';
    await load();
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Worker Verification">
        <div class="space-y-4">
            <div class="max-w-xs"><FormSelect id="verification_status" v-model="status" label="Status" :options="statusOptions" @update:model-value="load()" /></div>
            <AdminTable :columns="[{ key: 'worker', label: 'Worker' }, { key: 'experience', label: 'Experience' }, { key: 'status', label: 'Status' }]" :loading="loading" :has-records="verifications.length > 0">
                <tr v-for="item in verifications" :key="item.id">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900 dark:text-white">{{ item.worker?.name }}</p>
                        <a :href="item.id_proof_url" target="_blank" class="text-sm text-blue-600 dark:text-blue-300">View ID proof</a>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ item.experience_years }} years</td>
                    <td class="px-4 py-3"><StatusBadge :value="item.status" /></td>
                    <td class="px-4 py-3 text-right">
                        <button class="text-sm font-medium text-emerald-600" @click="approve(item)">Approve</button>
                        <button class="ml-3 text-sm font-medium text-red-600" @click="rejecting = item">Reject</button>
                    </td>
                </tr>
            </AdminTable>
            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <form class="w-full max-w-sm space-y-4 rounded-lg bg-white p-5 dark:bg-gray-900" @submit.prevent="reject">
                <h2 class="font-semibold text-gray-900 dark:text-white">Reject worker</h2>
                <textarea v-model="rejectionReason" required rows="4" class="block w-full rounded-md border-gray-300 dark:border-white/10 dark:bg-gray-950 dark:text-white" placeholder="Reason"></textarea>
                <AppButton type="submit" icon="pi-times">Reject</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="rejecting = null">Cancel</button>
            </form>
        </div>
    </AdminLayout>
</template>
