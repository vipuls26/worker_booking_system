import { bookingWindowDate, currentOrFutureTime, optionalTrimmedString, requiredNumber, yup } from './shared';

export const bookingRequestSchema = yup.object({
    service_id: requiredNumber('Service'),
    booking_date: bookingWindowDate('Booking date'),
    start_time: currentOrFutureTime('booking_date'),
    address: yup.string().transform((value) => value?.trim() || '').required('Add a service address or save a default address in your profile.').max(1000, 'Address must be at most 1000 characters.'),
    issue_description: yup.string().transform((value) => value?.trim() || '').required('Please describe the issue.').min(10, 'Please describe the issue in at least 10 characters.').max(2000, 'Issue description must be at most 2000 characters.'),
});

export const rescheduleSchema = yup.object({
    booking_date: bookingWindowDate('Booking date'),
    start_time: currentOrFutureTime('booking_date'),
});

export const disputeSchema = yup.object({
    category: yup.string().required('Category is required.'),
    title: yup.string().transform((value) => value?.trim() || '').required('Title is required.').max(160, 'Title must be at most 160 characters.'),
    description: yup.string().transform((value) => value?.trim() || '').required('Description is required.').min(10, 'Description must be at least 10 characters.').max(5000, 'Description must be at most 5000 characters.'),
});

export const workerBookingRequestResponseSchema = yup.object({
    status: yup.string().required('Status is required.').oneOf(['accepted', 'cancelled'], 'Choose a valid response.'),
    response_reason: yup
        .string()
        .transform((value) => value?.trim() || '')
        .max(1000, 'Cancellation reason must be at most 1000 characters.')
        .when('status', {
            is: 'cancelled',
            then: (schema) => schema.required('Cancellation reason is required.'),
            otherwise: (schema) => schema.nullable(),
        }),
});

export const workerBookingStatusSchema = yup.object({
    status: yup.string().required('Status is required.').oneOf(['accepted', 'rejected', 'cancelled', 'in_progress', 'completed'], 'Choose a valid status.'),
    rejection_reason: yup
        .string()
        .transform((value) => value?.trim() || '')
        .max(1000, 'Reject reason must be at most 1000 characters.')
        .when('status', {
            is: 'rejected',
            then: (schema) => schema.required('Reject reason is required.'),
            otherwise: (schema) => schema.nullable(),
        }),
    cancelled_reason: yup
        .string()
        .transform((value) => value?.trim() || '')
        .max(1000, 'Cancellation reason must be at most 1000 characters.')
        .when('status', {
            is: 'cancelled',
            then: (schema) => schema.required('Cancellation reason is required.'),
            otherwise: (schema) => schema.nullable(),
        }),
});

export const workerCustomerReviewSchema = yup.object({
    rating: requiredNumber('Rating').integer('Rating must be a whole number.').min(1, 'Select a rating between 1 and 5.').max(5, 'Select a rating between 1 and 5.'),
    review: optionalTrimmedString(2000),
});
