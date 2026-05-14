import { phonePattern, requiredNumber, requiredTrimmedString, yup } from './shared';

export const loginSchema = yup.object({
    email: yup.string().trim().email('Enter a valid email address.').required('Email is required.'),
    password: yup.string().required('Password is required.'),
});

export const registerSchema = yup.object({
    role_id: requiredNumber('Role'),
    name: requiredTrimmedString('Name', 255),
    email: yup.string().trim().email('Enter a valid email address.').required('Email is required.').max(255, 'Email must be at most 255 characters.'),
    phone: yup.string().trim().required('Phone is required.').matches(phonePattern, 'Enter a valid phone number.'),
    password: yup.string().required('Password is required.').min(8, 'Password must be at least 8 characters.'),
    password_confirmation: yup.string().required('Password confirmation is required.').oneOf([yup.ref('password')], 'Password confirmation must match the password.'),
});

export const forgotPasswordSchema = yup.object({
    email: yup.string().trim().email('Enter a valid email address.').required('Email is required.'),
});

export const resetPasswordSchema = yup.object({
    token: yup.string().trim().required('Reset token is missing. Open the password reset link again.'),
    email: yup.string().trim().email('Enter a valid email address.').required('Email is required.'),
    password: yup.string().required('New password is required.').min(8, 'New password must be at least 8 characters.'),
    password_confirmation: yup.string().required('Password confirmation is required.').oneOf([yup.ref('password')], 'Password confirmation must match the password.'),
});

export const passwordUpdateSchema = yup.object({
    current_password: yup.string().required('Current password is required.'),
    password: yup.string().required('New password is required.').min(8, 'New password must be at least 8 characters.').notOneOf([yup.ref('current_password')], 'New password must be different from the current password.'),
    password_confirmation: yup.string().required('Password confirmation is required.').oneOf([yup.ref('password')], 'Password confirmation must match the new password.'),
});
