@extends('admin-panel.layouts.app')

@section('title', $title ?? 'Guide')

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
                <!-- Table of Contents -->
                @if(!empty($toc))
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">Table of Contents</h2>
                    <ul class="space-y-2">
                        @foreach($toc as $item)
                            <li class="ml-{{ ($item['level'] - 1) * 4 }}">
                                <a href="#{{ $item['id'] }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $item['text'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!-- Content -->
                <div class="prose prose-lg max-w-none">
                    {!! $content !!}
                </div>
            </div>
        </div>
</div>
@endsection