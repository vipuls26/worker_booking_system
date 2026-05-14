import { expect, test, type APIRequestContext, type Page } from '@playwright/test';
import { apiJson, apiResponse } from './helpers/api';
import { loginByApi, loginWithCredentials, logout } from './helpers/auth';
import { completeWorkerBooking, createBookingRequest, fetchBookingDetail } from './helpers/bookings';
import { futureDate, uniqueReference } from './helpers/date';
import { approveWorkerService, createWorkerService, moveBookingStartIntoPast, onboardCustomer, onboardWorker, type OnboardedWorker, type RegisteredUser } from './helpers/onboarding';
import { testUsers } from './helpers/users';

type LoginAccount = {
    email?: string;
    password: string;
    dashboardPath: string;
    user?: {
        email: string;
    };
};

type ServiceRequestSummary = {
    id: number;
    status: string;
    worker_requests: Array<{
        id: number;
        worker_id: number;
        status: string;
    }>;
};

type AdminServiceResponse = {
    data: {
        service: {
            id: number;
            name: string;
        };
    };
};

async function loginAccount(page: Page, account: LoginAccount, expectedPath = account.dashboardPath): Promise<void> {
    const email = account.email ?? account.user?.email;

    expect(email).toBeTruthy();

    await loginWithCredentials(page, {
        email,
        password: account.password,
        expectedPath,
    });
}

async function updateWorkerProfile(
    request: APIRequestContext,
    workerToken: string,
    overrides: {
        city: string;
        experienceYears: number;
        bio: string;
        address?: string;
    },
): Promise<void> {
    const profilePayload = await apiJson<{
        data: {
            profile: {
                address: string | null;
                city: string | null;
                experience_years: number;
                bio: string | null;
                user?: {
                    phone: string;
                };
            };
        };
    }>(request, workerToken, '/api/worker/profile');

    const currentProfile = profilePayload.data.profile;
    const response = await apiResponse(request, workerToken, '/api/worker/profile', {
        method: 'POST',
        data: {
            phone: currentProfile.user?.phone || `70000${Math.floor(Math.random() * 90000)}`,
            city: overrides.city,
            experience_years: overrides.experienceYears,
            bio: overrides.bio,
            address: overrides.address ?? currentProfile.address ?? 'Updated by Playwright',
            skills: [],
        },
    });

    expect(response.status()).toBe(200);
}

async function blockUserAsAdmin(
    request: APIRequestContext,
    adminToken: string,
    userId: number,
    blockType: 'partially_blocked' | 'fully_blocked' = 'fully_blocked',
): Promise<void> {
    const response = await apiResponse(request, adminToken, `/api/admin/users/${userId}/block`, {
        method: 'PATCH',
        data: {
            block_type: blockType,
        },
    });

    expect(response.status()).toBe(200);
}

async function createAutoMatchedRequest(
    request: APIRequestContext,
    customerToken: string,
    serviceId: number,
    issueDescription: string,
): Promise<ServiceRequestSummary> {
    const payload = await apiJson<{ data: { booking: ServiceRequestSummary } }>(request, customerToken, '/api/customer/bookings', {
        method: 'POST',
        expectedStatus: 201,
        data: {
            service_id: serviceId,
            booking_date: futureDate(14),
            start_time: '10:00',
            end_time: '11:00',
            address: '456 Workflow Street, Ahmedabad',
            issue_description: issueDescription,
        },
    });

    return payload.data.booking;
}

async function createUniqueServiceForTest(
    request: APIRequestContext,
    adminToken: string,
    namePrefix: string,
): Promise<{ id: number; name: string }> {
    const payload = await apiJson<AdminServiceResponse>(request, adminToken, '/api/admin/services', {
        method: 'POST',
        expectedStatus: 201,
        data: {
            name: uniqueReference(namePrefix),
            description: 'Playwright unique service for isolated UI workflow coverage.',
            icon: 'pi-wrench',
            is_active: true,
        },
    });

    return payload.data.service;
}

async function attachApprovedServiceToWorker(
    request: APIRequestContext,
    adminToken: string,
    workerToken: string,
    serviceId: number,
    price: string,
): Promise<void> {
    const workerServiceId = await createWorkerService(request, workerToken, {
        serviceId,
        price,
        description: uniqueReference('UI workflow worker service'),
    });

    await approveWorkerService(request, adminToken, workerServiceId);
}

