# Worker Booking System

A Laravel API and Vue single-page application for booking verified local workers. Customers can search for workers, send service requests, compare accepted workers, confirm bookings, pay after completion, review workers, and raise disputes. Workers manage profiles, service offerings, availability, booking requests, jobs, reviews, and earnings. Admins manage users, services, verifications, service approvals, disputes, unblock requests, audit logs, and dashboard reporting.

## Tech Stack

- PHP 8.4 runtime, Laravel 13
- Laravel Sanctum token authentication
- Vue 3, Vue Router, Pinia, Axios
- Tailwind CSS 3, PrimeIcons, Vue Sonner
- MySQL for the application database
- Database queues, database notifications, database sessions, and database cache
- PHPUnit 12 for automated tests
- Laravel Pint for PHP formatting

## Main Features

- Role-based access for admin, customer, and worker accounts.
- Email verification and platform verification gates.
- Customer worker search with keyword, service, city, price, rating, date, time, duration, and sorting filters.
- Auto-matched service requests sent to multiple eligible workers.
- Customer requests sent to one chosen worker.
- Worker request responses with accept, reject, and cancel flows.
- Customer requests auto-confirm when the single worker accepts.
- Customer final worker selection for multi-worker requests.
- Booking status workflow from confirmed to in progress, completed, cancelled, and payment-ready states.
- Manual payment recording with platform commission and worker earning breakdowns.
- Worker schedules, service approval, profile verification, and payout tracking.
- Reviews in both customer-to-worker and worker-to-customer directions.
- Dispute workflow with status history and admin resolution.
- Database notifications for request, booking, payment, review, and workflow events.
- Audit log coverage for important admin and booking actions.

## Demo Accounts

Seeded accounts use the password `password`.

| Role | Email |
| --- | --- |
| Admin | `admin@gmail.com` |
| Customer | `customer1@gmail.com` |
| Customer | `customer2@gmail.com` |
| Customer | `customer3@gmail.com` |
| Worker | `worker1@gmail.com` |
| Worker | `worker2@gmail.com` |
| Worker | `worker3@gmail.com` |
| Worker | `worker4@gmail.com` |
| Worker | `worker5@gmail.com` |
| Worker | `worker6@gmail.com` |

Additional workers are seeded through `worker10@gmail.com`.

## Requirements

- PHP 8.3 or newer, PHP 8.4 recommended for this project
- Composer
- Node.js and npm
- MySQL or another configured Laravel database
- PHP extensions required by Laravel, plus the database driver for your selected DB

For test execution, the default `phpunit.xml` uses in-memory SQLite. Install/enable `pdo_sqlite` or change the testing database configuration.

## Installation

1. Install dependencies:

```bash
composer install
npm install
```

2. Create the environment file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure the database in `.env`.

