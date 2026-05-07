<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppButton from '../../components/common/AppButton.vue';
import AppPanel from '../../components/common/AppPanel.vue';
import ConfirmDialog from '../../components/common/ConfirmDialog.vue';
import WorkerScheduleFormModal from '../../components/worker/WorkerScheduleFormModal.vue';
import WorkerScheduleList from '../../components/worker/WorkerScheduleList.vue';
import { useApiErrors } from '../../composables/useApiErrors';
import DashboardLayout from '../../layouts/DashboardLayout.vue';
import { useWorkerSchedulesStore } from '../../stores/worker/schedules';

const schedulesStore = useWorkerSchedulesStore();
const { errors, setApiError, clearApiErrors } = useApiErrors();
const modalOpen = ref(false);
const editing = ref(null);
const deleting = ref(null);
const selectedDay = ref('');

const workingWindows = computed(() => schedulesStore.schedules.filter((schedule) => !schedule.is_off_day).length);
const offDays = computed(() => schedulesStore.schedules.filter((schedule) => schedule.is_off_day).length);
const configuredDays = computed(() => new Set(schedulesStore.schedules.map((schedule) => schedule.day_of_week)).size);
const hasScheduleGaps = computed(() => configuredDays.value < 7);

const scheduleStats = computed(() => [
    {
        label: 'Configured days',
        value: `${configuredDays.value}/7`,
        icon: 'pi-calendar',
    },
    {
        label: 'Working windows',
        value: workingWindows.value,
        icon: 'pi-clock',
    },
    {
        label: 'Weekly off days',
        value: offDays.value,
        icon: 'pi-ban',
    },
]);

async function load() {
    try {
        await schedulesStore.fetch();
    } catch {
        toast.error('Unable to load schedules');
    }
}

function openCreateModal() {
    clearApiErrors();
    editing.value = null;
    selectedDay.value = '';
    modalOpen.value = true;
}

function openCreateForDay(day) {
    clearApiErrors();
    editing.value = null;
    selectedDay.value = day;
    modalOpen.value = true;
}

function openEditModal(schedule) {
    clearApiErrors();
    editing.value = schedule;
    selectedDay.value = '';
    modalOpen.value = true;
}

function closeModal() {
    clearApiErrors();
    editing.value = null;
    selectedDay.value = '';
    modalOpen.value = false;
}

async function saveSchedule(payload) {
    clearApiErrors();

    try {
        const response = editing.value
            ? await schedulesStore.update(editing.value.id, payload)
            : await schedulesStore.create(payload);

        toast.success(response.message || 'Schedule saved');
        closeModal();
        await load();
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to save schedule');
    }
}

async function markOffDay(day) {
    clearApiErrors();

    try {
        const response = await schedulesStore.create({
            day_of_week: day,
            start_time: null,
            end_time: null,
            is_off_day: true,
        });

        toast.success(response.message || 'Off day saved');
        await load();
    } catch (error) {
        setApiError(error);
        toast.error(error.response?.data?.message || 'Unable to mark off day');
    }
}

async function confirmDelete() {
    try {
        const response = await schedulesStore.delete(deleting.value.id);
        toast.success(response.message || 'Schedule deleted');
        deleting.value = null;
        await load();
    } catch {
        toast.error('Unable to delete schedule');
    }
}

onMounted(async () => {
    await load();
});
</script>

<template>
    <DashboardLayout title="Worker Availability">
        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Availability</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Plan weekly working windows and mark off days so customers see accurate availability.</p>
                </div>
                <AppButton type="button" icon="pi-plus" :full-width="false" @click="openCreateModal">Add window</AppButton>
            </div>

            <div class="grid gap-3 sm:grid-cols-3">
                <AppPanel v-for="stat in scheduleStats" :key="stat.label" class="flex items-center gap-3">
                    <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                        <i :class="['pi', stat.icon]" aria-hidden="true"></i>
                    </span>
                    <span>
                        <span class="block text-sm text-gray-500 dark:text-gray-400">{{ stat.label }}</span>
                        <span class="mt-1 block text-xl font-semibold text-gray-900 dark:text-white">{{ stat.value }}</span>
                    </span>
                </AppPanel>
            </div>

            <AppPanel v-if="hasScheduleGaps" class="border border-amber-200 bg-amber-50 text-amber-900 ring-0 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
                <div class="flex gap-3">
                    <i class="pi pi-exclamation-triangle mt-0.5 text-amber-600 dark:text-amber-300" aria-hidden="true"></i>
                    <div>
                        <p class="text-sm font-semibold">Some days are not configured</p>
                        <p class="mt-1 text-sm">Set working windows or mark off days for all seven days so customers see accurate availability.</p>
                    </div>
                </div>
            </AppPanel>

            <WorkerScheduleList
                :schedules="schedulesStore.schedules"
                :loading="schedulesStore.loading"
                @edit="openEditModal"
                @delete="deleting = $event"
                @add-day="openCreateForDay"
                @mark-off-day="markOffDay"
            />
        </div>

        <WorkerScheduleFormModal
            :open="modalOpen"
            :schedule="editing"
            :initial-day="selectedDay"
            :errors="errors"
            :loading="schedulesStore.saving"
            @close="closeModal"
            @submit="saveSchedule"
        />

        <ConfirmDialog
            :open="Boolean(deleting)"
            title="Delete schedule"
            :message="`Remove ${deleting?.day_name || 'this'} schedule?`"
            @cancel="deleting = null"
            @confirm="confirmDelete"
        />
    </DashboardLayout>
</template>
