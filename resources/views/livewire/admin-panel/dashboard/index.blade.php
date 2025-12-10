<div>
    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-neutral-900">Tableau de bord</h1>
        <p class="mt-2 text-neutral-600">Vue d’ensemble du système et supervision</p>
    </div>

    {{-- Metrics Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {{-- Amilon Contract Balance --}}
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            @if($isLoadingContract)
                <div class="flex items-center justify-center h-full py-4">
                    <svg class="animate-spin h-8 w-8 text-purple-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            @elseif($contractError)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-600">Contrat Amilon</p>
                        <p class="text-lg text-red-600 mt-1">{{ $contractError }}</p>
                        <button wire:click="fetchAmilon" class="text-sm text-purple-600 hover:text-purple-800 mt-2 underline">
                            Réessayer
                        </button>
                    </div>
                    <div class="p-3 bg-red-100 rounded-lg">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            @elseif($amilonContract)
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-neutral-600">Solde Amilon</p>
                            <p class="text-2xl font-bold text-neutral-900 mt-1">
                                {{ number_format($amilonContract['currentAmount'], 2) }} {{ $amilonContract['currencyIsoCode'] }}
                            </p>
                            @if($amilonContract['currentAmount'] != $amilonContract['previousAmount'])
                                @php
                                    $diff = $amilonContract['currentAmount'] - $amilonContract['previousAmount'];
                                    $isPositive = $diff > 0;
                                @endphp
                                <p class="text-sm {{ $isPositive ? 'text-green-600' : 'text-red-600' }} mt-1">
                                    {{ $isPositive ? '↑' : '↓' }} {{ number_format(abs($diff), 2) }} {{ $amilonContract['currencyIsoCode'] }}
                                </p>
                            @else
                                <p class="text-sm text-neutral-500 mt-1">Aucun changement</p>
                            @endif
                            <p class="text-xs text-neutral-500 mt-2">
                                {{ \Illuminate\Support\Facades\Date::parse($amilonContract['startDate'])->translatedFormat('d M') }} - {{ \Illuminate\Support\Facades\Date::parse($amilonContract['endDate'])->translatedFormat('d M Y') }}
                            </p>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-lg flex-shrink-0">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-neutral-100">
                        @if($amilonLastFetch)
                            <p class="text-xs text-neutral-400">
                                Mis à jour {{ \Illuminate\Support\Facades\Date::parse($amilonLastFetch)->diffForHumans() }}
                            </p>
                        @endif
                        <button wire:click="fetchAmilon(true)" class="inline-flex items-center justify-center rounded-md border border-neutral-200 p-2 text-neutral-500 transition-colors hover:bg-neutral-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span class="sr-only">Actualiser</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- OpenAI Balance --}}
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            @if($isLoadingOpenAi)
                <div class="flex items-center justify-center h-full py-4">
                    <svg class="animate-spin h-8 w-8 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            @elseif($openAiError)
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-600">Solde OpenAI</p>
                        <p class="text-lg text-red-600 mt-1">{{ $openAiError }}</p>
                        <button wire:click="fetchOpenAiBalance" class="text-sm text-green-600 hover:text-green-800 mt-2 underline">
                            Réessayer
                        </button>
                    </div>
                    <div class="p-3 bg-red-100 rounded-lg">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            @elseif($openAiBalance)
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-neutral-600">Budget OpenAI</p>
                            <p class="text-2xl font-bold text-neutral-900 mt-1">
                                ${{ number_format($openAiBalance['remaining'], 2) }}
                            </p>
                            @php
                                $percentUsed = $openAiBalance['budget_limit'] > 0
                                    ? ($openAiBalance['total_cost'] / $openAiBalance['budget_limit']) * 100
                                    : 0;
                                $statusColor = $percentUsed < 50 ? 'text-green-600' : ($percentUsed < 80 ? 'text-yellow-600' : 'text-red-600');
                            @endphp
                            <p class="text-sm {{ $statusColor }} mt-1">
                                Utilisé&nbsp;: ${{ number_format($openAiBalance['total_cost'], 2) }} / ${{ number_format($openAiBalance['budget_limit'], 2) }}
                            </p>
                            <p class="text-xs text-neutral-400 mt-1">
                                {{ \Illuminate\Support\Facades\Date::parse($openAiBalance['period_start'])->translatedFormat('d M') }} - {{ \Illuminate\Support\Facades\Date::parse($openAiBalance['period_end'])->translatedFormat('d M') }}
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-lg flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-neutral-100">
                        @if($openAiLastFetch)
                            <p class="text-xs text-neutral-400">
                                Mis à jour {{ \Illuminate\Support\Facades\Date::parse($openAiLastFetch)->diffForHumans() }}
                            </p>
                        @endif
                        <button wire:click="fetchOpenAiBalance(true)" class="inline-flex items-center justify-center rounded-md border border-neutral-200 p-2 text-neutral-500 transition-colors hover:bg-neutral-100">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span class="sr-only">Actualiser</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- API Requests --}}
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-600">Requêtes API (24&nbsp;h)</p>
                    <p class="text-2xl font-bold text-neutral-900 mt-1">{{ number_format(456789) }}</p>
                    <p class="text-sm text-yellow-600 mt-1">→ +0,3&nbsp;% vs. hier</p>
                </div>
                <div class="p-3 bg-purple-100 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
            </div>
        </div>

        {{-- System Health --}}
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-600">Santé du système</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">Opérationnel</p>
                    <p class="text-sm text-neutral-600 mt-1">Tous les services fonctionnent normalement</p>
                </div>
                <div class="p-3 bg-green-100 rounded-lg">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- System Status --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        {{-- Service Status --}}
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <h2 class="text-lg font-semibold text-neutral-900 mb-4">Statut des services</h2>
            <div class="space-y-3">
                @php
                $services = [
                    ['name' => 'Serveur API', 'status' => 'operational', 'uptime' => '99,9 %'],
                    ['name' => 'PostgreSQL', 'status' => 'operational', 'uptime' => '99,8 %'],
                    ['name' => 'Cache Redis', 'status' => 'operational', 'uptime' => '100 %'],
                    ['name' => 'Workers de file', 'status' => 'operational', 'uptime' => '99,7 %'],
                    ['name' => 'Serveur WebSocket', 'status' => 'maintenance', 'uptime' => '95,2 %'],
                ];
                $statusLabels = [
                    'operational' => 'Opérationnel',
                    'maintenance' => 'Maintenance',
                    'degraded' => 'Dégradé',
                ];
                @endphp
                @foreach($services as $service)
                    <div class="flex items-center justify-between py-2 border-b border-neutral-100 last:border-0">
                        <div class="flex items-center">
                            <div class="w-2 h-2 rounded-full mr-3
                                {{ $service['status'] === 'operational' ? 'bg-green-500' : 'bg-yellow-500' }}">
                            </div>
                            <span class="text-sm font-medium text-neutral-700">{{ $service['name'] }}</span>
                        </div>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-neutral-500">{{ $service['uptime'] }} de disponibilité</span>
                            <span class="text-xs px-2 py-1 rounded-full
                                {{ $service['status'] === 'operational' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                {{ $statusLabels[$service['status']] ?? ucfirst($service['status']) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <h2 class="text-lg font-semibold text-neutral-900 mb-4">Activité récente</h2>
            <div class="space-y-3">
                @php
                $activities = [
                    ['action' => "Inscription d’utilisateur", 'user' => 'john.doe@example.com', 'time' => 'il y a 2 min'],
                    ['action' => 'Clé API générée', 'user' => 'admin@upengage.com', 'time' => 'il y a 15 min'],
                    ['action' => 'Sauvegarde base de données', 'user' => 'Système', 'time' => 'il y a 1 heure'],
                    ['action' => 'Mise à jour de traduction', 'user' => 'maria.garcia@example.com', 'time' => 'il y a 2 heures'],
                    ['action' => 'Cache vidé', 'user' => 'Système', 'time' => 'il y a 3 heures'],
                ];
                @endphp
                @foreach($activities as $activity)
                    <div class="flex items-center justify-between py-2 border-b border-neutral-100 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-neutral-700">{{ $activity['action'] }}</p>
                            <p class="text-xs text-neutral-500">par {{ $activity['user'] }}</p>
                        </div>
                        <span class="text-xs text-neutral-500">{{ $activity['time'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
        <h2 class="text-lg font-semibold text-neutral-900 mb-4">Actions rapides</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('admin.docs.index') }}"
               class="flex flex-col items-center justify-center p-4 bg-neutral-50 rounded-lg hover:bg-neutral-100 transition-colors">
                <svg class="w-8 h-8 text-neutral-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span class="text-sm font-medium text-neutral-700">Documentation</span>
            </a>

            <a href="{{ route('admin.manager.translations.index') }}"
               class="flex flex-col items-center justify-center p-4 bg-neutral-50 rounded-lg hover:bg-neutral-100 transition-colors">
                <svg class="w-8 h-8 text-neutral-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                </svg>
                <span class="text-sm font-medium text-neutral-700">Traductions</span>
            </a>

            <a href="{{ route('admin.api-tester') }}"
               class="flex flex-col items-center justify-center p-4 bg-neutral-50 rounded-lg hover:bg-neutral-100 transition-colors">
                <svg class="w-8 h-8 text-neutral-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                </svg>
                <span class="text-sm font-medium text-neutral-700">Testeur API</span>
            </a>

            <a href="{{ route('admin.manager.audit') }}"
               class="flex flex-col items-center justify-center p-4 bg-neutral-50 rounded-lg hover:bg-neutral-100 transition-colors">
                <svg class="w-8 h-8 text-neutral-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="text-sm font-medium text-neutral-700">Journaux d’audit</span>
            </a>
        </div>
    </div>
</div>
