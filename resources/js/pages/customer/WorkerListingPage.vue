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
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';
import { useCustomerWorkersStore } from '../../stores/customer/workers';

const router = useRouter();
const route = useRoute();
const workersStore = useCustomerWorkersStore();
const bookingsStore = useCustomerBookingsStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const showAdvancedFilters = ref(false);
const filtersReady = ref(false);

const requestForm = reactive({
    service_id: '',
    booking_date: '',
    duration_minutes: 120,
    start_time: '',
    end_time: '',
    address: '',
    issue_description: '',
});

const canSendRequest = computed(() => requestForm.service_id && requestForm.booking_date && requestForm.start_time && requestForm.duration_minutes && requestForm.address && requestForm.issue_description);
const selectedService = computed(() => workersStore.serviceOptions.find((service) => String(service.id) === String(workersStore.filters.service_id)));
const requestService = computed(() => workersStore.serviceOptions.find((service) => String(service.id) === String(requestForm.service_id)));
const requestSlotLabel = computed(() => {
    if (! requestForm.start_time || ! requestForm.end_time) {
        return 'Choose a start time';
    }

    return `${requestForm.start_time} - ${requestForm.end_time}`;
});
const durationOptions = [
    { id: 60, name: '1 hour' },
    { id: 120, name: '2 hours' },
    { id: 180, name: '3 hours' },
    { id: 240, name: '4 hours' },
];
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
}

function resetFilters() {
    workersStore.filters.service_id = '';
    workersStore.filters.min_rating = '';
    workersStore.filters.max_price = '';
    workersStore.filters.city = '';
    workersStore.filters.available_date = '';
    workersStore.filters.available_time = '';
    workersStore.filters.slot_minutes = 60;
    workersStore.filters.sort = 'relevance';
    requestForm.service_id = '';
    requestForm.duration_minutes = 120;
    requestForm.start_time = '';
    requestForm.end_time = '';
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
    requestForm.duration_minutes = minutesBetween(slot.start, slot.end);
    syncRequestEndTime();
    workersStore.filters.available_time = slot.start;
    workersStore.filters.slot_minutes = requestForm.duration_minutes;
}

function selectRequestDuration(minutes) {
    requestForm.duration_minutes = minutes;
    syncRequestEndTime();
}

function syncRequestEndTime() {
    if (! requestForm.start_time || ! requestForm.duration_minutes) {
        requestForm.end_time = '';
        return;
    }

    requestForm.end_time = addMinutes(requestForm.start_time, Number(requestForm.duration_minutes));
    workersStore.filters.available_time = requestForm.start_time;
    workersStore.filters.slot_minutes = Number(requestForm.duration_minutes);
}

function addMinutes(time, minutes) {
    const [hours, currentMinutes] = time.split(':').map(Number);
    const totalMinutes = (hours * 60 + currentMinutes + minutes) % 1440;
    const nextHours = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
    const nextMinutes = String(totalMinutes % 60).padStart(2, '0');

    return `${nextHours}:${nextMinutes}`;
}

function minutesBetween(start, end) {
    const [startHours, startMinutes] = start.split(':').map(Number);
    const [endHours, endMinutes] = end.split(':').map(Number);

    return (endHours * 60 + endMinutes) - (startHours * 60 + startMinutes);
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
    syncRequestEndTime();

    try {
        const response = await bookingsStore.create(requestForm);

        toast.success('Booking request sent');
        await router.push(`/customer/bookings/${response.data.booking.id}`);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to send request');
    }
}

useDebouncedWatch(
    () => [
        workersStore.filters.service_id,
        workersStore.filters.min_rating,
        workersStore.filters.max_price,
        workersStore.filters.city,
        workersStore.filters.available_date,
        workersStore.filters.available_time,
        workersStore.filters.slot_minutes,
        workersStore.filters.sort,
    ],
    () => {
        if (filtersReady.value) {
            load();
        }
    },
);

watch(
    () => workersStore.filters.service_id,
    (serviceId) => {
        requestForm.service_id = serviceId || '';
    },
);

watch(
    () => requestForm.service_id,
    (serviceId) => {
        workersStore.filters.service_id = serviceId || '';
    },
);

watch(
    () => requestForm.booking_date,
    (bookingDate) => {
        workersStore.filters.available_date = bookingDate || '';
    },
);

watch(
    () => [requestForm.start_time, requestForm.duration_minutes],
    syncRequestEndTime,
);

onMounted(async () => {
    applyRouteFilters();

    await Promise.all([
        workersStore.fetchOptions(),
        load(),
    ]);

    filtersReady.value = true;
});
</script>

