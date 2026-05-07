<script setup>
import { computed, reactive, watch } from 'vue';
import AppButton from '../common/AppButton.vue';
import FormInput from '../forms/FormInput.vue';

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

const form = reactive({
    name: '',
    description: '',
    icon: '',
    is_active: true,
});

watch(
    () => [props.open, props.service],
    () => {
        Object.assign(form, {
            name: props.service?.name || '',
            description: props.service?.description || '',
            icon: props.service?.icon || '',
            is_active: props.service?.is_active ?? true,
        });
    },
    { immediate: true },
);

function submit() {
    emit('submit', { ...form });
}
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 py-6">
        <section class="w-full max-w-lg rounded-lg bg-white shadow-xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ title }}</h2>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-white" @click="$emit('close')">
                    <i class="pi pi-times" aria-hidden="true"></i>
                </button>
            </div>

            <form class="space-y-4 p-5" @submit.prevent="submit">
                <FormInput id="service_name" v-model="form.name" label="Name" :error="errors.name" />
                <FormInput id="service_icon" v-model="form.icon" label="PrimeIcon class" :error="errors.icon" />

                <label class="flex items-center justify-between gap-4 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-white/10 dark:text-gray-200">
                    <span>Active service</span>
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:focus:ring-white">
                </label>

                <div>
                    <label for="service_description" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Description</label>
                    <textarea
                        id="service_description"
                        v-model="form.description"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-gray-900 focus:ring-gray-900 dark:border-white/10 dark:bg-gray-950 dark:text-white dark:focus:border-white dark:focus:ring-white"
                    ></textarea>
                    <p v-if="errors.description?.length" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.description[0] }}</p>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5" @click="$emit('close')">
                        Cancel
                    </button>
                    <div class="w-auto">
                        <AppButton type="submit" icon="pi-save" :loading="loading">{{ loading ? 'Saving...' : 'Save service' }}</AppButton>
                    </div>
                </div>
            </form>
        </section>
    </div>
</template>
