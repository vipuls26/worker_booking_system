import { ref } from 'vue';

export function useApiErrors() {
    const errors = ref({});

    function setApiError(error) {
        errors.value = error.response?.data?.errors || {};
    }

    function clearApiErrors() {
        errors.value = {};
    }

    return {
        errors,
        setApiError,
        clearApiErrors,
    };
}
