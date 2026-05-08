<script setup>
defineProps({
    value: {
        type: [String, Boolean],
        required: true,
    },
});
</script>

<template>
    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium capitalize" :class="{
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300': value === true || value === 'active' || value === 'approved' || value === 'completed' || value === 'paid',
        'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300': value === false || value === 'blocked' || value === 'rejected' || value === 'cancelled' || value === 'auto_cancelled' || value === 'not_selected',
        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300': value === 'pending' || value === 'requested' || value === 'open',
        'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300': value === 'accepted' || value === 'confirmed' || value === 'in_progress' || value === 'selected' || value === 'worker_selected',
    }">
        {{ typeof value === 'boolean' ? (value ? 'active' : 'inactive') : value.replace('_', ' ') }}
    </span>
</template>
