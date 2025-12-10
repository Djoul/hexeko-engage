@extends('admin-panel.layouts.app')

@section('title', 'Tableau de bord des traductions')
@section('page-content')
<div class="space-y-6">
    {{-- Header and Statistics --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
        @php($statsUpdated = $lastUpdated['stats'] ?? ['iso' => null, 'label' => null])
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-neutral-900">Gestion des traductions</h1>
                <p class="mt-1 text-sm text-neutral-600">Gérez les traductions pour toutes les interfaces et toutes les langues</p>
            </div>
            <div class="flex items-center gap-3 sm:self-start">
                <span class="text-xs text-neutral-500" data-testid="stats-last-updated" data-value="{{ $statsUpdated['iso'] }}">
                    Dernière mise à jour : {{ $statsUpdated['label'] ?? 'N/A' }}
                </span>
                <form method="POST" action="{{ route('admin.manager.translations.refresh') }}">
                    @csrf
                    <input type="hidden" name="section" value="stats">
                    <button type="submit" class="inline-flex items-center justify-center rounded-md border border-neutral-200 p-2 text-neutral-500 transition-colors hover:bg-neutral-100" data-testid="stats-refresh-button">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span class="sr-only">Actualiser</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Statistics Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Total Keys --}}
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-2 bg-blue-100 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-xs font-medium text-neutral-600">Clés de traduction</p>
                        <p class="text-xl font-semibold text-neutral-900" data-testid="stats-card-count" data-value="{{ $stats['total_keys'] }}">{{ number_format($stats['total_keys']) }}</p>
                    </div>
                </div>
            </div>

            {{-- Completion Rate --}}
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-2 bg-green-100 rounded-lg">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-xs font-medium text-neutral-600">Taux d'achèvement</p>
                        <p class="text-xl font-semibold text-neutral-900">{{ $stats['completion_percentage'] }}%</p>
                    </div>
                </div>
            </div>

            {{-- Pending Migrations --}}
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-2 bg-yellow-100 rounded-lg">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-xs font-medium text-neutral-600">Migrations en attente</p>
                        <p class="text-xl font-semibold text-neutral-900">{{ $stats['pending_migrations'] }}</p>
                    </div>
                </div>
            </div>

            {{-- Recent Changes --}}
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-2 bg-purple-100 rounded-lg">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-xs font-medium text-neutral-600">Modifications récentes (24&nbsp;h)</p>
                        <p class="text-xl font-semibold text-neutral-900">{{ $stats['recent_changes'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Available Languages Settings --}}
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-neutral-900 mb-4">Langues disponibles</h2>
        <p class="text-sm text-neutral-600 mb-4">Sélectionnez les langues qui doivent être disponibles dans l'application</p>

        @if(session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <strong class="font-bold">Erreur!</strong>
                <ul class="mt-2 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.manager.translations.settings.update-locales') }}" x-data="{ selectedLocales: @js($localizationSettings->available_locales), open: false }">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-2">
                        Langues disponibles <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <!-- Dropdown button -->
                        <button @click="open = !open"
                                @click.away="open = false"
                                type="button"
                                class="w-full bg-white border border-neutral-300 rounded-md shadow-sm px-4 py-2 text-left cursor-pointer focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-wrap gap-1">
                                    <template x-if="selectedLocales.length > 0">
                                        <template x-for="locale in selectedLocales" :key="locale">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800" x-text="locale"></span>
                                        </template>
                                    </template>
                                    <template x-if="selectedLocales.length === 0">
                                        <span class="text-neutral-500">Sélectionnez des langues</span>
                                    </template>
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
                                @foreach($allLanguages as $language)
                                    <label class="flex items-center px-2 py-2 cursor-pointer hover:bg-gray-100 rounded">
                                        <input type="checkbox"
                                               name="available_locales[]"
                                               value="{{ $language['value'] }}"
                                               x-model="selectedLocales"
                                               class="h-4 w-4 rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-3 text-sm">{{ $language['label'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-neutral-500">
                        Actuellement sélectionné: <span class="font-semibold" x-text="selectedLocales.length"></span> langue(s)
                    </p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                    >
                        Enregistrer les langues
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Coverage Matrix --}}
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm overflow-hidden">
        @php($coverageUpdated = $lastUpdated['coverage'] ?? ['iso' => null, 'label' => null])
        <div class="flex flex-col gap-3 p-6 pb-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-lg font-semibold text-neutral-900">Couverture des traductions par interface</h2>
            <div class="flex items-center gap-3">
                <span class="text-xs text-neutral-500" data-testid="coverage-last-updated" data-value="{{ $coverageUpdated['iso'] }}">
                    Dernière mise à jour : {{ $coverageUpdated['label'] ?? 'N/A' }}
                </span>
                <form method="POST" action="{{ route('admin.manager.translations.refresh') }}">
                    @csrf
                    <input type="hidden" name="section" value="coverage">
                    <button type="submit" class="inline-flex items-center justify-center rounded-md border border-neutral-200 p-2 text-neutral-500 transition-colors hover:bg-neutral-100" data-testid="coverage-refresh-button">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span class="sr-only">Actualiser</span>
                    </button>
                </form>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="sticky left-0 z-10 bg-gray-50 px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                            Interface
                        </th>
                        @foreach(App\Enums\Languages::asSelectObject() as $language)
                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                            {{ $language['label'] }}
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($coverageByInterface as $interface => $languages)
                    <tr>
                        <td class="sticky left-0 z-10 bg-white px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 border-r border-gray-200">
                            {{ str_replace('_', ' ', ucfirst($interface)) }}
                        </td>
                        @foreach(App\Enums\Languages::asSelectObject() as $language)
                        <td class="px-3 py-3 whitespace-nowrap text-center">
                            @if(isset($languages[$language['value']]))
                            <div class="flex flex-col items-center min-w-[80px]">
                                <span class="text-xs font-medium text-gray-900">
                                    {{ $languages[$language['value']]['percentage'] }}%
                                </span>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                    <div class="bg-{{ $languages[$language['value']]['percentage'] >= 80 ? 'green' : ($languages[$language['value']]['percentage'] >= 50 ? 'yellow' : 'red') }}-600 h-1.5 rounded-full"
                                         style="width: {{ $languages[$language['value']]['percentage'] }}%"></div>
                                </div>
                                <span class="text-xs text-gray-500 mt-0.5">
                                    {{ $languages[$language['value']]['translated'] }}/{{ $languages[$language['value']]['total'] }}
                                </span>
                            </div>
                            @else
                            <span class="text-xs text-gray-400">N/A</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent Activity --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            @php($recentActivityUpdated = $lastUpdated['recent-activity'] ?? ['iso' => null, 'label' => null])
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-neutral-900">Activité récente</h2>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-neutral-500" data-testid="recent-activity-last-updated" data-value="{{ $recentActivityUpdated['iso'] }}">
                        Dernière mise à jour : {{ $recentActivityUpdated['label'] ?? 'N/A' }}
                    </span>
                    <form method="POST" action="{{ route('admin.manager.translations.refresh') }}">
                        @csrf
                        <input type="hidden" name="section" value="recent-activity">
                    <button type="submit" class="inline-flex items-center justify-center rounded-md border border-neutral-200 p-2 text-neutral-500 transition-colors hover:bg-neutral-100" data-testid="recent-activity-refresh-button">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span class="sr-only">Actualiser</span>
                    </button>
                    </form>
                </div>
            </div>
            <div>
                <div class="space-y-4">
                    @forelse($recentActivity as $activity)
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-2 h-2 bg-blue-600 rounded-full mt-2"></div>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-900">
                                <span class="font-medium">{{ $activity['key'] }}</span>
                                <span class="text-gray-600"> mise à jour pour </span>
                                <span class="font-medium">{{ $activity['language'] }}</span>
                                <span class="text-gray-600"> dans </span>
                                <span class="font-medium">{{ $activity['interface'] }}</span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                par {{ $activity['updated_by'] }} • {{ $activity['updated_at'] }}
                            </p>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500">Aucune activité récente</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Missing Translations --}}
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm">
            @php($missingUpdated = $lastUpdated['missing'] ?? ['iso' => null, 'label' => null])
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-semibold text-neutral-900">Traductions manquantes</h2>
                    <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">
                        {{ count($missingTranslations) }}
                    </span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-neutral-500" data-testid="missing-last-updated" data-value="{{ $missingUpdated['iso'] }}">
                        Dernière mise à jour : {{ $missingUpdated['label'] ?? 'N/A' }}
                    </span>
                    <form method="POST" action="{{ route('admin.manager.translations.refresh') }}">
                        @csrf
                        <input type="hidden" name="section" value="missing">
                        <button type="submit" class="inline-flex items-center justify-center rounded-md border border-neutral-200 p-2 text-neutral-500 transition-colors hover:bg-neutral-100" data-testid="missing-refresh-button">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="sr-only">Actualiser</span>
                        </button>
                    </form>
                </div>
            </div>
            <div>
                <div class="space-y-3">
                    @forelse(array_slice($missingTranslations, 0, 10) as $missing)
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $missing['key'] }}</p>
                            <p class="text-xs text-gray-600 mt-1">
                                {{ $missing['interface'] }} • {{ $missing['language'] }}
                            </p>
                        </div>
                        <button class="text-xs px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
                            Ajouter une traduction
                        </button>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500">Toutes les traductions sont complètes&nbsp;!</p>
                    @endforelse

                    @if(count($missingTranslations) > 10)
                    <div class="pt-3 border-t border-gray-200">
                        <a href="#" class="text-sm text-blue-600 hover:text-blue-700">
                            Voir les {{ count($missingTranslations) }} traductions manquantes →
                        </a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
