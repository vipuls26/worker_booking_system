<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { adminDispute, adminDisputes, updateAdminDispute } from '../../api/admin';
import AdminTable from '../../components/admin/AdminTable.vue';
import PaginationControls from '../../components/admin/PaginationControls.vue';
import AppButton from '../../components/common/AppButton.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import FormTextarea from '../../components/forms/FormTextarea.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import AdminLayout from '../../layouts/AdminLayout.vue';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const disputes = ref([]);
const meta = ref({});
const search = ref('');
const status = ref('');
const category = ref('');
const filtersReady = ref(false);
const reviewing = ref(null);
const viewing = ref(null);
const nextStatus = ref('under_review');
const resolutionNote = ref('');
const processingId = ref(null);
const loadingDetails = ref(false);

const statusOptions = [
    { id: '', name: 'All statuses' },
    { id: 'open', name: 'Open' },
    { id: 'under_review', name: 'Under review' },
    { id: 'resolved', name: 'Resolved' },
    { id: 'rejected', name: 'Rejected' },
];

const reviewStatusOptions = [
    { id: 'under_review', name: 'Under review' },
    { id: 'resolved', name: 'Resolved' },
    { id: 'rejected', name: 'Rejected' },
];

const categoryOptions = [
    { id: '', name: 'All categories' },
    { id: 'service_issue', name: 'Service issue' },
    { id: 'payment_issue', name: 'Payment issue' },
    { id: 'worker_no_show', name: 'Worker no show' },
    { id: 'customer_issue', name: 'Customer issue' },
    { id: 'other', name: 'Other' },
];

const chipBase = 'inline-flex items-center justify-center rounded-md px-2.5 py-1.5 text-xs font-semibold transition-all duration-150 hover:-translate-y-0.5 active:translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-50';
const neutralChip = `${chipBase} bg-blue-50 text-blue-700 shadow-[0_2px_0_#bfdbfe,0_6px_12px_rgba(37,99,235,0.12)] hover:bg-blue-100 active:shadow-[0_1px_0_#bfdbfe,0_4px_8px_rgba(37,99,235,0.12)] dark:bg-blue-500/10 dark:text-blue-300 dark:shadow-[0_2px_0_rgba(59,130,246,0.18)]`;
const dangerChip = `${chipBase} bg-red-50 text-red-700 shadow-[0_2px_0_#fecaca,0_6px_12px_rgba(220,38,38,0.12)] hover:bg-red-100 active:shadow-[0_1px_0_#fecaca,0_4px_8px_rgba(220,38,38,0.12)] dark:bg-red-500/10 dark:text-red-300 dark:shadow-[0_2px_0_rgba(248,113,113,0.18)]`;

const activeDisputes = computed(() => disputes.value.filter((dispute) => ['open', 'under_review'].includes(dispute.status)).length);

async function load(page = 1) {
    loading.value = true;

    try {
        const response = await adminDisputes({
            search: search.value || undefined,
            status: status.value || undefined,
            category: category.value || undefined,
            page,
            per_page: 15,
        });

        disputes.value = response.data.data.disputes;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load disputes');
    } finally {
        loading.value = false;
    }
}

useDebouncedWatch(
    () => [search.value, status.value, category.value],
    () => {
        if (! filtersReady.value) {
            return;
        }

        syncFiltersToRoute();
        load();
    },
);

function openReview(dispute, statusValue = 'under_review') {
    reviewing.value = dispute;
    nextStatus.value = statusValue;
    resolutionNote.value = dispute.resolution_note || '';
}

async function submitReview() {
    if (!reviewing.value) {
        return;
    }

    processingId.value = reviewing.value.id;

    try {
        await updateAdminDispute(reviewing.value.id, {
            status: nextStatus.value,
            resolution_note: resolutionNote.value || null,
        });

        toast.success('Dispute updated');
        reviewing.value = null;
        resolutionNote.value = '';
        await load(meta.value.current_page || 1);
    } catch (error) {
        toast.error(error.response?.data?.message || 'Unable to update dispute');
    } finally {
        processingId.value = null;
    }
}

async function openDetails(dispute) {
    viewing.value = dispute;
    loadingDetails.value = true;

    try {
        const response = await adminDispute(dispute.id);
        viewing.value = response.data.data.dispute;
    } catch {
        toast.error('Unable to load dispute details');
    } finally {
        loadingDetails.value = false;
    }
}

