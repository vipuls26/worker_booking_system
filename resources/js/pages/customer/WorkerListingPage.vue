<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import PaginationControls from '../../components/common/PaginationControls.vue';
import SkeletonList from '../../components/common/SkeletonList.vue';
import WorkerCard from '../../components/customer/WorkerCard.vue';
import FormInput from '../../components/forms/FormInput.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';
import { useCustomerWorkersStore } from '../../stores/customer/workers';

const router = useRouter();
const route = useRoute();
const workersStore = useCustomerWorkersStore();
const bookingsStore = useCustomerBookingsStore();
const selectedWorkerIds = ref([]);
const { errors, setApiError, clearApiErrors } = useApiErrors();

const requestForm = reactive({
    service_id: '',
    booking_date: '',
    start_time: '',
    end_time: '',
    address: '',
    issue_description: '',
});

const canSendRequest = computed(() => selectedWorkerIds.value.length > 0 && requestForm.service_id);
const selectedWorkers = computed(() => workersStore.workers.filter((worker) => selectedWorkerIds.value.includes(worker.id)));
const selectedService = computed(() => workersStore.serviceOptions.find((service) => String(service.id) === String(workersStore.filters.service_id)));
const hasActiveFilters = computed(() => (
    workersStore.filters.service_id
    || workersStore.filters.city
    || workersStore.filters.max_price
    || workersStore.filters.min_rating
    || workersStore.filters.available_date
    || workersStore.filters.available_time
    || workersStore.filters.sort !== 'relevance'
));

const sortOptions = [
    { label: 'Relevance', value: 'relevance' },
    { label: 'Price low to high', value: 'price_low' },
    { label: 'Price high to low', value: 'price_high' },
    { label: 'Rating', value: 'rating' },
    { label: 'Experience', value: 'experience' },
];

const quickFilters = [
    { label: 'Top rated', icon: 'pi-star-fill', payload: { min_rating: '4', sort: 'rating' } },
    { label: 'Lowest price', icon: 'pi-wallet', payload: { sort: 'price_low' } },
    { label: 'Experienced', icon: 'pi-briefcase', payload: { sort: 'experience' } },
    { label: 'Today', icon: 'pi-calendar', payload: { available_date: new Date().toISOString().slice(0, 10) } },
];

async function load(page = 1) {
    try {
        await workersStore.fetch(page);
    } catch {
        toast.error('Unable to load workers');
    }
}

function serviceIcon(service) {
    return service.icon?.startsWith('pi-') ? service.icon : 'pi-briefcase';
}

function selectService(service) {
    workersStore.filters.service_id = String(workersStore.filters.service_id) === String(service.id) ? '' : String(service.id);
    requestForm.service_id = workersStore.filters.service_id;
}

function applyQuickFilter(filter) {
    Object.assign(workersStore.filters, filter.payload);
    load();
}

function resetFilters() {
    workersStore.filters.service_id = '';
    workersStore.filters.min_rating = '';
    workersStore.filters.max_price = '';
    workersStore.filters.city = '';
    workersStore.filters.available_date = '';
    workersStore.filters.available_time = '';
    workersStore.filters.sort = 'relevance';
    requestForm.service_id = '';
    load();
}

function applyRouteFilters() {
    const allowedFilters = ['service_id', 'city', 'max_price', 'min_rating', 'sort'];

    allowedFilters.forEach((key) => {
        if (route.query[key] !== undefined) {
            workersStore.filters[key] = String(route.query[key]);
        }
    });
}

function toggleWorker(worker) {
    if (selectedWorkerIds.value.includes(worker.id)) {
        selectedWorkerIds.value = selectedWorkerIds.value.filter((id) => id !== worker.id);
        return;
    }

    selectedWorkerIds.value = [...selectedWorkerIds.value, worker.id];
}

async function sendRequest() {
    clearApiErrors();

    try {
        const response = await bookingsStore.create({
            ...requestForm,
            worker_ids: selectedWorkerIds.value,
        });

        toast.success('Booking request sent');
        selectedWorkerIds.value = [];
        await router.push(`/customer/bookings/${response.data.booking.id}`);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to send request');
    }
}

watch(
    () => workersStore.filters.service_id,
    (serviceId) => {
        requestForm.service_id = serviceId || '';
    },
);

onMounted(async () => {
    applyRouteFilters();

    await Promise.all([
        workersStore.fetchOptions(),
        load(),
    ]);
});
</script>

