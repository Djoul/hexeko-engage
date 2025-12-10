@extends('admin-panel.layouts.main')

@section('title', $title ?? 'API Endpoint')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        @if(!empty($breadcrumbs))
        <nav class="mb-8" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-gray-500">
                @foreach($breadcrumbs as $index => $breadcrumb)
                    @if($index > 0)
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </li>
                    @endif
                    <li>
                        @if(isset($breadcrumb['url']))
                            <a href="{{ $breadcrumb['url'] }}" class="text-blue-600 hover:text-blue-800">
                                {{ $breadcrumb['label'] }}
                            </a>
                        @else
                            <span class="text-gray-700">{{ $breadcrumb['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </nav>
        @endif

        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 bg-white border-b border-gray-200">
                <h1 class="text-2xl font-bold mb-6">{{ $endpoint['name'] ?? $title ?? 'API Endpoint' }}</h1>

                <!-- Endpoint Details -->
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold">Endpoint</h3>
                        <code class="block bg-gray-100 p-2 rounded">
                            {{ $endpoint['method'] ?? 'GET' }} {{ $endpoint['path'] ?? '' }}
                        </code>
                    </div>

                    @if(!empty($endpoint['description']))
                    <div>
                        <h3 class="text-lg font-semibold">Description</h3>
                        <p>{{ $endpoint['description'] }}</p>
                    </div>
                    @endif

                    @if(!empty($endpoint['parameters']))
                    <div>
                        <h3 class="text-lg font-semibold">Parameters</h3>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Required</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($endpoint['parameters'] as $param)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $param['name'] ?? '' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $param['type'] ?? 'string' }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $param['required'] ?? false ? 'Yes' : 'No' }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $param['description'] ?? '' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    <!-- API Tester Component -->
                    @if(class_exists(\Livewire\Livewire::class))
                        @livewire('admin-panel.api-endpoint-tester', ['endpoint' => $endpoint])
                    @else
                        <div class="p-4 bg-gray-100 rounded">
                            <p>API tester component placeholder</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection