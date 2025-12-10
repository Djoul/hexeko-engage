<div>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="space-y-6">
        <!-- Header -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">Gestionnaire de traductions</h1>
                    <p class="mt-1 text-sm text-neutral-600">Gérez les traductions pour les différentes interfaces et langues.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.translation-migrations.index') }}"
                       class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-neutral-500 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                        </svg>
                        Migrations
                    </a>
                </div>
            </div>

            @if($hasPendingExport)
                @php
                    $changeMessages = [
                        'import' => "Un import récent a modifié les traductions.",
                        'create' => "Une nouvelle clé ou de nouvelles valeurs ont été ajoutées manuellement.",
                        'update' => "Des traductions ont été modifiées manuellement.",
                        'delete' => "Certaines clés ou valeurs ont été supprimées.",
                        'pending' => "Des modifications locales ont été détectées depuis le dernier export.",
                    ];
                    $alertMessage = $changeMessages[$pendingChangeSource] ?? "Des modifications locales n'ont pas encore été exportées.";
                @endphp
                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/80 text-amber-500">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold">{{ $alertMessage }}</p>
                                <p class="mt-1 text-xs text-amber-800">Exportez les traductions vers S3 pour synchroniser les changements et assurer la traçabilité.</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-start gap-2 md:justify-end">
                            <button wire:click="openExportToS3Modal"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    wire:target="openExportToS3Modal"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-transparent bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4" />
                                </svg>
                                <span wire:loading.remove wire:target="exportToS3">Exporter vers S3</span>
                                <span wire:loading wire:target="exportToS3">Export...</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filters and Actions -->
            <div class="mt-8 mb-4 border-t border-neutral-200 pt-6">
                <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <!-- Interface Selector -->
                    <div>
                        <label for="interface" class="block text-sm font-medium text-neutral-700 mb-1">Interface</label>
                        <select wire:model.live="selectedInterface"
                                id="interface"
                                class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @foreach($interfaces as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Locale Selector -->
                    <div wire:key="locale-selector">
                        <label class="block text-sm font-medium text-neutral-700 mb-1">Langues affichées</label>
                        <div x-data="{ open: false }" class="relative">
                            <!-- Dropdown button -->
                            <button @click="open = !open"
                                    @click.away="open = false"
                                    type="button"
                                    class="w-full bg-white border border-neutral-300 rounded-md shadow-sm px-4 py-2 text-left cursor-pointer focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                <div class="flex items-center justify-between">
                                    <div class="flex flex-wrap gap-1">
                                        @if(count($selectedLocales) > 0)
                                            @foreach($selectedLocales as $locale)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                                    {{ $availableLocales[$locale]['flag'] ?? '' }} {{ $locale }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-neutral-500">Sélectionnez des langues</span>
                                        @endif
                                    </div>
                                    <svg class="h-5 w-5 text-gray-400 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </button>

                            <!-- Dropdown panel -->
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-100"
                                 x-transition:enter-start="transform opacity-0 scale-95"
                                 x-transition:enter-end="transform opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-75"
                                 x-transition:leave-start="transform opacity-100 scale-100"
                                 x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute z-10 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm">
                                <div class="p-2 space-y-1">
                                    @foreach($availableLocales as $locale => $info)
                                        <label class="flex items-center px-2 py-2 cursor-pointer hover:bg-gray-100 rounded">
                                            <input type="checkbox"
                                                   wire:model.live="selectedLocales"
                                                   value="{{ $locale }}"
                                                   @if(in_array($locale, $selectedLocales)) checked @endif
                                                   class="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                                            <span class="ml-3 flex items-center text-sm">
                                                <span class="mr-2">{{ $info['flag'] }}</span>
                                                <span>{{ $info['name'] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search -->
                    <div>
                        <label for="search" class="block text-sm font-medium text-neutral-700 mb-1">Rechercher</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="search"
                               id="search"
                               placeholder="Rechercher..."
                               class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-neutral-600 md:text-sm">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-neutral-700 shadow-sm">
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                {{ $interfaces[$selectedInterface] ?? $selectedInterface }}
                            </span>
                            <span class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-neutral-600 shadow-sm">
                                <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m6 4H3m6 0v2m6 4H3m6 0v2m6 4H3" />
                                </svg>
                                {{ count($selectedLocales) }} langue(s) sélectionnée(s)
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">

{{--                            <button wire:click="syncFromS3"--}}
{{--                                    wire:loading.attr="disabled"--}}
{{--                                    wire:loading.class="opacity-50 cursor-not-allowed"--}}
{{--                                    type="button"--}}
{{--                                    class="inline-flex items-center gap-2 rounded-lg border border-transparent bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-0">--}}
{{--                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />--}}
{{--                                </svg>--}}
{{--                                <span wire:loading.remove wire:target="syncFromS3">Synchroniser depuis S3</span>--}}
{{--                                <span wire:loading wire:target="syncFromS3">Sync...</span>--}}
{{--                            </button>--}}
                            @if($this->canEditTranslations())
                                <button wire:click="showImport"
                                        type="button"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="opacity-50 cursor-not-allowed"
                                        class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition-colors hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-0">
                                    <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span wire:loading.remove wire:target="showImport">Importer</span>
                                    <span wire:loading wire:target="showImport">Chargement...</span>
                                </button>
                            @endif
                            <button wire:click="showExport"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-sm transition-colors hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-0">
                                <svg class="h-4 w-4 text-neutral-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                                </svg>
                                Exporter
                            </button>
                            @if($this->canEditTranslations())
                                <button wire:click="showAddKey"
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-lg border border-transparent bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-0">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Ajouter une clé
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Translations Table -->
            <div class="bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden" wire:key="translations-table-{{ implode('-', $selectedLocales) }}">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-neutral-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Clé</th>
                            @foreach($selectedLocales as $locale)
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">
                                    {{ $availableLocales[$locale]['flag'] ?? '' }} {{ $availableLocales[$locale]['name'] ?? $locale }}
                                </th>
                            @endforeach
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Langues principales</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Langues secondaires</th>
                            <th scope="col" class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($translations as $translation)
                            <tr class="hover:bg-neutral-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $translation->group ? $translation->group . '.' . $translation->key : $translation->key }}
                                    </div>
                                </td>
                                @foreach($selectedLocales as $locale)
                                    <td class="px-6 py-4">
                                        @php
                                            $value = $translation->values->where('locale', $locale)->first();
                                        @endphp
                                        <span class="text-sm text-gray-900">{!! $value?->value ?? '-' !!}</span>
                                    </td>
                                @endforeach
                                @php
                                    $primaryLocales = [];
                                    $secondaryLocales = [];

                                    foreach ($availableLocales as $locale => $info) {
                                        if (\App\Enums\Languages::getLanguageType($locale) === 'main') {
                                            $primaryLocales[] = $locale;
                                        } else {
                                            $secondaryLocales[] = $locale;
                                        }
                                    }

                                    $primaryValues = $translation->values->whereIn('locale', $primaryLocales);
                                    $secondaryValues = $translation->values->whereIn('locale', $secondaryLocales);

                                    $primaryCount = $primaryValues->count();
                                    $primaryTotal = count($primaryLocales);
                                    $primaryPercentage = $primaryTotal > 0 ? round(($primaryCount / $primaryTotal) * 100) : 0;

                                    $secondaryCount = $secondaryValues->count();
                                    $secondaryTotal = count($secondaryLocales);
                                    $secondaryPercentage = $secondaryTotal > 0 ? round(($secondaryCount / $secondaryTotal) * 100) : 0;
                                @endphp

                                <!-- Langues principales -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-1 mr-2">
                                            <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-full @if($primaryPercentage >= 100) bg-green-500 @elseif($primaryPercentage >= 50) bg-yellow-500 @else bg-gray-400 @endif"
                                                     style="width: {{ $primaryPercentage }}%"></div>
                                            </div>
                                        </div>
                                        <span class="text-xs text-neutral-500">{{ $primaryCount }}/{{ $primaryTotal }}</span>
                                    </div>
                                </td>

                                <!-- Langues secondaires -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-1 mr-2">
                                            <div class="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-full @if($secondaryPercentage >= 100) bg-green-500 @elseif($secondaryPercentage >= 50) bg-yellow-500 @else bg-gray-400 @endif"
                                                     style="width: {{ $secondaryPercentage }}%"></div>
                                            </div>
                                        </div>
                                        <span class="text-xs text-neutral-500">{{ $secondaryCount }}/{{ $secondaryTotal }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="relative inline-block text-left" x-data="{ open: false }">
                                        <button @click="open = !open"
                                                @click.away="open = false"
                                                type="button"
                                                class="inline-flex justify-center w-8 h-8 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                            </svg>
                                        </button>

                                        <div x-show="open"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="transform opacity-100 scale-100"
                                             x-transition:leave-end="transform opacity-0 scale-95"
                                             class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50">
                                            <div class="py-1">
                                                @if($this->canEditTranslations())
                                                    <button type="button"
                                                            wire:click="openEditDrawer({{ $translation->id }})"
                                                            @click="open = false"
                                                            class="w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-gray-100 hover:text-gray-900 flex items-center">
                                                        <svg class="mr-3 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                        Modifier
                                                    </button>
                                                @endif
                                                <button wire:click="deleteKey({{ $translation->id }})"
                                                        wire:confirm="Êtes-vous sûr de vouloir supprimer cette clé et toutes ses traductions ?"
                                                        @click="open = false"
                                                        class="w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-100 hover:text-red-900 flex items-center">
                                                    <svg class="mr-3 h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                    Supprimer
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + count($selectedLocales) }}" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucune traduction</h3>
                                    <p class="mt-1 text-sm text-neutral-500">Commencez par ajouter une nouvelle clé de traduction.</p>
                                    <div class="mt-6">
                                        <button wire:click="showAddKey"
                                                type="button"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                            Nouvelle clé
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($translations->hasPages())
                <div class="mt-4">
                    {{ $translations->links() }}
                </div>
            @endif
        </div>

    <!-- Add Key Drawer -->
    @if($showAddKeyModal)
        <div class="fixed inset-0 overflow-hidden z-50" aria-labelledby="add-key-title" role="dialog" aria-modal="true">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Background overlay -->
                <div class="absolute inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
                     wire:click="$set('showAddKeyModal', false)"
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
                                    <h2 id="add-key-title" class="text-lg font-medium text-gray-900">
                                        Ajouter une nouvelle clé
                                    </h2>
                                    <div class="ml-3 h-7 flex items-center">
                                        <button wire:click="$set('showAddKeyModal', false)"
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

                            <!-- Content -->
                            <div class="flex-1 overflow-y-auto">
                                <div class="px-4 sm:px-6 py-6">
                                    <!-- Key -->
                                    <div class="mb-6">
                                        <label for="key" class="block text-sm font-medium text-neutral-700 mb-2">
                                            Clé de traduction
                                        </label>
                                        <input type="text"
                                               wire:model="newKey"
                                               id="key"
                                               placeholder="ex: validation.required ou common.buttons.save"
                                               class="block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                        <p class="mt-1 text-xs text-neutral-500">Format obligatoire: groupe.clé (le premier segment est le groupe, le reste est la clé)</p>
                                        @error('newKey')
                                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Primary Languages -->
                                    <div class="mb-6">
                                        <h3 class="text-sm font-medium text-gray-900 mb-4">Langues principales</h3>
                                        <div class="space-y-4">
                                            @php
                                                $primaryLanguages = [];
                                                foreach (\App\Enums\Languages::getValues() as $lang) {
                                                    if (\App\Enums\Languages::getLanguageType($lang) === 'main') {
                                                        $primaryLanguages[] = $lang;
                                                    }
                                                }
                                            @endphp

                                            @foreach($primaryLanguages as $locale)
                                                @php
                                                    $flag = \App\Enums\Languages::flag($locale);
                                                    $name = \App\Enums\Languages::nativeName($locale);
                                                @endphp
                                                <div>
                                                    <label for="new_{{ $locale }}" class="block text-sm font-medium text-neutral-700 mb-1">
                                                        {{ $flag }} {{ $name }}
                                                    </label>
                                                    <textarea wire:model="newValues.{{ $locale }}"
                                                              id="new_{{ $locale }}"
                                                              rows="2"
                                                              class="block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"></textarea>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Secondary Languages -->
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-sm font-medium text-gray-900">Langues secondaires</h3>
                                            @if(!$showAllSecondaryLanguagesForNew)
                                                <button wire:click="$set('showAllSecondaryLanguagesForNew', true)"
                                                        type="button"
                                                        class="text-sm text-blue-600 hover:text-blue-700">
                                                    Afficher toutes les langues
                                                </button>
                                            @else
                                                <button wire:click="$set('showAllSecondaryLanguagesForNew', false)"
                                                        type="button"
                                                        class="text-sm text-neutral-600 hover:text-neutral-500">
                                                    Masquer les langues secondaires
                                                </button>
                                            @endif
                                        </div>

                                        @if($showAllSecondaryLanguagesForNew)
                                            <div class="space-y-4">
                                                @php
                                                    $secondaryLanguages = [];
                                                    foreach (\App\Enums\Languages::getValues() as $lang) {
                                                        if (\App\Enums\Languages::getLanguageType($lang) === 'secondary') {
                                                            $secondaryLanguages[] = $lang;
                                                        }
                                                    }
                                                @endphp

                                                @foreach($secondaryLanguages as $locale)
                                                    @php
                                                        $flag = \App\Enums\Languages::flag($locale);
                                                        $name = \App\Enums\Languages::nativeName($locale);
                                                    @endphp
                                                    <div>
                                                        <label for="new_{{ $locale }}" class="block text-sm font-medium text-neutral-700 mb-1">
                                                            {{ $flag }} {{ $name }}
                                                        </label>
                                                        <textarea wire:model="newValues.{{ $locale }}"
                                                                  id="new_{{ $locale }}"
                                                                  rows="2"
                                                                  class="block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"></textarea>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex-shrink-0 px-4 py-4 flex justify-end space-x-3 border-t border-gray-200">
                                <button wire:click="$set('showAddKeyModal', false)"
                                        type="button"
                                        class="bg-white py-2 px-4 border border-neutral-300 rounded-md shadow-sm text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    Annuler
                                </button>
                                <button wire:click="addKey"
                                        type="button"
                                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Ajouter
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Export Modal -->
    @if($showExportModal)
        <div class="fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">Exporter les traductions</h3>
                        <div class="mt-2">
                            <p class="text-sm text-neutral-500">
                                Téléchargez toutes les traductions de l'interface <span class="font-medium">{{ ucfirst($selectedInterface) }}</span> au format JSON.
                            </p>
                        </div>
                    </div>
                    <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button wire:click="export"
                                type="button"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Télécharger
                        </button>
                        <button wire:click="$set('showExportModal', false)"
                                type="button"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Annuler
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Export to S3 Modal -->
    <div x-data="{ open: @entangle('showExportToS3Modal') }"
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
                                Exporter vers S3
                            </h3>
                            <p class="mt-1 text-sm text-neutral-500">
                                Sélectionnez les interfaces à exporter vers S3
                            </p>
                        </div>
                        <button wire:click="$set('showExportToS3Modal', false)"
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
                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-amber-800">
                                        Information importante
                                    </h3>
                                    <div class="mt-2 text-sm text-amber-700">
                                        <p>Les traductions exportées seront disponibles pour la synchronisation dans le gestionnaire de migrations.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-neutral-700 mb-3">
                                Interfaces à exporter
                            </label>
                            <div class="space-y-3">
                                <!-- Mobile Interface -->
                                <label class="flex items-start p-3 border border-neutral-200 rounded-lg cursor-pointer hover:bg-neutral-50 transition-colors">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox"
                                               wire:model="selectedInterfacesForExport.mobile"
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
                                               wire:model="selectedInterfacesForExport.web_financer"
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
                                               wire:model="selectedInterfacesForExport.web_beneficiary"
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
                                    wire:click="$set('selectedInterfacesForExport', {'mobile': true, 'web_financer': true, 'web_beneficiary': true})"
                                    class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                Tout sélectionner
                            </button>
                            <button type="button"
                                    wire:click="$set('selectedInterfacesForExport', {'mobile': false, 'web_financer': false, 'web_beneficiary': false})"
                                    class="text-sm text-neutral-600 hover:text-neutral-800 font-medium">
                                Tout désélectionner
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="exportToS3"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            type="button"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="exportToS3">
                            Exporter
                        </span>
                        <span wire:loading wire:target="exportToS3" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Export en cours...
                        </span>
                    </button>
                    <button wire:click="$set('showExportToS3Modal', false)"
                            type="button"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annuler
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug info - Remove after testing -->
    @if(config('app.debug'))
        <div class="bg-yellow-50 p-2 text-sm">
            showImportModal: {{ $showImportModal ? 'true' : 'false' }}
        </div>
    @endif

    <!-- Import Modal -->
    <div x-data="{ open: @entangle('showImportModal') }"
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

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                    @if(!$showPreview)
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Importer des traductions</h3>
                            <p class="text-sm text-neutral-500 mb-4">
                                Importez des traductions depuis un fichier JSON.
                            </p>

                            <div class="space-y-4">
                                <!-- Interface Selection -->
                                <div>
                                    <label for="import-interface" class="block text-sm font-medium text-neutral-700">Interface cible</label>
                                    <select wire:model="importInterface"
                                            id="import-interface"
                                            class="mt-1 block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm">
                                       @foreach(\App\Enums\OrigineInterfaces::asSelectArray() as $key => $value)
                                        <option value="{{ $key }}">{{ $value }}</option>
                                       @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-neutral-500">Choisissez dans quelle interface les traductions seront importées</p>
                                </div>

                                <!-- File Type Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-neutral-700">Type de fichier</label>
                                    <div class="mt-2 space-y-2">
                                        <div class="flex items-center">
                                            <input wire:model="importType"
                                                   id="import-multilingual"
                                                   name="import-type"
                                                   type="radio"
                                                   value="multilingual"
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-neutral-300">
                                            <label for="import-multilingual" class="ml-3 block text-sm font-medium text-neutral-700">
                                                Multilingue
                                            </label>
                                        </div>
                                        <p class="ml-7 text-xs text-neutral-500">Format d'export standard avec toutes les langues</p>

                                        <div class="flex items-center">
                                            <input wire:model="importType"
                                                   id="import-single"
                                                   name="import-type"
                                                   type="radio"
                                                   value="single"
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-neutral-300">
                                            <label for="import-single" class="ml-3 block text-sm font-medium text-neutral-700">
                                                Langue unique
                                            </label>
                                        </div>
                                        <p class="ml-7 text-xs text-neutral-500">Format xx.json (ex: fr.json, en.json)</p>
                                    </div>
                                    @error('importType')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Import Options -->
                                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input wire:model="updateExistingValues" id="updateExistingValues" type="checkbox"
                                                   class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-neutral-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="updateExistingValues" class="font-medium text-amber-900">
                                                Mettre à jour les valeurs existantes
                                            </label>
                                            <p class="text-amber-700">
                                                Si activé, les valeurs de traduction existantes seront écrasées par les valeurs importées.
                                                Si désactivé, seules les nouvelles clés et nouvelles langues seront ajoutées.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Upload -->
                                <div>
                                    <label for="file-upload" class="block text-sm font-medium text-neutral-700">Fichier JSON</label>
                                    <div class="mt-1" x-data="{ uploading: false, progress: 0 }">
                                        <input id="file-upload"
                                               wire:model="importFile"
                                               name="file-upload"
                                               type="file"
                                               accept=".json"
                                               x-on:livewire-upload-start="uploading = true; progress = 0; console.log('Upload started')"
                                               x-on:livewire-upload-finish="uploading = false; console.log('Upload finished')"
                                               x-on:livewire-upload-error="uploading = false; console.log('Upload error:', $event.detail)"
                                               x-on:livewire-upload-progress="progress = $event.detail.progress; console.log('Upload progress:', progress)"
                                               class="block w-full text-sm text-neutral-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">

                                        <!-- Progress Bar -->
                                        <div x-show="uploading" class="mt-2">
                                            <div class="bg-gray-200 rounded-full h-2.5">
                                                <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" :style="`width: ${progress}%`"></div>
                                            </div>
                                            <p class="text-xs text-neutral-600 mt-1">Upload en cours: <span x-text="progress"></span>%</p>
                                        </div>
                                    </div>

                                    <!-- Upload Loading Indicator -->
                                    <div wire:loading wire:target="importFile" class="mt-2">
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="animate-spin h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Téléchargement du fichier en cours...
                                        </div>
                                    </div>

                                    <!-- File Uploaded Success Message -->
                                    @if($importFile)
                                        <div wire:loading.remove wire:target="importFile" class="mt-2">
                                            <div class="flex items-center text-sm text-green-600">
                                                <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Fichier sélectionné : {{ $importFile->getClientOriginalName() }}
                                            </div>
                                        </div>
                                    @endif

                                    @error('importFile')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror

                                    @if($importType === 'single')
                                        <p class="mt-1 text-xs text-neutral-500">
                                            <strong>Note:</strong> La langue sera détectée automatiquement à partir du nom du fichier (fr.json → Français, en.json → Anglais)
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="previewImport"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50 cursor-not-allowed"
                                    type="button"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                <span wire:loading.remove wire:target="previewImport">Prévisualiser</span>
                                <span wire:loading wire:target="previewImport" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Traitement...
                                </span>
                            </button>
                            <button wire:click="cancelImport"
                                    type="button"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Annuler
                            </button>
                        </div>
                    @else
                        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <x-translation-changes-preview
                                :previewData="$previewData"
                                title="Aperçu des changements"
                                description="Modifications qui seront appliquées lors de l'import"
                            />
                        </div>
                        <div class="bg-neutral-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button wire:click="confirmImport"
                                    wire:loading.attr="disabled"
                                    type="button"
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                <span wire:loading.remove wire:target="confirmImport">Confirmer l'import</span>
                                <span wire:loading wire:target="confirmImport" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Import en cours...
                                </span>
                            </button>
                            <button wire:click="cancelImport"
                                    type="button"
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-neutral-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Annuler
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Drawer -->
    @if($showEditDrawer)
        <div class="fixed inset-0 overflow-hidden z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Background overlay -->
                <div class="absolute inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
                     wire:click="closeEditDrawer"
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
                                        Modifier les traductions
                                    </h2>
                                    <div class="ml-3 h-7 flex items-center">
                                        <button wire:click="closeEditDrawer"
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

                            <!-- Content -->
                            <div class="flex-1 overflow-y-auto">
                                <div class="px-4 sm:px-6 py-6">
                                @if($editingKey)
                                    <!-- Translation Key -->
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-neutral-700 mb-2">
                                            Clé de traduction
                                        </label>
                                        <input type="text"
                                               value="{{ $editingKey ? ($editingKey->group ? $editingKey->group . '.' . $editingKey->key : $editingKey->key) : '' }}"
                                               disabled
                                               class="block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm bg-neutral-50 text-neutral-500 sm:text-sm">
                                    </div>

                                    <!-- Primary Languages -->
                                    <div class="mb-6">
                                        <h3 class="text-sm font-medium text-gray-900 mb-4">Langues principales</h3>
                                        <div class="space-y-4">
                                            @php
                                                $primaryLanguages = [];
                                                foreach (\App\Enums\Languages::getValues() as $lang) {
                                                    if (\App\Enums\Languages::getLanguageType($lang) === 'main') {
                                                        $primaryLanguages[] = $lang;
                                                    }
                                                }
                                            @endphp

                                            @foreach($primaryLanguages as $locale)
                                                @php
                                                    $value = $editingValues[$locale] ?? '';
                                                    $flag = \App\Enums\Languages::flag($locale);
                                                    $name = \App\Enums\Languages::nativeName($locale);
                                                @endphp
                                                <div>
                                                    <label for="edit_{{ $locale }}" class="block text-sm font-medium text-neutral-700 mb-1">
                                                        {{ $flag }} {{ $name }}
                                                    </label>
                                                    <textarea wire:model="editingValues.{{ $locale }}"
                                                              id="edit_{{ $locale }}"
                                                              rows="2"
                                                              class="block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"></textarea>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <!-- Secondary Languages (Only filled ones by default) -->
                                    <div>
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-sm font-medium text-gray-900">Langues secondaires</h3>
                                            @if(!$showAllSecondaryLanguages)
                                                <button wire:click="toggleSecondaryLanguages"
                                                        type="button"
                                                        class="text-sm text-blue-600 hover:text-blue-700">
                                                    Afficher toutes les langues
                                                </button>
                                            @else
                                                <button wire:click="toggleSecondaryLanguages"
                                                        type="button"
                                                        class="text-sm text-neutral-600 hover:text-neutral-500">
                                                    Masquer les langues vides
                                                </button>
                                            @endif
                                        </div>

                                        <div class="space-y-4">
                                            @php
                                                $secondaryLanguages = [];
                                                foreach (\App\Enums\Languages::getValues() as $lang) {
                                                    if (\App\Enums\Languages::getLanguageType($lang) === 'secondary') {
                                                        $secondaryLanguages[] = $lang;
                                                    }
                                                }
                                            @endphp

                                            @foreach($secondaryLanguages as $locale)
                                                @php
                                                    $value = $editingValues[$locale] ?? '';
                                                    $hasValue = !empty($value);
                                                    $flag = \App\Enums\Languages::flag($locale);
                                                    $name = \App\Enums\Languages::nativeName($locale);
                                                @endphp

                                                @if($showAllSecondaryLanguages || $hasValue)
                                                    <div>
                                                        <label for="edit_{{ $locale }}" class="block text-sm font-medium text-neutral-700 mb-1">
                                                            {{ $flag }} {{ $name }}
                                                            @if(!$hasValue)
                                                                <span class="text-xs text-neutral-500 ml-2">(vide)</span>
                                                            @endif
                                                        </label>
                                                        <textarea wire:model="editingValues.{{ $locale }}"
                                                                  id="edit_{{ $locale }}"
                                                                  rows="2"
                                                                  class="block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 sm:text-sm"></textarea>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="text-center py-12">
                                        <p class="text-neutral-500">Aucune clé de traduction sélectionnée</p>
                                    </div>
                                @endif
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex-shrink-0 px-4 py-4 flex justify-end space-x-3 border-t border-gray-200">
                                <button wire:click="closeEditDrawer"
                                        type="button"
                                        class="bg-white py-2 px-4 border border-neutral-300 rounded-md shadow-sm text-sm font-medium text-neutral-700 hover:bg-neutral-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                    Annuler
                                </button>
                                <button wire:click="saveTranslations"
                                        type="button"
                                        class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    Enregistrer
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Notifications -->
    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:translation-saved.window="show = true; message = 'Traduction sauvegardée'; type = 'success'; setTimeout(() => show = false, 3000)"
         x-on:translation-deleted.window="show = true; message = 'Traduction supprimée'; type = 'success'; setTimeout(() => show = false, 3000)"
         x-on:translation-added.window="show = true; message = 'Traduction ajoutée'; type = 'success'; setTimeout(() => show = false, 3000)"
         x-on:import-success.window="show = true; message = ($event.detail && $event.detail.message) ? String($event.detail.message) : 'Import réussi'; type = 'success'; setTimeout(() => show = false, 3000)"
         x-on:import-error.window="show = true; message = ($event.detail && $event.detail.message) ? String($event.detail.message) : 'Erreur lors de l\'import'; type = 'error'; setTimeout(() => show = false, 5000)"
         x-on:export-s3-success.window="show = true; message = ($event.detail && $event.detail.message) ? String($event.detail.message) : 'Export S3 réussi'; type = 'success'; setTimeout(() => show = false, 3000)"
         x-on:export-s3-error.window="show = true; message = ($event.detail && $event.detail.message) ? String($event.detail.message) : 'Erreur lors de l\'export S3'; type = 'error'; setTimeout(() => show = false, 5000)"
         x-on:sync-s3-success.window="show = true; message = ($event.detail && $event.detail.message) ? String($event.detail.message) : 'Sync S3 réussi'; type = 'success'; setTimeout(() => show = false, 3000)"
         x-on:sync-s3-error.window="show = true; message = ($event.detail && $event.detail.message) ? String($event.detail.message) : 'Erreur lors de la sync S3'; type = 'error'; setTimeout(() => show = false, 5000)"
         x-on:translation-error.window="show = true; message = ($event.detail && $event.detail.message) ? String($event.detail.message) : 'Une erreur est survenue'; type = 'error'; setTimeout(() => show = false, 5000)"
         x-show="show"
         x-transition:enter="transform ease-out duration-300 transition"
         x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
         x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-0 right-0 m-6 w-full max-w-sm">
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 pointer-events-auto overflow-hidden">
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg x-show="type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg x-show="type === 'error'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-medium text-gray-900">
                            <span x-show="message" x-text="message"></span>
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button @click="show = false" class="bg-white rounded-md inline-flex text-neutral-400 hover:text-neutral-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <span class="sr-only">Fermer</span>
                            <svg class="h-5 w-5" x-description="Heroicon name: solid/x" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
