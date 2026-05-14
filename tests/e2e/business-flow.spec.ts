import { expect, test, type Page } from '@playwright/test';
import { login, loginByApi, logout } from './helpers/auth';
import { apiJson } from './helpers/api';
import { completeWorkerBooking, createBookingRequest, fetchBookingDetail } from './helpers/bookings';
import { futureDate, uniqueReference } from './helpers/date';
import {
    createPdfUploadBuffer,
    markUserEmailVerified,
    moveBookingStartIntoPast,
    onboardCustomer,
    onboardWorker,
    registerUserByApi,
    seedWorkerWeeklyAvailability,
} from './helpers/onboarding';
import { testUsers, type TestUser } from './helpers/users';

function toUiUser(user: {
    user: {
        email: string;
    };
    name: string;
    roleSlug: 'customer' | 'worker';
    password: string;
    dashboardPath: string;
}): TestUser {
    return {
        role: user.roleSlug,
        email: user.user.email,
        password: user.password,
        dashboardPath: user.dashboardPath,
    };
}

async function selectFirstAvailableWorkerService(page: Page): Promise<void> {
    const serviceSelect = page.getByTestId('worker-service-form-service');
    const serviceOptions = await serviceSelect.locator('option:not([disabled])').evaluateAll((options) => {
        return options
            .map((option) => ({
                label: option.textContent?.trim() ?? '',
                value: option.getAttribute('value') ?? '',
            }))
            .filter((option) => option.label !== '' && option.value !== '');
    });

    const firstService = serviceOptions[0];

    if (!firstService) {
        throw new Error('No available worker service option was found for the business flow test.');
    }

    await serviceSelect.selectOption(firstService.value);
}