For MySQL:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=worker_booking_system
DB_USERNAME=root
DB_PASSWORD=
```

For local SQLite:

```ini
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite
```

4. Run migrations and seed demo data:

```bash
php artisan migrate --seed
```

5. Build frontend assets:

```bash
npm run build
```

## Development

Run the full local development stack:

```bash
composer run dev
```

This starts:

- Laravel development server
- Queue listener
- Laravel Pail log tailing
- Vite development server

Alternatively, run pieces separately:

```bash
php artisan serve
npm run dev
php artisan queue:listen
```

The Vue app is served from Laravel routes and talks to the API under `/api`.
Frontend auth sessions are stored in browser `sessionStorage`, so closing the browser signs the user out more safely than long-lived `localStorage` tokens.
Frontend forms now use shared Yup schemas for the main auth, profile, password, worker service, worker schedule, and booking request flows.
The SPA router uses lazy-loaded page chunks so dashboard features load on demand instead of shipping the whole app on the first visit.

## Common Commands

```bash
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed
php artisan route:list --except-vendor
php artisan queue:work
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
```

## Application Structure

Important backend areas:

- `app/Http/Controllers/Api` - API controllers grouped by account, admin, customer, and worker contexts.
- `app/Http/Requests` - form request validation for API inputs.
- `app/Http/Resources` - JSON API resource shapes.
- `app/Models` - Eloquent models for users, services, requests, bookings, disputes, reviews, payments, payouts, and audit logs.
- `app/Policies` - authorization rules for protected workflows.
- `app/Services` - business logic for booking, payment, worker search, profiles, reviews, disputes, admin actions, and audits.
- `app/Notifications` - database notifications shown in the SPA.
- `database/migrations` - schema definitions.
- `database/seeders` - roles, services, users, demo bookings, and service requests.

Important frontend areas:

- `resources/js/router/index.js` - SPA route definitions and role guards.
- `resources/js/pages` - page-level Vue views.
- `resources/js/layouts` - auth, dashboard, and admin shells.
- `resources/js/components` - reusable UI components.
- `resources/js/stores` - Pinia stores for API-backed state.
- `resources/js/api` - Axios API wrappers.

## API Overview

Public and auth:

- `GET /api/roles`
- `POST /api/auth/register`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `POST /api/auth/forgot-password`
- `POST /api/auth/reset-password`
- `GET /api/email/verify/{id}/{hash}`
- `POST /api/email/verification-notification`

Shared authenticated routes:

- `GET /api/notifications`
- `PATCH /api/notifications/read-all`
- `DELETE /api/notifications/clear-all`
- `apiResource /api/disputes`

Customer routes:

- dashboard, worker search, worker detail, worker reviews
- service request creation and listing
- booking detail, worker selection, payment, cancellation, and reviews

Worker routes:

- dashboard, profile, verification
- availability schedules
- services and service approval requests
- booking requests, booking status updates, reviews, and earnings

Admin routes:

- dashboard and revenue reports
- service categories
- worker service approvals
- user management and verification
- worker verification review
- unblock requests
- disputes
- audit logs

Use `php artisan route:list --except-vendor` for the full current route table.

## Booking Workflow

1. Customer creates a service request from worker search or from a specific worker page.
2. The system creates one or more `service_request_workers` rows for eligible workers.
3. Workers receive a notification and can accept, reject, or cancel the request.
4. For multi-worker requests, the customer selects one accepted worker.
5. For direct one-worker requests, acceptance auto-selects that worker.
6. The system creates the official `bookings` row with quoted amount, commission, and worker earning.
7. Worker moves the booking through accepted, in progress, and completed states.
8. Customer pays after completion.
9. Customer and worker can review each other.
10. Either side can open a dispute when eligible.

## Notifications

Notifications are stored through Laravel's database notification channel and surfaced in the SPA notification dropdown/page.

Current notification sources include:

- new service requests to workers
- direct request acceptance to customers
- booking confirmation to customers and workers
- closed requests for non-selected workers
- booking status changes
- reviews received

Queue processing must be running for queued notifications in non-sync queue environments.

## Testing

Run all tests:

```bash
php artisan test --compact
```

Run one file:

```bash
php artisan test --compact tests/Feature/BookingAutoMatchingWorkflowTest.php
```

Run one test:

```bash
php artisan test --compact --filter=direct_worker_request_is_confirmed_when_worker_accepts
```

The test suite currently expects SQLite in memory:

```xml
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

If tests fail with `could not find driver`, enable the PHP SQLite PDO extension or point tests at a configured test database.

## Code Quality

Before submitting PHP changes:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Before submitting frontend changes:

```bash
npm run build
```

## Project Review Notes

- The project has a clear service-layer architecture for booking, worker search, payments, reviews, disputes, and admin workflows.
- API controllers are generally thin and delegate business behavior to services.
- Policies, middleware, and Sanctum are used to protect role-specific workflows.
- The current README was stock Laravel boilerplate and has now been replaced with project-specific documentation.
- The local test environment must include `pdo_sqlite` because `phpunit.xml` uses in-memory SQLite.
- There are still legacy `BookingRequest` model/tests alongside the newer `ServiceRequestWorker` workflow. Keep an eye on this during future refactors so old and new booking request concepts do not drift.
- Queue workers are important for notifications and after-commit jobs when `QUEUE_CONNECTION=database`.

## Troubleshooting

If frontend changes do not appear:

```bash
npm run build
```

Or run the Vite development server:

```bash
npm run dev
```

If queued notifications do not appear:

```bash
php artisan queue:work
```

If route or config changes seem stale:

```bash
php artisan optimize:clear
```

If the API returns unauthenticated responses, confirm `APP_URL` matches the local server URL and log in again so the SPA can refresh its session-scoped Sanctum token.
