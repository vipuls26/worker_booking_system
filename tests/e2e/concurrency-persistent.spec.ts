import { expect, test } from '@playwright/test';
import { closeProfileWindows, launchProfileWindows, type PersistentWindow } from './helpers/persistentProfiles';
import { ensurePersistentLogin } from './helpers/persistentAuth';
import { acceptBookingFromWindow, createBookingFromWindow, selectBookingServiceFromWindow } from './helpers/persistentBookings';
import { expectRealtimeNotification, getUnreadNotificationCount } from './helpers/persistentNotifications';
import { futureDate, uniqueReference } from './helpers/date';

async function addSecondWorkerService(workerWindow: PersistentWindow, adminWindow: PersistentWindow): Promise<string> {
    const description = uniqueReference('Persistent Service B');

    await workerWindow.page.goto('/worker/services');
    await expect(workerWindow.page.getByTestId('worker-services-page')).toBeVisible();
    await workerWindow.page.getByTestId('worker-service-open-create').click();
    await expect(workerWindow.page.getByTestId('worker-service-form-modal')).toBeVisible();

    const serviceSelect = workerWindow.page.getByTestId('worker-service-form-service');
    const existingServiceNames = new Set(
        (await workerWindow.page.locator('tbody tr').allTextContents())
            .map((rowText) => rowText.trim()),
    );
    const selectableServices = await serviceSelect.locator('option:not([disabled])').evaluateAll((options) => {
        return options
            .map((option) => ({
                label: option.textContent?.trim() ?? '',
                value: option.getAttribute('value') ?? '',
            }))
            .filter((option) => option.label !== '' && option.value !== '');
    });
    const secondServiceName = selectableServices.find((service) => {
        return ! Array.from(existingServiceNames).some((existingName) => existingName.includes(service.label));
    })?.label;

    expect(secondServiceName).toBeTruthy();

    await serviceSelect.selectOption({ label: secondServiceName });
    await workerWindow.page.getByTestId('worker-service-form-pricing-type').selectOption('fixed');
    await workerWindow.page.getByTestId('worker-service-form-price').fill('725');
    await workerWindow.page.getByTestId('worker-service-form-description').fill(description);
    await workerWindow.page.getByTestId('worker-service-form-submit').click();
    await expect(workerWindow.page.getByText(/Worker service (saved|submitted|resubmitted)/)).toBeVisible();

    await adminWindow.page.goto('/admin/worker-service-requests');
    const adminRow = adminWindow.page.locator('[data-testid^="admin-worker-service-row-"]').filter({
        hasText: description,
    }).first();

    await expect(adminRow).toBeVisible();
    await adminRow.locator('[data-testid^="admin-worker-service-approve-"]').click();
    await expect(adminWindow.page.getByText('Worker service approved')).toBeVisible();

    return secondServiceName;
}

test.setTimeout(3 * 60 * 1000);

test.skip('headful Chromium windows handle concurrency workflow without separate user profiles', async ({}, testInfo) => {
    const windows = await launchProfileWindows(['customer1', 'worker1', 'customer2', 'admin'], testInfo);

    try {
        await ensurePersistentLogin(windows.customer1);
        await ensurePersistentLogin(windows.worker1);
        await ensurePersistentLogin(windows.customer2);
        await ensurePersistentLogin(windows.admin);

        const serviceBName = await addSecondWorkerService(windows.worker1, windows.admin);
        const bookingDate = futureDate(5);
        const timeLabel = '09:00 - 10:00';
        const serviceAName = 'E2E AC Repair';
        const customerOneIssue = uniqueReference('Persistent Service A booking');
        const customerTwoIssue = uniqueReference('Persistent overlap booking');

        const workerUnreadBeforeRequest = await getUnreadNotificationCount(windows.worker1.page);
        const customerOneUnreadBeforeAccept = await getUnreadNotificationCount(windows.customer1.page);

        const customerOneBooking = await createBookingFromWindow(windows.customer1.page, {
            workerName: 'E2E Worker',
            serviceName: serviceAName,
            bookingDate,
            startTimeLabel: timeLabel,
            address: '101 Persistent Street, Ahmedabad',
            issueDescription: customerOneIssue,
        });

        await expectRealtimeNotification(windows.worker1.page, {
            previousUnreadCount: workerUnreadBeforeRequest,
            title: /New service request/i,
            message: /requested .* for .* at/i,
        });

        await acceptBookingFromWindow(windows.worker1.page, {
            issueDescription: customerOneIssue,
        });

        await expectRealtimeNotification(windows.customer1.page, {
            previousUnreadCount: customerOneUnreadBeforeAccept,
            title: /Worker accepted your request|Booking confirmed/i,
            message: /available for your booking request|accepted your .* request/i,
        });

        await windows.customer1.page.goto(`/customer/bookings/${customerOneBooking.serviceRequestId}`);
        await expect(windows.customer1.page.getByTestId('booking-status-tracker')).toHaveAttribute('data-status', /worker_selected|confirmed/);

        await windows.customer2.page.goto('/customer/workers');
        await windows.customer2.page.getByTestId('worker-card').filter({ hasText: 'E2E Worker' }).first().getByTestId('worker-view-link').click();
        await expect(windows.customer2.page.getByTestId('worker-detail-page')).toBeVisible();
        await selectBookingServiceFromWindow(windows.customer2.page, serviceBName);
        await windows.customer2.page.getByTestId('booking-date-input').fill(bookingDate);
        await windows.customer2.page.getByTestId('booking-address-input').fill('202 Persistent Street, Ahmedabad');
        await windows.customer2.page.getByTestId('booking-issue-input').fill(customerTwoIssue);

        const blockedSlot = windows.customer2.page.getByTestId('blocked-slot-button').filter({ hasText: timeLabel }).first();

        await expect(blockedSlot).toBeVisible();
        await expect(blockedSlot).toContainText(/booked|reserved|unavailable/i);
        await expect(windows.customer2.page.getByTestId('available-slot-button').filter({ hasText: timeLabel })).toHaveCount(0);

        if (await windows.worker1.page.getByTestId('worker-booking-requests-page').isVisible().catch(() => false)) {
            await expect(windows.worker1.page.locator('[data-testid^="worker-booking-request-card-"]').filter({
                hasText: customerOneIssue,
            }).first().locator('[data-testid^="worker-booking-request-status-"]')).toContainText(/selected|accepted/i);
        } else {
            const confirmedBookingCard = windows.worker1.page.locator('article').filter({
                hasText: customerOneIssue,
            }).first();

            await expect(confirmedBookingCard).toBeVisible();
            await expect(confirmedBookingCard).toContainText(/confirmed/i);
        }
    } finally {
        await closeProfileWindows(windows, testInfo);
    }
});