<template>
    <DashboardLayout title="Find Workers">
        <div class="space-y-4 sm:space-y-5">
            <section class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="border-b border-gray-200 p-4 dark:border-white/10 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Find workers</p>
                            <h2 class="mt-1 text-lg font-semibold text-gray-900 dark:text-white sm:text-xl">Pick a service and refine only when needed.</h2>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-row">
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                @click="showAdvancedFilters = !showAdvancedFilters"
                            >
                                <i class="pi pi-sliders-h" aria-hidden="true"></i>
                                Filters
                            </button>
                            <AppButton type="button" icon="pi-search" :loading="workersStore.loading" @click="load()">
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
                    <div class="grid grid-cols-2 gap-2 sm:flex sm:gap-3 sm:overflow-x-auto sm:pb-1">
                        <button
                            v-for="service in workersStore.serviceOptions.slice(0, 8)"
                            :key="service.id"
                            type="button"
                            class="group flex min-w-0 items-center gap-2 rounded-lg border p-2.5 text-left shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500/30 sm:min-w-40 sm:gap-3 sm:p-3"
                            :class="String(workersStore.filters.service_id) === String(service.id)
                                ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700 dark:border-blue-400 dark:bg-blue-500 dark:text-white dark:hover:bg-blue-400'
                                : 'border-gray-200 bg-gray-50 text-gray-900 hover:border-gray-300 hover:bg-white dark:border-white/10 dark:bg-gray-900 dark:text-white dark:hover:border-white/20 dark:hover:bg-gray-800'"
                            @click="selectService(service)"
                        >
                            <span
                                class="inline-flex size-8 shrink-0 items-center justify-center rounded-md transition sm:size-9"
                                :class="String(workersStore.filters.service_id) === String(service.id)
                                    ? 'bg-white/20 text-white'
                                    : 'bg-white text-gray-700 ring-1 ring-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10'"
                            >
                                <i :class="['pi', serviceIcon(service)]" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">{{ service.name }}</p>
                                <p class="hidden truncate text-xs opacity-75 min-[380px]:block">{{ service.slug }}</p>
                            </div>
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <button
                            v-for="filter in quickFilters"
                            :key="filter.label"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-full border border-gray-200 px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            @click="applyQuickFilter(filter)"
                        >
                            <i :class="['pi', filter.icon]" aria-hidden="true"></i>
                            {{ filter.label }}
                        </button>
                    </div>
                </div>

                <div v-if="showAdvancedFilters" class="border-t border-gray-200 p-4 dark:border-white/10 sm:p-5">
                    <div class="grid gap-3 lg:grid-cols-[1fr_180px_180px]">
                        <label class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                <i class="pi pi-map-marker" aria-hidden="true"></i>
                                City
                            </span>
                            <input
                                v-model="workersStore.filters.city"
                                type="text"
                                class="mt-3 block w-full border-0 bg-transparent p-0 text-base font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-600 sm:text-lg"
                                placeholder="Search by city"
                                @keyup.enter="load()"
                            >
                        </label>

                        <label class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                <i class="pi pi-money-bill" aria-hidden="true"></i>
                                Max price
                            </span>
                            <input
                                v-model="workersStore.filters.max_price"
                                type="number"
                                min="1"
                                class="mt-3 block w-full border-0 bg-transparent p-0 text-base font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-0 dark:text-white dark:placeholder:text-gray-600 sm:text-lg"
                                placeholder="Any"
                                @keyup.enter="load()"
                            >
                        </label>

                        <label class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                <i class="pi pi-star" aria-hidden="true"></i>
                                Rating
                            </span>
                            <select
                                v-model="workersStore.filters.min_rating"
                                class="mt-3 block w-full border-0 bg-white p-0 text-base font-semibold text-gray-900 [color-scheme:light] focus:ring-0 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark] sm:text-lg"
                            >
                                <option value="" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">Any</option>
                                <option value="3" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">3+</option>
                                <option value="4" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">4+</option>
                                <option value="4.5" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">4.5+</option>
                            </select>
                        </label>
                    </div>

                    <div class="mt-3 grid gap-3 md:grid-cols-[1fr_1fr_180px_220px]">
                        <label class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Available date</span>
                            <input v-model="workersStore.filters.available_date" type="date" class="mt-3 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-white">
                        </label>

                        <label class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Available time</span>
                            <input v-model="workersStore.filters.available_time" type="time" class="mt-3 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-white">
                        </label>

                        <FormSelect id="worker_search_duration" v-model="workersStore.filters.slot_minutes" label="Duration" :options="durationOptions" />

                        <label class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Sort</span>
                            <select v-model="workersStore.filters.sort" class="mt-3 block w-full border-0 bg-white p-0 text-sm font-semibold text-gray-900 [color-scheme:light] focus:ring-0 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark]">
                                <option v-for="option in sortOptions" :key="option.value" :value="option.value" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">{{ option.label }}</option>
                            </select>
                        </label>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600 dark:text-blue-300">Auto-match request</p>
                        <h2 class="mt-1 text-base font-semibold text-gray-900 dark:text-white sm:text-lg">Find matching workers for this job.</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Send one request to verified, available workers. Compare accepted workers and choose the final one.</p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        <i class="pi pi-send" aria-hidden="true"></i>
                        Multiple workers
                    </span>
                </div>

                <form class="mt-5 space-y-4" @submit.prevent="sendRequest">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
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

                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 dark:border-blue-500/20 dark:bg-blue-500/10 sm:p-4">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-md bg-blue-600 text-sm font-semibold text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">2</span>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">When should they come?</h3>
                                <p class="text-xs text-blue-700 dark:text-blue-200">Choose a date, start time, and duration. We calculate the exact slot.</p>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <button
                                v-for="shortcut in dateShortcuts"
                                :key="shortcut.label"
                                type="button"
                                class="rounded-full border px-3 py-1.5 text-sm font-semibold transition"
                                :class="requestForm.booking_date === shortcut.value
                                    ? 'border-blue-600 bg-blue-600 text-white dark:border-blue-400 dark:bg-blue-500'
                                    : 'border-blue-100 bg-white text-gray-700 hover:border-blue-300 hover:bg-blue-100/60 dark:border-blue-500/20 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-blue-500/10'"
                                @click="selectDate(shortcut.value)"
                            >
                                {{ shortcut.label }}
                            </button>
                        </div>

                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <FormInput id="request_booking_date" v-model="requestForm.booking_date" label="Date" type="date" :error="errors.booking_date" />
                            <FormInput id="request_start_time" v-model="requestForm.start_time" label="Start time" type="time" :error="errors.start_time" />
                        </div>

                        <div class="mt-4">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Duration</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button
                                    v-for="option in durationOptions"
                                    :key="option.id"
                                    type="button"
                                    class="rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition"
                                    :class="Number(requestForm.duration_minutes) === Number(option.id)
                                        ? 'border-blue-600 bg-blue-600 text-white shadow-blue-600/20 dark:border-blue-400 dark:bg-blue-500'
                                        : 'border-blue-100 bg-white text-gray-700 hover:border-blue-300 hover:bg-blue-100/60 dark:border-blue-500/20 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-blue-500/10'"
                                    @click="selectRequestDuration(option.id)"
                                >
                                    {{ option.name }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Popular slots</p>
                            <div class="mt-2 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                            <button
                                v-for="slot in timeShortcuts"
                                :key="slot.label"
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition"
                                :class="requestForm.start_time === slot.start && requestForm.end_time === slot.end
                                    ? 'border-emerald-600 bg-emerald-600 text-white shadow-emerald-600/20 dark:border-emerald-400 dark:bg-emerald-500'
                                    : 'border-emerald-100 bg-white text-gray-700 hover:border-emerald-300 hover:bg-emerald-50 dark:border-emerald-500/20 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-emerald-500/10'"
                                @click="selectTimeSlot(slot)"
                            >
                                <i class="pi pi-clock" aria-hidden="true"></i>
                                {{ slot.label }}
                            </button>
                            </div>
                        </div>

                        <div class="mt-4 rounded-lg border border-white bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-blue-500/20 dark:bg-gray-950 dark:text-gray-200">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">Selected slot</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only workers free for this full duration will receive the request.</p>
                                </div>
                                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-200">
                                    <i class="pi pi-calendar-clock" aria-hidden="true"></i>
                                    {{ requestSlotLabel }}
                                </span>
                            </div>
                            <p v-if="errors.end_time?.length" class="mt-2 text-sm text-red-600 dark:text-red-300">{{ errors.end_time[0] }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
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

                    <div class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-950 sm:flex-row sm:items-center sm:justify-between sm:p-4">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ requestService ? requestService.name : 'Select a service to start' }}
                            </p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">This request goes to multiple matching workers. You choose after they accept.</p>
                        </div>
                        <div class="sm:w-56">
                            <AppButton type="submit" icon="pi-send" :loading="bookingsStore.saving" :disabled="!canSendRequest">Find matching workers</AppButton>
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