function formatDate(value) {
    if (!value) {
        return 'Not set';
    }

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function userLabel(user) {
    return user?.name || user?.email || 'Unknown user';
}

function applyRouteFilters() {
    if (route.query.search !== undefined) {
        search.value = String(route.query.search);
    }

    if (route.query.status !== undefined) {
        status.value = String(route.query.status);
    }

    if (route.query.category !== undefined) {
        category.value = String(route.query.category);
    }
}

function syncFiltersToRoute() {
    router.replace({
        path: route.path,
        query: {
            ...(search.value ? { search: search.value } : {}),
            ...(status.value ? { status: status.value } : {}),
            ...(category.value ? { category: category.value } : {}),
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
    <AdminLayout title="Disputes">
        <div class="space-y-5">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="grid gap-3 lg:grid-cols-[1fr_220px_220px]">
                    <SearchFilter v-model="search" placeholder="Search title, description, or users" @search="load()" />
                    <FormSelect id="dispute_status" v-model="status" label="Status" :options="statusOptions" />
                    <FormSelect id="dispute_category" v-model="category" label="Category" :options="categoryOptions" />
                </div>
            </section>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Showing</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ disputes.length }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Needs attention</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ activeDisputes }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total results</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ meta.total || disputes.length }}</p>
                </div>
            </div>

            <AdminTable
                :columns="[
                    { key: 'dispute', label: 'Dispute' },
                    { key: 'booking', label: 'Booking' },
                    { key: 'parties', label: 'Parties' },
                    { key: 'status', label: 'Status' },
                ]"
                :loading="loading"
                :has-records="disputes.length > 0"
                empty-message="No disputes found."
            >
                <tr v-for="dispute in disputes" :key="dispute.id">
                    <td class="px-4 py-3">
                        <p class="max-w-xs font-medium text-gray-900 dark:text-white">{{ dispute.title }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ dispute.category_label }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                        <p class="font-medium">Booking #{{ dispute.booking_id }}</p>
                        <p class="text-gray-500 dark:text-gray-400">{{ dispute.booking?.service?.name || dispute.service_request?.service?.name || 'Service' }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                        <p>Opened by {{ userLabel(dispute.opened_by) }}</p>
                        <p class="text-gray-500 dark:text-gray-400">Against {{ userLabel(dispute.against_user) }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <StatusBadge :value="dispute.status" />
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ formatDate(dispute.created_at) }}</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" :class="neutralChip" @click="openDetails(dispute)">View</button>
                            <button
                                v-if="!['resolved', 'rejected'].includes(dispute.status)"
                                type="button"
                                :class="neutralChip"
                                :disabled="processingId === dispute.id"
                                @click="openReview(dispute, dispute.status === 'open' ? 'under_review' : dispute.status)"
                            >
                                Update
                            </button>
                            <button
                                v-if="!['resolved', 'rejected'].includes(dispute.status)"
                                type="button"
                                :class="dangerChip"
                                :disabled="processingId === dispute.id"
                                @click="openReview(dispute, 'rejected')"
                            >
                                Reject
                            </button>
                        </div>
                    </td>
                </tr>
            </AdminTable>

            <PaginationControls :meta="meta" @change="load" />
        </div>

        <div v-if="reviewing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <form class="w-full max-w-lg space-y-4 rounded-lg bg-white p-5 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="submitReview">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">Update dispute</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ reviewing.title }}</p>
                </div>

                <FormSelect id="dispute_next_status" v-model="nextStatus" label="Status" :options="reviewStatusOptions" />
                <FormTextarea id="dispute_resolution_note" v-model="resolutionNote" :label="nextStatus === 'under_review' ? 'Internal note optional' : 'Resolution note'" :required="nextStatus !== 'under_review'" />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-md px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10" @click="reviewing = null">
                        Cancel
                    </button>
                    <AppButton type="submit" icon="pi-check" :loading="processingId === reviewing.id" :full-width="false">Save status</AppButton>
                </div>
            </form>
        </div>

        <div v-if="viewing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
            <section class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-5 shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Dispute #{{ viewing.id }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">{{ viewing.title }}</h2>
                    </div>
                    <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10" title="Close" @click="viewing = null">
                        <i class="pi pi-times" aria-hidden="true"></i>
                    </button>
                </div>

                <div v-if="loadingDetails" class="mt-5 rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                    Loading details...
                </div>

                <div v-else class="mt-5 space-y-5">
                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Status</p>
                            <div class="mt-2"><StatusBadge :value="viewing.status" /></div>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Category</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ viewing.category_label }}</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-950">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Created</p>
                            <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ formatDate(viewing.created_at) }}</p>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Description</h3>
                        <p class="mt-2 whitespace-pre-line rounded-lg bg-gray-50 p-3 text-sm leading-6 text-gray-700 dark:bg-gray-950 dark:text-gray-300">{{ viewing.description }}</p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Opened by</p>
                            <p class="mt-2 font-semibold text-gray-900 dark:text-white">{{ userLabel(viewing.opened_by) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ viewing.opened_by?.email }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <p class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Against</p>
                            <p class="mt-2 font-semibold text-gray-900 dark:text-white">{{ userLabel(viewing.against_user) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ viewing.against_user?.email }}</p>
                        </div>
                    </div>

                    <div v-if="viewing.resolution_note">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Resolution note</h3>
                        <p class="mt-2 whitespace-pre-line rounded-lg bg-blue-50 p-3 text-sm leading-6 text-blue-900 dark:bg-blue-500/10 dark:text-blue-200">{{ viewing.resolution_note }}</p>
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Timeline</h3>
                        <div v-if="viewing.timeline?.length" class="mt-3 space-y-3">
                            <article v-for="entry in viewing.timeline" :key="entry.id" class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ entry.from_status || 'new' }} -> {{ entry.to_status }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(entry.created_at) }}</p>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ entry.actor?.name || 'System' }}</p>
                                <p v-if="entry.note" class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ entry.note }}</p>
                            </article>
                        </div>
                        <p v-else class="mt-2 rounded-lg bg-gray-50 p-3 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">No timeline entries yet.</p>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
