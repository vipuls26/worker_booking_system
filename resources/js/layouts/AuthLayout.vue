<template>
    <main class="app-auth-shell flex items-center justify-center px-4 py-10">
        <section class="w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="app-auth-brand mx-auto mb-4">
                    <i class="pi pi-briefcase text-lg" aria-hidden="true"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Local Worker Booking Platform</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Secure access for customers, workers, and admins.</p>
            </div>

            <div class="app-auth-card p-6">
                <slot />
            </div>
        </section>
    </main>
</template>
