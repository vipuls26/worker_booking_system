<script setup>
import { computed, reactive, watch } from 'vue';
import AppButton from '../common/AppButton.vue';
import FormError from '../forms/FormError.vue';
import FormInput from '../forms/FormInput.vue';
import FormTextarea from '../forms/FormTextarea.vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    service: {
        type: Object,
        default: null,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'submit']);
const title = computed(() => (props.service ? 'Edit service category' : 'Create service category'));
const iconOptions = [
    { label: 'Home repair', value: 'pi-home' },
    { label: 'Electrical', value: 'pi-bolt' },
    { label: 'Plumbing', value: 'pi-wrench' },
    { label: 'Cleaning', value: 'pi-sparkles' },
    { label: 'Painting', value: 'pi-palette' },
    { label: 'Appliance', value: 'pi-cog' },
    { label: 'Carpentry', value: 'pi-hammer' },
    { label: 'Gardening', value: 'pi-sun' },
    { label: 'Security', value: 'pi-shield' },
    { label: 'Moving', value: 'pi-truck' },
    { label: 'Beauty', value: 'pi-heart' },
    { label: 'General', value: 'pi-briefcase' },
];

const form = reactive({
    name: '',
    description: '',
    icon: 'pi-briefcase',
    is_active: true,
});

const visibleIconOptions = computed(() => {
    if (!form.icon || iconOptions.some((option) => option.value === form.icon)) {
        return iconOptions;
    }

    return [
        { label: 'Current icon', value: form.icon },
        ...iconOptions,
    ];
});

watch(
    () => [props.open, props.service],
    () => {
        Object.assign(form, {
            name: props.service?.name || '',
            description: props.service?.description || '',
            icon: props.service?.icon || 'pi-briefcase',
            is_active: props.service?.is_active ?? true,
        });
    },
    { immediate: true },
);

function submit() {
    emit('submit', {
        ...form,
        is_active: form.is_active ? 1 : 0,
    });
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-3 py-3 sm:items-center sm:px-4 sm:py-6">
        <section class="max-h-[calc(100vh-1.5rem)] w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:max-h-[calc(100vh-3rem)]">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white" @click="$emit('close')">
                    <i class="pi pi-times" aria-hidden="true"></i>
                </button>
            </div>

            <form class="max-h-[calc(100vh-6rem)] space-y-4 overflow-y-auto p-4 sm:p-5" @submit.prevent="submit">
                <FormInput id="service_name" v-model="form.name" label="Name" :error="errors.name" />

                <div>
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Icon</label>
                        <span class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-white/10 dark:text-gray-300">
                            <i :class="['pi', form.icon]" aria-hidden="true"></i>
                            {{ visibleIconOptions.find((option) => option.value === form.icon)?.label || 'Selected' }}
                        </span>
                    </div>

                    <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <button
                            v-for="option in visibleIconOptions"
                            :key="option.value"
                            type="button"
                            class="flex h-20 flex-col items-center justify-center gap-2 rounded-md border px-2 text-center text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400"
                            :class="form.icon === option.value
                                ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-500/10 dark:text-blue-300'
                                : 'border-gray-200 bg-white text-gray-600 hover:border-blue-200 hover:bg-blue-50/60 dark:border-white/10 dark:bg-gray-950 dark:text-gray-300 dark:hover:border-blue-500/40 dark:hover:bg-blue-500/10'"
                            @click="form.icon = option.value"
                        >
                            <i :class="['pi text-lg', option.value]" aria-hidden="true"></i>
                            <span class="leading-tight">{{ option.label }}</span>
                        </button>
                    </div>

                    <FormError :error="errors.icon" />
                </div>

                <label class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200">
                    <span>Active service</span>
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:focus:ring-white">
                </label>

                <FormTextarea id="service_description" v-model="form.description" label="Description" :error="errors.description" />

                <div class="grid gap-2 pt-2 sm:flex sm:justify-end">
                    <button type="button" class="rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 shadow-[0_3px_0_#bfdbfe,0_8px_16px_rgba(37,99,235,0.12)] transition-all duration-150 hover:-translate-y-0.5 hover:bg-blue-100 active:translate-y-0.5 active:shadow-[0_1px_0_#bfdbfe,0_5px_10px_rgba(37,99,235,0.12)] dark:border-white/10 dark:bg-white/5 dark:text-gray-200 dark:shadow-[0_3px_0_rgba(59,130,246,0.18)] dark:hover:bg-white/10" @click="$emit('close')">
                        Cancel
                    </button>
                    <div class="sm:w-auto">
                        <AppButton type="submit" icon="pi-save" :loading="loading">{{ loading ? 'Saving...' : 'Save service' }}</AppButton>
                    </div>
                </div>
            </form>
        </section>
    </div>
</template>
