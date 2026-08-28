<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;900&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-10 sm:pt-0 bg-gradient-to-b from-cream to-white px-4">
            <div>
                <a href="{{ route('platform.home') }}">
                    <x-brand-logo />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-8 px-8 py-8 bg-white shadow-xl shadow-gray-900/5 border border-gray-100 overflow-hidden rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
