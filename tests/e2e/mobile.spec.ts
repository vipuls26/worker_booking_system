import { expect, test } from '@playwright/test';
import { login } from './helpers/auth';
import { futureDate } from './helpers/date';
import { testUsers } from './helpers/users';

test.describe('Mobile responsiveness', () => {
    test.beforeEach(async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
    });

    test('mobile navbar stays usable for customer navigation', async ({ page }) => {
        await login(page, testUsers.customer);
        await expect(page.getByTestId('mobile-navbar')).toBeVisible();

        await page.getByTestId('mobile-nav-bookings').click();
        await expect(page).toHaveURL(/\/customer\/bookings$/);
    });

    test('mobile booking flow still creates a booking', async ({ page }) => {
        await login(page, testUsers.customer);
        await page.goto('/customer/workers');
        await page
            .getByTestId('worker-card')
            .filter({ hasText: 'E2E Worker' })
            .first()
            .getByTestId('worker-view-link')
            .click();

        await page.getByTestId('booking-date-input').fill(futureDate(13));
        await page.getByTestId('booking-address-input').fill('123 E2E Street, Ahmedabad');
        await page.getByTestId('booking-issue-input').fill('Mobile booking flow coverage.');
        await page.getByTestId('available-slot-button').first().click();
        await page.getByTestId('booking-submit-button').click();

        await expect(page.getByTestId('booking-detail-page')).toBeVisible();
    });

    test('mobile notifications dropdown opens correctly', async ({ page }) => {
        await login(page, testUsers.customer);
        await page.getByTestId('notification-toggle').click();

        await expect(page.getByTestId('notification-panel')).toBeVisible();
    });
});
