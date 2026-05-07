<script setup>
import SkeletonList from '../common/SkeletonList.vue';
import SkeletonTableRows from '../common/SkeletonTableRows.vue';
import StatusBadge from '../common/StatusBadge.vue';

defineProps({
    services: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['edit', 'delete', 'toggle']);
</script>

<template>
    <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="divide-y divide-gray-100 dark:divide-white/10 md:hidden">
            <div v-if="loading" class="p-4">
                <SkeletonList :count="3" />
            </div>

            <div v-else-if="services.length === 0" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                No service categories found.
            </div>

            <template v-else>
                <article v-for="service in services" :key="service.id" class="p-4">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            <i :class="['pi', service.icon || 'pi-briefcase']" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-900 dark:text-white">{{ service.name }}</p>
                                    <p class="truncate text-sm text-gray-500 dark:text-gray-400">{{ service.slug }}</p>
                                </div>
                                <StatusBadge :value="service.is_active" />
                            </div>
                            <p v-if="service.description" class="mt-2 line-clamp-2 text-sm text-gray-500 dark:text-gray-400">
                                {{ service.description }}
                            </p>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                Created by {{ service.creator?.name || 'System' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-3 gap-2">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            :title="service.is_active ? 'Deactivate' : 'Activate'"
                            @click="$emit('toggle', service)"
                        >
                            <i :class="['pi', service.is_active ? 'pi-eye-slash' : 'pi-eye']" aria-hidden="true"></i>
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            title="Edit"
                            @click="$emit('edit', service)"
                        >
                            <i class="pi pi-pencil" aria-hidden="true"></i>
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-red-200 px-3 py-2 text-sm text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                            title="Delete"
                            @click="$emit('delete', service)"
                        >
                            <i class="pi pi-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </article>
            </template>
        </div>

        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Service</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Icon</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Created By</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    <SkeletonTableRows v-if="loading" :columns="4" />

                    <tr v-else-if="services.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                            No service categories found.
                        </td>
                    </tr>

                    <template v-else>
                        <tr v-for="service in services" :key="service.id" class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900 dark:text-white">{{ service.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ service.slug }}</p>
                                <p v-if="service.description" class="mt-1 max-w-md truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ service.description }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex size-9 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                    <i :class="['pi', service.icon || 'pi-briefcase']" aria-hidden="true"></i>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge :value="service.is_active" />
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                {{ service.creator?.name || 'System' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-md border border-gray-300 text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                        :title="service.is_active ? 'Deactivate' : 'Activate'"
                                        @click="$emit('toggle', service)"
                                    >
                                        <i :class="['pi', service.is_active ? 'pi-eye-slash' : 'pi-eye']" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-md border border-gray-300 text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                                        title="Edit"
                                        @click="$emit('edit', service)"
                                    >
                                        <i class="pi pi-pencil" aria-hidden="true"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex size-9 items-center justify-center rounded-md border border-red-200 text-red-600 transition hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                        title="Delete"
                                        @click="$emit('delete', service)"
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
