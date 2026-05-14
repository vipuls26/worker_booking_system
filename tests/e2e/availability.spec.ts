import { expect, test } from '@playwright/test';
import { loginByApi } from './helpers/auth';
import { apiJson, apiResponse } from './helpers/api';
import { createBookingRequest, getWorkerForBooking } from './helpers/bookings';
import { futureDate } from './helpers/date';
import { testUsers } from './helpers/users';

function dayOfWeek(dateString: string): number {
    return new Date(`${dateString}T00:00:00`).getDay();
}

test.describe('Worker availability', () => {
    test('worker available returns open slots', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const payload = await apiJson<{
            data: {
                availability: Array<{ available: boolean }>;
            };
        }>(request, customerSession.token, `/api/customer/workers/${worker.workerId}`, {
            params: {
                available_date: futureDate(),
                service_id: worker.serviceId,
                slot_minutes: 60,
            },
        });

        expect(payload.data.availability.some((slot) => slot.available)).toBeTruthy();
    });

    test('worker unavailable marks a taken slot as blocked', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const workerSession = await loginByApi(request, testUsers.worker);
        const worker = await getWorkerForBooking(request, customerSession.token);
        const createdBooking = await createBookingRequest(request, customerSession.token, {
            workerId: worker.workerId,
            serviceId: worker.serviceId,
            bookingDate: futureDate(5),
            startTime: '10:00',
            endTime: '11:00',
            issueDescription: 'Booked slot for availability blocking coverage.',
        });

        expect(createdBooking.status).toBe('open');

        await apiJson(
            request,
            workerSession.token,
            `/api/worker/booking-requests/${createdBooking.worker_requests[0].id}/respond`,
            {
                method: 'PATCH',
                data: {
                    status: 'accepted',
                },
            },
        );

        const detailPayload = await apiJson<{
            data: {
                availability: Array<{ start_time: string; available: boolean }>;
            };
        }>(request, customerSession.token, `/api/customer/workers/${worker.workerId}`, {
            params: {
                available_date: futureDate(5),
                service_id: worker.serviceId,
                slot_minutes: 60,
            },
        });

        const blockedSlot = detailPayload.data.availability.find((slot) => slot.start_time === '10:00');
        expect(blockedSlot?.available).toBeFalsy();
    });

    test('off-day booking rejection blocks customer booking', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const workerSession = await loginByApi(request, testUsers.workerTwo);
        const worker = await getWorkerForBooking(request, customerSession.token, 'E2E Worker Two');
        const bookingDate = futureDate(6);
        const schedulesPayload = await apiJson<{
            data: {
                schedules: Array<{ id: number; day_of_week: number }>;
            };
        }>(request, workerSession.token, '/api/worker/schedules');
        const scheduleToUpdate = schedulesPayload.data.schedules.find((item) => item.day_of_week === dayOfWeek(bookingDate));

        expect(scheduleToUpdate).toBeTruthy();

        await apiJson(
            request,
            workerSession.token,
            `/api/worker/schedules/${scheduleToUpdate?.id}`,
            {
                method: 'PUT',
                data: {
                    day_of_week: dayOfWeek(bookingDate),
                    is_off_day: true,
                },
            },
        );

        const response = await apiResponse(request, customerSession.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: worker.workerId,
                service_id: worker.serviceId,
                booking_date: bookingDate,
                start_time: '10:00',
                end_time: '11:00',
                address: '123 E2E Street, Ahmedabad',
                issue_description: 'This should fail on an off day.',
            },
        });

        expect(response.status()).toBe(422);

        const payload = await response.json();
        expect(payload.errors.start_time[0]).toContain('not available');
    });

    test('outside schedule booking rejection returns validation feedback', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const worker = await getWorkerForBooking(request, customerSession.token);

        const response = await apiResponse(request, customerSession.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: worker.workerId,
                service_id: worker.serviceId,
                booking_date: futureDate(7),
                start_time: '08:00',
                end_time: '09:00',
                address: '123 E2E Street, Ahmedabad',
                issue_description: 'Outside worker schedule.',
            },
        });

        expect(response.status()).toBe(422);

        const payload = await response.json();
        expect(payload.errors.start_time[0]).toContain('scheduled');
    });
});
