{{--default Livewire layout do not delete --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name', 'UpEngage') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-neutral-50 min-h-screen antialiased">
    <div class="container mx-auto py-6 px-4">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
