@extends('admin-panel.layouts.main')

@section('content')
@php
    $userAgent = request()->header('User-Agent', '');
    $isMobileDevice = (bool) preg_match('/Mobile|Android|iP(hone|od|ad)|IEMobile|BlackBerry|Opera Mini|webOS/i', $userAgent);
@endphp

@if($isMobileDevice)
    @include('admin-panel.partials.desktop-only')
@else
    <div class="min-h-screen bg-neutral-50">
        <!-- Header -->
        <livewire:admin-panel.header />

        <!-- Main Layout -->
        <div class="flex">
            <!-- Sidebar -->
            <livewire:admin-panel.sidebar />

            <!-- Main Content -->
            <main class="flex-1">
                @if(request()->is('admin-panel/translation-migrations*'))
                    @include('admin-panel.partials.env-banner')
                @endif

                <div class="px-4 sm:px-6 lg:px-8 py-8">
                    <div class="max-w-7xl mx-auto">
                        @if(isset($slot))
                            {{ $slot }}
                        @else
                            @yield('page-content')
                        @endif
                    </div>
                </div>
            </main>
        </div>
    </div>
@endif
@endsection