async function createCompletedBookingScenario(
    request: APIRequestContext,
    label: string,
): Promise<{
    customer: RegisteredUser;
    worker: OnboardedWorker;
    serviceRequestId: number;
    officialBookingId: number;
}> {
    const adminSession = await loginByApi(request, testUsers.admin);
    const customer = await onboardCustomer(request, adminSession.token, `${label} Customer`);
    const worker = await onboardWorker(request, adminSession.token, { namePrefix: `${label} Worker`, serviceCount: 1 });

    const createdRequest = await createBookingRequest(request, customer.token, {
        workerId: worker.user.id,
        serviceId: worker.approvedServices[0].serviceId,
        bookingDate: futureDate(12),
        issueDescription: `${label} issue ${uniqueReference('booking')}`,
    });

    const workerRequestId = createdRequest.worker_requests[0]?.id;
    expect(workerRequestId).toBeTruthy();

    const acceptancePayload = await apiJson<{ data: { worker_request: { status: string } } }>(
        request,
        worker.token,
        `/api/worker/booking-requests/${workerRequestId}/respond`,
        {
            method: 'PATCH',
            data: {
                status: 'accepted',
            },
        },
    );

    expect(acceptancePayload.data.worker_request.status).toBe('selected');

    const bookingDetail = await fetchBookingDetail(request, customer.token, createdRequest.id);
    const officialBookingId = Number(bookingDetail.booking?.id);

    expect(officialBookingId).toBeGreaterThan(0);

    moveBookingStartIntoPast(officialBookingId);
    await completeWorkerBooking(request, worker.token, officialBookingId);

    return {
        customer,
        worker,
        serviceRequestId: createdRequest.id,
        officialBookingId,
    };
}

