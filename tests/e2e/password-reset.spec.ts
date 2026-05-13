import { expect, test } from '@playwright/test';
import { loginWithCredentials, loginByApi } from './helpers/auth';
import { clearMailpitInbox, waitForPasswordResetEmail } from './helpers/mailpit';
import { onboardCustomer } from './helpers/onboarding';
import { testUsers } from './helpers/users';

test.describe('Password reset', () => {
    test('customer can request a password reset email, open the Mailpit link, reset the password, and sign in with the new password only', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const customer = await onboardCustomer(request, adminSession.token, 'Reset Mailpit Customer');
        const newPassword = 'new-password-123';

        await clearMailpitInbox(request);

        await page.goto('/forgot-password');
        await expect(page.getByTestId('forgot-password-form')).toBeVisible();

        await page.getByTestId('forgot-password-email').fill(customer.user.email);
        await page.getByTestId('forgot-password-submit').click();

        await expect(page.getByTestId('forgot-password-success')).toBeVisible();

        const { resetLink } = await waitForPasswordResetEmail(request, customer.user.email);

        expect(resetLink).toContain('/reset-password?');
        expect(resetLink).toContain(`email=${encodeURIComponent(customer.user.email)}`);

        await page.goto(resetLink);
        await expect(page.getByTestId('reset-password-form')).toBeVisible();

        await page.getByTestId('reset-password-new-password').fill(newPassword);
        await page.getByTestId('reset-password-confirm-password').fill(newPassword);
        await page.getByTestId('reset-password-submit').click();

        await expect(page).toHaveURL(/\/login$/);

        await page.getByTestId('login-email').fill(customer.user.email);
        await page.getByTestId('login-password').fill(customer.password);
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/login$/);
        await expect.poll(async () => {
            return await page.evaluate(() => localStorage.getItem('auth_token'));
        }).toBeNull();

        await loginWithCredentials(page, {
            email: customer.user.email,
            password: newPassword,
            expectedPath: customer.dashboardPath,
        });
    });

    test('invalid and reused reset tokens are rejected', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const customer = await onboardCustomer(request, adminSession.token, 'Reset Token Customer');

        await clearMailpitInbox(request);

        await page.goto('/forgot-password');
        await page.getByTestId('forgot-password-email').fill(customer.user.email);
        await page.getByTestId('forgot-password-submit').click();
        await expect(page.getByTestId('forgot-password-success')).toBeVisible();

        const { resetLink } = await waitForPasswordResetEmail(request, customer.user.email);

        await page.goto(resetLink);
        await page.getByTestId('reset-password-new-password').fill('fresh-password-123');
        await page.getByTestId('reset-password-confirm-password').fill('fresh-password-123');
        await page.getByTestId('reset-password-submit').click();
        await expect(page).toHaveURL(/\/login$/);

        await page.goto(resetLink);
        await page.getByTestId('reset-password-new-password').fill('another-password-123');
        await page.getByTestId('reset-password-confirm-password').fill('another-password-123');
        await page.getByTestId('reset-password-submit').click();
        await expect(page).toHaveURL(/\/reset-password\?/);

        await page.goto(`/reset-password?token=invalid-token&email=${encodeURIComponent(customer.user.email)}`);
        await page.getByTestId('reset-password-new-password').fill('invalid-password-123');
        await page.getByTestId('reset-password-confirm-password').fill('invalid-password-123');
        await page.getByTestId('reset-password-submit').click();
        await expect(page).toHaveURL(/\/reset-password\?/);
    });
});
