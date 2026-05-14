import { optionalTrimmedString, requiredNumber, requiredTrimmedString, yup } from './shared';

export const adminServiceSchema = yup.object({
    name: requiredTrimmedString('Name', 255),
    description: optionalTrimmedString(2000),
    icon: optionalTrimmedString(255),
});

export const commissionSettingsSchema = yup.object({
    commission_rate: requiredNumber('Commission rate').min(0, 'Commission rate must be at least 0.').max(100, 'Commission rate must be 100 or less.'),
});

export const adminDisputeReviewSchema = yup.object({
    status: yup.string().required('Status is required.').oneOf(['under_review', 'resolved', 'rejected'], 'Choose a valid status.'),
    resolution_note: yup
        .string()
        .transform((value) => value?.trim() || '')
        .max(5000, 'Resolution note must be at most 5000 characters.')
        .when('status', {
            is: (status) => ['resolved', 'rejected'].includes(status),
            then: (schema) => schema.required('Resolution note is required.'),
            otherwise: (schema) => schema.nullable(),
        }),
});

export const adminWorkerServiceRejectionSchema = yup.object({
    rejection_reason: requiredTrimmedString('Reason', 1000),
});

export const adminUnblockReviewSchema = yup.object({
    admin_note: optionalTrimmedString(2000),
});

export const adminWorkerVerificationRejectionSchema = yup.object({
    rejection_reason: requiredTrimmedString('Reason', 1000),
});

export const adminWorkerVerificationResubmissionSchema = yup.object({
    resubmission_reason: requiredTrimmedString('Resubmission reason', 1000),
});
