@extends('admin-panel.layouts.app')

@section('title', 'Migration Details')

@section('page-content')
    <div class="space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <a href="{{ route('admin.translation-migrations.index') }}" class="text-neutral-500 hover:text-neutral-700">
                        <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                        </svg>
                    </a>
                </li>
                <li class="flex items-center">
                    <svg class="flex-shrink-0 h-5 w-5 text-neutral-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                    <a href="{{ route('admin.translation-migrations.index') }}" class="ml-4 text-sm font-medium text-neutral-500 hover:text-neutral-700">
                        Translation Migrations
                    </a>
                </li>
                <li class="flex items-center">
                    <svg class="flex-shrink-0 h-5 w-5 text-neutral-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                    <span class="ml-4 text-sm font-medium text-neutral-900">{{ $migration->filename }}</span>
                </li>
            </ol>
        </nav>

        <!-- Main Content -->
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200">
            <!-- Header -->
            <div class="px-6 py-4 border-b border-neutral-200">
                <div class="flex items-center justify-between">
                    <h1 class="text-xl font-bold text-neutral-900">Migration Details</h1>
                    <div class="flex gap-3">
                        @if($migration->status === 'pending')
                            <button type="button"
                                    onclick="applyMigration({{ $migration->id }})"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Apply Migration
                            </button>
                        @elseif($migration->status === 'completed' && ($migration->metadata['backup_path'] ?? false))
                            <button type="button"
                                    onclick="rollbackMigration({{ $migration->id }})"
                                    class="inline-flex items-center px-4 py-2 bg-warning-600 text-white rounded-md hover:bg-warning-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-warning-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                                Rollback
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Details Grid -->
            <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Interface</dt>
                        <dd class="mt-1">
                            @php
                                $interfaces = [
                                    'mobile' => ['label' => 'Mobile', 'color' => 'purple'],
                                    'web_financer' => ['label' => 'Web Financer', 'color' => 'blue'],
                                    'web_beneficiary' => ['label' => 'Web Beneficiary', 'color' => 'green'],
                                ];
                                $interface = $interfaces[$migration->interface_origin] ?? ['label' => $migration->interface_origin, 'color' => 'neutral'];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $interface['color'] }}-100 text-{{ $interface['color'] }}-800">
                                {{ $interface['label'] }}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Version</dt>
                        <dd class="mt-1 text-sm text-neutral-900 font-mono">{{ $migration->version }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Filename</dt>
                        <dd class="mt-1 text-sm text-neutral-900 font-mono">{{ $migration->filename }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Checksum</dt>
                        <dd class="mt-1 text-sm text-neutral-900 font-mono break-all">
                            {{ $migration->checksum ?: '-' }}
                        </dd>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-4">
                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Status</dt>
                        <dd class="mt-1">
                            @php
                                $statuses = [
                                    'pending' => ['label' => 'Pending', 'color' => 'warning'],
                                    'processing' => ['label' => 'Processing', 'color' => 'info'],
                                    'completed' => ['label' => 'Completed', 'color' => 'success'],
                                    'failed' => ['label' => 'Failed', 'color' => 'error'],
                                    'rolled_back' => ['label' => 'Rolled Back', 'color' => 'neutral'],
                                ];
                                $status = $statuses[$migration->status] ?? ['label' => $migration->status, 'color' => 'neutral'];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $status['color'] }}-100 text-{{ $status['color'] }}-800">
                                {{ $status['label'] }}
                            </span>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Created At</dt>
                        <dd class="mt-1 text-sm text-neutral-900">
                            {{ $migration->created_at->format('Y-m-d H:i:s') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Executed At</dt>
                        <dd class="mt-1 text-sm text-neutral-900">
                            {{ $migration->executed_at ? $migration->executed_at->format('Y-m-d H:i:s') : '-' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-neutral-600">Rolled Back At</dt>
                        <dd class="mt-1 text-sm text-neutral-900">
                            {{ $migration->rolled_back_at ? $migration->rolled_back_at->format('Y-m-d H:i:s') : '-' }}
                        </dd>
                    </div>
                </div>
            </div>

            <!-- Metadata Section -->
            @if($migration->metadata && count($migration->metadata) > 0)
                <div class="px-6 py-4 border-t border-neutral-200">
                    <h2 class="text-lg font-semibold text-neutral-900 mb-3">Metadata</h2>
                    <div class="bg-neutral-50 rounded-lg p-4">
                        <pre class="text-sm text-neutral-700 font-mono overflow-x-auto">{{ json_encode($migration->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            @endif

            <!-- Activity Log -->
            <div class="px-6 py-4 border-t border-neutral-200">
                <h2 class="text-lg font-semibold text-neutral-900 mb-3">Activity Log</h2>
                <div class="space-y-3">
                    @if($migration->rolled_back_at)
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-warning-100 flex items-center justify-center">
                                    <svg class="h-4 w-4 text-warning-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-neutral-900">Rolled back</p>
                                <p class="text-sm text-neutral-600">{{ $migration->rolled_back_at->diffForHumans() }}</p>
                                @if($migration->metadata['rollback_reason'] ?? false)
                                    <p class="text-sm text-neutral-600 mt-1">Reason: {{ $migration->metadata['rollback_reason'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($migration->executed_at)
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="h-8 w-8 rounded-full bg-success-100 flex items-center justify-center">
                                    <svg class="h-4 w-4 text-success-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-neutral-900">Applied successfully</p>
                                <p class="text-sm text-neutral-600">{{ $migration->executed_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 rounded-full bg-neutral-100 flex items-center justify-center">
                                <svg class="h-4 w-4 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-neutral-900">Created</p>
                            <p class="text-sm text-neutral-600">{{ $migration->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Changes Preview -->
        <x-translation-changes-preview
            :previewData="$previewData"
            title="Aperçu des changements"
            description="Modifications qui seront appliquées lors de l'exécution de cette migration"
        />
    </div>

    <!-- Include Modals -->
    @include('admin-panel.pages.translation-migrations.partials.apply-modal')
    @include('admin-panel.pages.translation-migrations.partials.rollback-modal')
@endsection

@push('scripts')
<script>
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
