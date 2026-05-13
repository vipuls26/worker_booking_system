import { expect, type Page } from '@playwright/test';

export type BookingCreationOptions = {
    workerName: string;
    serviceName: string;
    bookingDate: string;
    startTimeLabel: string;
    address: string;
    issueDescription: string;
};

export type BookingAcceptanceOptions = {
    issueDescription: string;
};

async function selectServiceOptionByName(page: Page, serviceName: string): Promise<void> {
    const serviceSelect = page.getByTestId('booking-service-select');
    const matchingLabel = await serviceSelect.locator('option').evaluateAll((options, expectedServiceName) => {
        const matchingOption = options.find((option) => {
            return option.textContent?.trim().includes(String(expectedServiceName));
        });

        return matchingOption?.textContent?.trim() ?? '';
    }, serviceName);

    expect(matchingLabel).toBeTruthy();
    await serviceSelect.selectOption({ label: matchingLabel });
}

export async function createBookingFromWindow(
    page: Page,
    options: BookingCreationOptions,
): Promise<{ serviceRequestId: number }> {
    await page.goto('/customer/workers');
    await page.getByTestId('worker-card').filter({ hasText: options.workerName }).first().getByTestId('worker-view-link').click();
    await expect(page.getByTestId('worker-detail-page')).toBeVisible();

    await selectServiceOptionByName(page, options.serviceName);
    await page.getByTestId('booking-date-input').fill(options.bookingDate);
    await page.getByTestId('booking-address-input').fill(options.address);
    await page.getByTestId('booking-issue-input').fill(options.issueDescription);

    const targetSlot = page.getByTestId('available-slot-button').filter({ hasText: options.startTimeLabel }).first();

    await expect(targetSlot).toBeVisible();
    await targetSlot.click();
    const [createBookingResponse] = await Promise.all([
        page.waitForResponse((response) => {
            return response.url().includes('/api/customer/bookings') && response.request().method() === 'POST';
        }),
        page.getByTestId('booking-submit-button').click(),
    ]);

    expect(createBookingResponse.status()).toBe(201);

    const createBookingPayload = await createBookingResponse.json() as {
        data: {
            booking: {
                id: number;
            };
        };
    };
    const serviceRequestId = Number(createBookingPayload.data.booking.id ?? 0);

    expect(serviceRequestId).toBeGreaterThan(0);

    if (!page.url().match(/\/customer\/bookings\/\d+$/)) {
        await page.goto(`/customer/bookings/${serviceRequestId}`);
    }

    await expect(page).toHaveURL(new RegExp(`/customer/bookings/${serviceRequestId}$`));

    return { serviceRequestId };
}

export async function selectBookingServiceFromWindow(page: Page, serviceName: string): Promise<void> {
    await selectServiceOptionByName(page, serviceName);
}

export async function acceptBookingFromWindow(
    page: Page,
    options: BookingAcceptanceOptions,
): Promise<void> {
    await page.goto('/worker/booking-requests');
    await expect(page.getByTestId('worker-booking-requests-page')).toBeVisible();

    const requestCard = page.locator('[data-testid^="worker-booking-request-card-"]').filter({
        hasText: options.issueDescription,
    }).first();

    await expect(requestCard).toBeVisible();
    await requestCard.locator('[data-testid^="worker-booking-request-accept-"]').click();

    await expect(requestCard.locator('[data-testid^="worker-booking-request-status-"]')).toContainText(/accepted|selected/i);
}
