import { expect } from '@playwright/test';
import { login } from './auth';
import type { PersistentWindow } from './persistentProfiles';

export async function ensurePersistentLogin(window: PersistentWindow): Promise<void> {
    const { page, user } = window;

    // Each window gets its own browser session, so the dashboard route is the quickest truth check.
    await page.goto(user.dashboardPath);

    if (await page.getByTestId('login-form').isVisible().catch(() => false)) {
        await login(page, user);
    }

    const authenticatedLayout = user.role === 'admin'
        ? page.getByTestId('admin-layout')
        : page.getByTestId('dashboard-layout');

    await expect(authenticatedLayout).toBeVisible();
}
