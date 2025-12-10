@extends('admin-panel.layouts.app')

@section('title', 'Guide de démarrage rapide - UpEngage Documentation')

@section('page-content')
    <nav class="text-sm mb-4">
        <ol class="list-none p-0 inline-flex">
            <li class="flex items-center">
                <a href="{{ route('admin.index') }}" class="text-primary-600 hover:text-primary-800">Documentation</a>
                <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
            </li>
            <li>
                <span class="text-neutral-500">Guide de démarrage rapide</span>
            </li>
        </ol>
    </nav>

    <h1 class="text-3xl font-bold text-neutral-900 mb-6">Guide de démarrage rapide</h1>

    <livewire:admin-panel.quick-start-page />
@endsection