<script setup>
import DashboardLayout from '../layouts/DashboardLayout.vue';

const steps = [
    { label: 'Find matching workers', icon: 'pi-send' },
    { label: 'Workers respond', icon: 'pi-comments' },
    { label: 'Choose worker', icon: 'pi-user-plus' },
    { label: 'Track work', icon: 'pi-check-circle' },
];
</script>

<template>
    <DashboardLayout title="Customer Dashboard">
        <div class="space-y-5">
            <Transition appear enter-active-class="fade-up-enter-active" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
                <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.08)] dark:border-white/10 dark:bg-slate-900 dark:shadow-none">
                    <div class="grid gap-0 lg:grid-cols-[1fr_320px]">
                        <div class="relative overflow-hidden p-5 sm:p-6">
                        <div class="absolute inset-x-0 top-0 h-40 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.16),_transparent_58%),radial-gradient(circle_at_top,_rgba(16,185,129,0.10),_transparent_42%)] dark:bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.18),_transparent_58%),radial-gradient(circle_at_top,_rgba(16,185,129,0.10),_transparent_42%)]"></div>
                        <div class="relative">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Welcome back</p>
                            <h2 class="mt-2 max-w-2xl text-2xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-3xl">Book a verified local worker without chasing calls.</h2>
                            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                            Send one request, let available workers respond, then choose the best match by price, rating, and experience.
                            </p>

                            <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                                <RouterLink to="/customer/workers" class="inline-flex items-center justify-center gap-2 rounded-full bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                                    <i class="pi pi-search" aria-hidden="true"></i>
                                    Find matching workers
                                </RouterLink>
                                <RouterLink to="/customer/bookings" class="inline-flex items-center justify-center gap-2 rounded-full border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-100 dark:border-white/10 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                                    <i class="pi pi-calendar" aria-hidden="true"></i>
                                    My requests
                                </RouterLink>
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-950/70">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Fast compare</p>
                                    <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white">Price, rating, and experience in one flow.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-950/70">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Verified only</p>
                                    <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white">Choose from workers already approved on the platform.</p>
                                </div>
                                <div class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 backdrop-blur dark:border-white/10 dark:bg-slate-950/70">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Live updates</p>
                                    <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white">Track responses, selection, and job progress in one place.</p>
                                </div>
                            </div>
                        </div>
                        </div>

                        <div class="border-t border-slate-200 bg-slate-50 p-5 dark:border-white/10 dark:bg-slate-950 lg:border-l lg:border-t-0">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">How it works</h3>
                            <div class="mt-4 space-y-3">
                                <div v-for="step in steps" :key="step.label" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-3 dark:border-white/10 dark:bg-slate-900">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-white dark:bg-white dark:text-slate-950">
                                        <i :class="['pi', step.icon]" aria-hidden="true"></i>
                                    </span>
                                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ step.label }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </Transition>

            <Transition appear enter-active-class="fade-up-enter-active delay-100" enter-from-class="fade-up-enter-from" enter-to-class="fade-up-enter-to">
                <section class="grid gap-4 md:grid-cols-2">
                    <RouterLink to="/customer/profile" class="group rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg dark:border-white/10 dark:bg-slate-900 dark:hover:border-white/20">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">Profile and address</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">Keep your phone and address ready so requests are faster to send.</p>
                            </div>
                            <i class="pi pi-arrow-right text-sm text-slate-400 transition group-hover:translate-x-0.5" aria-hidden="true"></i>
                        </div>
                    </RouterLink>

                    <RouterLink to="/notifications" class="group rounded-[24px] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg dark:border-white/10 dark:bg-slate-900 dark:hover:border-white/20">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-white">Worker responses</p>
                                <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-400">Check alerts when workers accept, reject, or when a booking is confirmed.</p>
                            </div>
                            <i class="pi pi-arrow-right text-sm text-slate-400 transition group-hover:translate-x-0.5" aria-hidden="true"></i>
                        </div>
                    </RouterLink>
                </section>
            </Transition>
        </div>
    </DashboardLayout>
</template>
