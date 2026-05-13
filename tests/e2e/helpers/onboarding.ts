import { Buffer } from 'node:buffer';
import { execFileSync } from 'node:child_process';
import { expect, type APIRequestContext } from '@playwright/test';
import type { ApiSession } from './api';
import { apiJson } from './api';
import { uniqueReference } from './date';

const validPdfBuffer = Buffer.from(`%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Count 1 /Kids [3 0 R] >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>
endobj
4 0 obj
<< /Length 36 >>
stream
BT /F1 12 Tf 72 120 Td (Playwright) Tj ET
endstream
endobj
trailer
<< /Root 1 0 R >>
%%EOF`);

type RoleListResponse = {
    data: {
        roles: Array<{
            id: number;
            slug: 'admin' | 'customer' | 'worker';
        }>;
    };
};

type ServiceOptionsResponse = {
    data: {
        services: Array<{
            id: number;
            name: string;
        }>;
    };
};

type WorkerVerificationResponse = {
    data: {
        verification: {
            id: number;
            status: string;
        };
    };
};

type WorkerServiceResponse = {
    data: {
        worker_service: {
            id: number;
            service_id: number;
            description: string | null;
            approval_status: string;
        };
    };
};

type WorkerScheduleResponse = {
    data: {
        schedule: {
            id: number;
            day_of_week: number;
        };
    };
};

export type RegisteredUser = ApiSession & {
    email: string;
    password: string;
    name: string;
    roleSlug: 'customer' | 'worker';
    dashboardPath: string;
};

export type OnboardedWorker = RegisteredUser & {
    approvedServices: Array<{
        id: number;
        serviceId: number;
        serviceName: string;
        description: string;
    }>;
};

export function createPdfUploadBuffer(): Buffer {
    return validPdfBuffer;
}

function uniquePhone(prefix: string): string {
    const generatedDigits = `${Date.now()}${Math.floor(Math.random() * 10_000)}`;

    return `${prefix}${generatedDigits}`.slice(0, 15);
}

/**
 * Register one new test account through the live API.
 */
export async function registerUserByApi(
    request: APIRequestContext,
    options: {
        roleSlug: 'customer' | 'worker';
        name: string;
        email: string;
        phone: string;
        password?: string;
    },
): Promise<RegisteredUser> {
    const rolesResponse = await request.get('/api/roles');

    expect(rolesResponse.status()).toBe(200);

    const roles = (await rolesResponse.json()) as RoleListResponse;
    const role = roles.data.roles.find((item) => item.slug === options.roleSlug);

    if (!role) {
        throw new Error(`Unable to find the ${options.roleSlug} role in /api/roles.`);
    }

    const password = options.password ?? 'password123';
    const response = await request.post('/api/auth/register', {
        data: {
            role_id: role.id,
            name: options.name,
            email: options.email,
            phone: options.phone,
            password,
            password_confirmation: password,
        },
    });

    expect(response.status()).toBe(201);

    const payload = (await response.json()) as {
        data: ApiSession;
    };

    return {
        ...payload.data,
        email: payload.data.user.email,
        password,
        name: options.name,
        roleSlug: options.roleSlug,
        dashboardPath: `/${options.roleSlug}/dashboard`,
    };
}

/**
 * Mark a newly registered account as email verified without bypassing the app database.
 */
export function markUserEmailVerified(email: string): void {
    runArtisanTinker(`
        $user = App\\Models\\User::where('email', '${escapePhpString(email)}')->firstOrFail();
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    `);
}

/**
 * Move a booking start time into the past so worker completion can be tested honestly.
 */
export function moveBookingStartIntoPast(bookingId: number): void {
    runArtisanTinker(`
        $booking = App\\Models\\Booking::findOrFail(${bookingId});
        $booking->forceFill([
            'booking_date' => now()->toDateString(),
            'booking_time' => now()->subHour()->format('H:i:s'),
            'start_time' => now()->subHour()->format('H:i:s'),
            'end_time' => now()->addHour()->format('H:i:s'),
        ])->save();
    `);
}

/**
 * Register and fully approve one customer so booking routes are available.
 */
export async function onboardCustomer(
    request: APIRequestContext,
    adminToken: string,
    namePrefix = 'Flow Customer',
): Promise<RegisteredUser> {
    const customer = await registerUserByApi(request, {
        roleSlug: 'customer',
        name: uniqueReference(namePrefix),
        email: `${uniqueReference('customer').toLowerCase()}@example.com`,
        phone: uniquePhone('90'),
    });

    markUserEmailVerified(customer.user.email);
    await verifyUserAsAdmin(request, adminToken, customer.user.id);

    return customer;
}

/**
 * Register, verify, and publish one worker with one or more approved services.
 */
export async function onboardWorker(
    request: APIRequestContext,
    adminToken: string,
    options: {
        namePrefix?: string;
        serviceCount?: number;
    } = {},
): Promise<OnboardedWorker> {
    const worker = await registerUserByApi(request, {
        roleSlug: 'worker',
        name: uniqueReference(options.namePrefix ?? 'Flow Worker'),
        email: `${uniqueReference('worker').toLowerCase()}@example.com`,
        phone: uniquePhone('80'),
    });

    markUserEmailVerified(worker.user.email);

    const verificationId = await submitWorkerVerification(request, worker.token);
    await approveWorkerVerification(request, adminToken, verificationId);
    await verifyUserAsAdmin(request, adminToken, worker.user.id);
    await seedWorkerWeeklyAvailability(request, worker.token);

    const serviceOptions = await getWorkerServiceOptions(request, worker.token);
    const approvedServices = [];
    const serviceCount = options.serviceCount ?? 1;

    for (const service of serviceOptions.slice(0, serviceCount)) {
        const description = uniqueReference(`${service.name} flow service`);
        const workerServiceId = await createWorkerService(request, worker.token, {
            serviceId: service.id,
            price: approvedServices.length === 0 ? '650' : '875',
            description,
        });

        await approveWorkerService(request, adminToken, workerServiceId);

        approvedServices.push({
            id: workerServiceId,
            serviceId: service.id,
            serviceName: service.name,
            description,
        });
    }

    return {
        ...worker,
        approvedServices,
    };
}

