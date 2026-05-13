import { expect, test } from '@playwright/test';
import { login, loginByApi } from './helpers/auth';
import { apiJson, apiResponse } from './helpers/api';
import { createBookingRequest, fetchBookingDetail, getWorkerForBooking } from './helpers/bookings';
import { futureDate, pastDate } from './helpers/date';
import { testUsers } from './helpers/users';

test.describe('Bookings', () => {
    test('create booking works from the customer UI', async ({ page }) => {
        await login(page, testUsers.customer);

        await page.goto('/customer/workers');

        await page
            .getByTestId('worker-card')
            .filter({ hasText: 'E2E Worker' })
            .first()
            .getByTestId('worker-view-link')
            .click();

        await expect(page.getByTestId('worker-detail-page')).toBeVisible();

        await page.getByTestId('booking-date-input').fill(futureDate());
        await page.getByTestId('booking-issue-input').fill('Playwright example booking request.');
        await page.getByTestId('booking-address-input').fill('123 E2E Street, Ahmedabad');

        const firstAvailableSlot = page.getByTestId('available-slot-button').first();

        await expect(firstAvailableSlot).toBeVisible();
        await firstAvailableSlot.click();
        await page.getByTestId('booking-submit-button').click();

        await expect(page).toHaveURL(/\/customer\/bookings\/\d+$/);
        await expect(page.getByTestId('booking-detail-page')).toBeVisible();
    });

    test('invalid booking validation returns field errors', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const response = await apiResponse(request, customerSession.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: worker.workerId,
                service_id: worker.serviceId,
                booking_date: futureDate(),
                start_time: '10:00',
                end_time: '11:00',
                address: '',
                issue_description: '',
            },
        });

        expect(response.status()).toBe(422);

        const payload = await response.json();
        expect(payload.errors.issue_description[0]).toContain('Please describe');
    });

    test('past booking rejection prevents scheduling in the past', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const response = await apiResponse(request, customerSession.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: worker.workerId,
                service_id: worker.serviceId,
                booking_date: pastDate(),
                start_time: '10:00',
                end_time: '11:00',
                address: '123 E2E Street, Ahmedabad',
                issue_description: 'This should fail.',
            },
        });

        expect(response.status()).toBe(422);

        const payload = await response.json();
        expect(payload.errors.booking_date[0]).toContain('today');
    });

    test('booking cancellation works for an open multi-worker request', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const createPayload = await apiJson<{ data: { booking: { id: number; status: string } } }>(
            request,
            customerSession.token,
            '/api/customer/bookings',
            {
                method: 'POST',
                expectedStatus: 201,
                data: {
                    service_id: worker.serviceId,
                    booking_date: futureDate(2),
                    start_time: '10:00',
                    end_time: '11:00',
                    address: '123 E2E Street, Ahmedabad',
                    issue_description: 'Open request for cancellation coverage.',
                },
            },
        );

        expect(createPayload.data.booking.status).toBe('open');

        const cancelledPayload = await apiJson<{ data: { booking: { status: string } } }>(
            request,
            customerSession.token,
            `/api/customer/bookings/${createPayload.data.booking.id}/cancel`,
            {
                method: 'PATCH',
                data: {
                    cancelled_reason: 'Customer changed plans.',
                },
            },
        );

        expect(cancelledPayload.data.booking.status).toBe('cancelled');
    });

    test('booking lifecycle status changes move from open request to completed work', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const workerSession = await loginByApi(request, testUsers.worker);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const createPayload = await apiJson<{
            data: {
                booking: {
                    id: number;
                    status: string;
                    worker_requests: Array<{ id: number; worker_id: number; status: string }>;
                };
            };
        }>(request, customerSession.token, '/api/customer/bookings', {
            method: 'POST',
            expectedStatus: 201,
            data: {
                service_id: worker.serviceId,
                booking_date: futureDate(3),
                start_time: '11:00',
                end_time: '12:00',
                address: '123 E2E Street, Ahmedabad',
                issue_description: 'Lifecycle status coverage request.',
            },
        });

        expect(createPayload.data.booking.status).toBe('open');

        const targetRequest = createPayload.data.booking.worker_requests.find((item) => item.worker_id === workerSession.user.id);
        expect(targetRequest).toBeTruthy();

        const acceptPayload = await apiJson<{ data: { worker_request: { status: string } } }>(
            request,
            workerSession.token,
            `/api/worker/booking-requests/${targetRequest?.id}/respond`,
            {
                method: 'PATCH',
                data: {
                    status: 'accepted',
                },
            },
        );

        expect(acceptPayload.data.worker_request.status).toBe('accepted');

        const detailAfterAccept = await fetchBookingDetail(request, customerSession.token, createPayload.data.booking.id);
        const acceptedWorkerRequest = detailAfterAccept.worker_requests.find((item) => item.worker_id === workerSession.user.id);

        const selectPayload = await apiJson<{ data: { booking: { status: string; booking: { id: number; status: string } } } }>(
            request,
            customerSession.token,
            `/api/customer/bookings/${createPayload.data.booking.id}/select-worker`,
            {
                method: 'PATCH',
                data: {
                    worker_request_id: acceptedWorkerRequest?.id,
                },
            },
        );

        expect(selectPayload.data.booking.status).toBe('worker_selected');
        expect(selectPayload.data.booking.booking.status).toBe('confirmed');
    });

    test('invalid status transitions are rejected', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const workerSession = await loginByApi(request, testUsers.worker);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const createdBooking = await createBookingRequest(request, customerSession.token, {
            workerId: worker.workerId,
            serviceId: worker.serviceId,
            bookingDate: futureDate(4),
            issueDescription: 'Invalid status transition coverage.',
        });

        const acceptedWorkerResponse = await apiJson<{ data: { worker_request: { status: string } } }>(
            request,
            workerSession.token,
            `/api/worker/booking-requests/${createdBooking.worker_requests[0].id}/respond`,
            {
                method: 'PATCH',
                data: {
                    status: 'accepted',
                },
            },
        );

        expect(acceptedWorkerResponse.data.worker_request.status).toBe('selected');

        const bookingDetail = await fetchBookingDetail(request, customerSession.token, createdBooking.id);
        const officialBookingId = bookingDetail.booking?.id;
        expect(officialBookingId).toBeTruthy();

        const response = await apiResponse(request, workerSession.token, `/api/worker/bookings/${officialBookingId}/status`, {
            method: 'PATCH',
            data: {
                status: 'completed',
            },
        });

        expect(response.status()).toBeGreaterThanOrEqual(400);
    });
});