<template>
    <DashboardLayout title="Find Workers">
        <div class="space-y-5">
            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Worker Discovery</p>
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Choose a service, then refine the match</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Use cards and quick filters to find verified workers faster.</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <AppButton type="button" icon="pi-refresh" variant="secondary" :full-width="false" :disabled="!hasActiveFilters" @click="resetFilters">
                            Reset
                        </AppButton>
                        <AppButton type="button" icon="pi-search" :full-width="false" :loading="workersStore.loading" @click="load()">
                            Search
                        </AppButton>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <button
                        v-for="service in workersStore.serviceOptions.slice(0, 8)"
                        :key="service.id"
                        type="button"
                        class="group min-h-28 rounded-lg border p-4 text-left transition hover:-translate-y-0.5 hover:shadow-sm"
                        :class="String(workersStore.filters.service_id) === String(service.id)
                            ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-950'
                            : 'border-gray-200 bg-gray-50 text-gray-900 hover:bg-white dark:border-white/10 dark:bg-gray-950 dark:text-white dark:hover:bg-white/5'"
                        @click="selectService(service)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex size-10 items-center justify-center rounded-md" :class="String(workersStore.filters.service_id) === String(service.id) ? 'bg-white/15 dark:bg-gray-950/10' : 'bg-white text-gray-700 dark:bg-white/10 dark:text-gray-200'">
                                <i :class="['pi', serviceIcon(service)]" aria-hidden="true"></i>
                            </span>
                            <i v-if="String(workersStore.filters.service_id) === String(service.id)" class="pi pi-check-circle" aria-hidden="true"></i>
                        </div>
                        <p class="mt-4 font-semibold">{{ service.name }}</p>
                        <p class="mt-1 text-xs opacity-70">{{ service.slug }}</p>
                    </button>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-[1fr_180px_180px]">
                    <label class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                            <i class="pi pi-map-marker" aria-hidden="true"></i>
                            City
                        </span>
                        <input
                            v-model="workersStore.filters.city"
                            type="text"
                            class="mt-3 block w-full border-0 bg-transparent p-0 text-lg font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-600"
                            placeholder="Search by city"
                            @keyup.enter="load()"
                        >
                    </label>

                    <label class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                            <i class="pi pi-money-bill" aria-hidden="true"></i>
                            Max price
                        </span>
                        <input
                            v-model="workersStore.filters.max_price"
                            type="number"
                            min="1"
                            class="mt-3 block w-full border-0 bg-transparent p-0 text-lg font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-600"
                            placeholder="Any"
                            @keyup.enter="load()"
                        >
                    </label>

                    <label class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                            <i class="pi pi-star" aria-hidden="true"></i>
                            Rating
                        </span>
                        <select
                            v-model="workersStore.filters.min_rating"
                            class="mt-3 block w-full border-0 bg-transparent p-0 text-lg font-semibold text-gray-900 focus:ring-0 dark:text-white"
                        >
                            <option value="">Any</option>
                            <option value="3">3+</option>
                            <option value="4">4+</option>
                            <option value="4.5">4.5+</option>
                        </select>
                    </label>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_220px]">
                    <label class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Available date</span>
                        <input v-model="workersStore.filters.available_date" type="date" class="mt-3 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-white">
                    </label>

                    <label class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Available time</span>
                        <input v-model="workersStore.filters.available_time" type="time" class="mt-3 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-white">
                    </label>

                    <label class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950">
                        <span class="text-sm font-semibold text-gray-900 dark:text-white">Sort</span>
                        <select v-model="workersStore.filters.sort" class="mt-3 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-white">
                            <option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="filter in quickFilters"
                        :key="filter.label"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                        @click="applyQuickFilter(filter)"
                    >
                        <i :class="['pi', filter.icon]" aria-hidden="true"></i>
                        {{ filter.label }}
                    </button>
                </div>
            </section>

            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900 dark:text-white">Send request to multiple workers</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pick workers from the results below. The request panel will use your selected service.</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        <i class="pi pi-users" aria-hidden="true"></i>
                        {{ selectedWorkerIds.length }} selected
                    </span>
                </div>

                <div v-if="selectedWorkers.length" class="mt-4 flex flex-wrap gap-2">
                    <span v-for="worker in selectedWorkers" :key="worker.id" class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        {{ worker.name }}
                        <button type="button" class="text-gray-500 hover:text-gray-900 dark:hover:text-white" @click="toggleWorker(worker)">
                            <i class="pi pi-times text-xs" aria-hidden="true"></i>
                        </button>
                    </span>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Service</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ selectedService?.name || 'Select from filters or choose here' }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Workers</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ selectedWorkerIds.length }} selected for this request</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Flow</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Workers respond first, then you choose final worker.</p>
                    </div>
                </div>

                <form class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3" @submit.prevent="sendRequest">
                    <FormSelect id="request_service_id" v-model="requestForm.service_id" label="Service" :options="workersStore.serviceOptions" placeholder="Select service" :error="errors.service_id" />
                    <FormInput id="request_booking_date" v-model="requestForm.booking_date" label="Date" type="date" :error="errors.booking_date" />
                    <FormInput id="request_start_time" v-model="requestForm.start_time" label="Start time" type="time" :error="errors.start_time" />
                    <FormInput id="request_end_time" v-model="requestForm.end_time" label="End time" type="time" :error="errors.end_time" />
                    <div class="md:col-span-2">
                        <FormInput id="request_address" v-model="requestForm.address" label="Address" :error="errors.address" />
                    </div>
                    <div class="lg:col-span-3">
                        <label for="request_issue_description" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Issue description</label>
                        <textarea
                            id="request_issue_description"
                            v-model="requestForm.issue_description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                        ></textarea>
                        <p v-if="errors.issue_description?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.issue_description[0] }}</p>
                        <p v-if="errors.worker_ids?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.worker_ids[0] }}</p>
                    </div>
                    <div class="lg:col-span-3 sm:w-56">
                        <AppButton type="submit" icon="pi-send" :loading="bookingsStore.saving" :disabled="!canSendRequest">Send request</AppButton>
                    </div>
                </form>
            </section>

            <div v-if="workersStore.loading" class="grid gap-4 lg:grid-cols-2">
                <SkeletonList :count="4" actions />
            </div>

            <div v-else-if="workersStore.workers.length === 0" class="rounded-lg bg-white p-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                No workers matched your filters.
            </div>

            <div v-else class="grid gap-4 lg:grid-cols-2">
                <WorkerCard
                    v-for="worker in workersStore.workers"
                    :key="worker.id"
                    :worker="worker"
                    selectable
                    :selected="selectedWorkerIds.includes(worker.id)"
                    @toggle-select="toggleWorker"
                />
            </div>

            <PaginationControls :meta="workersStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
