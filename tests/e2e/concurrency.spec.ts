import { execFileSync } from 'node:child_process';
import { expect, test, type APIRequestContext, type APIResponse } from '@playwright/test';
import { apiJson, apiResponse } from './helpers/api';
import { fetchBookingDetail } from './helpers/bookings';
import { futureDate, pastDate } from './helpers/date';
import { onboardCustomer, onboardWorker, type OnboardedWorker, type RegisteredUser } from './helpers/onboarding';
import { testUsers } from './helpers/users';

type WorkerRequestSummary = {
    id: number;
    worker_id: number;
    status: string;
};

type ServiceRequestSummary = {
    id: number;
    status: string;
    worker_requests: WorkerRequestSummary[];
    booking?: {
        id: number;
        status: string;
    };
};

type Scenario = {
    workerA: OnboardedWorker;
    workerB: OnboardedWorker;
    customer1: RegisteredUser;
    customer2: RegisteredUser;
    customer3: RegisteredUser;
    services: {
        electrician: { serviceId: number; serviceName: string };
        acRepair: { serviceId: number; serviceName: string };
        plumbing: { serviceId: number; serviceName: string };
    };
};

function runArtisanTinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', normalizePhpCode(code)], {
        cwd: process.cwd(),
        stdio: 'pipe',
        env: {
            ...process.env,
            APP_ENV: 'testing',
            APP_URL: 'http://127.0.0.1:8000',
        },
        encoding: 'utf-8',
    }).trim();
}

function normalizePhpCode(code: string): string {
    return code
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .join(' ');
}

function escapePhpString(value: string): string {
    return value
        .replaceAll('\\', '\\\\')
        .replaceAll('\'', '\\\'');
}

function ensureApprovedWorkerService(workerId: number, serviceName: string, price: string): { workerServiceId: number; serviceId: number; serviceName: string } {
    const payload = runArtisanTinker(`
        $admin = App\\Models\\User::where('email', '${escapePhpString(testUsers.admin.email)}')->firstOrFail();
        $service = App\\Models\\Service::withTrashed()->firstOrCreate(
            ['slug' => Illuminate\\Support\\Str::slug('${escapePhpString(serviceName)}')],
            [
                'name' => '${escapePhpString(serviceName)}',
                'description' => '${escapePhpString(serviceName)} checklist service.',
                'icon' => 'pi-wrench',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );
        if ($service->trashed()) {
            $service->restore();
        }
        $service->forceFill([
            'name' => '${escapePhpString(serviceName)}',
            'description' => '${escapePhpString(serviceName)} checklist service.',
            'icon' => 'pi-wrench',
            'is_active' => true,
            'created_by' => $admin->id,
        ])->save();
        $workerService = App\\Models\\WorkerService::updateOrCreate(
            ['worker_id' => ${workerId}, 'service_id' => $service->id],
            [
                'pricing_type' => App\\Models\\WorkerService::PricingFixed,
                'price' => '${escapePhpString(price)}',
                'description' => '${escapePhpString(serviceName)} checklist worker service.',
                'is_active' => true,
                'approval_status' => App\\Models\\WorkerService::StatusApproved,
                'rejection_reason' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ],
        );
        echo json_encode([
            'worker_service_id' => $workerService->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
        ]);
    `);

    const decodedPayload = JSON.parse(payload) as {
        worker_service_id: number;
        service_id: number;
        service_name: string;
    };

    return {
        workerServiceId: Number(decodedPayload.worker_service_id),
        serviceId: Number(decodedPayload.service_id),
        serviceName: decodedPayload.service_name,
    };
}

function forceBookingStatus(bookingId: number, status: string): void {
    runArtisanTinker(`
        $booking = App\\Models\\Booking::findOrFail(${bookingId});
        $booking->forceFill([
            'status' => '${escapePhpString(status)}',
        ])->save();
    `);
}

