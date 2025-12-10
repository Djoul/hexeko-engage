@props([
    'previewData' => [],
    'title' => 'Aperçu des changements',
    'description' => 'Modifications qui seront appliquées'
])

@if(!empty($previewData))
<div class="bg-white rounded-lg shadow-sm border border-neutral-200">
    <div class="px-6 py-4 border-b border-neutral-200">
        <h2 class="text-lg font-bold text-neutral-900">{{ $title }}</h2>
        <p class="text-sm text-neutral-600 mt-1">{{ $description }}</p>
    </div>

    <div class="p-6">
        <!-- Summary Stats -->
        <dl class="grid grid-cols-2 gap-5 sm:grid-cols-4 mb-6">
            <div class="px-4 py-5 bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden sm:p-6">
                <dt class="text-sm font-medium text-neutral-500 truncate">Nouvelles clés</dt>
                <dd class="mt-1 text-3xl font-semibold text-green-600">{{ $previewData['summary']['new_keys'] ?? 0 }}</dd>
            </div>
            <div class="px-4 py-5 bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden sm:p-6">
                <dt class="text-sm font-medium text-neutral-500 truncate">Mises à jour</dt>
                <dd class="mt-1 text-3xl font-semibold text-yellow-600">{{ $previewData['summary']['updated_values'] ?? 0 }}</dd>
            </div>
            <div class="px-4 py-5 bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden sm:p-6">
                <dt class="text-sm font-medium text-neutral-500 truncate">Nouvelles valeurs</dt>
                <dd class="mt-1 text-3xl font-semibold text-blue-600">{{ $previewData['summary']['new_values'] ?? 0 }}</dd>
            </div>
            <div class="px-4 py-5 bg-white rounded-lg shadow-sm border border-neutral-200 overflow-hidden sm:p-6">
                <dt class="text-sm font-medium text-neutral-500 truncate">Inchangées</dt>
                <dd class="mt-1 text-3xl font-semibold text-neutral-600">{{ $previewData['summary']['unchanged'] ?? 0 }}</dd>
            </div>
        </dl>

        <!-- Changes details -->
        <div class="space-y-4 max-h-96 overflow-y-auto">
            @if(!empty($previewData['changes']['new_keys']))
                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Nouvelles clés</h4>
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <ul class="space-y-2">
                            @foreach($previewData['changes']['new_keys'] as $newKey)
                                <li class="text-sm">
                                    <div class="font-medium text-green-900">
                                        {{ $newKey['group'] ? $newKey['group'] . '.' . $newKey['key'] : $newKey['key'] }}
                                    </div>
                                    <div class="ml-4 mt-1 space-y-1">
                                        @if(isset($newKey['locales']) && is_array($newKey['locales']))
                                            @foreach($newKey['locales'] as $locale => $value)
                                                <div class="text-xs text-green-700">
                                                    <span class="font-medium">{{ $locale }}:</span>
                                                    <span class="text-green-600">{{ e(Str::limit($value, 100)) }}</span>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if(!empty($previewData['changes']['updated_values']))
                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Valeurs modifiées</h4>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                        <ul class="space-y-3">
                            @foreach($previewData['changes']['updated_values'] as $value)
                                <li class="text-sm">
                                    <div class="font-medium text-yellow-900">
                                        {{ $value['group'] ? $value['group'] . '.' . $value['key'] : $value['key'] }}
                                        <span class="text-yellow-700">({{ $value['locale'] }})</span>
                                    </div>
                                    <div class="mt-1 text-xs text-yellow-800">
                                        <div>Avant: {{ e(Str::limit($value['old_value'], 80)) }}</div>
                                        <div>Après: {{ e(Str::limit($value['new_value'], 80)) }}</div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if(!empty($previewData['changes']['new_values']))
                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Nouvelles valeurs</h4>
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <ul class="space-y-2">
                            @php
                                $groupedNewValues = [];
                                foreach($previewData['changes']['new_values'] as $value) {
                                    $fullKey = $value['group'] ? $value['group'] . '.' . $value['key'] : $value['key'];
                                    if (!isset($groupedNewValues[$fullKey])) {
                                        $groupedNewValues[$fullKey] = [];
                                    }
                                    $groupedNewValues[$fullKey][$value['locale']] = $value['value'];
                                }
                            @endphp
                            @foreach($groupedNewValues as $fullKey => $translations)
                                <li class="text-sm">
                                    <div class="font-medium text-blue-900">{{ $fullKey }}</div>
                                    <div class="ml-4 mt-1 space-y-1">
                                        @foreach($translations as $locale => $value)
                                            <div class="text-xs text-blue-700">
                                                <span class="font-medium">{{ $locale }}:</span>
                                                <span class="text-blue-600">{{ e(Str::limit($value, 100)) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endif