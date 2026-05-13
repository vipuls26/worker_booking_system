import { expect, type APIRequestContext } from '@playwright/test';
import { apiJson } from './api';
import { futureDate } from './date';

type WorkerListResponse = {
    data: {
        workers: Array<{
            id: number;
            name: string;
            services: Array<{
                id: number;
                service_id: number;
                price: string;
            }>;
        }>;
    };
};

type WorkerDetailResponse = {
    data: {
        worker: {
            id: number;
            name: string;
            services: Array<{
                id: number;
                service_id: number;
            }>;
        };
        availability: Array<{
            start_time: string;
            end_time: string;
            available: boolean;
        }>;
    };
};

type CreateBookingResponse = {
    data: {
        booking: {
            id: number;
            status: string;
            worker_requests: Array<{
                id: number;
                worker_id: number;
                status: string;
            }>;
            booking?: {
                id: number;
                status: string;
                payment_status: string;
            };
        };
    };
};

type BookingDetailResponse = {
    data: {
        booking: {
            id: number;
            status: string;
            booking_date: string;
            start_time: string;
            end_time: string;
            worker_requests: Array<{
                id: number;
                worker_id: number;
                status: string;
            }>;
            booking?: {
                id: number;
                status: string;
                payment_status: string;
            };
        };
    };
};

/**
 * Find one seeded worker that a customer can book.
 */
export async function getWorkerForBooking(
    request: APIRequestContext,
    customerToken: string,
    workerName = 'E2E Worker',
): Promise<{ workerId: number; serviceId: number }> {
    const payload = await apiJson<WorkerListResponse>(request, customerToken, '/api/customer/workers');
    const worker = payload.data.workers.find((item) => item.name === workerName);

    if (!worker || worker.services.length === 0) {
        throw new Error(`Worker "${workerName}" is not available for booking.`);
    }

    return {
        workerId: worker.id,
        serviceId: Number(worker.services[0].service_id),
    };
}

/**
 * Read the first available slot for the worker detail flow on the requested date.
 */
export async function getFirstAvailableSlot(
    request: APIRequestContext,
    customerToken: string,
    workerId: number,
    serviceId: number,
    bookingDate = futureDate(),
): Promise<{ bookingDate: string; startTime: string; endTime: string }> {
    const payload = await apiJson<WorkerDetailResponse>(request, customerToken, `/api/customer/workers/${workerId}`, {
        params: {
            available_date: bookingDate,
            service_id: serviceId,
            slot_minutes: 60,
        },
    });

    const slot = payload.data.availability.find((item) => item.available);

    if (!slot) {
        throw new Error(`No available slot found for worker ${workerId} on ${bookingDate}.`);
    }

    return {
        bookingDate,
        startTime: slot.start_time,
        endTime: slot.end_time,
    };
}

/**
 * Create one direct single-worker booking request with predictable payload defaults.
 */
export async function createBookingRequest(
    request: APIRequestContext,
    customerToken: string,
    options: {
        workerId: number;
        serviceId: number;
        bookingDate?: string;
        startTime?: string;
        endTime?: string;
        address?: string;
        issueDescription?: string;
    },
): Promise<CreateBookingResponse['data']['booking']> {
    const slot = options.startTime && options.endTime
        ? {
            bookingDate: options.bookingDate ?? futureDate(),
            startTime: options.startTime,
            endTime: options.endTime,
        }
        : await getFirstAvailableSlot(
            request,
            customerToken,
            options.workerId,
            options.serviceId,
            options.bookingDate,
        );

    const payload = await apiJson<CreateBookingResponse>(request, customerToken, '/api/customer/bookings', {
        method: 'POST',
        expectedStatus: 201,
        data: {
            worker_id: options.workerId,
            service_id: options.serviceId,
            booking_date: slot.bookingDate,
            start_time: slot.startTime,
            end_time: slot.endTime,
            address: options.address ?? '123 E2E Street, Ahmedabad',
            issue_description: options.issueDescription ?? 'Playwright booking request.',
        },
    });

    return payload.data.booking;
}

/**
 * Fetch the latest service request details after a workflow step changes it.
 */
export async function fetchBookingDetail(
    request: APIRequestContext,
    customerToken: string,
    serviceRequestId: number,
): Promise<BookingDetailResponse['data']['booking']> {
    const payload = await apiJson<BookingDetailResponse>(request, customerToken, `/api/customer/bookings/${serviceRequestId}`);

    return payload.data.booking;
}

/**
 * Move an existing seeded or created booking through worker completion.
 */
export async function completeWorkerBooking(
    request: APIRequestContext,
    workerToken: string,
    bookingId: number,
): Promise<void> {
    const startResponse = await apiJson<{ data: { booking: { status: string } } }>(
        request,
        workerToken,
        `/api/worker/bookings/${bookingId}/start`,
        {
            method: 'PATCH',
        },
    );

    expect(startResponse.data.booking.status).toBe('in_progress');

    const completeResponse = await apiJson<{ data: { booking: { status: string } } }>(
        request,
        workerToken,
        `/api/worker/bookings/${bookingId}/status`,
        {
            method: 'PATCH',
            data: {
                status: 'completed',
            },
        },
    );

    expect(completeResponse.data.booking.status).toBe('completed');
}