async function loginAdminToken(request: APIRequestContext): Promise<string> {
    const response = await request.post('/api/auth/login', {
        data: {
            email: testUsers.admin.email,
            password: testUsers.admin.password,
        },
    });

    expect(response.ok()).toBeTruthy();

    const payload = await response.json() as {
        data: {
            token: string;
        };
    };

    return payload.data.token;
}

function findApprovedService(worker: OnboardedWorker, serviceMatcher: RegExp): { serviceId: number; serviceName: string } {
    const approvedService = worker.approvedServices.find((service) => serviceMatcher.test(service.serviceName));

    if (!approvedService) {
        throw new Error(`Unable to find a service matching ${serviceMatcher} in [${worker.approvedServices.map((service) => service.serviceName).join(', ')}].`);
    }

    return {
        serviceId: Number(approvedService.serviceId),
        serviceName: approvedService.serviceName,
    };
}

async function createScenario(request: APIRequestContext): Promise<Scenario> {
    const adminToken = await loginAdminToken(request);
    const workerA = await onboardWorker(request, adminToken, { serviceCount: 20, namePrefix: 'Checklist Worker A' });
    const workerB = await onboardWorker(request, adminToken, { serviceCount: 20, namePrefix: 'Checklist Worker B' });
    const customer1 = await onboardCustomer(request, adminToken, 'Checklist Customer One');
    const customer2 = await onboardCustomer(request, adminToken, 'Checklist Customer Two');
    const customer3 = await onboardCustomer(request, adminToken, 'Checklist Customer Three');
    const electricianService = ensureApprovedWorkerService(workerA.user.id, 'Electrician', '725');

    if (!workerA.approvedServices.some((service) => /electrician/i.test(service.serviceName))) {
        workerA.approvedServices.push({
            id: electricianService.workerServiceId,
            serviceId: electricianService.serviceId,
            serviceName: electricianService.serviceName,
            description: 'Electrician checklist worker service.',
        });
    }

    return {
        workerA,
        workerB,
        customer1,
        customer2,
        customer3,
        services: {
            electrician: findApprovedService(workerA, /electrician/i),
            acRepair: findApprovedService(workerA, /ac repair/i),
            plumbing: findApprovedService(workerA, /plumb/i),
        },
    };
}

async function createDirectRequest(
    request: APIRequestContext,
    customerToken: string,
    workerId: number,
    serviceId: number,
    bookingDate: string,
    startTime: string,
    endTime: string,
    issueDescription: string,
): Promise<ServiceRequestSummary> {
    const payload = await apiJson<{ data: { booking: ServiceRequestSummary } }>(request, customerToken, '/api/customer/bookings', {
        method: 'POST',
        expectedStatus: 201,
        data: {
            worker_id: workerId,
            service_id: serviceId,
            booking_date: bookingDate,
            start_time: startTime,
            end_time: endTime,
            address: '123 Concurrency Street, Ahmedabad',
            issue_description: issueDescription,
        },
    });

    return payload.data.booking;
}

async function acceptSingleWorkerRequest(
    request: APIRequestContext,
    workerToken: string,
    serviceRequest: ServiceRequestSummary,
): Promise<{ status: number; payload?: { data?: { worker_request?: { status: string; service_request?: { status: string } } } } }> {
    const workerRequestId = serviceRequest.worker_requests[0]?.id;

    expect(workerRequestId).toBeTruthy();

    const response = await apiResponse(
        request,
        workerToken,
        `/api/worker/booking-requests/${workerRequestId}/respond`,
        {
            method: 'PATCH',
            data: {
                status: 'accepted',
            },
        },
    );

    const payload = await response.json().catch(() => undefined);

    return {
        status: response.status(),
        payload,
    };
}

async function expectConfirmedBooking(
    request: APIRequestContext,
    customerToken: string,
    serviceRequestId: number,
): Promise<number> {
    const detail = await fetchBookingDetail(request, customerToken, serviceRequestId);

    expect(detail.status).toBe('worker_selected');
    expect(detail.booking?.status).toBe('confirmed');
    expect(detail.booking?.id).toBeTruthy();

    return Number(detail.booking?.id);
}

