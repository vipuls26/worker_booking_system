<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { adminAuditLogs, adminBookingAuditLogs, adminUserAuditLogs } from '../../api/admin';
import PaginationControls from '../../components/common/PaginationControls.vue';
import AppPanel from '../../components/common/AppPanel.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import FormInput from '../../components/forms/FormInput.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import AdminLayout from '../../layouts/AdminLayout.vue';

const loading = ref(false);
const route = useRoute();
const router = useRouter();
const auditLogs = ref([]);
const meta = ref({});
const filtersReady = ref(false);
const filters = ref({
    search: '',
    action: '',
    actor_role: '',
    date_from: '',
    date_to: '',
});

const roleOptions = [
    { id: '', name: 'All roles' },
    { id: 'admin', name: 'Admin' },
    { id: 'customer', name: 'Customer' },
    { id: 'worker', name: 'Worker' },
];

const actionOptions = [
    { id: '', name: 'All actions' },
    { id: 'auth.login', name: 'Login' },
    { id: 'auth.registered', name: 'Registration' },
    { id: 'service_request.created', name: 'Request created' },
    { id: 'service_request.worker_selected', name: 'Worker selected' },
    { id: 'booking.status_changed', name: 'Booking status changed' },
    { id: 'payment.paid', name: 'Payment paid' },
    { id: 'worker_payout.weekly_paid', name: 'Weekly payout' },
    { id: 'admin.commission_rate_updated', name: 'Commission updated' },
    { id: 'admin.worker_service_approved', name: 'Service approved' },
    { id: 'admin.worker_service_rejected', name: 'Service rejected' },
];

const visibleActorRoles = computed(() => new Set(auditLogs.value.map((log) => log.actor_role || 'system')).size);

const latestActivity = computed(() => auditLogs.value[0]?.created_at || null);

const activeFiltersCount = computed(() => Object.values(filters.value).filter(Boolean).length);
const timelineScope = computed(() => {
    if (route.query.user) {
        return `User #${route.query.user}`;
    }

    if (route.query.booking) {
        return `Booking #${route.query.booking}`;
    }

    return 'Global';
});

const summaryCards = computed(() => [
    {
        label: 'Showing',
        value: auditLogs.value.length,
        icon: 'pi-list',
        class: 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20',
    },
    {
        label: 'Roles',
        value: visibleActorRoles.value,
        icon: 'pi-users',
        class: 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20',
    },
    {
        label: 'Filters',
        value: activeFiltersCount.value,
        icon: 'pi-filter',
        class: 'bg-amber-50 text-amber-700 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20',
    },
]);

function actionIcon(action) {
    if (action?.startsWith('auth.')) {
        return 'pi-lock';
    }

    if (action?.startsWith('booking.') || action?.startsWith('service_request.')) {
        return 'pi-calendar';
    }

    if (action?.startsWith('payment.') || action?.startsWith('worker_payout.')) {
        return 'pi-wallet';
    }

    if (action?.startsWith('admin.')) {
        return 'pi-shield';
    }

    return 'pi-history';
}

function actionTone(action) {
    if (action?.includes('rejected') || action?.includes('cancelled') || action?.includes('blocked')) {
        return 'bg-red-50 text-red-700 ring-red-100 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/20';
    }

    if (action?.includes('approved') || action?.includes('verified') || action?.includes('paid')) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20';
    }

    if (action?.includes('created') || action?.includes('selected')) {
        return 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20';
    }

    return 'bg-gray-100 text-gray-700 ring-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10';
}

async function load(page = 1) {
    loading.value = true;

    try {
        const params = {
            ...filters.value,
            page,
            per_page: 15,
        };
        const response = route.query.user
            ? await adminUserAuditLogs(route.query.user, params)
            : route.query.booking
                ? await adminBookingAuditLogs(route.query.booking, params)
                : await adminAuditLogs(params);

        auditLogs.value = response.data.data.audit_logs;
        meta.value = response.data.data.meta;
    } catch {
        toast.error('Unable to load audit logs');
    } finally {
        loading.value = false;
    }
}

