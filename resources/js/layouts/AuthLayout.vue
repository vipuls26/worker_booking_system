<template>
    <main class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-10 dark:bg-slate-950">
        <section class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">
                    <i class="pi pi-briefcase text-lg" aria-hidden="true"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Local Worker Booking Platform</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Secure access for customers, workers, and admins.</p>
            </div>

            <div class="app-surface p-6">
                <slot />
            </div>
        </section>
    </main>
</template>
