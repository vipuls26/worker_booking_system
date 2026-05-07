<script setup>
import { RouterLink } from 'vue-router';
import DashboardLayout from '../layouts/DashboardLayout.vue';

const bookingFlow = [
    {
        title: 'Search workers',
        description: 'Filter by service, price, city, rating, and availability.',
        icon: 'pi-search',
        to: '/customer/workers',
    },
    {
        title: 'Send request',
        description: 'Choose one or more workers and share the service details.',
        icon: 'pi-send',
        to: '/customer/workers',
    },
    {
        title: 'Track booking',
        description: 'Follow requests, accepted workers, progress, and completion.',
        icon: 'pi-calendar',
        to: '/customer/bookings',
    },
    {
        title: 'Review worker',
        description: 'Rate completed work so future customers can choose confidently.',
        icon: 'pi-star',
        to: '/customer/bookings',
    },
];

const actionCards = [
    {
        title: 'Find Workers',
        description: 'Use the full card-based search with service, city, price, rating, and availability filters.',
        icon: 'pi-search',
        to: '/customer/workers',
        primary: true,
    },
    {
        title: 'My Bookings',
        description: 'Track requests, accepted workers, progress updates, cancellations, and completed jobs.',
        icon: 'pi-calendar',
        to: '/customer/bookings',
    },
    {
        title: 'Profile',
        description: 'Keep your phone and service address ready before sending booking requests.',
        icon: 'pi-user',
        to: '/customer/profile',
    },
    {
        title: 'Notifications',
        description: 'Check worker responses, booking updates, and review activity.',
        icon: 'pi-bell',
        to: '/notifications',
    },
];
</script>

<template>
    <DashboardLayout title="Customer Dashboard">
        <section class="rounded-lg bg-gray-900 p-5 text-white shadow-sm dark:bg-white dark:text-gray-950 sm:p-6">
            <div class="grid gap-5 lg:grid-cols-[1fr_360px] lg:items-center">
                <div>
                    <p class="text-sm font-medium uppercase opacity-70">Local Worker Booking</p>
                    <h2 class="mt-2 max-w-3xl text-2xl font-semibold sm:text-3xl">Book local help with a simple request-first workflow.</h2>
                    <p class="mt-3 max-w-2xl text-sm text-white/75 dark:text-gray-700">Search approved workers, send one request to multiple people, choose the final worker, then track the job until review.</p>
                </div>
                <div class="rounded-lg bg-white/10 p-4 dark:bg-gray-950/5">
                    <p class="text-sm font-semibold">Next best action</p>
                    <p class="mt-1 text-sm opacity-75">Start with the worker search and select the service you need.</p>
                    <RouterLink to="/customer/workers" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-950 transition hover:bg-gray-100 dark:bg-gray-950 dark:text-white dark:hover:bg-gray-800">
                        <i class="pi pi-search" aria-hidden="true"></i>
                        Find workers
                    </RouterLink>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap gap-3">
                <RouterLink to="/customer/workers" class="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-950 transition hover:bg-gray-100 dark:bg-gray-950 dark:text-white dark:hover:bg-gray-800">
                    <i class="pi pi-search" aria-hidden="true"></i>
                    Find workers
                </RouterLink>
                <RouterLink to="/customer/bookings" class="inline-flex items-center gap-2 rounded-md border border-white/30 px-4 py-2 text-sm font-semibold text-white transition hover:bg-white/10 dark:border-gray-950/20 dark:text-gray-950 dark:hover:bg-gray-950/5">
                    <i class="pi pi-calendar" aria-hidden="true"></i>
                    My bookings
                </RouterLink>
            </div>
        </section>

        <section class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <RouterLink
                v-for="card in actionCards"
                :key="card.title"
                :to="card.to"
                class="group rounded-lg border p-4 transition hover:-translate-y-0.5 hover:shadow-sm"
                :class="card.primary
                    ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-950'
                    : 'border-gray-200 bg-white text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white'"
            >
                <span
                    class="inline-flex size-10 items-center justify-center rounded-md"
                    :class="card.primary ? 'bg-white/15 dark:bg-gray-950/10' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'"
                >
                    <i :class="['pi', card.icon]" aria-hidden="true"></i>
                </span>
                <h3 class="mt-4 font-semibold">{{ card.title }}</h3>
                <p class="mt-1 text-sm" :class="card.primary ? 'text-white/75 dark:text-gray-700' : 'text-gray-600 dark:text-gray-400'">{{ card.description }}</p>
                <span class="mt-4 inline-flex items-center gap-2 text-sm font-semibold">
                    Open
                    <i class="pi pi-arrow-right text-xs transition group-hover:translate-x-0.5" aria-hidden="true"></i>
                </span>
            </RouterLink>
        </section>

        <section class="mt-6 rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10 sm:p-5">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase text-gray-500 dark:text-gray-400">Customer Flow</p>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">From search to review</h2>
                </div>
                <RouterLink to="/customer/bookings" class="text-sm font-semibold text-gray-700 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white">View bookings</RouterLink>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <RouterLink v-for="(step, index) in bookingFlow" :key="step.title" :to="step.to" class="group rounded-lg border border-gray-200 p-4 transition hover:-translate-y-0.5 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="inline-flex size-10 items-center justify-center rounded-md bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200">
                            <i :class="['pi', step.icon]" aria-hidden="true"></i>
                        </span>
                        <span class="text-sm font-semibold text-gray-400">0{{ index + 1 }}</span>
                    </div>
                    <h3 class="mt-4 font-semibold text-gray-900 dark:text-white">{{ step.title }}</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ step.description }}</p>
                </RouterLink>
            </div>
        </section>
    </DashboardLayout>
</template>
