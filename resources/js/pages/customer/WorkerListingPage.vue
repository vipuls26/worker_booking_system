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
const { errors, setApiError, clearApiErrors } = useApiErrors();
const showAdvancedFilters = ref(false);

const requestForm = reactive({
    service_id: '',
    booking_date: '',
    start_time: '',
    end_time: '',
    address: '',
    issue_description: '',
});

const canSendRequest = computed(() => requestForm.service_id && requestForm.booking_date && requestForm.start_time && requestForm.end_time && requestForm.address && requestForm.issue_description);
const selectedService = computed(() => workersStore.serviceOptions.find((service) => String(service.id) === String(workersStore.filters.service_id)));
const requestService = computed(() => workersStore.serviceOptions.find((service) => String(service.id) === String(requestForm.service_id)));
const dateShortcuts = computed(() => [
    { label: 'Today', value: dateValueFromNow(0) },
    { label: 'Tomorrow', value: dateValueFromNow(1) },
    { label: 'In 2 days', value: dateValueFromNow(2) },
]);
const activeFilterLabels = computed(() => {
    const labels = [];

    if (selectedService.value) {
        labels.push(selectedService.value.name);
    }

    if (workersStore.filters.city) {
        labels.push(workersStore.filters.city);
    }

    if (workersStore.filters.max_price) {
        labels.push(`Under ₹${workersStore.filters.max_price}`);
    }

    if (workersStore.filters.min_rating) {
        labels.push(`${workersStore.filters.min_rating}+ rating`);
    }

    if (workersStore.filters.available_date) {
        labels.push('Date selected');
    }

    return labels;
});

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