function formatDate(value) {
    if (! value) {
        return 'Unknown time';
    }

    return new Intl.DateTimeFormat('en-IN', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function resetFilters() {
    filters.value = {
        search: '',
        action: '',
        actor_role: '',
        date_from: '',
        date_to: '',
    };

    syncFiltersToRoute();
    load();
}

function prettyMetadata(metadata) {
    const entries = Object.entries(metadata || {}).slice(0, 4);

    if (entries.length === 0) {
        return 'No extra metadata';
    }

    return entries
        .map(([key, value]) => `${key}: ${value}`)
        .join(' · ');
}

useDebouncedWatch(
    () => [filters.value.search, filters.value.action, filters.value.actor_role, filters.value.date_from, filters.value.date_to],
    () => {
        if (! filtersReady.value) {
            return;
        }

        syncFiltersToRoute();
        load();
    },
);

function applyRouteFilters() {
    const allowedFilters = ['search', 'action', 'actor_role', 'date_from', 'date_to'];

    allowedFilters.forEach((key) => {
        if (route.query[key] !== undefined) {
            filters.value[key] = String(route.query[key]);
        }
    });
}

function syncFiltersToRoute() {
    router.replace({
        path: route.path,
        query: {
            ...(route.query.user ? { user: route.query.user } : {}),
            ...(route.query.booking ? { booking: route.query.booking } : {}),
            ...(filters.value.search ? { search: filters.value.search } : {}),
            ...(filters.value.action ? { action: filters.value.action } : {}),
            ...(filters.value.actor_role ? { actor_role: filters.value.actor_role } : {}),
            ...(filters.value.date_from ? { date_from: filters.value.date_from } : {}),
            ...(filters.value.date_to ? { date_to: filters.value.date_to } : {}),
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
    <AdminLayout title="Audit Logs">
        <div class="space-y-5">
            <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex max-w-2xl gap-4">
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">
                            <i class="pi pi-history" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-blue-700 dark:text-blue-300">Platform activity</p>
                            <h2 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">Audit trail</h2>
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">
                                Review important account, booking, payment, and admin actions from one timeline. Current scope: {{ timelineScope }}.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center sm:min-w-[360px]">
                        <div
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-lg p-3 text-left ring-1"
                            :class="card.class"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold">{{ card.label }}</p>
                                <i :class="['pi', card.icon, 'text-xs']" aria-hidden="true"></i>
                            </div>
                            <p class="mt-2 text-xl font-semibold">{{ card.value }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <AppPanel class="bg-blue-50/60 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
                <div class="grid gap-4 xl:grid-cols-[220px_minmax(320px,1fr)] xl:items-end">
                    <div class="flex flex-col gap-2">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Find activity</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Latest: {{ latestActivity ? formatDate(latestActivity) : 'No activity yet' }}
                            </p>
                        </div>
                        <div v-if="activeFiltersCount" class="inline-flex w-fit items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                            <i class="pi pi-filter" aria-hidden="true"></i>
                            {{ activeFiltersCount }} active
                        </div>
                    </div>

                    <div class="grid gap-3 2xl:grid-cols-[minmax(280px,1fr)_190px_160px_160px_160px_auto] 2xl:items-end">
                        <div class="xl:min-w-0">
                            <SearchFilter v-model="filters.search" placeholder="Search actor, IP, action, or subject" @search="load()" />
                        </div>

                        <FormSelect id="audit_action" v-model="filters.action" label="Action" :options="actionOptions" />
                        <FormSelect id="audit_actor_role" v-model="filters.actor_role" label="Actor role" :options="roleOptions" />
                        <FormInput id="audit_date_from" v-model="filters.date_from" label="From" type="date" />
                        <FormInput id="audit_date_to" v-model="filters.date_to" label="To" type="date" />

                        <button
                            v-if="activeFiltersCount"
                            type="button"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                            @click="resetFilters"
                        >
                            <i class="pi pi-filter-slash" aria-hidden="true"></i>
                            <span>Clear</span>
                        </button>
                        <div v-else class="hidden h-10 2xl:block"></div>
                    </div>
                </div>
            </AppPanel>

            <div v-if="loading" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <SkeletonList :count="6" />
            </div>

            <div v-else-if="auditLogs.length === 0" class="rounded-lg bg-white p-10 text-center shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="mx-auto flex size-12 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300">
                    <i class="pi pi-history" aria-hidden="true"></i>
                </div>
                <h2 class="mt-4 font-semibold text-gray-900 dark:text-white">No audit activity found</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try changing the filters or perform an action in the platform.</p>
            </div>

            <div v-else class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
                <article
                    v-for="log in auditLogs"
                    :key="log.id"
                    class="border-b border-blue-50 p-4 transition hover:bg-blue-50/40 last:border-b-0 dark:border-white/10 dark:hover:bg-white/5 sm:p-5"
                >
                    <div class="flex gap-4">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-lg ring-1" :class="actionTone(log.action)">
                            <i :class="['pi', actionIcon(log.action)]" aria-hidden="true"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ log.action_label }}</h2>
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                            {{ log.actor_role || 'system' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        {{ log.actor?.name || 'System' }} · {{ log.subject_label }} #{{ log.subject_id || '-' }}
                                    </p>
                                </div>
                                <p class="shrink-0 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(log.created_at) }}</p>
                            </div>

                            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                                <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950">
                                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">IP address</dt>
                                    <dd class="mt-1 truncate font-semibold text-gray-900 dark:text-white">{{ log.ip_address || 'Unknown' }}</dd>
                                </div>
                                <div class="rounded-md bg-gray-50 p-3 dark:bg-gray-950 sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Metadata</dt>
                                    <dd class="mt-1 line-clamp-2 text-xs text-gray-700 dark:text-gray-300">{{ prettyMetadata(log.metadata) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </article>
            </div>

            <PaginationControls :meta="meta" @change="load" />
        </div>
    </AdminLayout>
</template>
