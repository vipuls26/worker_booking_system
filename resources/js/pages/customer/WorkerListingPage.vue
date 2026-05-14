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
import FormTextarea from '../../components/forms/FormTextarea.vue';
import SearchFilter from '../../components/forms/SearchFilter.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useDebouncedWatch } from '../../composables/useDebouncedWatch';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';
import { useCustomerWorkersStore } from '../../stores/customer/workers';

function localDateString() {
    const today = new Date();
    const timezoneOffsetInMilliseconds = today.getTimezoneOffset() * 60 * 1000;

    return new Date(today.getTime() - timezoneOffsetInMilliseconds).toISOString().slice(0, 10);
}

function localTimeString() {
    const now = new Date();

    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
}

function roundTimeUpToFiveMinutes(time) {
    const [hours, minutes] = time.split(':').map(Number);
    const totalMinutes = hours * 60 + minutes;
    const roundedMinutes = Math.ceil(totalMinutes / 5) * 5;
    const boundedMinutes = Math.min(roundedMinutes, 23 * 60 + 55);
    const nextHours = String(Math.floor(boundedMinutes / 60)).padStart(2, '0');
    const nextMinutes = String(boundedMinutes % 60).padStart(2, '0');

    return `${nextHours}:${nextMinutes}`;
}

const router = useRouter();
const route = useRoute();
const workersStore = useCustomerWorkersStore();
const bookingsStore = useCustomerBookingsStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const showAdvancedFilters = ref(false);
const filtersReady = ref(false);
const frontendErrors = reactive({
    service_id: [],
    booking_date: [],
    start_time: [],
    address: [],
    issue_description: [],
});

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
const minimumBookingDate = computed(() => localDateString());
const minimumBookingTime = computed(() => requestForm.booking_date === minimumBookingDate.value ? roundTimeUpToFiveMinutes(localTimeString()) : '');
const minimumAvailableDate = computed(() => localDateString());
const minimumAvailableTime = computed(() => workersStore.filters.available_date === minimumAvailableDate.value ? roundTimeUpToFiveMinutes(localTimeString()) : '');
const requestStartTimeInputKey = computed(() => `${requestForm.booking_date || 'no-date'}:${minimumBookingTime.value || 'any-time'}`);
const availableTimeInputKey = computed(() => `${workersStore.filters.available_date || 'no-date'}:${minimumAvailableTime.value || 'any-time'}`);
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

    if (workersStore.filters.search) {
        labels.push(`"${workersStore.filters.search}"`);
    }

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
    { label: 'Today', icon: 'pi-calendar', payload: { available_date: localDateString() } },
];

const timeShortcuts = [
    { label: 'Morning', start: '09:00', end: '11:00' },
    { label: 'Noon', start: '12:00', end: '14:00' },
    { label: 'Evening', start: '16:00', end: '18:00' },
    { label: 'Late', start: '18:00', end: '20:00' },
];
const visibleTimeShortcuts = computed(() => timeShortcuts.filter((slot) => ! minimumBookingTime.value || slot.start >= minimumBookingTime.value));
const timeAdjustmentOptions = [
    { label: '15 min earlier', minutes: -15 },
    { label: '10 min earlier', minutes: -10 },
    { label: '10 min later', minutes: 10 },
    { label: '15 min later', minutes: 15 },
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
    workersStore.filters.search = '';
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
    const timezoneOffsetInMilliseconds = date.getTimezoneOffset() * 60 * 1000;

    return new Date(date.getTime() - timezoneOffsetInMilliseconds).toISOString().slice(0, 10);
}

function selectDate(value) {
    requestForm.booking_date = value;
    workersStore.filters.available_date = value;
}