const timeShortcuts = [
    { label: 'Morning', start: '09:00', end: '11:00' },
    { label: 'Noon', start: '12:00', end: '14:00' },
    { label: 'Evening', start: '16:00', end: '18:00' },
    { label: 'Late', start: '18:00', end: '20:00' },
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

function dateValueFromNow(days) {
    const date = new Date();
    date.setDate(date.getDate() + days);

    return date.toISOString().slice(0, 10);
}

function selectDate(value) {
    requestForm.booking_date = value;
    workersStore.filters.available_date = value;
}

function selectTimeSlot(slot) {
    requestForm.start_time = slot.start;
    requestForm.end_time = slot.end;
    workersStore.filters.available_time = slot.start;
}

function applyRouteFilters() {
    const allowedFilters = ['service_id', 'city', 'max_price', 'min_rating', 'sort'];

    allowedFilters.forEach((key) => {
        if (route.query[key] !== undefined) {
            workersStore.filters[key] = String(route.query[key]);
        }
    });
}

async function sendRequest() {
    clearApiErrors();

    try {
        const response = await bookingsStore.create(requestForm);

        toast.success('Booking request sent');
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
            <section class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Find workers</p>
                            <h2 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">Pick a service and refine only when needed.</h2>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                @click="showAdvancedFilters = !showAdvancedFilters"
                            >
                                <i class="pi pi-sliders-h" aria-hidden="true"></i>
                                Filters
                            </button>
                            <AppButton type="button" icon="pi-search" :full-width="false" :loading="workersStore.loading" @click="load()">
                                Search
                            </AppButton>
                        </div>
                    </div>

                    <div v-if="activeFilterLabels.length" class="mt-4 flex flex-wrap gap-2">
                        <span v-for="label in activeFilterLabels" :key="label" class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ label }}
                        </span>
                        <button type="button" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10" @click="resetFilters">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
                    <div class="flex gap-3 overflow-x-auto pb-1">
                    <button
                        v-for="service in workersStore.serviceOptions.slice(0, 8)"
                        :key="service.id"
                        type="button"
                        class="group flex min-w-40 items-center gap-3 rounded-lg border p-3 text-left transition hover:bg-gray-50 dark:hover:bg-white/5"
                        :class="String(workersStore.filters.service_id) === String(service.id)
                            ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-950'
                            : 'border-gray-200 bg-white text-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white'"
                        @click="selectService(service)"
                    >
                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-md" :class="String(workersStore.filters.service_id) === String(service.id) ? 'bg-white/15 dark:bg-gray-950/10' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'">
                            <i :class="['pi', serviceIcon(service)]" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ service.name }}</p>
                            <p class="truncate text-xs opacity-70">{{ service.slug }}</p>
                        </div>
                    </button>
                </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-for="filter in quickFilters"
                            :key="filter.label"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            @click="applyQuickFilter(filter)"
                        >
                            <i :class="['pi', filter.icon]" aria-hidden="true"></i>
                            {{ filter.label }}
                        </button>
                    </div>
                </div>

                <div v-if="showAdvancedFilters" class="border-t border-gray-200 p-4 dark:border-white/10 sm:p-5">
                    <div class="grid gap-3 lg:grid-cols-[1fr_180px_180px]">
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

                    <div class="mt-3 grid gap-3 md:grid-cols-[1fr_1fr_220px]">
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
                </div>
            </section>

            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Request workers</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">Tell us the job. We will find matching workers.</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Send one request to verified, available workers. You choose the final worker after they accept.</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        <i class="pi pi-send" aria-hidden="true"></i>
                        Auto matching
                    </span>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="sendRequest">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-gray-900 text-sm font-semibold text-white dark:bg-white dark:text-gray-950">1</span>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">What service do you need?</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Pick from the approved service categories.</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <FormSelect id="request_service_id" v-model="requestForm.service_id" label="Service" :options="workersStore.serviceOptions" placeholder="Select service" :error="errors.service_id" />
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-gray-900 text-sm font-semibold text-white dark:bg-white dark:text-gray-950">2</span>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">When should they come?</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Use quick choices or enter your exact time.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="shortcut in dateShortcuts"
                                :key="shortcut.label"
                                type="button"
                                class="rounded-full border px-3 py-1.5 text-sm font-semibold transition"
                                :class="requestForm.booking_date === shortcut.value
                                    ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-950'
                                    : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-100 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/5'"
                                @click="selectDate(shortcut.value)"
                            >
                                {{ shortcut.label }}
                            </button>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <FormInput id="request_booking_date" v-model="requestForm.booking_date" label="Date" type="date" :error="errors.booking_date" />
                            <FormInput id="request_start_time" v-model="requestForm.start_time" label="Start time" type="time" :error="errors.start_time" />
                            <FormInput id="request_end_time" v-model="requestForm.end_time" label="End time" type="time" :error="errors.end_time" />
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="slot in timeShortcuts"
                                :key="slot.label"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-semibold transition"
                                :class="requestForm.start_time === slot.start && requestForm.end_time === slot.end
                                    ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-950'
                                    : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-100 dark:border-white/10 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/5'"
                                @click="selectTimeSlot(slot)"
                            >
                                <i class="pi pi-clock" aria-hidden="true"></i>
                                {{ slot.label }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-gray-950">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-gray-900 text-sm font-semibold text-white dark:bg-white dark:text-gray-950">3</span>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Where is the issue?</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">A clear address and problem note helps workers respond faster.</p>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <FormInput id="request_address" v-model="requestForm.address" label="Address" :error="errors.address" />
                            <div>
                        <label for="request_issue_description" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Issue description</label>
                        <textarea
                            id="request_issue_description"
                            v-model="requestForm.issue_description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                            placeholder="Example: AC is not cooling properly."
                        ></textarea>
                        <p v-if="errors.issue_description?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.issue_description[0] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-950 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ requestService ? requestService.name : 'Select a service to start' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only matching verified workers will receive this request.</p>
                        </div>
                        <div class="sm:w-56">
                            <AppButton type="submit" icon="pi-send" :loading="bookingsStore.saving" :disabled="!canSendRequest">Send request</AppButton>
                        </div>
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
                />
            </div>

            <PaginationControls :meta="workersStore.meta" @change="load" />
        </div>
    </DashboardLayout>
</template>
