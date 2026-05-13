import { expect, test } from '@playwright/test';
import { login, loginByApi } from './helpers/auth';
import { apiResponse } from './helpers/api';
import { createBookingRequest, getWorkerForBooking } from './helpers/bookings';
import { futureDate } from './helpers/date';
import { testUsers } from './helpers/users';

test.describe('Security and authorization', () => {
    test('customer cannot access admin routes in the SPA', async ({ page }) => {
        await login(page, testUsers.customer);
        await page.goto('/admin/dashboard');

        await expect(page).toHaveURL(/\/customer\/dashboard$/);
    });

    test('unauthorized API access returns an authentication error', async ({ request }) => {
        const response = await request.fetch('/api/auth/me', {
            headers: {
                Accept: 'application/json',
            },
        });

        expect(response.status()).toBe(401);

        const payload = await response.json();
        expect(payload.message).toBe('Unauthenticated');
    });

    test('user cannot modify another user booking', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const otherCustomerSession = await loginByApi(request, testUsers.customerTwo);
        const worker = await getWorkerForBooking(request, customerSession.token);
        const booking = await createBookingRequest(request, customerSession.token, {
            workerId: worker.workerId,
            serviceId: worker.serviceId,
            bookingDate: futureDate(11),
            issueDescription: 'Booking ownership protection coverage.',
        });

        const response = await apiResponse(request, otherCustomerSession.token, `/api/customer/bookings/${booking.id}/cancel`, {
            method: 'PATCH',
            data: {
                cancelled_reason: 'Unauthorized cancellation attempt.',
            },
        });

        expect([403, 404]).toContain(response.status());
    });

    test('role-based access checks block worker endpoints for customers', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const response = await apiResponse(request, customerSession.token, '/api/worker/services');

        expect(response.status()).toBe(403);
    });
});
