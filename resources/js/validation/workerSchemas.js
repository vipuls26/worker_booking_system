import { optionalTrimmedString, requiredNumber, yup } from './shared';

export const workerServiceSchema = yup.object({
    service_id: requiredNumber('Service'),
    pricing_type: yup.string().required('Pricing type is required.').oneOf(['fixed', 'hourly'], 'Choose a valid pricing type.'),
    price: requiredNumber('Price').min(1, 'Price must be at least 1.').max(999999.99, 'Price must be 999999.99 or less.'),
    minimum_hours: yup
        .number()
        .transform((value, originalValue) => (originalValue === '' || originalValue === null ? null : value))
        .nullable()
        .when('pricing_type', {
            is: 'hourly',
            then: (schema) => schema.required('Minimum hours is required for hourly pricing.').min(1, 'Minimum hours must be at least 1.').max(24, 'Minimum hours must be 24 or less.'),
            otherwise: (schema) => schema.nullable(),
        }),
    description: optionalTrimmedString(2000),
});

export const workerScheduleSchema = yup.object({
    day_of_week: requiredNumber('Day').min(0, 'Choose a valid day.').max(6, 'Choose a valid day.'),
    is_off_day: yup.boolean().required(),
    start_time: yup.string().when('is_off_day', {
        is: false,
        then: (schema) => schema.required('Start time is required.'),
        otherwise: (schema) => schema.nullable(),
    }),
    end_time: yup.string().when('is_off_day', {
        is: false,
        then: (schema) => schema.required('End time is required.').test('after-start', 'End time must be after the start time.', function validateEndTime(value) {
            if (! value || ! this.parent?.start_time) {
                return false;
            }

            return value > this.parent.start_time;
        }),
        otherwise: (schema) => schema.nullable(),
    }),
});
