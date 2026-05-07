import { ref } from 'vue';

export function useApiErrors() {
    const errors = ref({});
    const message = ref('');

    function setApiError(error) {
        errors.value = error.response?.data?.errors || {};
        message.value = error.response?.data?.message || 'Something went wrong. Please try again.';
    }

    function clearApiErrors() {
        errors.value = {};
        message.value = '';
    }

    return {
        errors,
        message,
        setApiError,
        clearApiErrors,
    };
}
