<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { toast } from 'vue-sonner';
import { listDisputes } from '../../api/disputes';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import DashboardLayout from '../../layouts/DashboardLayout.vue';

const loading = ref(true);
const disputes = ref([]);
const meta = ref({});

const activeDisputes = computed(() => disputes.value.filter((dispute) => ['open', 'under_review'].includes(dispute.status)).length);

async function load(page = 1) {
    loading.value = true;

    try {
        const response = await listDisputes({ page });
        disputes.value = response.data.data.disputes;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load disputes');
    } finally {
        loading.value = false;
    }
}

function formatDate(value) {
    if (! value) {
        return 'Not available';
    }

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

onMounted(() => load());
</script>

<template>
    <DashboardLayout title="My Disputes">
        <div class="space-y-5">
            <section class="grid gap-3 md:grid-cols-3">
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total disputes</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ meta.total || disputes.length }}</p>
                </div>
                <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ activeDisputes }}</p>
                </div>
                <RouterLink to="/customer/bookings" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 transition hover:bg-gray-50 dark:bg-gray-900 dark:ring-white/10 dark:hover:bg-white/5">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Open new dispute</p>
                    <p class="mt-2 flex items-center gap-2 text-sm font-semibold text-blue-700 dark:text-blue-300">
                        Go to bookings
                        <i class="pi pi-arrow-right text-xs" aria-hidden="true"></i>
                    </p>
                </RouterLink>
            </section>

            <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div v-if="loading" class="p-4">
                    <SkeletonList :count="4" />
                </div>

                <div v-else-if="disputes.length === 0" class="p-8 text-center">
                    <p class="font-semibold text-gray-900 dark:text-white">No disputes yet</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open a dispute from a booking detail page after a booking has moved past request stage.</p>
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-white/10">
                    <RouterLink
                        v-for="dispute in disputes"
                        :key="dispute.id"
                        :to="`/customer/bookings/${dispute.service_request_id || dispute.booking?.service_request_id}`"
                        class="block px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/5"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-semibold text-gray-900 dark:text-white">{{ dispute.title }}</p>
                                    <StatusBadge :value="dispute.status" />
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ dispute.category_label }} · Booking #{{ dispute.booking_id }}
                                </p>
                                <p class="mt-2 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ dispute.description }}</p>
                            </div>
                            <div class="shrink-0 text-sm text-gray-500 dark:text-gray-400 sm:text-right">
                                <p>{{ formatDate(dispute.created_at) }}</p>
                                <p v-if="dispute.resolution_note" class="mt-1 max-w-xs text-gray-700 dark:text-gray-300">{{ dispute.resolution_note }}</p>
                            </div>
                        </div>
                    </RouterLink>
                </div>
            </div>

            <PaginationControls :meta="meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
