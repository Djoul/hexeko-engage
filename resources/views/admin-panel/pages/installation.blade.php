@extends('admin-panel.layouts.main')

@section('title', 'Installation & Docker - UpEngage Documentation')

@section('content')
<div class="page-wrapper">
    <livewire:admin-panel.sidebar />

    <main class="main-content">
        <div class="breadcrumb">
            <a href="{{ route('admin.index') }}">Documentation</a> / Installation & Docker
        </div>

        <h1 class="page-title">Installation & Docker</h1>

        <livewire:admin-panel.installation-page />
    </main>
</div>
@endsection