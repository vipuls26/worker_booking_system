import { expect, test } from '@playwright/test';
import { loginByApi } from './helpers/auth';
import { apiJson, apiResponse } from './helpers/api';
import { uniqueReference } from './helpers/date';
import { completeWorkerBooking, createBookingRequest, fetchBookingDetail } from './helpers/bookings';
import { moveBookingStartIntoPast, onboardCustomer, onboardWorker } from './helpers/onboarding';
import { testUsers } from './helpers/users';

async function createCompletedBookingForReview(
    request: Parameters<typeof apiJson>[0],
): Promise<{ customerToken: string; bookingId: number }> {
    const adminSession = await loginByApi(request, testUsers.admin);
    const customer = await onboardCustomer(request, adminSession.token, 'Review Customer');
    const worker = await onboardWorker(request, adminSession.token, { namePrefix: 'Review Worker', serviceCount: 1 });
    const approvedService = worker.approvedServices[0];

    const createdRequest = await createBookingRequest(request, customer.token, {
        workerId: worker.user.id,
        serviceId: approvedService.serviceId,
        issueDescription: uniqueReference('Review flow booking'),
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
    const officialBookingId = bookingDetail.booking?.id;
    expect(officialBookingId).toBeTruthy();

    moveBookingStartIntoPast(Number(officialBookingId));
    await completeWorkerBooking(request, worker.token, Number(officialBookingId));

    return {
        customerToken: customer.token,
        bookingId: Number(officialBookingId),
    };
}

test.describe('Reviews', () => {
    test('customer can review a worker and a duplicate review is rejected', async ({ request }) => {
        const completedBooking = await createCompletedBookingForReview(request);

        const firstResponse = await apiResponse(request, completedBooking.customerToken, `/api/customer/bookings/${completedBooking.bookingId}/review`, {
            method: 'POST',
            data: {
                rating: 5,
                review: uniqueReference('Great review'),
            },
        });

        expect(firstResponse.status()).toBe(201);

        const duplicateResponse = await apiResponse(request, completedBooking.customerToken, `/api/customer/bookings/${completedBooking.bookingId}/review`, {
            method: 'POST',
            data: {
                rating: 4,
                review: 'Duplicate review attempt.',
            },
        });

        expect(duplicateResponse.status()).toBeGreaterThanOrEqual(400);
    });

    test('invalid rating validation rejects out-of-range review values', async ({ request }) => {
        const completedBooking = await createCompletedBookingForReview(request);

        const response = await apiResponse(request, completedBooking.customerToken, `/api/customer/bookings/${completedBooking.bookingId}/review`, {
            method: 'POST',
            data: {
                rating: 6,
                review: 'This rating should fail.',
            },
        });

        expect(response.status()).toBe(422);
    });
});
