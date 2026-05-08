<script setup>
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { adminBookings, cancelAdminBooking } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/admin/PaginationControls.vue';
import AppButton from '../../components/common/AppButton.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(false);
const bookings = ref([]);
const meta = ref({});
const search = ref('');
const status = ref('');
const cancelling = ref(null);
const cancelReason = ref('');
const statusOptions = [
    { id: '', name: 'All statuses' },
    { id: 'requested', name: 'Requested' },
    { id: 'pending', name: 'Pending' },
    { id: 'confirmed', name: 'Confirmed' },
    { id: 'accepted', name: 'Accepted' },
    { id: 'rejected', name: 'Rejected' },
    { id: 'in_progress', name: 'In progress' },
    { id: 'completed', name: 'Completed' },
    { id: 'cancelled', name: 'Cancelled' },
];

async function load(page = 1) {
    loading.value = true;
    try {
        const response = await adminBookings({ search: search.value, status: status.value, page });
        bookings.value = response.data.data.bookings;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load bookings');
    } finally {
        loading.value = false;
    }
}

async function cancel() {
    await cancelAdminBooking(cancelling.value.id, cancelReason.value);
    toast.success('Booking cancelled');
    cancelling.value = null;
    cancelReason.value = '';
    await load();
}

onMounted(load);
</script>

<template>
    <AdminLayout title="Bookings Management">
        <div class="space-y-4">
            <div class="grid gap-3 md:grid-cols-[1fr_220px]">
                <SearchFilter v-model="search" placeholder="Search bookings" @search="load()" />
                <FormSelect id="booking_status" v-model="status" label="Status" :options="statusOptions" @update:model-value="load()" />
            </div>
            <AdminTable :columns="[{ key: 'booking', label: 'Booking' }, { key: 'amount', label: 'Amount' }, { key: 'status', label: 'Status' }]" :loading="loading" :has-records="bookings.length > 0">
                <tr v-for="booking in bookings" :key="booking.id">
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-900 dark:text-white">{{ booking.service?.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ booking.customer?.name }} -> {{ booking.worker?.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ booking.booking_date }} {{ booking.start_time }} - {{ booking.end_time }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">₹{{ booking.total_amount }}</td>
                    <td class="px-4 py-3"><StatusBadge :value="booking.status" /></td>
                    <td class="px-4 py-3 text-right">
                        <button v-if="booking.status !== 'cancelled'" class="text-sm font-medium text-red-600" @click="cancelling = booking">Cancel</button>
                    </td>
                </tr>
            </AdminTable>
            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="cancelling" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <form class="w-full max-w-sm space-y-4 rounded-lg bg-white p-5 dark:bg-gray-900" @submit.prevent="cancel">
                <h2 class="font-semibold text-gray-900 dark:text-white">Cancel booking</h2>
                <FormTextarea id="admin_booking_cancel_reason" v-model="cancelReason" label="Cancellation reason" required />
                <AppButton type="submit" icon="pi-times">Cancel booking</AppButton>
                <button type="button" class="w-full text-sm text-gray-600 dark:text-gray-400" @click="cancelling = null">Close</button>
            </form>
        </div>
    </AdminLayout>
</template>
