<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Mode</title>

    @vite(['resources/css/app.css'])

    <style>
        body {
            min-height: 100vh;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900">

    <div class="min-h-screen flex items-center justify-center px-6">
        <div class="max-w-lg w-full bg-white dark:bg-gray-800 shadow-2xl rounded-2xl p-10 text-center">

            <!-- Logo / Icon -->
            <div class="flex justify-center mb-6">
                <div class="w-20 h-20 rounded-full bg-yellow-100 flex items-center justify-center">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="w-10 h-10 text-yellow-600"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="1.8"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"
                        />
                    </svg>
                </div>
            </div>

            <!-- Heading -->
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white mb-4">
                We’ll Be Back Soon 🚀
            </h1>

            <!-- Description -->
            <p class="text-gray-600 dark:text-gray-300 text-base leading-relaxed mb-6">
                Our system is currently undergoing scheduled maintenance to improve
                performance and reliability.
            </p>

            <p class="text-gray-500 dark:text-gray-400 text-sm mb-8">
                Please check again in a few minutes.
            </p>

            <!-- Loader -->
            <div class="flex justify-center mb-8">
                <div class="w-10 h-10 border-4 border-gray-300 border-t-transparent rounded-full animate-spin"></div>
            </div>

            <!-- Status -->
            <div class="bg-gray-100 dark:bg-gray-700 rounded-lg px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                <span class="font-semibold">Status:</span>
                Scheduled Maintenance
            </div>

            <!-- Footer -->
            <div class="mt-8 text-xs text-gray-400">
                © {{ date('Y') }} Worker Booking System. All rights reserved.
            </div>
        </div>
    </div>

</body>
</html>