async function createAndConfirmDirectBooking(
    request: APIRequestContext,
    customer: RegisteredUser,
    worker: OnboardedWorker,
    serviceId: number,
    bookingDate: string,
    startTime: string,
    endTime: string,
    issueDescription: string,
): Promise<{ serviceRequest: ServiceRequestSummary; bookingId: number }> {
    const serviceRequest = await createDirectRequest(
        request,
        customer.token,
        worker.user.id,
        serviceId,
        bookingDate,
        startTime,
        endTime,
        issueDescription,
    );

    const acceptance = await acceptSingleWorkerRequest(request, worker.token, serviceRequest);

    expect(acceptance.status).toBe(200);
    expect(acceptance.payload?.data?.worker_request?.status).toBe('selected');

    const bookingId = await expectConfirmedBooking(request, customer.token, serviceRequest.id);

    return { serviceRequest, bookingId };
}

async function expectOverlapRejection(
    response: APIResponse,
): Promise<void> {
    expect(response.status()).toBeGreaterThanOrEqual(400);

    const payload = await response.json();
    const errorMessages = JSON.stringify(payload.errors ?? payload.message ?? payload.data ?? '');

    expect(errorMessages).toMatch(/booked|reserved|available|overlap|conflict/i);
}

async function createAwaitingRescheduleRequest(
    request: APIRequestContext,
    customerWithConfirmedBooking: RegisteredUser,
    customerNeedingReschedule: RegisteredUser,
    worker: OnboardedWorker,
    serviceId: number,
    bookingDate: string,
    startTime: string,
    endTime: string,
): Promise<ServiceRequestSummary> {
    const confirmedRequest = await createDirectRequest(
        request,
        customerWithConfirmedBooking.token,
        worker.user.id,
        serviceId,
        bookingDate,
        startTime,
        endTime,
        'Confirmed booking that should take the shared slot.',
    );

    const overlappingRequest = await createDirectRequest(
        request,
        customerNeedingReschedule.token,
        worker.user.id,
        serviceId,
        bookingDate,
        startTime,
        endTime,
        'Overlapping booking that should move to awaiting reschedule.',
    );

    const acceptance = await acceptSingleWorkerRequest(request, worker.token, confirmedRequest);

    expect(acceptance.status).toBe(200);

    const overlappingDetail = await fetchBookingDetail(request, customerNeedingReschedule.token, overlappingRequest.id);

    expect(overlappingDetail.worker_requests[0]?.status).toBe('awaiting_reschedule');
    expect(overlappingDetail.status).toBe('open');

    return overlappingRequest;
}

