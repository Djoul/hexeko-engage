@extends('admin-panel.layouts.app')

@section('title', 'Translation Migrations')

@section('page-content')
    <div class="space-y-6">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900">Translation Migrations</h1>
                    <p class="text-sm text-neutral-600 mt-1">Manage and monitor translation file migrations across interfaces</p>
                </div>
                <div class="flex gap-3">
                    <a href="{{ route('admin.translations.index') }}"
                       class="inline-flex items-center px-4 py-2 border border-neutral-300 text-neutral-700 rounded-md hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-neutral-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                        Translations
                    </a>
                    <button type="button" 
                            onclick="openSyncModal()"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Sync from S3
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="border-t border-neutral-200 pt-4">
                <form method="GET" action="{{ route('admin.translation-migrations.index') }}" class="flex flex-wrap gap-4">
                    <!-- Search -->
                    <div class="flex-1 min-w-[200px]">
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}"
                               placeholder="Search by filename or version..." 
                               class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    </div>

                    <!-- Interface Filter -->
                    <div class="min-w-[150px]">
                        <select name="interface" 
                                class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">All Interfaces</option>
                            @foreach($interfaces as $value => $label)
                                <option value="{{ $value }}" {{ request('interface') == $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="min-w-[150px]">
                        <select name="status" 
                                class="w-full px-3 py-2 border border-neutral-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $value => $config)
                                <option value="{{ $value }}" {{ request('status') == $value ? 'selected' : '' }}>
                                    {{ $config['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Filter Button -->
                    <button type="submit" 
                            class="px-4 py-2 bg-neutral-100 text-neutral-700 rounded-md hover:bg-neutral-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-neutral-500 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </button>

                    @if(request()->hasAny(['search', 'interface', 'status']))
                        <a href="{{ route('admin.translation-migrations.index') }}" 
                           class="px-4 py-2 text-neutral-600 hover:text-neutral-800">
                            Clear filters
                        </a>
                    @endif
                </form>
            </div>
        </div>

        <!-- Migrations Table -->
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-600 uppercase tracking-wider">
                                Interface
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-600 uppercase tracking-wider">
                                Version
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-600 uppercase tracking-wider">
                                Filename
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-600 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-600 uppercase tracking-wider">
                                Executed At
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-600 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-neutral-200">
                        @forelse($migrations as $migration)
                            <tr class="hover:bg-neutral-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                 @if($migration->interface_origin == 'mobile') bg-purple-100 text-purple-800
                                                 @elseif($migration->interface_origin == 'web_financer') bg-blue-100 text-blue-800
                                                 @else bg-green-100 text-green-800
                                                 @endif">
                                        {{ $interfaces[$migration->interface_origin] ?? $migration->interface_origin }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900 font-mono">
                                    {{ $migration->version }}
                                </td>
                                <td class="px-6 py-4 text-sm text-neutral-900">
                                    <a href="{{ route('admin.translation-migrations.show', $migration) }}" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $migration->filename }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @php
                                        $statusConfig = $statuses[$migration->status] ?? ['label' => $migration->status, 'color' => 'neutral'];
                                        $color = $statusConfig['color'];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                 bg-{{ $color }}-100 text-{{ $color }}-800">
                                        {{ $statusConfig['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-600">
                                    {{ $migration->executed_at ? $migration->executed_at->format('Y-m-d H:i') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center gap-2">
                                        @if($migration->status === 'pending')
                                            <button type="button" 
                                                    onclick="applyMigration({{ $migration->id }})"
                                                    class="text-blue-600 hover:text-blue-800">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        @elseif($migration->status === 'completed' && isset($migration->metadata) && is_array($migration->metadata) && ($migration->metadata['backup_path'] ?? false))
                                            <button type="button"
                                                    onclick="rollbackMigration({{ $migration->id }})"
                                                    class="text-orange-600 hover:text-orange-800">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                </svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('admin.translation-migrations.show', $migration) }}" 
                                           class="text-neutral-600 hover:text-neutral-800">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-neutral-500">
                                    <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p class="mt-2">No migrations found</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($migrations->hasPages())
                <div class="bg-white px-4 py-3 border-t border-neutral-200">
                    {{ $migrations->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Include Modals -->
    @include('admin-panel.pages.translation-migrations.partials.sync-modal')
    @include('admin-panel.pages.translation-migrations.partials.apply-modal')
    @include('admin-panel.pages.translation-migrations.partials.rollback-modal')
@endsection

@push('scripts')
<script>
    // Modal functions will be implemented in the next step
    function openSyncModal() {
        document.getElementById('syncModal').classList.remove('hidden');
    }

    function applyMigration(id) {
        document.getElementById('applyMigrationId').value = id;
        document.getElementById('applyModal').classList.remove('hidden');
    }

    function rollbackMigration(id) {
        document.getElementById('rollbackMigrationId').value = id;
        document.getElementById('rollbackModal').classList.remove('hidden');
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.add('hidden');
    }
</script>
@endpush