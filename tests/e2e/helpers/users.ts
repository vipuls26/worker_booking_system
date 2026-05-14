export type TestUser = {
    role: 'admin' | 'customer' | 'worker';
    email: string;
    password: string;
    dashboardPath: string;
};

export const testUsers = {
    admin: {
        role: 'admin',
        email: 'e2e.admin@example.com',
        password: 'password',
        dashboardPath: '/admin/dashboard',
    },
    customer: {
        role: 'customer',
        email: 'e2e.customer@example.com',
        password: 'password',
        dashboardPath: '/customer/dashboard',
    },
    customerTwo: {
        role: 'customer',
        email: 'e2e.customer.two@example.com',
        password: 'password',
        dashboardPath: '/customer/dashboard',
    },
    blockedCustomer: {
        role: 'customer',
        email: 'e2e.customer.blocked@example.com',
        password: 'password',
        dashboardPath: '/account/blocked',
    },
    partiallyBlockedCustomer: {
        role: 'customer',
        email: 'e2e.customer.partial@example.com',
        password: 'password',
        dashboardPath: '/customer/dashboard',
    },
    worker: {
        role: 'worker',
        email: 'e2e.worker@example.com',
        password: 'password',
        dashboardPath: '/worker/dashboard',
    },
    workerTwo: {
        role: 'worker',
        email: 'e2e.worker.two@example.com',
        password: 'password',
        dashboardPath: '/worker/dashboard',
    },
    pendingWorker: {
        role: 'worker',
        email: 'e2e.worker.pending@example.com',
        password: 'password',
        dashboardPath: '/worker/dashboard',
    },
    pendingWorkerTwo: {
        role: 'worker',
        email: 'e2e.worker.pending.two@example.com',
        password: 'password',
        dashboardPath: '/worker/dashboard',
    },
} satisfies Record<string, TestUser>;
