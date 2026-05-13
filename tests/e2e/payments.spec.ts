import { expect, test } from '@playwright/test';
import { loginByApi } from './helpers/auth';
import { apiJson, apiResponse } from './helpers/api';
import { uniqueReference } from './helpers/date';
import { completeWorkerBooking, createBookingRequest, fetchBookingDetail } from './helpers/bookings';
import { moveBookingStartIntoPast, onboardCustomer, onboardWorker } from './helpers/onboarding';
import { testUsers } from './helpers/users';

async function createCompletedBookingForPayment(
    request: Parameters<typeof apiJson>[0],
): Promise<{ customerToken: string; serviceRequestId: number }> {
    const adminSession = await loginByApi(request, testUsers.admin);
    const customer = await onboardCustomer(request, adminSession.token, 'Payment Customer');
    const worker = await onboardWorker(request, adminSession.token, { namePrefix: 'Payment Worker', serviceCount: 1 });
    const approvedService = worker.approvedServices[0];

    const createdRequest = await createBookingRequest(request, customer.token, {
        workerId: worker.user.id,
        serviceId: approvedService.serviceId,
        issueDescription: uniqueReference('Payment flow booking'),
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
        serviceRequestId: createdRequest.id,
    };
}

test.describe('Payments', () => {
    test('successful payment records the locked commission calculation', async ({ request }) => {
        const completedBooking = await createCompletedBookingForPayment(request);
        const bookingDetail = await fetchBookingDetail(request, completedBooking.customerToken, completedBooking.serviceRequestId);
        const lockedCommissionRate = Number(bookingDetail.booking?.commission_rate ?? bookingDetail.booking?.booking?.commission_rate ?? 0);

        const paymentPayload = await apiJson<{
            data: {
                payment: {
                    amount: string;
                    commission_rate: string;
                    platform_commission: string;
                    worker_earning: string;
                    status: string;
                };
                booking: {
                    booking: {
                        payment_status: string;
                    };
                };
            };
        }>(request, completedBooking.customerToken, `/api/customer/bookings/${completedBooking.serviceRequestId}/pay`, {
            method: 'POST',
            data: {
                provider: 'manual',
                transaction_reference: uniqueReference('E2E-PAY'),
            },
        });

        const paidAmount = Number(paymentPayload.data.payment.amount);
        const platformCommission = Number(paymentPayload.data.payment.platform_commission);
        const workerEarning = Number(paymentPayload.data.payment.worker_earning);
        const expectedPlatformCommission = Number((paidAmount * (lockedCommissionRate / 100)).toFixed(2));
        const expectedWorkerEarning = Number((paidAmount - expectedPlatformCommission).toFixed(2));

        expect(paymentPayload.data.payment.status).toBe('paid');
        expect(paidAmount).toBeGreaterThan(0);
        expect(Number(paymentPayload.data.payment.commission_rate)).toBe(lockedCommissionRate);
        expect(platformCommission).toBe(expectedPlatformCommission);
        expect(workerEarning).toBe(expectedWorkerEarning);
        expect(paymentPayload.data.booking.booking.payment_status).toBe('paid');
    });

    test('duplicate payment prevention rejects paying an already paid booking', async ({ request }) => {
        const completedBooking = await createCompletedBookingForPayment(request);

        await apiJson(request, completedBooking.customerToken, `/api/customer/bookings/${completedBooking.serviceRequestId}/pay`, {
            method: 'POST',
            data: {
                provider: 'manual',
                transaction_reference: uniqueReference('E2E-FIRST-PAY'),
            },
        });

        const response = await apiResponse(request, completedBooking.customerToken, `/api/customer/bookings/${completedBooking.serviceRequestId}/pay`, {
            method: 'POST',
            data: {
                provider: 'manual',
                transaction_reference: uniqueReference('E2E-DUPLICATE'),
            },
        });

        expect(response.status()).toBeGreaterThanOrEqual(400);
    });
});
