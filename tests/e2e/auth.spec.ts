import { expect, test } from '@playwright/test';
import { login, logout } from './helpers/auth';
import { testUsers } from './helpers/users';

test.describe('Auth', () => {
    test('customer login works with the real form', async ({ page }) => {
        await login(page, testUsers.customer);
        await expect(page.getByTestId('customer-dashboard-page')).toBeVisible();
    });

    test('worker login routes to the worker dashboard', async ({ page }) => {
        await login(page, testUsers.worker);
        await expect(page).toHaveURL(/\/worker\/dashboard$/);
    });

    test('admin login routes to the admin dashboard', async ({ page }) => {
        await login(page, testUsers.admin);
        await expect(page).toHaveURL(/\/admin\/dashboard$/);
    });

    test('invalid login stays on the login page', async ({ page }) => {
        await page.goto('/login');
        await page.getByTestId('login-email').fill('missing@example.com');
        await page.getByTestId('login-password').fill('wrong-password');
        await page.getByTestId('login-submit').click();

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByText('Invalid credentials')).toBeVisible();
    });

    test('blocked user login redirects to the blocked account page', async ({ page }) => {
        await login(page, testUsers.blockedCustomer);
        await expect(page).toHaveURL(/\/account\/blocked$/);
        await expect(page.getByTestId('blocked-account-page')).toBeVisible();
    });

    test('logout clears the authenticated browser session', async ({ page }) => {
        await login(page, testUsers.customer);
        await logout(page);
    });

    test('session persistence survives a full page reload', async ({ page }) => {
        await login(page, testUsers.customer);
        await page.reload();

        await expect(page).toHaveURL(/\/customer\/dashboard$/);
        await expect(page.getByTestId('customer-dashboard-page')).toBeVisible();
    });
});
