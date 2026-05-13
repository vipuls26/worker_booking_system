import { expect, test } from '@playwright/test';
import { login, loginByApi } from './helpers/auth';
import { apiResponse } from './helpers/api';
import { createPdfUploadBuffer, onboardWorker } from './helpers/onboarding';
import { testUsers } from './helpers/users';

test.describe('Worker self-service', () => {
    test('worker can add and remove a worker service', async ({ page, request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const worker = await onboardWorker(request, adminSession.token, { namePrefix: 'Worker Service Flow', serviceCount: 1 });
        const serviceDescription = 'Playwright worker service coverage.';

        await login(page, {
            role: 'worker',
            email: worker.email,
            password: worker.password,
            dashboardPath: worker.dashboardPath,
        });
        await page.goto('/worker/services');

        await page.getByTestId('worker-service-open-create').click();
        await expect(page.getByTestId('worker-service-form-modal')).toBeVisible();

        const serviceSelect = page.getByTestId('worker-service-form-service');
        const existingServiceNames = new Set(
            (await page.locator('[data-testid^="worker-service-row-"]').allTextContents())
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
        const nextService = selectableServices.find((service) => {
            return !Array.from(existingServiceNames).some((existingName) => existingName.includes(service.label));
        });

        expect(nextService).toBeTruthy();

        await serviceSelect.selectOption(nextService!.value);
        await page.getByTestId('worker-service-form-pricing-type').selectOption('fixed');
        await page.getByTestId('worker-service-form-price').fill('725');
        await page.getByTestId('worker-service-form-description').fill(serviceDescription);
        await page.getByTestId('worker-service-form-submit').click();

        const createdRow = page.locator('[data-testid^="worker-service-row-"]').filter({
            hasText: serviceDescription,
        }).first();

        await expect(createdRow).toBeVisible();
        await createdRow.locator('[data-testid^="worker-service-delete-"]').click();
        await page.getByTestId('confirm-dialog-confirm').click();
        await expect(createdRow).toHaveCount(0);
    });

    test('worker can update a worker schedule', async ({ page }) => {
        await login(page, testUsers.worker);
        await page.goto('/worker/availability');

        const mondayCard = page.getByTestId('worker-schedule-day-1');
        await expect(mondayCard).toBeVisible();

        const editButton = mondayCard.locator('[data-testid^="worker-schedule-edit-"]').first();
        await editButton.click();

        await page.getByTestId('worker-schedule-form-start-time').fill('10:00');
        await page.getByTestId('worker-schedule-form-end-time').fill('17:00');
        await page.getByTestId('worker-schedule-form-submit').click();

        await expect(mondayCard.getByText('10:00 - 17:00')).toBeVisible();
    });

    test('worker verification flow accepts a fresh verification submission', async ({ request }) => {
        const workerSession = await loginByApi(request, testUsers.pendingWorkerTwo);

        const response = await request.fetch('/api/worker/verification', {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${workerSession.token}`,
                Accept: 'application/json',
            },
            multipart: {
                id_proof: {
                    name: 'e2e-id-proof.pdf',
                    mimeType: 'application/pdf',
                    buffer: createPdfUploadBuffer(),
                },
                'certificates[]': {
                    name: 'e2e-certificate.pdf',
                    mimeType: 'application/pdf',
                    buffer: createPdfUploadBuffer(),
                },
                experience_years: 3,
                mobile_verified: '1',
            },
        });

        expect(response.status()).toBe(200);

        const payload = await response.json();
        expect(payload.data.verification.status).toBe('pending');
    });
});