test.describe('Business workflow coverage', () => {
    test('worker onboarding follows document review, admin approval, service approval, booking, and cancellation flow', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const customer = await onboardCustomer(request, adminSession.token, 'Flow Reject Customer');
        const worker = await registerUserByApi(request, {
            roleSlug: 'worker',
            name: uniqueReference('Flow Reject Worker'),
            email: `${uniqueReference('reject-worker').toLowerCase()}@example.com`,
            phone: `80123${String(Math.floor(Math.random() * 99999)).padStart(5, '0')}`,
        });

        markUserEmailVerified(worker.user.email);

        const workerUiUser = toUiUser(worker);
        const customerUiUser = toUiUser(customer);
        const serviceDescription = uniqueReference('Flow service pending approval');
        const bookingDate = futureDate(5);

        await test.step('worker submits verification through the UI', async () => {
            await login(page, workerUiUser);
            await page.goto('/worker/profile');

            await page.getByTestId('worker-verification-id-proof').setInputFiles({
                name: 'worker-id-proof.pdf',
                mimeType: 'application/pdf',
                buffer: createPdfUploadBuffer(),
            });
            await page.getByTestId('worker-verification-certificates').setInputFiles({
                name: 'worker-certificate.pdf',
                mimeType: 'application/pdf',
                buffer: createPdfUploadBuffer(),
            });
            await page.getByTestId('worker-verification-submit').click();

            await expect(page.getByTestId('worker-verification-status')).toContainText('pending');
            await logout(page);
        });

        await test.step('admin approves the verification and verifies the worker account', async () => {
            await login(page, testUsers.admin);
            await page.goto('/admin/worker-verifications');

            const verificationRow = page.locator('[data-testid^="admin-worker-verification-row-"]').filter({
                hasText: worker.name,
            }).first();

            await expect(verificationRow).toBeVisible();
            await verificationRow.locator('[data-testid^="admin-worker-verification-approve-"]').click();
            await expect(verificationRow).toContainText(/approved/i);

            await page.goto('/admin/users');

            const userRow = page.locator('[data-testid^="admin-user-row-"]').filter({
                hasText: worker.user.email,
            }).first();

            await expect(userRow).toBeVisible();
            await userRow.locator('[data-testid^="admin-user-verify-"]').click();
            await expect(userRow).toContainText(/active|verified/i);
            await logout(page);
        });

        await test.step('worker gets a predictable weekly availability schedule', async () => {
            await seedWorkerWeeklyAvailability(request, worker.token);
        });

        await test.step('worker adds a service that still needs admin approval', async () => {
            await login(page, workerUiUser);
            await page.goto('/worker/services');

            await page.getByTestId('worker-service-open-create').click();
            await expect(page.getByTestId('worker-service-form-modal')).toBeVisible();
            await selectFirstAvailableWorkerService(page);
            await page.getByTestId('worker-service-form-pricing-type').selectOption('fixed');
            await page.getByTestId('worker-service-form-price').fill('725');
            await page.getByTestId('worker-service-form-description').fill(serviceDescription);
            await page.getByTestId('worker-service-form-submit').click();

            const createdRow = page.locator('[data-testid^="worker-service-row-"]').filter({
                hasText: serviceDescription,
            }).first();

            await expect(createdRow).toBeVisible();
            await expect(createdRow).toContainText(/pending/i);
            await logout(page);
        });

        await test.step('admin approves the worker service so customers can book it', async () => {
            await login(page, testUsers.admin);
            await page.goto('/admin/worker-service-requests');

            const serviceRow = page.locator('[data-testid^="admin-worker-service-row-"]').filter({
                hasText: worker.user.email,
            }).filter({
                hasText: serviceDescription,
            }).first();

            await expect(serviceRow).toBeVisible();
            await serviceRow.locator('[data-testid^="admin-worker-service-approve-"]').click();
            await expect(page.getByText('Worker service approved')).toBeVisible();

            await expect.poll(async () => {
                const workerServicesPayload = await apiJson<{
                    data: {
                        worker_services: Array<{
                            description: string | null;
                            approval_status: string;
                        }>;
                    };
                }>(request, worker.token, '/api/worker/services');

                const approvedService = workerServicesPayload.data.worker_services.find((workerService) => {
                    return workerService.description === serviceDescription;
                });

                return approvedService?.approval_status ?? null;
            }).toBe('approved');

            await logout(page);
        });

        let serviceRequestId = 0;

        await test.step('customer books the worker after the full approval chain is complete', async () => {
            await login(page, customerUiUser);
            await page.goto('/customer/workers');

            await page.getByTestId('worker-card').filter({ hasText: worker.name }).first().getByTestId('worker-view-link').click();
            await page.getByTestId('booking-service-select').selectOption({ index: 1 });
            await page.getByTestId('booking-date-input').fill(bookingDate);
            await page.getByTestId('booking-address-input').fill('101 Flow Street, Ahmedabad');
            await page.getByTestId('booking-issue-input').fill('Customer books after admin and service approvals.');
            await expect(page.getByTestId('available-slot-button').first()).toBeVisible();
            await page.getByTestId('available-slot-button').first().click();
            await page.getByTestId('booking-submit-button').click();

            await expect(page).toHaveURL(/\/customer\/bookings\/\d+$/);

            serviceRequestId = Number(page.url().match(/\/customer\/bookings\/(\d+)$/)?.[1] ?? 0);
            expect(serviceRequestId).toBeGreaterThan(0);
            await logout(page);
        });

        await test.step('worker cancels the booking request and the customer sees the cancelled request state', async () => {
            const workerRequests = await apiJson<{
                data: {
                    worker_requests: Array<{
                        id: number;
                        service_request_id: number;
                        status: string;
                    }>;
                };
            }>(request, worker.token, '/api/worker/booking-requests');

            const workerRequest = workerRequests.data.worker_requests.find((item) => item.service_request_id === serviceRequestId);

            expect(workerRequest).toBeTruthy();

            const cancellationPayload = await apiJson<{
                data: {
                    worker_request: {
                        status: string;
                    };
                };
            }>(request, worker.token, `/api/worker/booking-requests/${workerRequest?.id}/respond`, {
                method: 'PATCH',
                data: {
                    status: 'cancelled',
                    response_reason: 'Worker is unavailable for this job.',
                },
            });

            expect(cancellationPayload.data.worker_request.status).toBe('cancelled');

            const customerSession = await loginByApi(request, customerUiUser);
            const bookingDetail = await fetchBookingDetail(request, customerSession.token, serviceRequestId);
            expect(bookingDetail.worker_requests[0]?.status).toBe('cancelled');
        });
    });

    test('customer can book an approved worker, worker can accept and complete the job, and customer can rate the service', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const worker = await onboardWorker(request, adminSession.token, { serviceCount: 1, namePrefix: 'Flow Complete Worker' });
        const customer = await onboardCustomer(request, adminSession.token, 'Flow Complete Customer');
        const workerUiUser = toUiUser(worker);
        const customerUiUser = toUiUser(customer);

        const bookingRequest = await createBookingRequest(request, customer.token, {
            workerId: worker.user.id,
            serviceId: worker.approvedServices[0].serviceId,
            bookingDate: futureDate(6),
            address: '202 Completion Road, Ahmedabad',
            issueDescription: uniqueReference('Accepted booking flow'),
        });

        const workerResponse = await apiJson<{
            data: {
                worker_request: {
                    status: string;
                };
            };
        }>(request, worker.token, `/api/worker/booking-requests/${bookingRequest.worker_requests[0].id}/respond`, {
            method: 'PATCH',
            data: {
                status: 'accepted',
            },
        });

        expect(['accepted', 'selected']).toContain(workerResponse.data.worker_request.status);

        const bookingDetail = await fetchBookingDetail(request, customer.token, bookingRequest.id);
        const createdBookingId = bookingDetail.booking?.id;

        expect(createdBookingId).toBeTruthy();

        moveBookingStartIntoPast(Number(createdBookingId));
        await completeWorkerBooking(request, worker.token, Number(createdBookingId));

        await login(page, customerUiUser);
        await page.goto(`/customer/bookings/${bookingRequest.id}`);
        await expect(page.getByTestId('booking-review-form')).toBeVisible();
        await page.getByTestId('booking-review-form').locator('button').nth(3).click();
        await page.getByTestId('booking-review-text').fill('Worker completed the service well and on time.');
        await page.getByTestId('booking-review-submit').click();

        const refreshedBookingDetail = await apiJson<{
            data: {
                booking: {
                    review: {
                        review: string;
                    } | null;
                };
            };
        }>(request, customer.token, `/api/customer/bookings/${bookingRequest.id}`);

        expect(refreshedBookingDetail.data.booking.review?.review).toBe('Worker completed the service well and on time.');

        await logout(page);
        await login(page, workerUiUser);
        await expect(page).toHaveURL(/\/worker\/dashboard$/);
    });
});
