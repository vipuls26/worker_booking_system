<script setup>
const props = defineProps({
    modelValue: {
        type: Number,
        default: 0,
    },
    testidPrefix: {
        type: String,
        default: '',
    },
    readonly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

function setRating(rating) {
    if (! props.readonly) {
        emit('update:modelValue', rating);
    }
}
</script>

<template>
    <div class="flex items-center gap-1">
        <button
            v-for="rating in 5"
            :key="rating"
            type="button"
            :data-testid="testidPrefix ? `${testidPrefix}-${rating}` : null"
            class="inline-flex size-8 items-center justify-center rounded-md text-lg transition"
            :class="[
                rating <= modelValue ? 'text-amber-500' : 'text-gray-300 dark:text-gray-600',
                readonly ? 'cursor-default' : 'hover:bg-amber-50 dark:hover:bg-amber-500/10',
            ]"
            :disabled="readonly"
            @click="setRating(rating)"
        >
            <i class="pi pi-star-fill" aria-hidden="true"></i>
        </button>
    </div>
</template>
