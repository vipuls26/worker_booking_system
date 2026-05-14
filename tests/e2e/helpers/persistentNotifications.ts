import { expect, type Page } from '@playwright/test';

function unreadCountValue(text: string | null): number {
    if (!text) {
        return 0;
    }

    return Number.parseInt(text.replace('+', ''), 10) || 0;
}

export async function getUnreadNotificationCount(page: Page): Promise<number> {
    const badge = page.getByTestId('notification-unread-count');

    if (!await badge.isVisible().catch(() => false)) {
        return 0;
    }

    return unreadCountValue(await badge.textContent());
}

export async function expectRealtimeNotification(
    page: Page,
    options: {
        previousUnreadCount?: number;
        title: string | RegExp;
        message: string | RegExp;
    },
): Promise<void> {
    if (typeof options.previousUnreadCount === 'number' && options.previousUnreadCount < 30) {
        // Realtime delivery should increase the unread badge before the user opens the drawer.
        await expect.poll(async () => {
            return await getUnreadNotificationCount(page);
        }, {
            timeout: 20000,
        }).toBeGreaterThan(options.previousUnreadCount);
    }

    const notificationPanel = page.getByTestId('notification-panel');

    if (!await notificationPanel.isVisible().catch(() => false)) {
        await page.getByTestId('notification-toggle').click();
    }

    await expect(notificationPanel).toBeVisible();

    const matchingNotification = page.getByTestId('notification-item').filter({
        has: page.getByTestId('notification-item-title').filter({
            hasText: options.title,
        }),
    }).filter({
        has: page.getByTestId('notification-item-message').filter({
            hasText: options.message,
        }),
    }).first();

    await expect(matchingNotification).toBeVisible({
        timeout: 20000,
    });
}
