import { expect, test } from '@playwright/test';
import { login, loginByApi } from './helpers/auth';
import { apiJson } from './helpers/api';
import { getWorkerForBooking } from './helpers/bookings';
import { futureDate, uniqueReference } from './helpers/date';
import { testUsers } from './helpers/users';

test.describe('Notifications', () => {
    test('realtime booking notification appears for the worker', async ({ page, request }) => {
        test.skip(!process.env.E2E_ENABLE_REVERB, 'Realtime notification coverage needs a running Reverb server.');

        const customerSession = await loginByApi(request, testUsers.customer);
        const worker = await getWorkerForBooking(request, customerSession.token);

        await login(page, testUsers.worker);
        await page.goto('/notifications');

        const initialCount = await page.getByTestId('notifications-page-item').count();

        await apiJson(request, customerSession.token, '/api/customer/bookings', {
            method: 'POST',
            expectedStatus: 201,
            data: {
                service_id: worker.serviceId,
                booking_date: futureDate(10),
                start_time: '15:00',
                end_time: '16:00',
                address: '123 E2E Street, Ahmedabad',
                issue_description: uniqueReference('Realtime notification request'),
            },
        });

        await expect.poll(async () => page.getByTestId('notifications-page-item').count()).toBeGreaterThan(initialCount);
    });

    test('notification center can mark as read and clear all', async ({ page }) => {
        await login(page, testUsers.customer);
        await page.goto('/notifications');

        await expect(page.getByTestId('notifications-page')).toBeVisible();
        const initialCount = await page.getByTestId('notifications-page-item').count();
        await page.getByTestId('notifications-page-mark-all').click();
        await expect(page.getByTestId('notifications-page-mark-all')).toBeDisabled();
        await expect(page.getByTestId('notifications-page-item')).toHaveCount(initialCount);

        await page.getByTestId('notifications-page-open-clear-all').click();
        await page.getByTestId('confirm-dialog-confirm').click();
        await expect(page.getByTestId('notifications-page-item')).toHaveCount(0);
        await expect(page.getByTestId('notifications-page-open-clear-all')).toBeDisabled();
    });

    test('notification dropdown closes after clear all', async ({ page }) => {
        await login(page, testUsers.admin);

        await page.getByTestId('notification-toggle').click();
        await expect(page.getByTestId('notification-panel')).toBeVisible();

        await page.getByTestId('notification-clear-all').click();
        await expect(page.getByTestId('notification-panel')).toBeHidden();
    });
});