test.describe('Overlap and concurrency', () => {
    test('Customer 1 books Plumbing service from 10:00-11:00 for Worker A and booking succeeds', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(8);

        const result = await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 1 plumbing booking.',
        );

        expect(result.serviceRequest.status).toBe('open');
    });

    test('Customer 2 booking Electrician service from 10:30-11:30 for the same worker is rejected due to overlap', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(9);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 2 first booking.',
        );

        const overlapResponse = await apiResponse(request, scenario.customer2.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerA.user.id,
                service_id: scenario.services.electrician.serviceId,
                booking_date: bookingDate,
                start_time: '10:30',
                end_time: '11:30',
                address: '456 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 2 overlap booking.',
            },
        });

        await expectOverlapRejection(overlapResponse);
    });

    test('Customer 3 booking the same Plumbing service from 10:15-10:45 for the same worker is rejected', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(10);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 3 first booking.',
        );

        const overlapResponse = await apiResponse(request, scenario.customer3.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerA.user.id,
                service_id: scenario.services.plumbing.serviceId,
                booking_date: bookingDate,
                start_time: '10:15',
                end_time: '10:45',
                address: '789 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 3 overlap booking.',
            },
        });

        await expectOverlapRejection(overlapResponse);
    });

    test('Customer 2 books AC Repair from 11:00-12:00 after the previous booking ends and booking succeeds', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(11);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 4 first booking.',
        );

        await createAndConfirmDirectBooking(
            request,
            scenario.customer2,
            scenario.workerA,
            scenario.services.acRepair.serviceId,
            bookingDate,
            '11:00',
            '12:00',
            'Checklist case 4 second booking.',
        );
    });

    test('Customer 3 booking the exact same slot 10:00-11:00 for the same worker is rejected', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(12);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 5 first booking.',
        );

        const overlapResponse = await apiResponse(request, scenario.customer3.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerA.user.id,
                service_id: scenario.services.plumbing.serviceId,
                booking_date: bookingDate,
                start_time: '10:00',
                end_time: '11:00',
                address: '789 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 5 exact overlap booking.',
            },
        });

        await expectOverlapRejection(overlapResponse);
    });

    test('Existing booking from 10:00-12:00 rejects a nested 10:30-11:00 booking', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(13);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '12:00',
            'Checklist case 6 first booking.',
        );

        const overlapResponse = await apiResponse(request, scenario.customer2.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerA.user.id,
                service_id: scenario.services.electrician.serviceId,
                booking_date: bookingDate,
                start_time: '10:30',
                end_time: '11:00',
                address: '456 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 6 nested booking.',
            },
        });

        await expectOverlapRejection(overlapResponse);
    });

    test('Existing booking from 10:30-11:00 rejects a wrapping 10:00-12:00 booking', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(14);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.electrician.serviceId,
            bookingDate,
            '10:30',
            '11:30',
            'Checklist case 7 first booking.',
        );

        const overlapResponse = await apiResponse(request, scenario.customer3.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerA.user.id,
                service_id: scenario.services.plumbing.serviceId,
                booking_date: bookingDate,
                start_time: '10:00',
                end_time: '12:00',
                address: '789 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 7 wrapping booking.',
            },
        });

        await expectOverlapRejection(overlapResponse);
    });

    test('Cancelled booking from 10:00-11:00 does not block a new booking for the same slot', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(15);

        const firstBooking = await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 8 first booking.',
        );

        forceBookingStatus(firstBooking.bookingId, 'cancelled');

        await createAndConfirmDirectBooking(
            request,
            scenario.customer2,
            scenario.workerA,
            scenario.services.electrician.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 8 replacement booking.',
        );
    });

    test('Completed 08:00-09:00 booking does not block a later 10:00-11:00 booking', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(16);

        const firstBooking = await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.acRepair.serviceId,
            bookingDate,
            '09:00',
            '10:00',
            'Checklist case 9 early booking.',
        );

        forceBookingStatus(firstBooking.bookingId, 'completed');

        await createAndConfirmDirectBooking(
            request,
            scenario.customer2,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 9 later booking.',
        );
    });

    test('Two customers simultaneously booking the same worker and same slot results in only one successful booking', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(17);

        const [firstRequest, secondRequest] = await Promise.all([
            createDirectRequest(
                request,
                scenario.customer1.token,
                scenario.workerA.user.id,
                scenario.services.plumbing.serviceId,
                bookingDate,
                '10:00',
                '11:00',
                'Checklist case 10 concurrent booking one.',
            ),
            createDirectRequest(
                request,
                scenario.customer2.token,
                scenario.workerA.user.id,
                scenario.services.electrician.serviceId,
                bookingDate,
                '10:00',
                '11:00',
                'Checklist case 10 concurrent booking two.',
            ),
        ]);

        const [firstAcceptance, secondAcceptance] = await Promise.all([
            acceptSingleWorkerRequest(request, scenario.workerA.token, firstRequest),
            acceptSingleWorkerRequest(request, scenario.workerA.token, secondRequest),
        ]);

        const successStatuses = [firstAcceptance.status, secondAcceptance.status].filter((status) => status === 200);
        const failureStatuses = [firstAcceptance.status, secondAcceptance.status].filter((status) => status >= 400);

        expect(successStatuses).toHaveLength(1);
        expect(failureStatuses).toHaveLength(1);

        const firstDetail = await fetchBookingDetail(request, scenario.customer1.token, firstRequest.id);
        const secondDetail = await fetchBookingDetail(request, scenario.customer2.token, secondRequest.id);
        const confirmedBookings = [firstDetail.booking?.status, secondDetail.booking?.status].filter((status) => status === 'confirmed');

        expect(confirmedBookings).toHaveLength(1);
    });

    test('Worker B can still be booked for the same slot when Worker A is already booked', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(18);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 11 worker A booking.',
        );

        const workerBPlumbing = findApprovedService(scenario.workerB, /plumb/i);

        await createAndConfirmDirectBooking(
            request,
            scenario.customer2,
            scenario.workerB,
            workerBPlumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 11 worker B booking.',
        );
    });

    test('Blocking status matrix matches overlap rules for reserved, confirmed, in-progress, cancelled, completed, and rejected states', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(19);

        const reservedRequest = await createDirectRequest(
            request,
            scenario.customer1.token,
            scenario.workerA.user.id,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
            'Checklist case 12 reserved request.',
        );

        const reservedAcceptance = await acceptSingleWorkerRequest(request, scenario.workerA.token, reservedRequest);
        expect(reservedAcceptance.status).toBe(200);

        const reservedConflict = await apiResponse(request, scenario.customer2.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerA.user.id,
                service_id: scenario.services.electrician.serviceId,
                booking_date: bookingDate,
                start_time: '10:30',
                end_time: '11:30',
                address: '456 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 12 reserved overlap.',
            },
        });

        await expectOverlapRejection(reservedConflict);

        const inProgressRequest = await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerB,
            findApprovedService(scenario.workerB, /ac repair/i).serviceId,
            bookingDate,
            '12:00',
            '13:00',
            'Checklist case 12 confirmed booking.',
        );

        const confirmedConflict = await apiResponse(request, scenario.customer2.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerB.user.id,
                service_id: findApprovedService(scenario.workerB, /electrician/i).serviceId,
                booking_date: bookingDate,
                start_time: '12:30',
                end_time: '13:30',
                address: '456 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 12 confirmed overlap.',
            },
        });

        await expectOverlapRejection(confirmedConflict);

        forceBookingStatus(inProgressRequest.bookingId, 'in_progress');

        const inProgressConflict = await apiResponse(request, scenario.customer3.token, '/api/customer/bookings', {
            method: 'POST',
            data: {
                worker_id: scenario.workerB.user.id,
                service_id: findApprovedService(scenario.workerB, /plumb/i).serviceId,
                booking_date: bookingDate,
                start_time: '12:30',
                end_time: '13:30',
                address: '789 Concurrency Street, Ahmedabad',
                issue_description: 'Checklist case 12 in-progress overlap.',
            },
        });

        await expectOverlapRejection(inProgressConflict);

        forceBookingStatus(inProgressRequest.bookingId, 'completed');

        await createAndConfirmDirectBooking(
            request,
            scenario.customer3,
            scenario.workerB,
            findApprovedService(scenario.workerB, /plumb/i).serviceId,
            bookingDate,
            '12:00',
            '13:00',
            'Checklist case 12 completed no longer blocks.',
        );

        const cancelledBooking = await createAndConfirmDirectBooking(
            request,
            scenario.customer1,
            scenario.workerA,
            scenario.services.acRepair.serviceId,
            bookingDate,
            '14:00',
            '15:00',
            'Checklist case 12 cancelled booking.',
        );

        forceBookingStatus(cancelledBooking.bookingId, 'cancelled');

        await createAndConfirmDirectBooking(
            request,
            scenario.customer2,
            scenario.workerA,
            scenario.services.electrician.serviceId,
            bookingDate,
            '14:00',
            '15:00',
            'Checklist case 12 cancelled no longer blocks.',
        );

        const rejectedRequest = await createDirectRequest(
            request,
            scenario.customer3.token,
            scenario.workerA.user.id,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '16:00',
            '17:00',
            'Checklist case 12 rejected request.',
        );

        const rejectionResponse = await apiJson<{ data: { worker_request: { status: string } } }>(
            request,
            scenario.workerA.token,
            `/api/worker/booking-requests/${rejectedRequest.worker_requests[0].id}/respond`,
            {
                method: 'PATCH',
                data: {
                    status: 'rejected',
                },
            },
        );

        expect(rejectionResponse.data.worker_request.status).toBe('rejected');

        await createAndConfirmDirectBooking(
            request,
            scenario.customer2,
            scenario.workerA,
            scenario.services.acRepair.serviceId,
            bookingDate,
            '16:00',
            '17:00',
            'Checklist case 12 rejected no longer blocks.',
        );
    });

    test('Customer can reschedule an awaiting-reschedule request into the next available slot', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(20);

        const awaitingRescheduleRequest = await createAwaitingRescheduleRequest(
            request,
            scenario.customer1,
            scenario.customer2,
            scenario.workerA,
            scenario.services.plumbing.serviceId,
            bookingDate,
            '10:00',
            '11:00',
        );

        const reschedulePayload = await apiJson<{ data: { booking: { requested_date: string; start_time: string; end_time: string; worker_requests: Array<{ status: string }> } } }>(
            request,
            scenario.customer2.token,
            `/api/customer/bookings/${awaitingRescheduleRequest.id}/reschedule`,
            {
                method: 'PATCH',
                data: {
                    booking_date: bookingDate,
                    start_time: '11:00',
                    end_time: '12:00',
                    duration_minutes: 60,
                },
            },
        );

        expect(reschedulePayload.data.booking.requested_date).toBe(bookingDate);
        expect(reschedulePayload.data.booking.start_time).toBe('11:00:00');
        expect(reschedulePayload.data.booking.end_time).toBe('12:00:00');
        expect(reschedulePayload.data.booking.worker_requests[0]?.status).toBe('pending');
    });

    test('Customer cannot reschedule an awaiting-reschedule request to a past date', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(21);

        const awaitingRescheduleRequest = await createAwaitingRescheduleRequest(
            request,
            scenario.customer1,
            scenario.customer2,
            scenario.workerA,
            scenario.services.acRepair.serviceId,
            bookingDate,
            '10:00',
            '11:00',
        );

        const response = await apiResponse(request, scenario.customer2.token, `/api/customer/bookings/${awaitingRescheduleRequest.id}/reschedule`, {
            method: 'PATCH',
            data: {
                booking_date: pastDate(),
                start_time: '11:00',
                end_time: '12:00',
                duration_minutes: 60,
            },
        });

        expect(response.status()).toBe(422);

        const payload = await response.json();
        expect(payload.errors.booking_date[0]).toContain('today or a future date');
    });

    test('Customer cannot reschedule an awaiting-reschedule request to a past time today', async ({ request }) => {
        const scenario = await createScenario(request);
        const bookingDate = futureDate(22);
        const now = new Date();
        const pastHour = now.getHours() === 0 ? 0 : now.getHours() - 1;
        const pastStartTime = `${String(pastHour).padStart(2, '0')}:00`;
        const pastEndTime = `${String((pastHour + 1) % 24).padStart(2, '0')}:00`;

        const awaitingRescheduleRequest = await createAwaitingRescheduleRequest(
            request,
            scenario.customer1,
            scenario.customer2,
            scenario.workerA,
            scenario.services.electrician.serviceId,
            bookingDate,
            '10:00',
            '11:00',
        );

        const response = await apiResponse(request, scenario.customer2.token, `/api/customer/bookings/${awaitingRescheduleRequest.id}/reschedule`, {
            method: 'PATCH',
            data: {
                booking_date: futureDate(0),
                start_time: pastStartTime,
                end_time: pastEndTime,
                duration_minutes: 60,
            },
        });

        expect(response.status()).toBe(422);

        const payload = await response.json();
        expect(payload.errors.start_time[0]).toContain('current time or a future time');
    });
});
