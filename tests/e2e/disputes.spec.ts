import { expect, test } from '@playwright/test';
import { loginByApi } from './helpers/auth';
import { apiJson } from './helpers/api';
import { uniqueReference } from './helpers/date';
import { createBookingRequest, fetchBookingDetail } from './helpers/bookings';
import { onboardCustomer, onboardWorker } from './helpers/onboarding';
import { testUsers } from './helpers/users';

async function createConfirmedBookingForDispute(
    request: Parameters<typeof apiJson>[0],
): Promise<{ adminToken: string; customerToken: string; officialBookingId: number }> {
    const adminSession = await loginByApi(request, testUsers.admin);
    const customer = await onboardCustomer(request, adminSession.token, 'Dispute Customer');
    const worker = await onboardWorker(request, adminSession.token, { namePrefix: 'Dispute Worker', serviceCount: 1 });
    const approvedService = worker.approvedServices[0];

    const createdRequest = await createBookingRequest(request, customer.token, {
        workerId: worker.user.id,
        serviceId: approvedService.serviceId,
        issueDescription: uniqueReference('Dispute flow booking'),
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
    expect(bookingDetail.booking?.status).toBe('confirmed');
    expect(bookingDetail.booking?.id).toBeTruthy();

    return {
        adminToken: adminSession.token,
        customerToken: customer.token,
        officialBookingId: Number(bookingDetail.booking?.id),
    };
}

test.describe('Disputes', () => {
    test('customer can create a dispute and admin can resolve it with history tracking', async ({ request }) => {
        const bookingScenario = await createConfirmedBookingForDispute(request);

        const disputePayload = await apiJson<{
            data: {
                dispute: {
                    id: number;
                    status: string;
                };
            };
        }>(request, bookingScenario.customerToken, '/api/disputes', {
            method: 'POST',
            expectedStatus: 201,
            data: {
                booking_id: bookingScenario.officialBookingId,
                category: 'service_issue',
                title: uniqueReference('Dispute'),
                description: 'Playwright dispute coverage.',
            },
        });

        expect(disputePayload.data.dispute.status).toBe('open');

        const resolvedPayload = await apiJson<{
            data: {
                dispute: {
                    id: number;
                    status: string;
                    timeline: Array<{ from_status: string; to_status: string }>;
                };
            };
        }>(request, bookingScenario.adminToken, `/api/admin/disputes/${disputePayload.data.dispute.id}`, {
            method: 'PATCH',
            data: {
                status: 'resolved',
                resolution_note: 'Resolved during Playwright coverage.',
            },
        });

        expect(resolvedPayload.data.dispute.status).toBe('resolved');
        expect(resolvedPayload.data.dispute.timeline.length).toBeGreaterThan(0);
    });
});
