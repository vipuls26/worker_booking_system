<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { workerEarnings } from '../../api/worker/earnings';
import AnalyticsTable from '../../components/common/AnalyticsTable.vue';
import DashboardCard from '../../components/common/DashboardCard.vue';
import SkeletonCard from '../../components/common/SkeletonCard.vue';
import StatusBadge from '../../components/common/StatusBadge.vue';
import DashboardLayout from '../../layouts/DashboardLayout.vue';

const loading = ref(true);
const summary = ref({
    total_earned: 0,
    platform_commission: 0,
    paid_out: 0,
    pending_payout: 0,
    paid_bookings: 0,
    recent_payments: [],
    recent_payouts: [],
});

function money(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function formatDate(value) {
    if (! value) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

const payoutProgress = computed(() => {
    const earned = Number(summary.value.total_earned || 0);
    const paidOut = Number(summary.value.paid_out || 0);

    if (earned <= 0) {
        return 0;
    }

    return Math.min(Math.round((paidOut / earned) * 100), 100);
});

const cards = computed(() => [
    {
        eyebrow: 'Paid by customers',
        title: money(summary.value.total_earned),
        description: 'Your earnings after platform commission.',
        icon: 'pi-indian-rupee',
    },
    {
        eyebrow: 'Paid out',
        title: money(summary.value.paid_out),
        description: 'Amount already transferred in weekly payouts.',
        icon: 'pi-check-circle',
    },
    {
        eyebrow: 'Available for payout',
        title: money(summary.value.pending_payout),
        description: 'Will be included in the next weekly payout run.',
        icon: 'pi-wallet',
    },
    {
        eyebrow: 'Platform commission',
        title: money(summary.value.platform_commission),
        description: 'The platform fee retained from paid bookings.',
        icon: 'pi-percentage',
    },
]);

const paymentColumns = [
    { key: 'booking', label: 'Booking', format: (row) => row.booking?.service?.name || `Booking #${row.booking_id}` },
    { key: 'amount', label: 'Customer paid', format: (row) => money(row.amount) },
    { key: 'worker_earning', label: 'Your earning', format: (row) => money(row.worker_earning) },
    { key: 'paid_at', label: 'Paid at', format: (row) => formatDate(row.paid_at) },
];

const payoutColumns = [
    { key: 'amount', label: 'Amount', format: (row) => money(row.amount) },
    { key: 'period', label: 'Period', format: (row) => `${row.period_start || '-'} to ${row.period_end || '-'}` },
    { key: 'reference', label: 'Reference', format: (row) => row.reference || '-' },
    { key: 'paid_at', label: 'Paid at', format: (row) => formatDate(row.paid_at) },
];

onMounted(async () => {
    try {
        const response = await workerEarnings();
        summary.value = response.data.data;
    } catch {
        toast.error('Unable to load account summary');
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <DashboardLayout title="Account">
        <div v-if="loading" class="space-y-5">
            <SkeletonCard :lines="4" :avatar="false" />
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <SkeletonCard v-for="item in 4" :key="item" :lines="2" :avatar="false" />
            </div>
        </div>

        <div v-else class="space-y-6">
            <section class="overflow-hidden rounded-lg bg-gray-900 text-white shadow-sm dark:bg-white dark:text-gray-950">
                <div class="grid gap-5 p-5 lg:grid-cols-[1fr_340px] lg:p-6">
                    <div>
                        <p class="text-sm font-medium text-gray-300 dark:text-gray-600">Worker account</p>
                        <h2 class="mt-2 text-2xl font-semibold">Automatic weekly payouts</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-300 dark:text-gray-600">
                            Your paid customer earnings are collected here. The platform keeps commission separately and sends available earnings through the weekend payout command.
                        </p>
                    </div>
                    <div class="rounded-lg bg-white/10 p-4 ring-1 ring-white/15 dark:bg-gray-950/5 dark:ring-gray-950/10">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-sm font-medium text-gray-300 dark:text-gray-600">Available for payout</p>
                            <StatusBadge :value="Number(summary.pending_payout) > 0 ? 'pending' : 'paid'" />
                        </div>
                        <p class="mt-3 text-3xl font-semibold">{{ money(summary.pending_payout) }}</p>
                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/15 dark:bg-gray-950/10">
                            <div class="h-full rounded-full bg-white dark:bg-gray-950" :style="{ width: `${payoutProgress}%` }"></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-300 dark:text-gray-600">{{ payoutProgress }}% of earned balance paid out</p>
                    </div>
                </div>
            </section>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <DashboardCard
                    v-for="card in cards"
                    :key="card.eyebrow"
                    :eyebrow="card.eyebrow"
                    :title="card.title"
                    :description="card.description"
                    :icon="card.icon"
                />
            </div>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Weekly payout</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Automatic weekend transfer</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Payouts run every Sunday at 11 PM and create a permanent payout history entry.
                        </p>
                    </div>
                    <StatusBadge :value="Number(summary.pending_payout) > 0 ? 'pending' : 'paid'" />
                </div>
            </section>

            <div class="grid gap-5 xl:grid-cols-2">
                <AnalyticsTable title="Recent payments" :rows="summary.recent_payments" :columns="paymentColumns" />
                <AnalyticsTable title="Payout history" :rows="summary.recent_payouts" :columns="payoutColumns" />
            </div>
        </div>
    </DashboardLayout>
</template>
