<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Documentation') - {{ config('app.name', 'UpEngage') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon_io/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon_io/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon_io/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon_io/site.webmanifest') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@100..800&display=swap" rel="stylesheet">

    <!-- Styles -->
    @unless(app()->environment('testing'))
        @vite(['resources/css/app.css', 'resources/css/style.css'])
    @endunless
    @if(class_exists(\Livewire\Livewire::class))
        @livewireStyles
    @endif
</head>
<body class="font-sans antialiased text-neutral-700 max-w-[100vw] overflow-x-hidden">
    <div class="min-h-screen bg-neutral-50 max-w-[100vw] overflow-x-hidden">
        <!-- Page Content -->

        <main class="w-full max-w-[100vw]">
            @yield('content')
        </main>
    </div>

    <!-- Scripts -->
    @unless(app()->environment('testing'))
        @vite(['resources/js/script.js'])
    @endunless

    @stack('scripts')

    @if(class_exists(\Livewire\Livewire::class))
        @livewireScripts
    @endif
</body>
</html>
