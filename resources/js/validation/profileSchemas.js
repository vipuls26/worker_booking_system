import { optionalTrimmedString, phonePattern, requiredNumber, requiredTrimmedString, yup } from './shared';

export const profileSchema = yup.object({
    name: requiredTrimmedString('Name', 255),
    email: yup.string().trim().email('Enter a valid email address.').required('Email is required.').max(255, 'Email must be at most 255 characters.'),
    phone: yup.string().trim().required('Phone is required.').matches(phonePattern, 'Enter a valid phone number.'),
    address: optionalTrimmedString(1000),
});

export const workerProfileSchema = yup.object({
    bio: optionalTrimmedString(2000),
    experience_years: requiredNumber('Experience years').min(0, 'Experience years must be at least 0.').max(60, 'Experience years must be 60 or less.'),
    address: optionalTrimmedString(1000),
    city: requiredTrimmedString('City', 120),
    skills_text: yup
        .string()
        .transform((value) => value?.trim() || '')
        .test('skills-count', 'You can list up to 30 skills.', (value) => {
            if (! value) {
                return true;
            }

            return value.split(',').map((skill) => skill.trim()).filter(Boolean).length <= 30;
        })
        .test('skills-length', 'Each skill must be 80 characters or fewer.', (value) => {
            if (! value) {
                return true;
            }

            return value.split(',').map((skill) => skill.trim()).filter(Boolean).every((skill) => skill.length <= 80);
        }),
    phone: yup.string().trim().required('Phone is required.').matches(phonePattern, 'Enter a valid phone number.'),
});

export const unblockRequestSchema = yup.object({
    reason: yup.string().transform((value) => value?.trim() || '').required('Reason is required.').min(10, 'Reason must be at least 10 characters.').max(2000, 'Reason must be at most 2000 characters.'),
});
