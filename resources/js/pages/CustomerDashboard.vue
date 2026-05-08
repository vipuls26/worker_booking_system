<script setup>
import DashboardLayout from '../layouts/DashboardLayout.vue';

const steps = [
    { label: 'Create request', icon: 'pi-send' },
    { label: 'Workers respond', icon: 'pi-comments' },
    { label: 'Choose worker', icon: 'pi-user-plus' },
    { label: 'Track work', icon: 'pi-check-circle' },
];
</script>

<template>
    <DashboardLayout title="Customer Dashboard">
        <div class="space-y-5">
            <section class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
                <div class="grid gap-0 lg:grid-cols-[1fr_320px]">
                    <div class="p-5 sm:p-6">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Welcome back</p>
                        <h2 class="mt-2 max-w-2xl text-2xl font-semibold text-gray-900 dark:text-white">Book a verified local worker without chasing calls.</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-400">
                            Send one request, let available workers respond, then choose the best match by price, rating, and experience.
                        </p>

                        <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                            <RouterLink to="/customer/workers" class="inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-950 dark:hover:bg-gray-200">
                                <i class="pi pi-search" aria-hidden="true"></i>
                                Find worker
                            </RouterLink>
                            <RouterLink to="/customer/bookings" class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">
                                <i class="pi pi-calendar" aria-hidden="true"></i>
                                My requests
                            </RouterLink>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 bg-gray-50 p-5 dark:border-white/10 dark:bg-gray-950 lg:border-l lg:border-t-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">How it works</h3>
                        <div class="mt-4 space-y-3">
                            <div v-for="step in steps" :key="step.label" class="flex items-center gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-md bg-white text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">
                                    <i :class="['pi', step.icon]" aria-hidden="true"></i>
                                </span>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ step.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <RouterLink to="/customer/profile" class="group rounded-lg border border-gray-200 bg-white p-5 transition hover:border-gray-300 hover:shadow-sm dark:border-white/10 dark:bg-gray-900 dark:hover:border-white/20">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">Profile and address</p>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">Keep your phone and address ready so requests are faster to send.</p>
                        </div>
                        <i class="pi pi-arrow-right text-sm text-gray-400 transition group-hover:translate-x-0.5" aria-hidden="true"></i>
                    </div>
                </RouterLink>

                <RouterLink to="/notifications" class="group rounded-lg border border-gray-200 bg-white p-5 transition hover:border-gray-300 hover:shadow-sm dark:border-white/10 dark:bg-gray-900 dark:hover:border-white/20">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white">Worker responses</p>
                            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400">Check alerts when workers accept, reject, or when a booking is confirmed.</p>
                        </div>
                        <i class="pi pi-arrow-right text-sm text-gray-400 transition group-hover:translate-x-0.5" aria-hidden="true"></i>
                    </div>
                </RouterLink>
            </section>
        </div>
    </DashboardLayout>
</template>
