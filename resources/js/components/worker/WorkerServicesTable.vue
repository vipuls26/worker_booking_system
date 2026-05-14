<script setup>
import SkeletonList from '../common/SkeletonList.vue';
import SkeletonTableRows from '../common/SkeletonTableRows.vue';
import StatusBadge from '../common/StatusBadge.vue';

defineProps({
    workerServices: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['edit', 'reapply', 'delete']);

function visibilityStatus(workerService) {
    if (workerService.approval_status !== 'approved') {
        return false;
    }

    return Boolean(workerService.is_active && workerService.service?.is_active);
}

function serviceWarning(workerService) {
    if (workerService.service?.is_active === false) {
        return 'Admin has disabled this master service, so customers cannot book it right now.';
    }

    return '';
}

function editButtonLabel(workerService) {
    return workerService.approval_status === 'rejected' ? 'Reapply' : 'Edit';
}

function editButtonIcon(workerService) {
    return workerService.approval_status === 'rejected' ? 'pi-refresh' : 'pi-pencil';
}

function editEvent(workerService) {
    return workerService.approval_status === 'rejected' ? 'reapply' : 'edit';
}
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="divide-y divide-gray-100 dark:divide-white/10 md:hidden">
            <div v-if="loading" class="p-4">
                <SkeletonList :count="3" actions />
            </div>
            <div v-else-if="workerServices.length === 0" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No services added yet.</div>

            <template v-else>
                <article v-for="workerService in workerServices" :key="workerService.id" class="p-4" :data-testid="`worker-service-card-${workerService.id}`">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            <i :class="['pi', workerService.service?.icon || 'pi-briefcase']" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-900 dark:text-white">{{ workerService.service?.name || 'Service' }}</p>
                                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ workerService.service?.slug }}</p>
                                    <p v-if="serviceWarning(workerService)" class="mt-1 text-xs text-amber-600 dark:text-amber-300">{{ serviceWarning(workerService) }}</p>
                                </div>
                                <span
                                    class="inline-flex shrink-0"
                                ><StatusBadge :value="workerService.approval_status" /></span>
                            </div>
                            <p class="mt-2 text-sm font-medium text-gray-900 dark:text-white">₹{{ workerService.price }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ workerService.pricing_type === 'hourly' ? `Per hour, min ${workerService.minimum_hours}h` : 'Fixed price' }}
                            </p>
                            <p v-if="workerService.description" class="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ workerService.description }}
                            </p>
                            <p v-if="workerService.rejection_reason" class="mt-2 rounded-md bg-red-50 p-2 text-xs text-red-700 dark:bg-red-500/10 dark:text-red-300">
                                {{ workerService.rejection_reason }}
                            </p>
                            <p v-if="serviceWarning(workerService)" class="mt-2 rounded-md bg-amber-50 p-2 text-xs text-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                                {{ serviceWarning(workerService) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            :data-testid="`worker-service-edit-${workerService.id}`"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            :title="editButtonLabel(workerService)"
                            @click="$emit(editEvent(workerService), workerService)"
                        >
                            <i :class="['pi', editButtonIcon(workerService)]" aria-hidden="true"></i>
                            {{ editButtonLabel(workerService) }}
                        </button>
                        <button
                            type="button"
                            :data-testid="`worker-service-delete-${workerService.id}`"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-red-200 px-3 py-2 text-sm text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                            title="Delete"
                            @click="$emit('delete', workerService)"
                        >
                            <i class="pi pi-trash" aria-hidden="true"></i>
                            Delete
                        </button>
                    </div>
                </article>
            </template>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 lg:px-4 lg:py-3">Service</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 lg:px-4 lg:py-3">Pricing</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 lg:px-4 lg:py-3">Approval</th>
                        <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 lg:px-4 lg:py-3">Visibility</th>
                        <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 lg:px-4 lg:py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    <SkeletonTableRows v-if="loading" :columns="4" />
                    <tr v-else-if="workerServices.length === 0">
                        <td colspan="5" class="px-3 py-10 text-center text-sm text-gray-500 dark:text-gray-400 lg:px-4">No services added yet.</td>
                    </tr>
                    <template v-else>
                        <tr v-for="workerService in workerServices" :key="workerService.id" class="transition hover:bg-gray-50 dark:hover:bg-white/5" :data-testid="`worker-service-row-${workerService.id}`">
                            <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex size-9 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                        <i :class="['pi', workerService.service?.icon || 'pi-briefcase']" aria-hidden="true"></i>
                                    </span>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white">{{ workerService.service?.name || 'Service' }}</p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ workerService.service?.slug }}</p>
                                        <p v-if="serviceWarning(workerService)" class="mt-1 max-w-md text-sm text-amber-600 dark:text-amber-300">
                                            {{ serviceWarning(workerService) }}
                                        </p>
                                        <p v-if="workerService.description" class="mt-1 max-w-md truncate text-sm text-gray-500 dark:text-gray-400">
                                            {{ workerService.description }}
                                        </p>
                                        <p v-if="workerService.rejection_reason" class="mt-1 max-w-md truncate text-sm text-red-600 dark:text-red-300">
                                            {{ workerService.rejection_reason }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-sm text-gray-700 dark:text-gray-200 lg:px-4 lg:py-3">
                                <p class="font-medium">₹{{ workerService.price }}</p>
                                <p class="text-gray-500 dark:text-gray-400">
                                    {{ workerService.pricing_type === 'hourly' ? `Per hour, min ${workerService.minimum_hours}h` : 'Fixed price' }}
                                </p>
                            </td>
                            <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                                <StatusBadge :value="workerService.approval_status" />
                            </td>
                            <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                                <StatusBadge :value="visibilityStatus(workerService)" />
                            </td>
                            <td class="px-3 py-2.5 lg:px-4 lg:py-3">
                                <div class="flex justify-end gap-1.5 lg:gap-2">
                                    <button
                                        type="button"
                                        :data-testid="`worker-service-edit-${workerService.id}`"
                                        class="inline-flex size-9 items-center justify-center rounded-md border border-gray-300 text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                        :title="editButtonLabel(workerService)"
                                        @click="$emit(editEvent(workerService), workerService)"
                                    >
                                        <i :class="['pi', editButtonIcon(workerService)]" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        type="button"
                                        :data-testid="`worker-service-delete-${workerService.id}`"
                                        class="inline-flex size-9 items-center justify-center rounded-md border border-red-200 text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                        title="Delete"
                                        @click="$emit('delete', workerService)"
                                    >
                                        <i class="pi pi-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
