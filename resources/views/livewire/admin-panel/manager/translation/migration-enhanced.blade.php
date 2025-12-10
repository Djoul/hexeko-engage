<div class="overflow-x-hidden">
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="space-y-6 px-4 sm:px-6 lg:px-8 mx-auto max-w-7xl">

            <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm space-y-6 w-full max-w-full overflow-hidden" wire:key="migration-card">
                @include('admin-panel.partials.env-banner')

                <div class="space-y-6">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-neutral-900">Gestionnaire de migrations de traductions</h1>
                        <p class="mt-1 text-sm text-neutral-600">Suivez, synchronisez et appliquez les migrations de traductions pour chaque interface.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('admin.translations.index') }}"
                           class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Translations
                        </a>
                            <button wire:click="openSyncModal"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Synchroniser depuis S3
                            </button>
                    </div>
                </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <div class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    <span class="text-sm font-medium text-neutral-700">{{ $counters['pending'] }} en attente</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2">
                    <span class="h-2 w-2 rounded-full bg-red-500"></span>
                    <span class="text-sm font-medium text-neutral-700">{{ $counters['failed_recent'] }} échecs (24h)</span>
                </div>
                <div class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2">
                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                    <span class="text-sm font-medium text-neutral-700">{{ $counters['processing'] }} en cours</span>
                </div>
            </div>

            <div class="border-t border-neutral-200 pt-6 space-y-6">
                <div>
                    <span class="text-sm font-medium text-neutral-700">Filtres rapides</span>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button wire:click="applyPreset('to-apply')"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors {{ $activePreset === 'to-apply' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50' }}">
                            À appliquer
                            @if($counters['pending'] > 0)
                                <span class="inline-flex h-5 min-w-[1.75rem] items-center justify-center rounded-full bg-blue-500 px-2 text-xs font-semibold text-white">{{ $counters['pending'] }}</span>
                            @endif
                        </button>
                        <button wire:click="applyPreset('failed-24h')"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors {{ $activePreset === 'failed-24h' ? 'border-red-500 bg-red-50 text-red-600' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50' }}">
                            Échecs (24h)
                            @if($counters['failed_recent'] > 0)
                                <span class="inline-flex h-5 min-w-[1.75rem] items-center justify-center rounded-full bg-red-500 px-2 text-xs font-semibold text-white">{{ $counters['failed_recent'] }}</span>
                            @endif
                        </button>
                        <button wire:click="applyPreset('processing')"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors {{ $activePreset === 'processing' ? 'border-amber-500 bg-amber-50 text-amber-600' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50' }}">
                            En traitement
                            @if($counters['processing'] > 0)
                                <span class="inline-flex h-5 min-w-[1.75rem] items-center justify-center rounded-full bg-amber-500 px-2 text-xs font-semibold text-white">{{ $counters['processing'] }}</span>
                            @endif
                        </button>
                        <button wire:click="applyPreset('custom')"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors {{ $activePreset === 'custom' ? 'border-neutral-900 bg-neutral-900 text-white' : 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50' }}">
                            Personnalisé
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Interface</label>
                        <select wire:model.live="selectedInterface"
                                class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Toutes les interfaces</option>
                            @foreach($interfaces as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Statut</label>
                        <select wire:model.live="selectedStatus"
                                class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Tous les statuts</option>
                            @foreach($statuses as $key => $status)
                                <option value="{{ $key }}">{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Depuis le</label>
                        <input type="date"
                               wire:model.live="dateFrom"
                               class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Recherche</label>
                        <div class="relative">
                            <input type="text"
                                   wire:model.live.debounce.300ms="search"
                                   placeholder="Nom de fichier ou version..."
                                   class="block w-full rounded-md border-neutral-300 pl-10 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-neutral-400">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-neutral-600 md:text-sm">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-neutral-700 shadow-sm">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                {{ $interfaces[$selectedInterface] ?? 'Toutes les interfaces' }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-neutral-600 shadow-sm">
                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m6 4H3m6 0v2m6 4H3m6 0v2m6 4H3" />
                                </svg>
                                {{ $selectedStatus === '' ? 'Tous les statuts' : ($statuses[$selectedStatus]['label'] ?? $selectedStatus) }}
                            </span>
                            @if($dateFrom)
                                <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-neutral-600 shadow-sm">
                                    <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-6 4h2m-7 6h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    Depuis le {{ \Illuminate\Support\Facades\Date::parse($dateFrom)->translatedFormat('d M Y') }}
                                </span>
                            @endif
                            @if($search !== '')
                                <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-neutral-600 shadow-sm">
                                    « {{ $search }} »
                                    <button type="button" wire:click="$set('search', '')" class="text-neutral-400 hover:text-neutral-600">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            @endif
                        </div>

                        @if($this->canEditTranslations())
                            <div class="flex flex-wrap items-center gap-2">
                                <button wire:click="retryFailed"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition-colors hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-0">
                                    <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Relancer les échecs
                                </button>
                                <button wire:click="exportSelected"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition-colors hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-0">
                                    <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                    </svg>
                                    Exporter
                                </button>
                                <button wire:click="applyBulk"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Appliquer la sélection
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                @if(count($selectedMigrations) > 0 && $this->canEditTranslations())
                    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-blue-800">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium">{{ trans_choice(':count migration sélectionnée|:count migrations sélectionnées', count($selectedMigrations)) }}</span>
                                <button type="button"
                                        wire:click="$set('selectedMigrations', [])"
                                        class="text-sm text-blue-600 hover:text-blue-800">
                                    Effacer la sélection
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button wire:click="applyBulk"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0">
                                    Appliquer
                                </button>
                                <button wire:click="retryFailed"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-sm hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-0">
                                    Relancer
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="rounded-xl border border-neutral-200">
                    @if($migrations->isEmpty())
                        <div class="px-6 py-12 text-center">
                            <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-neutral-900">Aucune migration</h3>
                            <p class="mt-1 text-sm text-neutral-500">Synchronisez avec S3 pour découvrir de nouvelles migrations disponibles.</p>
                            <div class="mt-6">
                                <button wire:click="openSyncModal"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    Synchroniser
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-neutral-200 table-fixed">
                                <thead class="bg-neutral-50">
                                    <tr>
                                        <th class="w-12 px-4 py-3">
                                            <input type="checkbox"
                                                   wire:model.live="selectAll"
                                                   class="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            Interface
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            Version
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            Statut
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            Créée
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            Appliquée
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            Vérifications
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200 bg-white">
                                    @foreach($migrations as $migration)
                                        @php
                                            $metadata = is_array($migration->metadata) ? $migration->metadata : [];
                                            $appliedAtRaw = data_get($metadata, 'applied_at');
                                            $appliedAt = null;

                                            if ($appliedAtRaw) {
                                                try {
                                                    $appliedAt = \Illuminate\Support\Facades\Date::parse((string) $appliedAtRaw);
                                                } catch (\Throwable $exception) {
                                                    $appliedAt = null;
                                                }
                                            } elseif ($migration->getAttribute('executed_at')) {
                                                $appliedAt = $migration->getAttribute('executed_at');
                                            }

                                            $manifestValid = (bool) data_get($metadata, 'manifest_valid', false);
                                            $backupCreated = (bool) data_get($metadata, 'backup_created', false);
                                        @endphp
                                        <tr class="hover:bg-neutral-50">
                                            <td class="px-6 py-4">
                                                <input type="checkbox"
                                                       wire:model.live="selectedMigrations"
                                                       value="{{ $migration->id }}"
                                                       class="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                                    {{ ucfirst($migration->interface_origin) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-600">
                                                {{ $migration->version }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                @switch($migration->status)
                                                    @case('pending')
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-800">
                                                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-neutral-500"></span>
                                                            {{ __('Pending') }}
                                                        </span>
                                                        @break
                                                    @case('processing')
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">
                                                            <svg class="h-3 w-3 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                            </svg>
                                                            {{ __('Processing') }}
                                                        </span>
                                                        @break
                                                    @case('completed')
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                            </svg>
                                                            {{ __('Completed') }}
                                                        </span>
                                                        @break
                                                    @case('failed')
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-700">
                                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                            </svg>
                                                            {{ __('Failed') }}
                                                        </span>
                                                        @break
                                                    @default
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-700">
                                                            {{ ucfirst($migration->status) }}
                                                        </span>
                                                @endswitch
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-600">
                                                {{ optional($migration->created_at)->diffForHumans() ?? '—' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-neutral-600">
                                                {{ $appliedAt ? $appliedAt->diffForHumans() : '—' }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                                <div class="flex justify-center gap-2">
                                                    @if($manifestValid)
                                                        <span class="text-green-600" title="Manifest valide">
                                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                            </svg>
                                                        </span>
                                                    @else
                                                        <span class="text-amber-500" title="Manifest à vérifier">
                                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                            </svg>
                                                        </span>
                                                    @endif

                                                    @if($backupCreated)
                                                        <span class="text-blue-600" title="Sauvegarde disponible">
                                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                        </span>
                                                    @else
                                                        <span class="text-neutral-400" title="Pas de sauvegarde">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-right text-sm">
                                                <div class="relative inline-block text-left" x-data="{ open: false }">
                                                    <button @click="open = !open"
                                                            class="rounded-full border border-neutral-200 p-1.5 text-neutral-500 transition hover:border-neutral-300 hover:text-neutral-700">
                                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
                                                        </svg>
                                                    </button>
                                                    <div x-show="open"
                                                         x-transition
                                                         @click.away="open = false"
                                                         class="absolute right-0 z-20 mt-2 w-48 rounded-md border border-neutral-200 bg-white py-1 shadow-lg">
                                                        @if($migration->status === 'pending')
                                                            <button wire:click="applyMigration({{ $migration->id }})"
                                                                    type="button"
                                                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50"
                                                                    >
                                                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                                {{ __('Apply') }}
                                                            </button>
                                                        @endif
                                                        @if($migration->status === 'completed')
                                                            <button wire:click="rollbackMigration({{ $migration->id }})"
                                                                    type="button"
                                                                    class="flex w-full items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50"
                                                               >
                                                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                                </svg>
                                                                {{ __('Rollback') }}
                                                            </button>
                                                        @endif
                                                        <button wire:click="viewDetails({{ $migration->id }})"
                                                                type="button"
                                                                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50">
                                                            <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                            </svg>
                                                            {{ __('View Details') }}
                                                        </button>
                                                        <button wire:click="downloadJson({{ $migration->id }})"
                                                                type="button"
                                                                class="flex w-full items-center gap-2 px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-50">
                                                            <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                            </svg>
                                                            {{ __('Download JSON') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-between border-t border-neutral-200 px-6 py-4 text-sm text-neutral-600">
                            <span>
                                {{ trans_choice('Aucune migration|:count migration affichée|:count migrations affichées', $migrations->total(), ['count' => $migrations->total()]) }}
                            </span>
                            {{ $migrations->links() }}
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3 text-xs text-neutral-500">
                    <span class="font-medium">Raccourcis clavier :</span>
                    <span class="inline-flex items-center gap-1 rounded border border-neutral-200 bg-white px-2 py-1"><kbd>/</kbd> Rechercher</span>
                    <span class="inline-flex items-center gap-1 rounded border border-neutral-200 bg-white px-2 py-1"><kbd>a</kbd> Appliquer la sélection</span>
                    <span class="inline-flex items-center gap-1 rounded border border-neutral-200 bg-white px-2 py-1"><kbd>r</kbd> Actualiser</span>
                </div>
            </div>
        </div>

    </div>

    {{-- Synchronisation Modal --}}
@if($showSyncModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4" role="dialog" aria-modal="true">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl">
            <div class="border-b border-neutral-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-neutral-900">Synchroniser depuis S3</h2>
                        <p class="mt-1 text-sm text-neutral-500">Sélectionnez les interfaces que vous souhaitez synchroniser.</p>
                    </div>
                    <button type="button" wire:click="closeModals" class="rounded-full p-2 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-6 py-5 space-y-5">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-700">Interfaces</h3>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        @foreach($selectedInterfacesForSync as $interface => $isChecked)
                            <label class="flex items-center gap-3 rounded-lg border border-neutral-200 p-3 shadow-sm transition hover:border-blue-500">
                                <input type="checkbox"
                                       wire:model.live="selectedInterfacesForSync.{{ $interface }}"
                                       class="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm font-medium text-neutral-700">{{ ucfirst(str_replace('_', ' ', $interface)) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 text-sm text-neutral-700">
                    <input type="checkbox" wire:model.live="autoProcess" class="mt-1 h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                    <span>Appliquer automatiquement les migrations synchronisées</span>
                </label>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-neutral-200 px-6 py-4">
                <button type="button" wire:click="closeModals" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100">Annuler</button>
                <button type="button" wire:click="syncFromS3" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0">
                    <span wire:loading.remove wire:target="syncFromS3">Synchroniser</span>
                    <span wire:loading wire:target="syncFromS3" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Chargement…
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Apply Modal --}}
@if($showApplyModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4" role="dialog" aria-modal="true">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl">
            <div class="border-b border-neutral-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-neutral-900">Appliquer la migration</h2>
                        <p class="mt-1 text-sm text-neutral-500">Confirmez les options avant d’appliquer la migration sélectionnée.</p>
                    </div>
                    <button type="button" wire:click="closeModals" class="rounded-full p-2 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-6 py-5 space-y-4">
                @if($selectedMigration)
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700">
                        Migration : <span class="font-medium text-neutral-900">{{ $selectedMigration->filename }}</span>
                    </div>
                @endif
                <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 text-sm text-neutral-700">
                    <input type="checkbox" wire:model.live="createBackup" class="mt-1 h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        Créer une sauvegarde
                        <span class="block text-xs text-neutral-500">Recommandé afin de pouvoir restaurer les traductions si nécessaire.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-lg border border-neutral-200 p-3 text-sm text-neutral-700">
                    <input type="checkbox" wire:model.live="validateChecksum" class="mt-1 h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                    <span>
                        Valider le checksum
                        <span class="block text-xs text-neutral-500">Vérifie l’intégrité du fichier avant de l’appliquer.</span>
                    </span>
                </label>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-neutral-200 px-6 py-4">
                <button type="button" wire:click="closeModals" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100">Annuler</button>
                <button type="button" wire:click="applyMigration" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0">
                    <span wire:loading.remove wire:target="applyMigration">Appliquer</span>
                    <span wire:loading wire:target="applyMigration" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Application…
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Rollback Modal --}}
@if($showRollbackModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/40 px-4" role="dialog" aria-modal="true">
        <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl">
            <div class="border-b border-neutral-200 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-neutral-900">Annuler la migration</h2>
                        <p class="mt-1 text-sm text-neutral-500">Restaurez l’état précédent des traductions.</p>
                    </div>
                    <button type="button" wire:click="closeModals" class="rounded-full p-2 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            <div class="px-6 py-5 space-y-4">
                @if($selectedMigration)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Vous êtes sur le point d’annuler la migration <span class="font-medium">{{ $selectedMigration->filename }}</span>.
                    </div>
                    <p class="text-sm text-neutral-600">Vous pourrez réappliquer cette migration plus tard si besoin.</p>
                @endif
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-neutral-200 px-6 py-4">
                <button type="button" wire:click="closeModals" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100">Fermer</button>
                <button type="button" wire:click="rollbackMigration" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-0">
                    <span wire:loading.remove wire:target="rollbackMigration">Annuler la migration</span>
                    <span wire:loading wire:target="rollbackMigration" class="flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Annulation…
                    </span>
                </button>
            </div>
        </div>
    </div>
@endif

{{-- Preview Drawer --}}
@if($showPreviewDrawer)
    <div class="fixed inset-0 z-50 overflow-hidden" aria-modal="true" role="dialog">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-neutral-900/40" wire:click="closeModals" aria-hidden="true"></div>
            <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div class="w-screen max-w-xl">
                    <div class="flex h-full flex-col bg-white shadow-xl">
                        <div class="border-b border-neutral-200 px-6 py-5">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-neutral-900">Aperçu de la migration</h3>
                                    @if($selectedMigration)
                                        <p class="mt-1 text-sm text-neutral-500">{{ $selectedMigration->filename }}</p>
                                    @endif
                                </div>
                                <button type="button" wire:click="closeModals" class="rounded-full p-2 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-6 py-5">
                            @if(!empty($previewData))
                                <div class="space-y-6">
                                    @if(isset($previewData['summary']))
                                        <div class="rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-5">
                                            <h4 class="text-sm font-semibold text-neutral-700">Résumé</h4>
                                            <dl class="mt-4 grid grid-cols-2 gap-4">
                                                <div>
                                                    <dt class="text-xs text-neutral-500">Total de clés</dt>
                                                    <dd class="text-lg font-semibold text-neutral-900">{{ $previewData['summary']['total_keys'] ?? 0 }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs text-neutral-500">Nouvelles clés</dt>
                                                    <dd class="text-lg font-semibold text-green-600">{{ $previewData['summary']['new_keys'] ?? 0 }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs text-neutral-500">Clés mises à jour</dt>
                                                    <dd class="text-lg font-semibold text-amber-600">{{ $previewData['summary']['updated_keys'] ?? 0 }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-xs text-neutral-500">Clés supprimées</dt>
                                                    <dd class="text-lg font-semibold text-red-600">{{ $previewData['summary']['deleted_keys'] ?? 0 }}</dd>
                                                </div>
                                            </dl>
                                        </div>
                                    @endif

                                    @if(isset($previewData['changes']) && is_array($previewData['changes']) && count($previewData['changes']) > 0)
                                        <div class="space-y-3">
                                            <h4 class="text-sm font-semibold text-neutral-700">Modifications</h4>
                                            <div class="space-y-2">
                                                @foreach(array_slice($previewData['changes'], 0, 10) as $change)
                                                    <div class="rounded-xl border border-neutral-200 bg-white px-4 py-3">
                                                        <p class="font-mono text-xs text-neutral-500">{{ $change['key'] ?? 'N/A' }}</p>
                                                        <p class="mt-1 text-sm text-neutral-800">{{ $change['value'] ?? 'N/A' }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800">
                                    Aucun aperçu disponible pour le moment. Réessayez plus tard.
                                </div>
                            @endif
                        </div>
                        <div class="border-t border-neutral-200 px-6 py-4">
                            <div class="flex items-center justify-end gap-3">
                                <button type="button" wire:click="closeModals" class="rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-100">Fermer</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

    {{-- Toast notifications --}}
    <div x-data="{ show: false, type: '', message: '' }"
     x-on:migration-synced.window="show = true; type = 'success'; message = $event.detail[0].message; setTimeout(() => show = false, 4000)"
     x-on:migration-applied.window="show = true; type = 'success'; message = $event.detail[0].message; setTimeout(() => show = false, 4000)"
     x-on:migration-rolled-back.window="show = true; type = 'warning'; message = $event.detail[0].message; setTimeout(() => show = false, 4000)"
     x-on:migration-error.window="show = true; type = 'error'; message = $event.detail[0].message; setTimeout(() => show = false, 4000)"
     x-show="show"
     x-transition
     x-cloak
     class="fixed bottom-6 right-6 z-50 max-w-sm">
    <div :class="{
            'border-green-200 bg-green-50 text-green-800': type === 'success',
            'border-amber-200 bg-amber-50 text-amber-800': type === 'warning',
            'border-red-200 bg-red-50 text-red-800': type === 'error'
        }"
         class="flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg">
        <div>
            <svg x-show="type === 'success'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <svg x-show="type === 'warning'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <svg x-show="type === 'error'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </div>
        <div class="text-sm font-medium" x-text="message"></div>
        <button @click="show = false" type="button" class="ml-auto rounded-full bg-white/70 p-1 text-neutral-500 hover:text-neutral-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('livewire:load', () => {
        const componentId = '{{ $attributes['wire:id'] ?? '' }}';
        const getInstance = () => Livewire.find(componentId);

        document.addEventListener('keydown', (event) => {
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') {
                return;
            }

            if (event.key === 'a' && !event.ctrlKey && !event.metaKey) {
                const instance = getInstance();
                instance && instance.call('applyBulk');
            }

            if (event.key === 'r' && !event.ctrlKey && !event.metaKey) {
                const instance = getInstance();
                instance && instance.call('$refresh');
            }
        });

        Livewire.on('download', (payload) => {
            try {
                const { filename, content, contentType } = payload || {};
                const blob = new Blob([content], { type: contentType || 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = filename || 'migration.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            } catch (error) {
                console.error('Failed to download migration file', error);
            }
        });
    });
</script>
@endpush
