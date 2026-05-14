<script setup>
import { computed, onMounted, reactive, watch } from 'vue';
import { useRoute, RouterLink, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import RatingStars from '../../components/common/RatingStars.vue';
import SkeletonCard from '../../components/common/SkeletonCard.vue';
import FormInput from '../../components/forms/FormInput.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import { useYupValidation } from '../../composables/useYupValidation';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useAuthStore } from '../../stores/auth';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';
import { useCustomerWorkersStore } from '../../stores/customer/workers';
import { bookingRequestSchema } from '../../validation/bookingSchemas';
import { currentLocalLatestBookingDateString } from '../../validation/shared';

function localDateString() {
    const today = new Date();
    const timezoneOffsetInMilliseconds = today.getTimezoneOffset() * 60 * 1000;

    return new Date(today.getTime() - timezoneOffsetInMilliseconds).toISOString().slice(0, 10);
}

const route = useRoute();
const router = useRouter();
const workersStore = useCustomerWorkersStore();
const bookingsStore = useCustomerBookingsStore();
const authStore = useAuthStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const { validationErrors, clearValidationErrors, validateWithSchema } = useYupValidation(bookingRequestSchema);
const worker = computed(() => workersStore.worker);
const form = reactive({
    source_booking_id: '',
    service_id: '',
    booking_date: localDateString(),
    duration_minutes: 60,
    start_time: '',
    end_time: '',
    address: '',
    issue_description: '',
});
const savedAddress = computed(() => authStore.user?.address || '');
const isUsingSavedAddress = computed(() => savedAddress.value && form.address === savedAddress.value);
const selectedWorkerService = computed(() => worker.value?.services.find((workerService) => String(workerService.service_id) === String(form.service_id)));
const visibleReviews = computed(() => workersStore.reviews.slice(0, 3));
const durationOptions = computed(() => {
    const minimumHours = selectedWorkerService.value?.pricing_type === 'hourly'
        ? Number(selectedWorkerService.value.minimum_hours || 1)
        : 1;

    if (selectedWorkerService.value?.pricing_type === 'hourly') {
        return [{
            id: minimumHours * 60,
            name: `${minimumHours} ${minimumHours === 1 ? 'hour' : 'hours'}`,
        }];
    }

    return [
        minimumHours,
        Math.max(minimumHours + 1, 2),
        Math.max(minimumHours + 2, 3),
        Math.max(minimumHours + 3, 4),
    ]
        .filter((hours, index, values) => values.indexOf(hours) === index)
        .map((hours) => ({
            id: hours * 60,
            name: `${hours} ${hours === 1 ? 'hour' : 'hours'}`,
        }));
});
const slotMinutes = computed(() => Number(form.duration_minutes || durationOptions.value[0]?.id || 60));
const availableSlots = computed(() => workersStore.availability.filter((slot) => slot.available));
const blockedSlots = computed(() => workersStore.availability.filter((slot) => !slot.available));
const selectedSlot = computed(() => workersStore.availability.find((slot) => slot.start_time === form.start_time && slot.end_time === form.end_time));
const selectedSlotLabel = computed(() => {
    if (! form.start_time || ! form.end_time) {
        return 'Choose an available slot';
    }

    return `${form.start_time} - ${form.end_time}`;
});
const bookingError = computed(() => firstError(['start_time', 'end_time', 'service_id', 'worker_id', 'booking_date', 'address']));
const slotGroups = computed(() => {
    const groups = [
        { key: 'morning', title: 'Morning', icon: 'pi-sun', slots: [] },
        { key: 'afternoon', title: 'Afternoon', icon: 'pi-clock', slots: [] },
        { key: 'evening', title: 'Evening', icon: 'pi-moon', slots: [] },
    ];

    workersStore.availability.forEach((slot) => {
        const hour = Number(String(slot.start_time || slot.time).slice(0, 2));
        const group = hour < 12 ? groups[0] : hour < 17 ? groups[1] : groups[2];
        group.slots.push(slot);
    });

    return groups.filter((group) => group.slots.length);
});
const availableSlotGroups = computed(() => slotGroups.value
    .map((group) => ({
        ...group,
        slots: group.slots.filter((slot) => slot.available),
    }))
    .filter((group) => group.slots.length));
const blockedSlotSummary = computed(() => slotGroups.value
    .map((group) => ({
        key: group.key,
        title: group.title,
        count: group.slots.filter((slot) => !slot.available).length,
    }))
    .filter((group) => group.count > 0));
const slotSummary = computed(() => {
    if (! selectedWorkerService.value) {
        return 'Select a service to load accurate slot pricing.';
    }

    const pricing = selectedWorkerService.value.pricing_type === 'hourly'
        ? `₹${selectedWorkerService.value.price}/hr`
        : `₹${selectedWorkerService.value.price} fixed`;

    return `${formatDuration(slotMinutes.value)} slot · ${pricing}`;
});
const requestReferenceHint = computed(() => {
    if (! worker.value?.id || ! form.booking_date) {
        return 'A request reference appears right after you submit.';
    }

    return `A request reference will be generated for ${worker.value.name} after submission.`;
});

async function submitBooking() {
    if (bookingsStore.saving) {
        return;
    }

    clearApiErrors();
    clearValidationErrors();

    const isValid = await validateWithSchema(form);

    if (! isValid) {
        toast.error('Please fix the highlighted booking request fields.');

        return;
    }

    try {
        const response = await bookingsStore.create({
            ...form,
            worker_id: Number(route.params.id),
        });
        toast.success(response.message || 'Booking request sent');
        await router.push(`/customer/bookings/${response.data.booking.id}`);
    } catch (error) {
        setApiError(error);
        toast.error(firstErrorFromResponse(error) || error.response?.data?.message || 'Unable to create booking');
    }
}

function applyBookAgainPrefill() {
    if (! route.query.book_again_from) {
        return;
    }

    const prefill = storedBookAgainPrefill(route.query.book_again_from) || route.query;
    const serviceId = String(prefill.service_id || '');
    const hasService = worker.value?.services?.some((workerService) => String(workerService.service_id) === serviceId);

    if (hasService) {
        form.service_id = serviceId;
    }

    form.source_booking_id = route.query.book_again_from || '';
    form.booking_date = prefill.booking_date || form.booking_date;
    form.duration_minutes = Number(prefill.duration_minutes || form.duration_minutes);
    form.start_time = prefill.start_time || '';
    form.end_time = prefill.end_time || '';
    form.address = prefill.address || form.address;
    form.issue_description = prefill.issue_description || form.issue_description;
}

function storedBookAgainPrefill(sourceBookingId) {
    const storedPrefill = sessionStorage.getItem(`book-again:${sourceBookingId}`);

    if (! storedPrefill) {
        return null;
    }

    try {
        return JSON.parse(storedPrefill);
    } catch {
        return null;
    }
}

function firstError(fields) {
    for (const field of fields) {
        const fieldErrors = errors.value[field];

        if (Array.isArray(fieldErrors) && fieldErrors.length) {
            return fieldErrors[0];
        }
    }

    return '';
}

function firstErrorFromResponse(error) {
    const responseErrors = error.response?.data?.errors || {};
    const firstField = Object.keys(responseErrors)[0];

    return firstField && Array.isArray(responseErrors[firstField]) ? responseErrors[firstField][0] : '';
}

function useSavedAddress() {
    if (! savedAddress.value) {
        return;
    }

    form.address = savedAddress.value;
}

function useDifferentAddress() {
    form.address = '';
}

function selectSlot(slot) {
    if (! slot.available) {
        return;
    }

    form.start_time = slot.start_time || slot.time;
    form.end_time = slot.end_time;
}

function selectDuration(minutes) {
    form.duration_minutes = minutes;
}

function formatDuration(minutes) {
    const hours = Number(minutes) / 60;

    return `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
}

async function refreshAvailability(options = {}) {
    if (! form.booking_date || ! route.params.id) {
        return;
    }

    const selectedStartTime = form.start_time;
    const selectedEndTime = form.end_time;

    if (! options.keepSelection) {
        form.start_time = '';
        form.end_time = '';
    }

    try {
        await workersStore.fetchAvailability(route.params.id, {
            available_date: form.booking_date,
            slot_minutes: slotMinutes.value,
            service_id: form.service_id,
        });

        if (options.keepSelection) {
            const matchingSlot = workersStore.availability.find((slot) => slot.available && String(slot.start_time).slice(0, 5) === String(selectedStartTime).slice(0, 5) && String(slot.end_time).slice(0, 5) === String(selectedEndTime).slice(0, 5));

            form.start_time = matchingSlot ? String(selectedStartTime).slice(0, 5) : '';
            form.end_time = matchingSlot ? String(selectedEndTime).slice(0, 5) : '';
        }
    } catch {
        toast.error('Unable to load available slots');
    }
}

onMounted(async () => {
    try {
        await workersStore.fetchWorker(route.params.id, {
            available_date: localDateString(),
        });
        await workersStore.fetchWorkerReviews(route.params.id);
        await authStore.refreshUser();
        form.service_id = worker.value?.services?.[0]?.service_id || '';
        form.duration_minutes = durationOptions.value[0]?.id || 60;
        useSavedAddress();
        applyBookAgainPrefill();
        await refreshAvailability({ keepSelection: Boolean(route.query.book_again_from) });
    } catch {
        toast.error('Unable to load worker details');
    }
});

watch(
    () => [form.booking_date, form.service_id, form.duration_minutes],
    () => {
        clearValidationErrors(['booking_date', 'service_id', 'start_time']);

        if (durationOptions.value.length && ! durationOptions.value.some((option) => Number(option.id) === Number(form.duration_minutes))) {
            form.duration_minutes = durationOptions.value[0].id;
            return;
        }

        refreshAvailability();
    },
);

watch(() => form.address, () => clearValidationErrors('address'));
watch(() => form.issue_description, () => clearValidationErrors('issue_description'));
</script>

<template>
    <DashboardLayout title="Worker Details">
        <div v-if="workersStore.detailLoading" class="space-y-5">
            <SkeletonCard :lines="5" />
            <div class="grid gap-5 lg:grid-cols-[1fr_380px]">
                <SkeletonCard :lines="6" :avatar="false" />
                <SkeletonCard :lines="4" :avatar="false" />
            </div>
        </div>

        <div v-else-if="worker" class="space-y-5" data-testid="worker-detail-page">
            <RouterLink to="/customer/workers" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                <i class="pi pi-arrow-left text-xs" aria-hidden="true"></i>
                Back to workers
            </RouterLink>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-5 sm:flex-row">
                    <div class="flex size-28 items-center justify-center overflow-hidden rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-950 dark:text-gray-500">
                        <img v-if="worker.profile?.profile_photo_url" :src="worker.profile.profile_photo_url" :alt="worker.name" class="size-full object-cover">
                        <i v-else class="pi pi-user text-4xl" aria-hidden="true"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ worker.name }}</h2>
                                    <span
                                        v-if="worker.profile?.is_verified"
                                        class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"
                                    >
                                        <i class="pi pi-verified text-[11px]" aria-hidden="true"></i>
                                        Verified worker
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ worker.profile?.city }} · {{ worker.profile?.experience_years }} years experience</p>
                            </div>
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-sm font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                {{ worker.rating_average.toFixed(1) }} ★ · {{ worker.reviews_count }} reviews
                            </span>
                        </div>
                        <p class="mt-4 text-sm text-gray-700 dark:text-gray-300">{{ worker.profile?.bio || 'No bio added yet.' }}</p>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ worker.profile?.address }}</p>
                    </div>
                </div>
            </section>

            <section>
                <form class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="submitBooking">
                    <div class="bg-blue-50 p-5 dark:bg-blue-500/10">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-medium text-blue-600 dark:text-blue-300">Book this worker</p>
                                <h3 class="mt-1 font-semibold text-gray-900 dark:text-white">Quick booking</h3>
                                <p class="mt-1 text-sm text-blue-700 dark:text-blue-200">Choose a service, pick a time, and send the request.</p>
                            </div>
                            <span class="inline-flex w-fit rounded-full bg-white px-3 py-1 text-xs font-semibold text-blue-700 shadow-sm ring-1 ring-blue-100 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-400/20">
                                {{ slotMinutes }} min
                            </span>
                        </div>
                    </div>

                    <div class="space-y-5 p-5">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="inline-flex size-7 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">Choose a service</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Tap one option to set the job type and price.</p>
                                </div>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <button
                                    v-for="service in worker.services"
                                    :key="service.id"
                                    type="button"
                                    class="rounded-xl border p-4 text-left shadow-sm transition"
                                    :class="String(form.service_id) === String(service.service_id)
                                        ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-200 dark:border-blue-400 dark:bg-blue-500/10 dark:ring-blue-400/20'
                                        : 'border-gray-200 bg-white hover:border-blue-300 hover:bg-blue-50/60 dark:border-white/10 dark:bg-gray-950 dark:hover:border-blue-400/30 dark:hover:bg-blue-500/10'"
                                    @click="form.service_id = service.service_id"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ service.service?.name }}</p>
                                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ service.description || service.service?.slug }}</p>
                                        </div>
                                        <i v-if="String(form.service_id) === String(service.service_id)" class="pi pi-check-circle text-blue-600 dark:text-blue-300" aria-hidden="true"></i>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-gray-900 dark:text-white">
                                        ₹{{ service.price }} <span class="font-normal text-gray-500">{{ service.pricing_type === 'hourly' ? '/hr' : 'fixed' }}</span>
                                    </p>
                                </button>
                            </div>
                            <p v-if="(validationErrors.service_id || errors.service_id)?.length" class="mt-2 text-sm text-red-600 dark:text-red-400">{{ (validationErrors.service_id || errors.service_id)[0] }}</p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-gray-950/70">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex size-7 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">Pick date and duration</h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Only matching slots will stay available.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <FormInput id="slot_booking_date" v-model="form.booking_date" label="Date" type="date" :min="localDateString()" :max="currentLocalLatestBookingDateString()" :error="validationErrors.booking_date || errors.booking_date || []" data-testid="booking-date-input" />
                                <div>
                                    <p class="block text-sm font-medium text-gray-700 dark:text-gray-200">Duration</p>
                                    <div class="mt-1 grid grid-cols-1 gap-2 min-[360px]:grid-cols-2">
                                        <button
                                            v-for="option in durationOptions"
                                            :key="option.id"
                                            type="button"
                                            data-testid="duration-option-button"
                                            class="rounded-md border px-3 py-2 text-sm font-semibold shadow-sm transition"
                                            :class="Number(form.duration_minutes) === Number(option.id)
                                                ? 'border-blue-600 bg-blue-600 text-white shadow-blue-600/20 dark:border-blue-400 dark:bg-blue-500'
                                                : 'border-blue-100 bg-white text-gray-700 hover:border-blue-300 hover:bg-blue-50 dark:border-white/10 dark:bg-gray-950 dark:text-gray-200 dark:hover:bg-blue-500/10'"
                                            @click="selectDuration(option.id)"
                                        >
                                            {{ option.name }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedWorkerService" class="rounded-lg border border-blue-100 bg-white p-3 text-sm text-gray-700 shadow-sm dark:border-blue-500/20 dark:bg-gray-950 dark:text-gray-200">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="inline-flex items-center gap-2 font-semibold text-gray-900 dark:text-white">
                                    <i class="pi pi-tag text-blue-600 dark:text-blue-300" aria-hidden="true"></i>
                                    {{ selectedWorkerService.pricing_type === 'hourly' ? 'Hourly price' : 'Fixed price' }}
                                </span>
                                <span>
                                    ₹{{ selectedWorkerService.price }}{{ selectedWorkerService.pricing_type === 'hourly' ? '/hr' : '' }}
                                    <template v-if="selectedWorkerService.pricing_type === 'hourly'">
                                        · Required {{ selectedWorkerService.minimum_hours || 1 }}h
                                    </template>
                                </span>
                            </div>
                        </div>
                        <p v-else class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                            Pick a service above to continue.
                        </p>

                        <p class="rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                            {{ slotSummary }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2 rounded-lg border p-3 text-sm" :class="selectedSlot ? 'border-blue-100 bg-blue-50 text-blue-800 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-200' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200'">
                            <span class="inline-flex items-center gap-2 font-semibold">
                                <i :class="['pi', selectedSlot ? 'pi-check-circle' : 'pi-clock']" aria-hidden="true"></i>
                                {{ selectedWorkerService?.service?.name || 'Choose a service' }}
                            </span>
                            <span class="hidden text-blue-300/40 dark:text-blue-200/30 sm:inline">•</span>
                            <span>{{ selectedSlotLabel }}</span>
                            <span v-if="selectedSlot" class="hidden text-blue-300/40 dark:text-blue-200/30 sm:inline">•</span>
                            <span v-if="selectedSlot" class="font-semibold">₹{{ selectedSlot.estimated_total ?? selectedWorkerService?.price }}</span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ requestReferenceHint }}</p>
                        <p v-if="errors.start_time?.length" class="text-sm text-red-600 dark:text-red-300">{{ errors.start_time[0] }}</p>
                        <p v-if="errors.end_time?.length" class="text-sm text-red-600 dark:text-red-300">{{ errors.end_time[0] }}</p>
                        <p v-if="validationErrors.start_time?.length || bookingError" class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-200">
                            {{ validationErrors.start_time?.[0] || bookingError }}
                        </p>
                    </div>

                    <div class="border-t border-gray-100 p-5 dark:border-white/10">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="inline-flex size-7 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">3</span>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Choose a time slot</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Only available times can be selected.</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 min-[420px]:flex-row min-[420px]:items-center min-[420px]:justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Available slots</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pick one available time below.</p>
                            </div>
                            <span class="inline-flex w-fit shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                {{ availableSlots.length }} open
                            </span>
                        </div>

                    <div v-if="workersStore.availabilityLoading" class="mt-4 grid grid-cols-2 gap-2">
                        <span v-for="item in 6" :key="item" class="h-9 animate-pulse rounded-md bg-gray-100 dark:bg-white/10"></span>
                    </div>

                    <div v-else class="mt-4 space-y-4">
                        <div v-for="group in availableSlotGroups" :key="group.key" class="rounded-lg border border-gray-100 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950">
                            <div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <i :class="['pi', group.icon]" aria-hidden="true"></i>
                                {{ group.title }}
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="slot in group.slots"
                                    :key="`${slot.start_time}-${slot.end_time}`"
                                    type="button"
                                    data-testid="available-slot-button"
                                    :class="[
                                        'rounded-lg border px-3 py-2 text-left text-sm font-semibold shadow-sm transition',
                                        form.start_time === slot.start_time
                                            ? 'border-blue-600 bg-blue-600 text-white shadow-blue-600/20 dark:border-blue-400 dark:bg-blue-500'
                                            : '',
                                        form.start_time !== slot.start_time
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:hover:bg-emerald-500/20'
                                            : '',
                                    ]"
                                    @click="selectSlot(slot)"
                                >
                                    <span class="flex items-center justify-between gap-2">
                                        <span>{{ slot.start_time }}</span>
                                        <i v-if="form.start_time === slot.start_time" class="pi pi-check-circle text-xs" aria-hidden="true"></i>
                                    </span>
                                    <span class="mt-1 block text-[11px] font-medium opacity-75">
                                        {{ slot.end_time }}
                                    </span>
                                </button>
                            </div>
                        </div>
                        <div v-if="blockedSlotSummary.length" class="rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:border-white/10 dark:bg-gray-950 dark:text-gray-400">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-gray-700 dark:text-gray-200">Unavailable:</span>
                                <span v-for="group in blockedSlotSummary" :key="group.key" class="inline-flex rounded-full bg-white px-2.5 py-1 dark:bg-white/5">
                                    {{ group.title }} {{ group.count }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-if="!workersStore.availabilityLoading && workersStore.availability.length === 0" class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                        This worker is not scheduled for the selected date or duration.
                    </p>
                    <p v-else-if="!workersStore.availabilityLoading" class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ availableSlots.length }} available · {{ blockedSlots.length }} booked/reserved
                    </p>
                    </div>
                    <div class="border-t border-gray-100 p-5 dark:border-white/10">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="inline-flex size-7 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">4</span>
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">Confirm address and note</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Add the location details the worker needs before arrival.</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <div class="mb-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <label for="booking_address" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Service address</label>
                                    <RouterLink to="/customer/profile" class="inline-flex w-fit items-center gap-1 text-xs font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">
                                        <i class="pi pi-user-edit" aria-hidden="true"></i>
                                        Update profile address
                                    </RouterLink>
                                </div>

                                <div v-if="savedAddress" class="mb-3 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-gray-950">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Saved address</p>
                                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">{{ savedAddress }}</p>
                                        </div>
                                        <div class="flex shrink-0 flex-col gap-2 min-[420px]:flex-row">
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-xs font-semibold transition"
                                                :class="isUsingSavedAddress ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-950' : 'border border-gray-300 text-gray-700 hover:bg-white dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10'"
                                                @click="useSavedAddress"
                                            >
                                                <i class="pi pi-check" aria-hidden="true"></i>
                                                Use saved
                                            </button>
                                            <button
                                                type="button"
                                                class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-white dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/10"
                                                @click="useDifferentAddress"
                                            >
                                                Different
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200">
                                    Save a default address in your profile to book faster next time.
                                </div>

                                <textarea id="booking_address" v-model="form.address" rows="2" data-testid="booking-address-input" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"></textarea>
                                <p v-if="(validationErrors.address || errors.address)?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ (validationErrors.address || errors.address)[0] }}</p>
                            </div>

                            <div>
                                <label for="booking_issue" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Issue note</label>
                                <textarea id="booking_issue" v-model="form.issue_description" rows="2" data-testid="booking-issue-input" placeholder="Short issue summary" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"></textarea>
                                <p v-if="(validationErrors.issue_description || errors.issue_description)?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ (validationErrors.issue_description || errors.issue_description)[0] }}</p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-4 dark:border-white/10">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Open requests can still be cancelled before final confirmation.</p>
                            <AppButton type="submit" icon="pi-send" :loading="bookingsStore.saving" :disabled="!selectedSlot" data-testid="booking-submit-button">{{ bookingsStore.saving ? 'Sending...' : 'Send request' }}</AppButton>
                        </div>
                    </div>
                </form>
            </section>

            <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Reviews</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Customer feedback from completed bookings.</p>
                    </div>
                    <div class="text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">{{ workersStore.reviewSummary.average.toFixed(1) }}</span>
                        average · {{ workersStore.reviewSummary.count }} reviews
                    </div>
                </div>

                <div v-if="workersStore.reviews.length === 0" class="mt-4 rounded-md bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                    No reviews yet.
                </div>

                <div v-else class="mt-4 divide-y divide-gray-100 dark:divide-white/10">
                    <article v-for="review in visibleReviews" :key="review.id" class="py-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ review.customer?.name || 'Customer' }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ review.booking?.service?.name }}</p>
                            </div>
                            <RatingStars :model-value="review.rating" readonly />
                        </div>
                        <p v-if="review.review" class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ review.review }}</p>
                    </article>
                </div>
                <p v-if="workersStore.reviews.length > visibleReviews.length" class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                    Showing the latest {{ visibleReviews.length }} reviews.
                </p>
            </section>

        </div>
    </DashboardLayout>
</template>
