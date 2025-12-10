<div>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="space-y-6">
        <!-- Header - Exact copy of Translation Manager style -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">Gestionnaire de migrations de traductions -- </h1>
                    <p class="mt-1 text-sm text-neutral-600">Gérez les migrations de traductions depuis S3 et appliquez-les aux différentes interfaces.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.translations.index') }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Traductions
                    </a>
                    <button wire:click="openSyncModal"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Synchroniser depuis S3
                    </button>
                </div>
            </div>

            <!-- Filters and Actions - Same style as Translation Manager -->
            <div class="mt-8 mb-4 border-t border-neutral-200 pt-6">
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <!-- Interface Filter -->
                    <div>
                        <label for="interface" class="block text-sm font-medium text-neutral-700 mb-1">Interface</label>
                        <select wire:model.live="selectedInterface"
                                id="interface"
                                class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Toutes les interfaces</option>
                            @foreach($interfaces as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-neutral-700 mb-1">Statut</label>
                        <select wire:model.live="selectedStatus"
                                id="status"
                                class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">Tous les statuts</option>
                            @foreach($statuses as $key => $status)
                                <option value="{{ $key }}">{{ $status['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-neutral-700 mb-1">Rechercher</label>
                        <div class="relative">
                            <input wire:model.live.debounce.300ms="search"
                                   type="text"
                                   id="search"
                                   placeholder="Nom de fichier ou version..."
                                   class="block w-full rounded-md border-neutral-300 pl-10 pr-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Migrations Table - Same style as Translation Manager -->
        <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden" wire:poll.5s="$refresh">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-neutral-200">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                Fichier
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                Interface
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                Version
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                Statut
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                Date de création
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                Date d'exécution
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($migrations as $migration)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-900">
                                    {{ $migration->filename }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $interfaces[$migration->interface_origin] ?? $migration->interface_origin }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    {{ $migration->version }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    @php
                                        $statusConfig = $statuses[$migration->status] ?? ['label' => $migration->status, 'color' => 'neutral'];
                                        $colorClasses = [
                                            'warning' => 'bg-amber-100 text-amber-800',
                                            'info' => 'bg-blue-100 text-blue-800',
                                            'success' => 'bg-green-100 text-green-800',
                                            'error' => 'bg-red-100 text-red-800',
                                            'neutral' => 'bg-neutral-100 text-neutral-800',
                                        ];
                                        $statusClass = $colorClasses[$statusConfig['color']] ?? $colorClasses['neutral'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ $statusConfig['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    {{ $migration->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-neutral-500">
                                    {{ $migration->executed_at ? $migration->executed_at->format('d/m/Y H:i') : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button wire:click="openPreview({{ $migration->id }})"
                                                type="button"
                                                class="text-blue-600 hover:text-blue-900">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </button>

                                        @if($migration->status === 'pending')
                                            <button wire:click="openApplyModal({{ $migration->id }})"
                                                    type="button"
                                                    class="text-green-600 hover:text-green-900">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            </button>
                                        @endif

                                        @if($migration->status === 'completed')
                                            <button wire:click="openRollbackModal({{ $migration->id }})"
                                                    type="button"
                                                    class="text-amber-600 hover:text-amber-900">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-neutral-500">
                                    <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    <p class="mt-2 text-sm">Aucune migration trouvée</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($migrations->hasPages())
                <div class="bg-white px-4 py-3 border-t border-neutral-200 sm:px-6">
                    {{ $migrations->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Sync Modal - Same style as Translation Manager Export Modal -->
    <div x-data="{ open: @entangle('showSyncModal') }"
         x-show="open"
         x-cloak
         class="fixed z-50 inset-0 overflow-y-auto"
         aria-labelledby="modal-title"
         role="dialog"
         aria-modal="true"
         style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
                 aria-hidden="true"></div>

            <!-- This element is to trick the browser into centering the modal contents. -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                <!-- Modal Header -->
                <div class="bg-neutral-50 px-4 py-5 border-b border-neutral-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-neutral-900">
                                Synchroniser depuis S3
                            </h3>
                            <p class="mt-1 text-sm text-neutral-500">
                                Sélectionnez les interfaces à synchroniser depuis S3
                            </p>
                        </div>
                        <button wire:click="$set('showSyncModal', false)"
                                type="button"
                                class="ml-3 bg-white rounded-md text-neutral-400 hover:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="sr-only">Fermer</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Modal Content -->
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="space-y-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">
                                        Information importante
                                    </h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>Les fichiers de migration seront synchronisés depuis S3. Seuls les nouveaux fichiers seront ajoutés.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-neutral-700 mb-3">
                                Interfaces à synchroniser
                            </label>
                            <div class="space-y-3">
                                <!-- Mobile Interface -->
                                <label class="flex items-start p-3 border border-neutral-200 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox"
                                               wire:model="selectedInterfacesForSync.mobile"
                                               class="h-4 w-4 text-blue-600 border-neutral-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-neutral-900">
                                            Mobile
                                        </div>
                                        <div class="text-xs text-neutral-500 mt-1">
                                            Application mobile (iOS/Android)
                                        </div>
                                    </div>
                                </label>

                                <!-- Web Financer Interface -->
                                <label class="flex items-start p-3 border border-neutral-200 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox"
                                               wire:model="selectedInterfacesForSync.web_financer"
                                               class="h-4 w-4 text-blue-600 border-neutral-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-neutral-900">
                                            Web Financer
                                        </div>
                                        <div class="text-xs text-neutral-500 mt-1">
                                            Interface web pour les financeurs
                                        </div>
                                    </div>
                                </label>

                                <!-- Web Beneficiary Interface -->
                                <label class="flex items-start p-3 border border-neutral-200 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox"
                                               wire:model="selectedInterfacesForSync.web_beneficiary"
                                               class="h-4 w-4 text-blue-600 border-neutral-300 rounded focus:ring-blue-500">
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-neutral-900">
                                            Web Beneficiary
                                        </div>
                                        <div class="text-xs text-neutral-500 mt-1">
                                            Interface web pour les bénéficiaires
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Select/Deselect All -->
                        <div class="flex items-center justify-between pt-2 border-t border-neutral-200">
                            <button type="button"
                                    wire:click="$set('selectedInterfacesForSync', {'mobile': true, 'web_financer': true, 'web_beneficiary': true})"
                                    class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                Tout sélectionner
                            </button>
                            <button type="button"
                                    wire:click="$set('selectedInterfacesForSync', {'mobile': false, 'web_financer': false, 'web_beneficiary': false})"
                                    class="text-sm text-neutral-600 hover:text-neutral-800 font-medium">
                                Tout désélectionner
                            </button>
                        </div>

                        <!-- Auto Process Option -->
                        <div class="pt-2 border-t border-neutral-200">
                            <label class="flex items-center">
                                <input wire:model="autoProcess"
                                       type="checkbox"
                                       class="rounded border-neutral-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-neutral-700">
                                    Traiter automatiquement les nouvelles migrations après synchronisation
                                </span>
                            </label>
                            <p class="mt-1 ml-6 text-xs text-neutral-500">
                                Les migrations seront automatiquement appliquées après leur synchronisation
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="syncFromS3"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="syncFromS3">
                            Synchroniser
                        </span>
                        <span wire:loading wire:target="syncFromS3" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Synchronisation en cours...
                        </span>
                    </button>
                    <button wire:click="$set('showSyncModal', false)"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Apply Modal -->
    <div x-data="{ open: @entangle('showApplyModal') }"
         x-show="open"
         x-cloak
         class="fixed z-10 inset-0 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
                 @click="open = false"></div>

            <!-- Modal panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900">
                                Appliquer la migration
                            </h3>
                            <div class="mt-2">
                                @if($selectedMigration)
                                    <p class="text-sm text-neutral-500">
                                        Vous êtes sur le point d'appliquer la migration <strong>{{ $selectedMigration->filename }}</strong> pour l'interface <strong>{{ $interfaces[$selectedMigration->interface_origin] ?? $selectedMigration->interface_origin }}</strong>.
                                    </p>
                                @endif
                            </div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="flex items-center">
                                        <input wire:model="createBackup"
                                               type="checkbox"
                                               class="rounded border-neutral-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-neutral-700">
                                            Créer une sauvegarde avant l'application
                                        </span>
                                    </label>
                                </div>
                                <div>
                                    <label class="flex items-center">
                                        <input wire:model="validateChecksum"
                                               type="checkbox"
                                               class="rounded border-neutral-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-neutral-700">
                                            Valider le checksum du fichier
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="applyMigration"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <span wire:loading.remove wire:target="applyMigration">Appliquer</span>
                        <span wire:loading wire:target="applyMigration" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Application...
                        </span>
                    </button>
                    <button wire:click="closeModals"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rollback Modal -->
    <div x-data="{ open: @entangle('showRollbackModal') }"
         x-show="open"
         x-cloak
         class="fixed z-10 inset-0 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
                 @click="open = false"></div>

            <!-- Modal panel -->
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-neutral-900">
                                Annuler la migration
                            </h3>
                            <div class="mt-2">
                                @if($selectedMigration)
                                    <p class="text-sm text-neutral-500">
                                        Êtes-vous sûr de vouloir annuler la migration <strong>{{ $selectedMigration->filename }}</strong> ?
                                    </p>
                                    <p class="mt-2 text-sm text-amber-600">
                                        Cette action marquera la migration comme annulée. Les traductions devront être restaurées manuellement si nécessaire.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="rollbackMigration"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <span wire:loading.remove wire:target="rollbackMigration">Annuler la migration</span>
                        <span wire:loading wire:target="rollbackMigration" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Annulation...
                        </span>
                    </button>
                    <button wire:click="closeModals"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Drawer - Exact copy of Translation Manager Edit Drawer style -->
    @if($showPreviewDrawer)
        <div class="fixed inset-0 overflow-hidden z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Background overlay -->
                <div class="absolute inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
                     wire:click="closeModals"
                     aria-hidden="true"></div>

                <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex">
                    <div class="w-screen max-w-xl"
                         x-show="true"
                         x-transition:enter="transform transition ease-in-out duration-500 sm:duration-700"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-500 sm:duration-700"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full">
                        <div class="h-full bg-white shadow-xl flex flex-col">
                            <!-- Header -->
                            <div class="bg-neutral-50 px-4 py-6 sm:px-6">
                                <div class="flex items-start justify-between">
                                    <h2 id="slide-over-title" class="text-lg font-medium text-gray-900">
                                        Aperçu de la migration
                                    </h2>
                                    <div class="ml-3 h-7 flex items-center">
                                        <button wire:click="closeModals"
                                                type="button"
                                                class="bg-white rounded-md text-gray-400 hover:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                            <span class="sr-only">Fermer</span>
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Body -->
                            <div class="flex-1 overflow-y-auto px-4 py-6 sm:px-6">
                                @if($selectedMigration)
                                    <div class="space-y-6">
                                        <!-- Migration Info -->
                                        <div>
                                            <h3 class="text-lg font-medium text-neutral-900">Informations de la migration</h3>
                                            <dl class="mt-4 space-y-4">
                                                <div class="bg-neutral-50 rounded-lg px-4 py-3">
                                                    <dt class="text-sm font-medium text-neutral-500">Fichier</dt>
                                                    <dd class="mt-1 text-sm text-neutral-900">{{ $selectedMigration->filename }}</dd>
                                                </div>
                                                <div class="bg-neutral-50 rounded-lg px-4 py-3">
                                                    <dt class="text-sm font-medium text-neutral-500">Interface</dt>
                                                    <dd class="mt-1 text-sm text-neutral-900">{{ $interfaces[$selectedMigration->interface_origin] ?? $selectedMigration->interface_origin }}</dd>
                                                </div>
                                                <div class="bg-neutral-50 rounded-lg px-4 py-3">
                                                    <dt class="text-sm font-medium text-neutral-500">Version</dt>
                                                    <dd class="mt-1 text-sm text-neutral-900">{{ $selectedMigration->version }}</dd>
                                                </div>
                                                <div class="bg-neutral-50 rounded-lg px-4 py-3">
                                                    <dt class="text-sm font-medium text-neutral-500">Statut</dt>
                                                    <dd class="mt-1">
                                                        @php
                                                            $statusConfig = $statuses[$selectedMigration->status] ?? ['label' => $selectedMigration->status, 'color' => 'neutral'];
                                                            $colorClasses = [
                                                                'warning' => 'bg-amber-100 text-amber-800',
                                                                'info' => 'bg-blue-100 text-blue-800',
                                                                'success' => 'bg-green-100 text-green-800',
                                                                'error' => 'bg-red-100 text-red-800',
                                                                'neutral' => 'bg-neutral-100 text-neutral-800',
                                                            ];
                                                            $statusClass = $colorClasses[$statusConfig['color']] ?? $colorClasses['neutral'];
                                                        @endphp
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                                            {{ $statusConfig['label'] }}
                                                        </span>
                                                    </dd>
                                                </div>
                                                @if($selectedMigration->checksum)
                                                    <div class="bg-neutral-50 rounded-lg px-4 py-3">
                                                        <dt class="text-sm font-medium text-neutral-500">Checksum</dt>
                                                        <dd class="mt-1 text-sm text-neutral-900 font-mono text-xs break-all">{{ $selectedMigration->checksum }}</dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>

                                        <!-- Preview Data -->
                                        @if(!empty($previewData))
                                            <div>
                                                <h3 class="text-lg font-medium text-neutral-900 mb-4">Aperçu des changements</h3>

                                                @if(isset($previewData['summary']))
                                                    <div class="grid grid-cols-2 gap-4 mb-6">
                                                        <div class="bg-blue-50 rounded-lg p-4">
                                                            <p class="text-xs font-medium text-blue-800">Total des clés</p>
                                                            <p class="text-2xl font-bold text-blue-900">{{ $previewData['summary']['total_keys'] ?? 0 }}</p>
                                                        </div>
                                                        <div class="bg-green-50 rounded-lg p-4">
                                                            <p class="text-xs font-medium text-green-800">Nouvelles clés</p>
                                                            <p class="text-2xl font-bold text-green-900">{{ $previewData['summary']['new_keys'] ?? 0 }}</p>
                                                        </div>
                                                        <div class="bg-amber-50 rounded-lg p-4">
                                                            <p class="text-xs font-medium text-amber-800">Clés modifiées</p>
                                                            <p class="text-2xl font-bold text-amber-900">{{ $previewData['summary']['updated_keys'] ?? 0 }}</p>
                                                        </div>
                                                        <div class="bg-red-50 rounded-lg p-4">
                                                            <p class="text-xs font-medium text-red-800">Clés supprimées</p>
                                                            <p class="text-2xl font-bold text-red-900">{{ $previewData['summary']['deleted_keys'] ?? 0 }}</p>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if(isset($previewData['changes']) && count($previewData['changes']) > 0)
                                                    <div>
                                                        <h4 class="text-sm font-medium text-neutral-700 mb-3">Exemples de changements</h4>
                                                        <div class="space-y-3 max-h-96 overflow-y-auto">
                                                            @foreach(array_slice($previewData['changes'], 0, 10) as $change)
                                                                <div class="bg-neutral-50 rounded-lg p-3">
                                                                    <p class="text-xs font-mono text-neutral-600">{{ $change['key'] ?? 'N/A' }}</p>
                                                                    <p class="text-sm text-neutral-900 mt-1">{{ $change['value'] ?? 'N/A' }}</p>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <div class="bg-amber-50 rounded-lg p-4">
                                                <p class="text-sm text-amber-800">
                                                    Aucun aperçu disponible. Cliquez sur le bouton pour charger les données.
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Toast Notifications -->
    <div x-data="{ show: false, type: '', message: '' }"
         x-on:migration-synced.window="show = true; type = 'success'; message = $event.detail[0].message; setTimeout(() => { show = false }, 5000)"
         x-on:migration-applied.window="show = true; type = 'success'; message = $event.detail[0].message; setTimeout(() => { show = false }, 5000)"
         x-on:migration-rolled-back.window="show = true; type = 'warning'; message = $event.detail[0].message; setTimeout(() => { show = false }, 5000)"
         x-on:migration-error.window="show = true; type = 'error'; message = $event.detail[0].message; setTimeout(() => { show = false }, 5000)"
         x-show="show"
         x-transition:enter="transform ease-out duration-300 transition"
         x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
         x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak
         class="fixed bottom-0 right-0 mb-4 mr-4 max-w-sm">
        <div :class="{
            'bg-green-50 border-green-200': type === 'success',
            'bg-amber-50 border-amber-200': type === 'warning',
            'bg-red-50 border-red-200': type === 'error'
        }"
             class="rounded-lg border p-4 shadow-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg x-show="type === 'success'" class="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <svg x-show="type === 'warning'" class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <svg x-show="type === 'error'" class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p :class="{
                        'text-green-800': type === 'success',
                        'text-amber-800': type === 'warning',
                        'text-red-800': type === 'error'
                    }"
                       class="text-sm font-medium" x-text="message"></p>
                </div>
                <div class="ml-auto pl-3">
                    <div class="-mx-1.5 -my-1.5">
                        <button @click="show = false"
                                type="button"
                                :class="{
                                    'text-green-500 hover:bg-green-100': type === 'success',
                                    'text-amber-500 hover:bg-amber-100': type === 'warning',
                                    'text-red-500 hover:bg-red-100': type === 'error'
                                }"
                                class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-offset-2">
                            <span class="sr-only">Fermer</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
