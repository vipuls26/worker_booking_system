<template>
    <main class="flex min-h-screen items-center justify-center bg-blue-50 px-4 py-10 dark:bg-gray-950">
        <section class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm shadow-blue-600/20 dark:bg-blue-500">
                    <i class="pi pi-briefcase text-lg" aria-hidden="true"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Local Worker Booking Platform</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Secure access for customers, workers, and admins.</p>
            </div>

            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-blue-100 dark:bg-gray-900 dark:ring-white/10">
                <slot />
            </div>
        </section>
    </main>
</template>