test.describe('Additional UI workflows', () => {
    test('book again UI prefills the worker request and lets the customer submit a follow-up booking', async ({ page, request }) => {
        const scenario = await createCompletedBookingScenario(request, 'Book Again');

        await loginAccount(page, scenario.customer);
        await page.goto(`/customer/bookings/${scenario.serviceRequestId}`);

        await expect(page.getByTestId('booking-detail-page')).toBeVisible();
        await expect(page.getByTestId('booking-book-again-button')).toBeVisible();

        await page.getByTestId('booking-book-again-button').click();

        await expect(page.getByTestId('worker-detail-page')).toBeVisible();
        await expect(page).toHaveURL(new RegExp(`/customer/workers/${scenario.worker.user.id}`));
        await expect.poll(() => new URL(page.url()).searchParams.get('book_again_from')).toBeTruthy();

        await expect(page.getByTestId('booking-address-input')).not.toHaveValue('');
        await expect(page.getByTestId('booking-issue-input')).not.toHaveValue('');

        if (await page.getByTestId('booking-submit-button').isDisabled()) {
            await page.getByTestId('available-slot-button').first().click();
        }

        await Promise.all([
            page.waitForURL(/\/customer\/bookings\/\d+$/),
            page.getByTestId('booking-submit-button').click(),
        ]);
        await expect(page.getByTestId('booking-detail-page')).toBeVisible();
    });

    test('customer can choose one worker from multiple accepted workers in the booking detail UI', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const adminToken = adminSession.token;

        const customer = await onboardCustomer(request, adminToken, 'Selection Customer');
        const firstWorker = await onboardWorker(request, adminToken, { namePrefix: 'Selected Worker A', serviceCount: 1 });
        const secondWorker = await onboardWorker(request, adminToken, { namePrefix: 'Selected Worker B', serviceCount: 1 });
        const uniqueService = await createUniqueServiceForTest(request, adminToken, 'Worker selection service');
        await attachApprovedServiceToWorker(request, adminToken, firstWorker.token, uniqueService.id, '680');
        await attachApprovedServiceToWorker(request, adminToken, secondWorker.token, uniqueService.id, '720');
        const issueDescription = uniqueReference('Multiple accepted workers');

        const createdRequest = await createAutoMatchedRequest(request, customer.token, uniqueService.id, issueDescription);

        const firstWorkerRequest = createdRequest.worker_requests.find((item) => item.worker_id === firstWorker.user.id);
        const secondWorkerRequest = createdRequest.worker_requests.find((item) => item.worker_id === secondWorker.user.id);

        expect(firstWorkerRequest).toBeTruthy();
        expect(secondWorkerRequest).toBeTruthy();

        await apiJson(request, firstWorker.token, `/api/worker/booking-requests/${firstWorkerRequest?.id}/respond`, {
            method: 'PATCH',
            data: {
                status: 'accepted',
            },
        });

        await apiJson(request, secondWorker.token, `/api/worker/booking-requests/${secondWorkerRequest?.id}/respond`, {
            method: 'PATCH',
            data: {
                status: 'accepted',
            },
        });

        await loginAccount(page, customer);
        await page.goto(`/customer/bookings/${createdRequest.id}`);

        const selectedWorkerCard = page.locator('article').filter({ hasText: firstWorker.name }).first();
        await expect(selectedWorkerCard).toBeVisible();
        await expect(page.getByTestId(`booking-select-worker-${firstWorkerRequest?.id}`)).toBeVisible();
        await expect(page.getByTestId(`booking-select-worker-${secondWorkerRequest?.id}`)).toBeVisible();

        await page.getByTestId(`booking-select-worker-${firstWorkerRequest?.id}`).click();
        await expect(page.getByTestId('booking-status-tracker')).toContainText(/worker selected/i);
        await expect(selectedWorkerCard).toContainText(/selected/i);

        await expect.poll(async () => {
            const updatedBooking = await fetchBookingDetail(request, customer.token, createdRequest.id);

            return {
                status: updatedBooking.status,
                bookingStatus: updatedBooking.booking?.status,
            };
        }).toEqual({
            status: 'worker_selected',
            bookingStatus: 'confirmed',
        });
    });

    test('blocked customer can submit an unblock request and admin can approve it through the UI', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const adminToken = adminSession.token;

        const customer = await onboardCustomer(request, adminToken, 'Blocked Customer');
        await blockUserAsAdmin(request, adminToken, customer.user.id, 'partially_blocked');

        await loginAccount(page, customer);
        await page.goto('/account/blocked');
        await expect(page.getByTestId('blocked-account-page')).toBeVisible();

        await page.getByTestId('blocked-account-reason').fill('Please review my account and restore access.');
        await page.getByTestId('blocked-account-submit').click();
        await expect(page.getByTestId('blocked-account-latest-request')).toContainText(/pending/i);

        await page.getByTestId('blocked-account-logout').click();
        await expect(page).toHaveURL(/\/login$/);

        await loginAccount(page, testUsers.admin);
        await page.goto('/admin/unblock-requests');
        await expect(page.getByTestId('admin-unblock-requests-page')).toBeVisible();

        const requestRow = page.locator('[data-testid^="admin-unblock-request-row-"]').filter({
            hasText: customer.user.email,
        }).first();

        await expect(requestRow).toBeVisible();
        await requestRow.getByRole('button', { name: 'Approve' }).click();
        await expect(page.getByTestId('admin-unblock-review-modal')).toBeVisible();
        await page.getByTestId('admin-unblock-admin-note').fill('Access restored after manual review.');
        await page.getByTestId('admin-unblock-review-submit').click();
        await expect(requestRow).toHaveCount(0);

        await logout(page);

        await loginAccount(page, customer);
        await expect(page).toHaveURL(/\/customer\/dashboard$/);
    });

    test('customer worker search UI supports filtering by city and sorting by experience', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const adminToken = adminSession.token;

        const customer = await onboardCustomer(request, adminToken, 'Search Customer');
        const juniorWorker = await onboardWorker(request, adminToken, { namePrefix: 'Search Junior Worker', serviceCount: 1 });
        const seniorWorker = await onboardWorker(request, adminToken, { namePrefix: 'Search Senior Worker', serviceCount: 1 });
        const targetCity = uniqueReference('Surat');
        const uniqueService = await createUniqueServiceForTest(request, adminToken, 'Worker search service');

        await attachApprovedServiceToWorker(request, adminToken, juniorWorker.token, uniqueService.id, '630');
        await attachApprovedServiceToWorker(request, adminToken, seniorWorker.token, uniqueService.id, '910');

        await updateWorkerProfile(request, juniorWorker.token, {
            city: targetCity,
            experienceYears: 2,
            bio: 'Junior worker for customer search coverage.',
        });

        await updateWorkerProfile(request, seniorWorker.token, {
            city: targetCity,
            experienceYears: 12,
            bio: 'Senior worker for customer search coverage.',
        });

        await loginAccount(page, customer);
        await page.goto('/customer/workers');
        await expect(page.getByTestId('worker-listing-page')).toBeVisible();

        await page.locator('#request_service_id').selectOption(String(uniqueService.id));
        await page.getByTestId('worker-listing-toggle-filters').click();
        await page.getByTestId('worker-listing-city-filter').fill(targetCity);
        await page.getByTestId('worker-listing-date-filter').fill(futureDate(16));
        await page.getByTestId('worker-listing-time-filter').fill('10:00');
        await page.getByTestId('worker-listing-sort-filter').selectOption('experience');

        await expect.poll(async () => {
            const visibleNames = await page.getByTestId('worker-card').locator('h2').allTextContents();
            const juniorIndex = visibleNames.findIndex((name) => name.includes(juniorWorker.name));
            const seniorIndex = visibleNames.findIndex((name) => name.includes(seniorWorker.name));

            return {
                visibleNames,
                juniorIndex,
                seniorIndex,
            };
        }).toMatchObject({
            juniorIndex: expect.any(Number),
            seniorIndex: expect.any(Number),
        });

        await expect.poll(async () => {
            const visibleNames = await page.getByTestId('worker-card').locator('h2').allTextContents();
            const juniorIndex = visibleNames.findIndex((name) => name.includes(juniorWorker.name));
            const seniorIndex = visibleNames.findIndex((name) => name.includes(seniorWorker.name));

            if (juniorIndex === -1 || seniorIndex === -1) {
                return 'missing';
            }

            return seniorIndex < juniorIndex ? 'sorted' : 'unsorted';
        }).toBe('sorted');
    });
});
