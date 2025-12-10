@extends('admin-panel.layouts.app')

@section('title', $title ?? 'WebSocket Demo')

@section('page-content')
        <!-- Breadcrumbs -->
        @if(!empty($breadcrumbs))
        <nav class="mb-8" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-2 text-neutral-500">
                @foreach($breadcrumbs as $index => $breadcrumb)
                    @if($index > 0)
                        <li class="flex items-center">
                            <svg class="w-5 h-5 text-neutral-400" fill="currentColor" viewBox="0 0 20 20">
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
                <h1 class="text-2xl font-bold mb-6">{{ $title ?? 'WebSocket Demo' }}</h1>

                <!-- WebSocket Demo Component -->
                @if(class_exists(\Livewire\Livewire::class))
                    @livewire('admin-panel.websocket-demo', [
                        'reverbKey' => $reverbKey ?? config('reverb.apps.0.key')
                    ])
                @else
                    <div class="p-4 bg-gray-100 rounded">
                        <p>WebSocket demo component placeholder</p>
                        <p>Reverb Key: {{ $reverbKey ?? 'Not configured' }}</p>
                    </div>
                @endif
            </div>
        </div>
</div>
@endsection