function selectTimeSlot(slot) {
    if (minimumBookingTime.value && slot.start < minimumBookingTime.value) {
        frontendErrors.start_time = ['Please choose the current time or a future time.'];

        return;
    }

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

function adjustRequestStartTime(minutes) {
    if (! requestForm.start_time) {
        return;
    }

    const adjustedTime = addMinutes(requestForm.start_time, minutes);

    if (minimumBookingTime.value && adjustedTime < minimumBookingTime.value) {
        frontendErrors.start_time = ['Please choose the current time or a future time.'];

        return;
    }

    requestForm.start_time = adjustedTime;
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
    const minutesPerDay = 1440;
    const totalMinutes = ((hours * 60 + currentMinutes + minutes) % minutesPerDay + minutesPerDay) % minutesPerDay;
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
    const allowedFilters = ['search', 'service_id', 'city', 'max_price', 'min_rating', 'sort'];

    allowedFilters.forEach((key) => {
        if (route.query[key] !== undefined) {
            workersStore.filters[key] = String(route.query[key]);
        }
    });
}

function clearFrontendErrors() {
    frontendErrors.service_id = [];
    frontendErrors.booking_date = [];
    frontendErrors.start_time = [];
    frontendErrors.address = [];
    frontendErrors.issue_description = [];
}

function syncFiltersToRoute() {
    const nextQuery = {
        ...(workersStore.filters.search ? { search: workersStore.filters.search } : {}),
        ...(workersStore.filters.service_id ? { service_id: workersStore.filters.service_id } : {}),
        ...(workersStore.filters.city ? { city: workersStore.filters.city } : {}),
        ...(workersStore.filters.max_price ? { max_price: workersStore.filters.max_price } : {}),
        ...(workersStore.filters.min_rating ? { min_rating: workersStore.filters.min_rating } : {}),
        ...(workersStore.filters.sort && workersStore.filters.sort !== 'relevance' ? { sort: workersStore.filters.sort } : {}),
    };

    router.replace({
        path: route.path,
        query: nextQuery,
    });
}

function validateRequestSchedule() {
    clearFrontendErrors();

    // Customers should not be able to choose a day that has already passed.
    if (requestForm.booking_date && requestForm.booking_date < minimumBookingDate.value) {
        frontendErrors.booking_date = ['Please choose today or a future date.'];

        return false;
    }

    // Same-day requests must start now or later so workers are not asked for expired slots.
    if (requestForm.booking_date === minimumBookingDate.value && requestForm.start_time && requestForm.start_time < minimumBookingTime.value) {
        frontendErrors.start_time = ['Please choose the current time or a future time.'];

        return false;
    }

    return true;
}

function validateRequestForm() {
    const minimumIssueDescriptionLength = 10;

    if (! validateRequestSchedule()) {
        return false;
    }

    // A request must include a service so workers know which approved category is being booked.
    if (! requestForm.service_id) {
        frontendErrors.service_id = ['Please choose a service.'];
    }

    // A service address is required so the worker knows where to travel before accepting.
    if (! requestForm.address.trim()) {
        frontendErrors.address = ['Add a service address or save a default address in your profile.'];
    }

    // A meaningful issue note helps workers estimate the job accurately.
    if (! requestForm.issue_description.trim()) {
        frontendErrors.issue_description = ['Please describe the issue.'];
    } else if (requestForm.issue_description.trim().length < minimumIssueDescriptionLength) {
        frontendErrors.issue_description = [`Please describe the issue in at least ${minimumIssueDescriptionLength} characters.`];
    }

    return Object.values(frontendErrors).every((messages) => messages.length === 0);
}

async function sendRequest() {
    clearApiErrors();
    clearFrontendErrors();
    syncRequestEndTime();

    if (! validateRequestForm()) {
        return;
    }

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
        workersStore.filters.search,
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
            syncFiltersToRoute();
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
        frontendErrors.service_id = [];
    },
);

watch(
    () => workersStore.filters.available_date,
    (availableDate) => {
        if (availableDate === minimumAvailableDate.value && workersStore.filters.available_time && workersStore.filters.available_time < minimumAvailableTime.value) {
            workersStore.filters.available_time = '';
        }
    },
);

watch(
    () => workersStore.filters.available_time,
    (availableTime) => {
        if (workersStore.filters.available_date === minimumAvailableDate.value && availableTime && availableTime < minimumAvailableTime.value) {
            workersStore.filters.available_time = '';
        }
    },
);

watch(
    () => requestForm.booking_date,
    (bookingDate) => {
        workersStore.filters.available_date = bookingDate || '';
        frontendErrors.booking_date = [];

        if (bookingDate === minimumBookingDate.value && requestForm.start_time && requestForm.start_time < minimumBookingTime.value) {
            requestForm.start_time = '';
            requestForm.end_time = '';
            frontendErrors.start_time = ['Please choose the current time or a future time.'];
        }
    },
);

watch(
    () => requestForm.start_time,
    (startTime) => {
        if (! startTime) {
            frontendErrors.start_time = [];

            return;
        }

        if (requestForm.booking_date === minimumBookingDate.value && startTime < minimumBookingTime.value) {
            requestForm.start_time = '';
            requestForm.end_time = '';
            frontendErrors.start_time = ['Please choose the current time or a future time.'];

            return;
        }

        frontendErrors.start_time = [];
        syncRequestEndTime();
    },
);

watch(
    () => requestForm.address,
    () => {
        frontendErrors.address = [];
    },
);

watch(
    () => requestForm.issue_description,
    () => {
        frontendErrors.issue_description = [];
    },
);

watch(
    () => requestForm.duration_minutes,
    () => {
        syncRequestEndTime();
    },
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
        <div class="space-y-4 sm:space-y-5" data-testid="worker-listing-page">
            <Transition appear enter-active-class="fade-up-enter-active" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
                <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.07)] dark:border-white/10 dark:bg-slate-900 dark:shadow-none">
                <div class="border-b border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.10),_transparent_48%),linear-gradient(to_bottom,_rgba(248,250,252,1),_rgba(255,255,255,1))] p-4 dark:border-white/10 dark:bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.12),_transparent_48%),linear-gradient(to_bottom,_rgba(15,23,42,0.9),_rgba(15,23,42,1))] sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Find workers</p>
                            <h2 class="mt-1 text-lg font-semibold tracking-tight text-slate-900 dark:text-white sm:text-2xl">Pick a service and refine only when needed.</h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">Browse verified local workers, narrow by city or budget, and send one request that matches the job window you need.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-row">
                            <button
                                type="button"
                                data-testid="worker-listing-toggle-filters"
                                class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
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
                        <span v-for="label in activeFilterLabels" :key="label" class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-white/10 dark:text-slate-200">
                            {{ label }}
                        </span>
                        <button type="button" data-testid="worker-listing-clear-filters" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-50 dark:text-red-300 dark:hover:bg-red-500/10" @click="resetFilters">
                            Clear
                        </button>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
                    <div class="mb-4">
                        <SearchFilter
                            v-model="workersStore.filters.search"
                            placeholder="Search by worker, service, city, or skill"
                            @search="load()"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:flex sm:gap-3 sm:overflow-x-auto sm:pb-1">
                        <button
                            v-for="service in workersStore.serviceOptions.slice(0, 8)"
                            :key="service.id"
                            type="button"
                            class="group flex min-w-0 items-center gap-2 rounded-2xl border p-2.5 text-left shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500/30 sm:min-w-40 sm:gap-3 sm:p-3"
                            :class="String(workersStore.filters.service_id) === String(service.id)
                                ? 'border-blue-600 bg-blue-600 text-white hover:bg-blue-700 dark:border-blue-400 dark:bg-blue-500 dark:text-white dark:hover:bg-blue-400'
                                : 'border-slate-200 bg-slate-50 text-slate-900 hover:border-slate-300 hover:bg-white dark:border-white/10 dark:bg-slate-950 dark:text-white dark:hover:border-white/20 dark:hover:bg-slate-800'"
                            @click="selectService(service)"
                        >
                            <span
                                class="inline-flex size-8 shrink-0 items-center justify-center rounded-md transition sm:size-9"
                                :class="String(workersStore.filters.service_id) === String(service.id)
                                    ? 'bg-white/20 text-white'
                                    : 'bg-white text-slate-700 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-200 dark:ring-white/10'"
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
                            class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 dark:border-white/10 dark:text-slate-200 dark:hover:bg-slate-800"
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
                                data-testid="worker-listing-city-filter"
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
                                data-testid="worker-listing-max-price-filter"
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
                                data-testid="worker-listing-rating-filter"
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
                            <input v-model="workersStore.filters.available_date" data-testid="worker-listing-date-filter" type="date" :min="minimumAvailableDate" class="mt-3 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 dark:text-white">
                        </label>

                        <label class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Available time</span>
                            <input :key="availableTimeInputKey" v-model="workersStore.filters.available_time" data-testid="worker-listing-time-filter" type="time" :min="minimumAvailableTime" :disabled="!workersStore.filters.available_date" class="mt-3 block w-full border-0 bg-transparent p-0 text-sm font-semibold text-gray-900 focus:ring-0 disabled:cursor-not-allowed disabled:text-gray-400 dark:text-white dark:disabled:text-gray-500">
                        </label>

                        <FormSelect id="worker_search_duration" v-model="workersStore.filters.slot_minutes" label="Duration" :options="durationOptions" />

                        <label class="rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-950 sm:p-4">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">Sort</span>
                            <select v-model="workersStore.filters.sort" data-testid="worker-listing-sort-filter" class="mt-3 block w-full border-0 bg-white p-0 text-sm font-semibold text-gray-900 [color-scheme:light] focus:ring-0 dark:bg-gray-950 dark:text-white dark:[color-scheme:dark]">
                                <option v-for="option in sortOptions" :key="option.value" :value="option.value" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">{{ option.label }}</option>
                            </select>
                        </label>
                    </div>
                </div>
                </section>
            </Transition>

            <Transition appear enter-active-class="fade-up-enter-active delay-100" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
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
                            <FormSelect
                                id="request_service_id"
                                v-model="requestForm.service_id"
                                label="Service"
                                :options="workersStore.serviceOptions"
                                placeholder="Select service"
                                :error="frontendErrors.service_id.length ? frontendErrors.service_id : errors.service_id"
                            />
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
                            <FormInput id="request_booking_date" v-model="requestForm.booking_date" label="Date" type="date" :min="minimumBookingDate" :error="frontendErrors.booking_date.length ? frontendErrors.booking_date : errors.booking_date" />
                            <FormInput :key="requestStartTimeInputKey" id="request_start_time" v-model="requestForm.start_time" label="Start time" type="time" :min="minimumBookingTime" :disabled="!requestForm.booking_date" step="300" :error="frontendErrors.start_time.length ? frontendErrors.start_time : errors.start_time" />
                        </div>

                        <div class="mt-3">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="adjustment in timeAdjustmentOptions"
                                    :key="adjustment.label"
                                    type="button"
                                    class="inline-flex min-h-10 items-center justify-center rounded-full border border-blue-200 bg-white px-3 py-2 text-sm font-semibold text-blue-700 transition hover:border-blue-400 hover:bg-blue-100/70 disabled:cursor-not-allowed disabled:opacity-50 dark:border-blue-500/20 dark:bg-gray-950 dark:text-blue-200 dark:hover:bg-blue-500/10"
                                    :disabled="!requestForm.start_time"
                                    @click="adjustRequestStartTime(adjustment.minutes)"
                                >
                                    {{ adjustment.label }}
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-blue-700 dark:text-blue-200">
                                Need a little flexibility? Pick a nearby time here or type the exact start time manually.
                            </p>
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
                                    v-for="slot in visibleTimeShortcuts"
                                    :key="slot.label"
                                    type="button"
                                    class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition"
                                    :class="requestForm.start_time === slot.start && requestForm.end_time === slot.end
                                        ? 'border-emerald-600 bg-emerald-600 text-white shadow-emerald-600/20 dark:border-emerald-400 dark:bg-emerald-500'
                                        : 'border-emerald-100 bg-white text-gray-700 hover:border-emerald-300 hover:bg-emerald-50 dark:border-emerald-500/20 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-emerald-500/10'"
                                    @click="selectTimeSlot(slot)"
                                >
                                    <i class="pi pi-clock" aria-hidden="true"></i>
                                    {{ slot.label }}
                                </button>
                            </div>
                            <p v-if="requestForm.booking_date === minimumBookingDate && visibleTimeShortcuts.length === 0" class="mt-2 text-xs text-amber-700 dark:text-amber-200">
                                No preset slots are left for today. Please choose a later start time.
                            </p>
                        </div>

                        <div class="mt-4 rounded-lg border border-white bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-blue-500/20 dark:bg-gray-950 dark:text-gray-200">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">Selected slot</p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only workers free for this full duration will receive the request.</p>
                                </div>
                                <span class="inline-flex w-fit max-w-full items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700 dark:bg-blue-500/10 dark:text-blue-200">
                                    <i class="pi pi-calendar-clock" aria-hidden="true"></i>
                                    <span class="truncate">{{ requestSlotLabel }}</span>
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
                            <FormInput id="request_address" v-model="requestForm.address" label="Address" :error="frontendErrors.address.length ? frontendErrors.address : errors.address" />
                            <FormTextarea id="request_issue_description" v-model="requestForm.issue_description" label="Issue description" rows="3" placeholder="Example: AC is not cooling properly." :error="frontendErrors.issue_description.length ? frontendErrors.issue_description : errors.issue_description" />
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
            </Transition>

            <Transition appear enter-active-class="fade-up-enter-active delay-150" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
                <div>
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
            </Transition>
        </div>
    </DashboardLayout>
</template>
