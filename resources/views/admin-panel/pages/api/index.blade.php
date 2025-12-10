@extends('admin-panel.layouts.app')

@section('title', $title ?? 'API Reference')

@section('page-content')
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="p-6 bg-white border-b border-neutral-200">
                <h1 class="text-2xl font-bold mb-6">{{ $title ?? 'API Reference' }}</h1>

                <!-- API Endpoints List -->
                <div class="space-y-4">
                    @forelse($endpoints ?? [] as $controller => $routes)
                        <div class="border rounded-lg p-4">
                            <h2 class="text-lg font-semibold mb-2">{{ $controller }}</h2>
                            <ul class="space-y-2">
                                @foreach($routes as $route)
                                    <li>
                                        <a href="{{ route('admin.api.show', $route['name'] ?? '') }}" class="text-primary-600 hover:text-primary-800">
                                            {{ $route['method'] ?? 'GET' }} {{ $route['path'] ?? '' }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-neutral-500">No API endpoints found.</p>
                    @endforelse
                </div>
            </div>
    </div>
@endsection