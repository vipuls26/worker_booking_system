<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminWorkerVerifications, approveWorkerVerification, rejectWorkerVerification, requestWorkerVerificationResubmission } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/admin/PaginationControls.vue';
import AppButton from '../../components/common/AppButton.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(false);
const verifications = ref([]);
const meta = ref({});
const status = ref('');
const rejecting = ref(null);
const resubmitting = ref(null);
const rejectionReason = ref('');
const resubmissionReason = ref('');
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
        const response = await adminWorkerVerifications({ status: status.value, page });
        verifications.value = response.data.data.verifications;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load verifications');
    } finally {
        loading.value = false;
    }
}

useDebouncedWatch(
    () => status.value,
    () => load(),
);

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

async function requestResubmission() {
    await requestWorkerVerificationResubmission(resubmitting.value.id, resubmissionReason.value);
    toast.success('Resubmission requested');
    resubmitting.value = null;
    resubmissionReason.value = '';
    await load();
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Worker Verification">
        <div class="space-y-4">
            <div class="max-w-xs"><FormSelect id="verification_status" v-model="status" label="Status" :options="statusOptions" /></div>
            <AdminTable :columns="[{ key: 'worker', label: 'Worker' }, { key: 'experience', label: 'Experience' }, { key: 'status', label: 'Status' }]" :loading="loading" :has-records="verifications.length > 0">
                <tr v-for="item in verifications" :key="item.id">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900 dark:text-white">{{ item.worker?.name }}</p>
                        <a :href="item.id_proof_url" target="_blank" class="text-sm text-blue-600 dark:text-blue-300">View ID proof</a>
                        <div v-if="item.certificates?.length" class="mt-1 flex flex-wrap gap-2">
                            <a v-for="(certificate, index) in item.certificates" :key="certificate.path" :href="certificate.url" target="_blank" class="text-xs text-gray-600 underline dark:text-gray-300">
                                Certificate {{ index + 1 }}
                            </a>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">{{ item.experience_years }} years</td>
                    <td class="px-4 py-3"><StatusBadge :value="item.status" /></td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                        <button :class="successChip" @click="approve(item)">Approve</button>
                        <button :class="warningChip" @click="resubmitting = item">Resubmit</button>
                        <button :class="dangerChip" @click="rejecting = item">Reject</button>
                        </div>
                    </td>
                </tr>
            </AdminTable>
            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <form class="w-full max-w-sm space-y-4 rounded-lg bg-white p-5 dark:bg-gray-900" @submit.prevent="reject">
                <h2 class="font-semibold text-gray-900 dark:text-white">Reject worker</h2>
                <p v-if="rejectionWarning" class="rounded-md bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ rejectionWarning }}
                </p>
                <FormTextarea id="worker_rejection_reason" v-model="rejectionReason" label="Reason" required />
                <AppButton type="submit" icon="pi-times">Reject</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="rejecting = null">Cancel</button>
            </form>
        </div>

        <div v-if="resubmitting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <form class="w-full max-w-sm space-y-4 rounded-lg bg-white p-5 dark:bg-gray-900" @submit.prevent="requestResubmission">
                <h2 class="font-semibold text-gray-900 dark:text-white">Request resubmission</h2>
                <p v-if="resubmissionWarning" class="rounded-md bg-amber-50 p-3 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                    {{ resubmissionWarning }}
                </p>
                <FormTextarea id="worker_resubmission_reason" v-model="resubmissionReason" label="What should the worker fix?" required />
                <AppButton type="submit" icon="pi-refresh">Request resubmission</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="resubmitting = null">Cancel</button>
            </form>
        </div>
    </AdminLayout>
</template>
