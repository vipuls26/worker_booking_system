import { expect, test } from '@playwright/test';
import { loginByApi } from './helpers/auth';
import { apiJson } from './helpers/api';
import { createBookingRequest, fetchBookingDetail, getWorkerForBooking } from './helpers/bookings';
import { futureDate, uniqueReference } from './helpers/date';
import { testUsers } from './helpers/users';

test.describe('Audit logs', () => {
    test('login audit log is recorded for authenticated sessions', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const adminSession = await loginByApi(request, testUsers.admin);

        const payload = await apiJson<{
            data: {
                audit_logs: Array<{ action: string }>;
            };
        }>(request, adminSession.token, `/api/admin/audit-logs/users/${customerSession.user.id}`);

        expect(payload.data.audit_logs.some((item) => item.action === 'auth.login')).toBeTruthy();
    });

    test('booking audit log records booking creation activity', async ({ request }) => {
        const customerSession = await loginByApi(request, testUsers.customer);
        const adminSession = await loginByApi(request, testUsers.admin);
        const workerSession = await loginByApi(request, testUsers.worker);
        const worker = await getWorkerForBooking(request, customerSession.token);
        const serviceRequest = await createBookingRequest(request, customerSession.token, {
            workerId: worker.workerId,
            serviceId: worker.serviceId,
            bookingDate: futureDate(12),
            issueDescription: uniqueReference('Audit booking'),
        });

        await apiJson(
            request,
            workerSession.token,
            `/api/worker/booking-requests/${serviceRequest.worker_requests[0].id}/respond`,
            {
                method: 'PATCH',
                data: {
                    status: 'accepted',
                },
            },
        );

        const bookingDetail = await fetchBookingDetail(request, customerSession.token, serviceRequest.id);

        expect(bookingDetail.booking?.id).toBeTruthy();

        const payload = await apiJson<{
            data: {
                audit_logs: Array<{ action: string }>;
            };
        }>(request, adminSession.token, `/api/admin/audit-logs/bookings/${bookingDetail.booking?.id}`);

        expect(payload.data.audit_logs.some((item) => item.action === 'booking.created')).toBeTruthy();
    });

    test('admin action audit log is recorded after a moderation action', async ({ request }) => {
        const adminSession = await loginByApi(request, testUsers.admin);
        const rolesPayload = await request.get('/api/roles');
        const roles = await rolesPayload.json();
        const customerRole = roles.data.roles.find((item: { slug: string }) => item.slug === 'customer');
        const email = `${uniqueReference('audit-user').toLowerCase()}@example.com`;

        const registerResponse = await request.post('/api/auth/register', {
            data: {
                role_id: customerRole.id,
                name: 'Audit Target User',
                email,
                phone: '9399999999',
                password: 'password',
                password_confirmation: 'password',
            },
        });

        expect(registerResponse.status()).toBe(201);
        const registerPayload = await registerResponse.json();
        const createdUserId = registerPayload.data.user.id;

        await apiJson(request, adminSession.token, `/api/admin/users/${createdUserId}/block`, {
            method: 'PATCH',
            data: {
                block_type: 'partially_blocked',
            },
        });

        const auditPayload = await apiJson<{
            data: {
                audit_logs: Array<{ action: string }>;
            };
        }>(request, adminSession.token, `/api/admin/audit-logs/users/${createdUserId}`);

        expect(auditPayload.data.audit_logs.some((item) => item.action === 'admin.user_partially_blocked')).toBeTruthy();
    });
});