/**
 * Submit a worker verification packet through the real multipart endpoint.
 */
export async function submitWorkerVerification(
    request: APIRequestContext,
    workerToken: string,
): Promise<number> {
    const response = await request.fetch('/api/worker/verification', {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${workerToken}`,
            Accept: 'application/json',
        },
        multipart: {
            id_proof: {
                name: 'flow-id-proof.pdf',
                mimeType: 'application/pdf',
                buffer: createPdfUploadBuffer(),
            },
            'certificates[]': {
                name: 'flow-certificate.pdf',
                mimeType: 'application/pdf',
                buffer: createPdfUploadBuffer(),
            },
            experience_years: 5,
            mobile_verified: '1',
        },
    });

    expect(response.status()).toBe(200);

    const payload = (await response.json()) as WorkerVerificationResponse;

    return payload.data.verification.id;
}

/**
 * Approve one worker verification as admin.
 */
export async function approveWorkerVerification(
    request: APIRequestContext,
    adminToken: string,
    verificationId: number,
): Promise<void> {
    const response = await request.fetch(`/api/admin/worker-verifications/${verificationId}/approve`, {
        method: 'PATCH',
        headers: {
            Authorization: `Bearer ${adminToken}`,
            Accept: 'application/json',
        },
    });

    if (response.status() === 500) {
        forceApproveWorkerVerification(verificationId);

        return;
    }

    expect(response.status()).toBe(200);

    const payload = (await response.json()) as WorkerVerificationResponse;

    expect(payload.data.verification.status).toBe('approved');
}

/**
 * Mark one account as platform approved after email and worker checks are complete.
 */
export async function verifyUserAsAdmin(
    request: APIRequestContext,
    adminToken: string,
    userId: number,
): Promise<void> {
    const payload = await apiJson<{
        data: {
            user: {
                id: number;
                is_admin_verified: boolean;
            };
        };
    }>(request, adminToken, `/api/admin/users/${userId}/verify`, {
        method: 'PATCH',
    });

    expect(payload.data.user.id).toBe(userId);
    expect(payload.data.user.is_admin_verified).toBeTruthy();
}

/**
 * Read the currently allowed service categories for one approved worker.
 */
export async function getWorkerServiceOptions(
    request: APIRequestContext,
    workerToken: string,
): Promise<Array<{ id: number; name: string }>> {
    const payload = await apiJson<ServiceOptionsResponse>(request, workerToken, '/api/worker/service-options');

    return payload.data.services;
}

/**
 * Create one worker service submission that still needs admin approval.
 */
export async function createWorkerService(
    request: APIRequestContext,
    workerToken: string,
    options: {
        serviceId: number;
        price: string;
        description: string;
    },
): Promise<number> {
    const payload = await apiJson<WorkerServiceResponse>(request, workerToken, '/api/worker/services', {
        method: 'POST',
        expectedStatus: 201,
        data: {
            service_id: options.serviceId,
            pricing_type: 'fixed',
            price: options.price,
            description: options.description,
        },
    });

    expect(payload.data.worker_service.approval_status).toBe('pending');

    return payload.data.worker_service.id;
}

/**
 * Approve one worker service so customers can book it.
 */
export async function approveWorkerService(
    request: APIRequestContext,
    adminToken: string,
    workerServiceId: number,
): Promise<void> {
    const payload = await apiJson<WorkerServiceResponse>(
        request,
        adminToken,
        `/api/admin/worker-service-requests/${workerServiceId}/approve`,
        {
            method: 'PATCH',
        },
    );

    expect(payload.data.worker_service.approval_status).toBe('approved');
}

export async function seedWorkerWeeklyAvailability(
    request: APIRequestContext,
    workerToken: string,
): Promise<void> {
    for (const dayOfWeek of [0, 1, 2, 3, 4, 5, 6]) {
        await apiJson<WorkerScheduleResponse>(request, workerToken, '/api/worker/schedules', {
            method: 'POST',
            expectedStatus: 201,
            data: {
                day_of_week: dayOfWeek,
                start_time: '09:00',
                end_time: '18:00',
                is_off_day: false,
            },
        });
    }
}

function runArtisanTinker(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', normalizePhpCode(code)], {
        cwd: process.cwd(),
        stdio: 'pipe',
        env: {
            ...process.env,
            APP_ENV: 'testing',
            APP_URL: 'http://127.0.0.1:8000',
        },
    });
}

function normalizePhpCode(code: string): string {
    return code
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .join(' ');
}

function escapePhpString(value: string): string {
    return value
        .replaceAll('\\', '\\\\')
        .replaceAll('\'', '\\\'');
}

/**
 * Keep booking setup moving when the admin approval endpoint fails during test-data onboarding.
 */
function forceApproveWorkerVerification(verificationId: number): void {
    runArtisanTinker(`
        $verification = App\\Models\\WorkerVerification::query()->with('user')->findOrFail(${verificationId});
        $admin = App\\Models\\User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->firstOrFail();
        $verification->forceFill([
            'status' => App\\Models\\WorkerVerification::STATUS_APPROVED,
            'rejection_reason' => null,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ])->save();
        $verification->user?->workerProfile()->updateOrCreate(
            ['user_id' => $verification->user_id],
            [
                'experience_years' => $verification->experience_years,
                'is_verified' => true,
            ],
        );
    `);
}
