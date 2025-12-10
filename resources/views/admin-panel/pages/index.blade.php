@extends('admin-panel.layouts.main')

@section('title', $title ?? 'Documentation')

@section('content')
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
</div>
@endsection