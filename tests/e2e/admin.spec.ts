import { expect, test } from '@playwright/test';
import { login, loginByApi } from './helpers/auth';
import { apiJson, apiResponse } from './helpers/api';
import { createBookingRequest, fetchBookingDetail } from './helpers/bookings';
import { futureDate, uniqueReference } from './helpers/date';
import {
    markUserEmailVerified,
    onboardCustomer,
    onboardWorker,
    registerUserByApi,
    submitWorkerVerification,
} from './helpers/onboarding';
import { testUsers } from './helpers/users';

async function createAdminTargetCustomer(
    request: Parameters<typeof apiJson>[0],
    adminToken: string,
): Promise<{ email: string }> {
    const customer = await onboardCustomer(request, adminToken, 'Admin Target Customer');

    const blockResponse = await apiResponse(request, adminToken, `/api/admin/users/${customer.user.id}/block`, {
        method: 'PATCH',
        data: {
            block_type: 'partially_blocked',
        },
    });

    expect(blockResponse.status()).toBe(200);

    return {
        email: customer.user.email,
    };
}

async function createPendingVerificationWorker(
    request: Parameters<typeof apiJson>[0],
): Promise<{ name: string }> {
    const worker = await registerUserByApi(request, {
        roleSlug: 'worker',
        name: uniqueReference('Admin Pending Worker'),
        email: `${uniqueReference('admin-pending-worker').toLowerCase()}@example.com`,
        phone: `81${Date.now()}${Math.floor(Math.random() * 1000)}`.slice(0, 15),
    });

    markUserEmailVerified(worker.user.email);
    await submitWorkerVerification(request, worker.token);

    return {
        name: worker.name,
    };
}

async function findAdminUserStatus(
    request: Parameters<typeof apiJson>[0],
    adminToken: string,
    email: string,
): Promise<string | null> {
    const payload = await apiJson<{
        data: {
            users: Array<{
                email: string;
                account_status: string;
            }>;
        };
    }>(request, adminToken, '/api/admin/users', {
        params: {
            search: email,
        },
    });

    return payload.data.users.find((user) => user.email === email)?.account_status ?? null;
}

async function findVerificationStatus(
    request: Parameters<typeof apiJson>[0],
    adminToken: string,
    workerName: string,
): Promise<string | null> {
    const payload = await apiJson<{
        data: {
            verifications: Array<{
                status: string;
                worker?: {
                    name: string;
                };
            }>;
        };
    }>(request, adminToken, '/api/admin/worker-verifications');

    return payload.data.verifications.find((verification) => verification.worker?.name === workerName)?.status ?? null;
}

test.describe('Admin workflows', () => {
    test('admin can partial block, unblock, and fully block a user from the users screen', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const targetCustomer = await createAdminTargetCustomer(request, adminSession.token);

        await login(page, testUsers.admin);
        await page.goto('/admin/users');

        const targetRow = page.locator('tr').filter({ hasText: targetCustomer.email });
        await expect(targetRow).toBeVisible();

        await targetRow.getByText('Unblock').click();
        await page.getByTestId('confirm-dialog-confirm').click();
        await expect.poll(async () => findAdminUserStatus(request, adminSession.token, targetCustomer.email)).toBe('active');

        const reloadedRow = page.locator('tr').filter({ hasText: targetCustomer.email });
        await reloadedRow.getByText('Partial block').click();
        await page.getByTestId('confirm-dialog-confirm').click();
        await expect.poll(async () => findAdminUserStatus(request, adminSession.token, targetCustomer.email)).toBe('partially_blocked');

        const fullyBlockedRow = page.locator('tr').filter({ hasText: targetCustomer.email });
        await fullyBlockedRow.getByText('Full block').click();
        await page.getByTestId('confirm-dialog-confirm').click();
        await expect.poll(async () => findAdminUserStatus(request, adminSession.token, targetCustomer.email)).toBe('fully_blocked');
    });

    test('admin commission update changes new bookings without changing older booking quotes', async ({ request, page }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const originalSetting = await apiJson<{ data: { commission_setting: { commission_rate: string } } }>(
            request,
            adminSession.token,
            '/api/admin/commission-settings',
        );
        const originalCommissionRate = originalSetting.data.commission_setting.commission_rate;
        const customer = await onboardCustomer(request, adminSession.token, 'Commission Customer');
        const worker = await onboardWorker(request, adminSession.token, { namePrefix: 'Commission Worker', serviceCount: 1 });
        const approvedService = worker.approvedServices[0];

        try {
            const oldServiceRequest = await createBookingRequest(request, customer.token, {
                workerId: worker.user.id,
                serviceId: approvedService.serviceId,
                bookingDate: futureDate(9),
                issueDescription: 'Booking before commission update.',
            });

            await apiJson(
                request,
                worker.token,
                `/api/worker/booking-requests/${oldServiceRequest.worker_requests[0].id}/respond`,
                {
                    method: 'PATCH',
                    data: {
                        status: 'accepted',
                    },
                },
            );

            const oldBookingDetail = await fetchBookingDetail(request, customer.token, oldServiceRequest.id);
            expect(oldBookingDetail.booking?.commission_rate ?? oldBookingDetail.booking?.booking?.commission_rate).toBe(originalCommissionRate);

            await login(page, testUsers.admin);
            await page.goto('/admin/commission-settings');
            await page.getByTestId('commission-rate-input').fill('12.50');
            await page.getByTestId('commission-save-button').click();
            await page.getByTestId('confirm-dialog-confirm').click();
            await expect.poll(async () => {
                const updatedSetting = await apiJson<{ data: { commission_setting: { commission_rate: string } } }>(
                    request,
                    adminSession.token,
                    '/api/admin/commission-settings',
                );

                return updatedSetting.data.commission_setting.commission_rate;
            }).toBe('12.50');

            const newServiceRequest = await createBookingRequest(request, customer.token, {
                workerId: worker.user.id,
                serviceId: approvedService.serviceId,
                bookingDate: futureDate(10),
                issueDescription: 'Booking after commission update.',
            });

            await apiJson(
                request,
                worker.token,
                `/api/worker/booking-requests/${newServiceRequest.worker_requests[0].id}/respond`,
                {
                    method: 'PATCH',
                    data: {
                        status: 'accepted',
                    },
                },
            );

            const newBookingDetail = await fetchBookingDetail(request, customer.token, newServiceRequest.id);
            expect(newBookingDetail.booking?.commission_rate ?? newBookingDetail.booking?.booking?.commission_rate).toBe('12.50');

            const refreshedOldBooking = await fetchBookingDetail(request, customer.token, oldServiceRequest.id);
            expect(refreshedOldBooking.booking?.commission_rate ?? refreshedOldBooking.booking?.booking?.commission_rate).toBe(originalCommissionRate);
        } finally {
            await apiJson(
                request,
                adminSession.token,
                '/api/admin/commission-settings',
                {
                    method: 'PATCH',
                    data: {
                        commission_rate: originalCommissionRate,
                    },
                },
            );
        }
    });
});
