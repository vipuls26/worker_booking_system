import { expect, test } from '@playwright/test';
import { apiJson } from './helpers/api';
import { login, logout, loginByApi } from './helpers/auth';
import { fetchBookingDetail, getWorkerForBooking } from './helpers/bookings';
import { futureDate } from './helpers/date';
import { acceptBookingFromWindow } from './helpers/persistentBookings';
import { testUsers } from './helpers/users';

type ServiceRequestSummary = {
    id: number;
    worker_requests: Array<{
        id: number;
        worker_id: number;
        status: string;
    }>;
};

async function createDirectRequest(
    request: Parameters<typeof apiJson>[0],
    customerToken: string,
    workerId: number,
    serviceId: number,
    bookingDate: string,
    startTime: string,
    endTime: string,
    issueDescription: string,
): Promise<ServiceRequestSummary> {
    const payload = await apiJson<{ data: { booking: ServiceRequestSummary } }>(request, customerToken, '/api/customer/bookings', {
        method: 'POST',
        expectedStatus: 201,
        data: {
            worker_id: workerId,
            service_id: serviceId,
            booking_date: bookingDate,
            start_time: startTime,
            end_time: endTime,
            address: '123 UI Concurrency Street, Ahmedabad',
            issue_description: issueDescription,
        },
    });

    return payload.data.booking;
}

test.describe('Concurrency UI', () => {
    test('customer UI shows overlapping worker slot as blocked after another customer confirms the booking', async ({ page, request }) => {
        const bookingDate = futureDate(24);
        const timeLabel = '09:00 - 10:00';
        const customerSession = await loginByApi(request, testUsers.customer);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const confirmedRequest = await createDirectRequest(
            request,
            customerSession.token,
            worker.workerId,
            worker.serviceId,
            bookingDate,
            '09:00',
            '10:00',
            'UI overlap booking that should block the slot.',
        );

        await login(page, testUsers.customer);
        await page.goto(`/customer/bookings/${confirmedRequest.id}`);
        await expect(page.getByText('Booking Details')).toBeVisible();
        await logout(page);

        await login(page, testUsers.worker);
        await acceptBookingFromWindow(page, {
            issueDescription: 'UI overlap booking that should block the slot.',
        });
        await logout(page);

        await login(page, testUsers.customerTwo);
        await page.goto('/customer/workers');
        await page.getByTestId('worker-card').filter({ hasText: 'E2E Worker' }).first().getByTestId('worker-view-link').click();
        await expect(page.getByTestId('worker-detail-page')).toBeVisible();

        await page.getByTestId('booking-date-input').fill(bookingDate);

        const blockedSlot = page.getByTestId('blocked-slot-button').filter({ hasText: timeLabel }).first();

        await expect(blockedSlot).toBeVisible();
        await expect(blockedSlot).toContainText(/booked|reserved|unavailable/i);
        await expect(page.getByTestId('available-slot-button').filter({ hasText: timeLabel })).toHaveCount(0);
    });

    test('customer booking detail UI shows awaiting-reschedule state and allows rescheduling the request', async ({ page, request }) => {
        const customerOneSession = await loginByApi(request, testUsers.customer);
        const customerTwoSession = await loginByApi(request, testUsers.customerTwo);
        const bookingDate = futureDate(25);
        const worker = await getWorkerForBooking(request, customerOneSession.token);

        const confirmedRequest = await createDirectRequest(
            request,
            customerOneSession.token,
            worker.workerId,
            worker.serviceId,
            bookingDate,
            '09:00',
            '10:00',
            'UI booking that should take the slot.',
        );

        await login(page, testUsers.customer);
        await page.goto(`/customer/bookings/${confirmedRequest.id}`);
        await expect(page.getByText('Booking Details')).toBeVisible();
        await logout(page);

        const awaitingRescheduleRequest = await createDirectRequest(
            request,
            customerTwoSession.token,
            worker.workerId,
            worker.serviceId,
            bookingDate,
            '09:00',
            '10:00',
            'UI booking that should require reschedule.',
        );

        await login(page, testUsers.customerTwo);
        await page.goto(`/customer/bookings/${awaitingRescheduleRequest.id}`);
        await expect(page.getByText('Booking Details')).toBeVisible();
        await logout(page);

        await login(page, testUsers.worker);
        await acceptBookingFromWindow(page, {
            issueDescription: 'UI booking that should take the slot.',
        });
        await logout(page);

        await login(page, testUsers.customerTwo);
        await expect.poll(async () => {
            await page.goto(`/customer/bookings/${awaitingRescheduleRequest.id}`);
            return await page.getByText('Request awaiting reschedule').first().textContent().catch(() => null);
        }).toContain('Request awaiting reschedule');

        await expect(page.getByText('Request awaiting reschedule')).toBeVisible();
        await expect(page.getByTestId('booking-reschedule-form')).toBeVisible();

        await page.getByTestId('booking-reschedule-date').fill(bookingDate);
        await page.getByTestId('booking-reschedule-start-time').fill('11:00');
        await page.getByTestId('booking-reschedule-end-time').fill('12:00');
        await page.getByTestId('booking-reschedule-submit').click();

        await expect(page.getByText('Booking request rescheduled')).toBeVisible();
        await expect(page.getByText('Request awaiting reschedule')).toHaveCount(0);

        const refreshedBooking = await fetchBookingDetail(request, customerTwoSession.token, awaitingRescheduleRequest.id);

        expect(refreshedBooking.start_time).toBe('11:00:00');
        expect(refreshedBooking.end_time).toBe('12:00:00');
        expect(refreshedBooking.worker_requests[0]?.status).toBe('pending');
    });
});
