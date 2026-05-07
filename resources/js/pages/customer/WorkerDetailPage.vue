<script setup>
import { computed, onMounted, reactive, watch } from 'vue';
import { useRoute, RouterLink, useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import RatingStars from '../../components/common/RatingStars.vue';
import SkeletonCard from '../../components/common/SkeletonCard.vue';
import FormInput from '../../components/forms/FormInput.vue';
import FormSelect from '../../components/forms/FormSelect.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useCustomerBookingsStore } from '../../stores/customer/bookings';
import { useCustomerWorkersStore } from '../../stores/customer/workers';

const route = useRoute();
const router = useRouter();
const workersStore = useCustomerWorkersStore();
const bookingsStore = useCustomerBookingsStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const worker = computed(() => workersStore.worker);
const form = reactive({
    service_id: '',
    booking_date: new Date().toISOString().slice(0, 10),
    start_time: '',
    end_time: '',
    address: '',
    issue_description: '',
});
const serviceOptions = computed(() => worker.value?.services.map((workerService) => ({
    id: workerService.service_id,
    name: `${workerService.service?.name} - ₹${workerService.price}${workerService.pricing_type === 'hourly' ? '/hr' : ''}`,
})) || []);
const selectedWorkerService = computed(() => worker.value?.services.find((workerService) => workerService.service_id === form.service_id));
const slotMinutes = computed(() => {
    if (selectedWorkerService.value?.pricing_type === 'hourly') {
        return Number(selectedWorkerService.value.minimum_hours || 1) * 60;
    }

    return 60;
});
const selectedSlotLabel = computed(() => {
    if (! form.start_time || ! form.end_time) {
        return 'Choose an available slot';
    }

    return `${form.start_time} - ${form.end_time}`;
});

async function submitBooking() {
    clearApiErrors();

    try {
        const response = await bookingsStore.create({
            ...form,
            worker_id: worker.value.id,
        });
        toast.success(response.message || 'Booking request sent');
        await router.push(`/customer/bookings/${response.data.booking.id}`);
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to create booking');
    }
}

function addMinutes(time, minutes) {
    const [hours, mins] = time.split(':').map(Number);
    const date = new Date();
    date.setHours(hours, mins + minutes, 0, 0);

    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}

function selectSlot(slot) {
    if (! slot.available) {
        return;
    }

    form.start_time = slot.time;
    form.end_time = addMinutes(slot.time, slotMinutes.value);
}

async function refreshAvailability() {
    if (! form.booking_date || ! route.params.id) {
        return;
    }

    form.start_time = '';
    form.end_time = '';

    try {
        await workersStore.fetchAvailability(route.params.id, {
            available_date: form.booking_date,
            slot_minutes: slotMinutes.value,
        });
    } catch {
        toast.error('Unable to load available slots');
    }
}

onMounted(async () => {
    try {
        await workersStore.fetchWorker(route.params.id, {
            available_date: new Date().toISOString().slice(0, 10),
        });
        await workersStore.fetchWorkerReviews(route.params.id);
        form.service_id = worker.value?.services?.[0]?.service_id || '';
        await refreshAvailability();
    } catch {
        toast.error('Unable to load worker details');
    }
});

watch(
    () => [form.booking_date, form.service_id],
    () => {
        refreshAvailability();
    },
);
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

        <div v-else-if="worker" class="space-y-5">
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
                                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ worker.name }}</h2>
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

            <section class="grid gap-5 lg:grid-cols-[1fr_380px]">
                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Services</h3>
                    <div class="mt-4 divide-y divide-gray-100 dark:divide-white/10">
                        <article v-for="service in worker.services" :key="service.id" class="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ service.service?.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ service.description || service.service?.slug }}</p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                ₹{{ service.price }} <span class="font-normal text-gray-500">{{ service.pricing_type === 'hourly' ? '/hr' : 'fixed' }}</span>
                            </p>
                        </article>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Available slots</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Slots match the selected service duration, so there is no guess work.</p>
                        </div>
                        <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            {{ slotMinutes }} min
                        </span>
                    </div>

                    <FormInput id="slot_booking_date" v-model="form.booking_date" class="mt-4" label="Date" type="date" :error="errors.booking_date" />

                    <div v-if="workersStore.availabilityLoading" class="mt-4 grid grid-cols-2 gap-2">
                        <span v-for="item in 6" :key="item" class="h-9 animate-pulse rounded-md bg-gray-100 dark:bg-white/10"></span>
                    </div>

                    <div v-else class="mt-4 grid grid-cols-2 gap-2">
                        <button
                            v-for="slot in workersStore.availability"
                            :key="slot.time"
                            type="button"
                            :disabled="!slot.available"
                            :class="[
                                'rounded-md px-3 py-2 text-center text-sm font-semibold transition disabled:cursor-not-allowed',
                                slot.available && form.start_time === slot.time
                                    ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-950'
                                    : '',
                                slot.available && form.start_time !== slot.time
                                    ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:hover:bg-emerald-500/20'
                                    : '',
                                !slot.available
                                    ? 'bg-gray-100 text-gray-500 line-through dark:bg-white/10 dark:text-gray-400'
                                    : '',
                            ]"
                            @click="selectSlot(slot)"
                        >
                            {{ slot.time }}
                        </button>
                    </div>
                    <p v-if="!workersStore.availabilityLoading && workersStore.availability.length === 0" class="mt-3 text-sm text-gray-500 dark:text-gray-400">No slots available for this date and service duration.</p>
                    <p class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                        Selected: <span class="font-semibold text-gray-900 dark:text-white">{{ selectedSlotLabel }}</span>
                    </p>
                </div>
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
                    <article v-for="review in workersStore.reviews" :key="review.id" class="py-4">
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
            </section>

            <form class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10" @submit.prevent="submitBooking">
                <h3 class="font-semibold text-gray-900 dark:text-white">Send booking request</h3>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <FormSelect id="booking_service" v-model="form.service_id" label="Service" :options="serviceOptions" :error="errors.service_id" />
                    <FormInput id="booking_start" v-model="form.start_time" label="Start time" type="time" :error="errors.start_time" />
                    <FormInput id="booking_end" v-model="form.end_time" label="End time" type="time" :error="errors.end_time" />
                </div>
                <div class="mt-4 grid gap-4">
                    <div>
                        <label for="booking_address" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Address</label>
                        <textarea id="booking_address" v-model="form.address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"></textarea>
                        <p v-if="errors.address?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.address[0] }}</p>
                    </div>
                    <div>
                        <label for="booking_issue" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Issue description</label>
                        <textarea id="booking_issue" v-model="form.issue_description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"></textarea>
                        <p v-if="errors.issue_description?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.issue_description[0] }}</p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end">
                    <div class="w-full sm:w-auto">
                        <AppButton type="submit" icon="pi-send" :loading="bookingsStore.saving">{{ bookingsStore.saving ? 'Sending...' : 'Send booking request' }}</AppButton>
                    </div>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
