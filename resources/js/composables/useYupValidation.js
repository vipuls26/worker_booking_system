import { ref } from 'vue';

/**
 * Validate frontend forms with a Yup schema and keep field errors in the same array format as API errors.
 *
 * @param {import('yup').AnyObjectSchema|(() => import('yup').AnyObjectSchema)} schemaOrFactory
 * @returns {{
 *   validationErrors: import('vue').Ref<Record<string, string[]>>,
 *   clearValidationErrors: (fields?: string|string[]) => void,
 *   validateWithSchema: (values: Record<string, unknown>, options?: Record<string, unknown>) => Promise<boolean>,
 * }}
 */
export function useYupValidation(schemaOrFactory) {
    const validationErrors = ref({});

    /**
     * Reset either all validation errors or only the requested field keys.
     */
    function clearValidationErrors(fields = null) {
        if (! fields) {
            validationErrors.value = {};

            return;
        }

        const fieldList = Array.isArray(fields) ? fields : [fields];
        const nextErrors = { ...validationErrors.value };

        fieldList.forEach((field) => {
            delete nextErrors[field];
        });

        validationErrors.value = nextErrors;
    }

    /**
     * Run Yup validation and normalize any failures into Laravel-style error arrays.
     */
    async function validateWithSchema(values, options = {}) {
        const resolvedSchema = typeof schemaOrFactory === 'function' ? schemaOrFactory() : schemaOrFactory;

        try {
            await resolvedSchema.validate(values, {
                abortEarly: false,
                stripUnknown: false,
                ...options,
            });

            validationErrors.value = {};

            return true;
        } catch (error) {
            const nextErrors = {};

            if (Array.isArray(error?.inner) && error.inner.length > 0) {
                error.inner.forEach((issue) => {
                    if (! issue.path || nextErrors[issue.path]) {
                        return;
                    }

                    nextErrors[issue.path] = [issue.message];
                });
            } else if (error?.path && error?.message) {
                nextErrors[error.path] = [error.message];
            }

            validationErrors.value = nextErrors;

            return false;
        }
    }

    return {
        validationErrors,
        clearValidationErrors,
        validateWithSchema,
    };
}
