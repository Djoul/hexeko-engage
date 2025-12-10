<div class="translation-list-component">
    {{-- Header with filters --}}
    <div class="bg-white rounded-lg shadow-sm mb-4">
        <div class="p-4 border-b border-gray-200">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0">
                {{-- Search --}}
                <div class="flex-1 max-w-lg">
                    <div class="relative">
                        <input type="text"
                               wire:model.debounce.300ms="search"
                               placeholder="Search translations..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        @if($search)
                        <button wire:click="$set('search', '')"
                                class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap gap-3">
                    {{-- Interface Filter --}}
                    <select wire:model="selectedInterface"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Interfaces</option>
                        @foreach($interfaces as $interface)
                        <option value="{{ $interface->value }}">{{ str_replace('_', ' ', ucfirst($interface->value)) }}</option>
                        @endforeach
                    </select>

                    {{-- Language Filter --}}
                    <select wire:model="selectedLanguage"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Languages</option>
                        @foreach($languages as $language)
                        <option value="{{ $language->value }}">{{ $language->name }}</option>
                        @endforeach
                    </select>

                    {{-- Status Filter --}}
                    <select wire:model="filterStatus"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="all">All Status</option>
                        <option value="translated">Translated</option>
                        <option value="missing">Missing</option>
                    </select>

                    {{-- Add New Button --}}
                    <button wire:click="$dispatch('add-translation')"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Translation
                    </button>
                </div>
            </div>
        </div>

        {{-- Stats Bar --}}
        <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">
                        Showing <span class="font-medium text-gray-900">{{ $stats['filtered'] }}</span>
                        of <span class="font-medium text-gray-900">{{ $stats['total'] }}</span> translations
                    </span>
                    @if($stats['selected'] > 0)
                    <span class="text-blue-600 font-medium">
                        {{ $stats['selected'] }} selected
                    </span>
                    @endif
                </div>

                {{-- Bulk Actions --}}
                @if($showBulkActions)
                <div class="flex items-center space-x-2">
                    <button wire:click="bulkExport"
                            class="px-3 py-1 text-sm bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
                        Export Selected
                    </button>
                    <button wire:click="bulkDelete"
                            class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700 transition-colors">
                        Delete Selected
                    </button>
                    <button wire:click="deselectAll"
                            class="px-3 py-1 text-sm bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
                        Clear Selection
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Translation Table --}}
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-12 px-4 py-3">
                            <input type="checkbox"
                                   wire:click="selectAll"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                        <th class="px-6 py-3 text-left">
                            <button wire:click="sortBy('key')"
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center hover:text-gray-700">
                                Translation Key
                                @if($sortField === 'key')
                                    <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @if($sortDirection === 'asc')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                        @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        @endif
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Value
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Interface
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Language
                        </th>
                        <th class="px-6 py-3 text-left">
                            <button wire:click="sortBy('updated_at')"
                                    class="text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center hover:text-gray-700">
                                Updated
                                @if($sortField === 'updated_at')
                                    <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        @if($sortDirection === 'asc')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                        @else
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        @endif
                                    </svg>
                                @endif
                            </button>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($keys as $key)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3">
                            <input type="checkbox"
                                   wire:click="toggleKeySelection({{ $key->id }})"
                                   value="{{ $key->id }}"
                                   @if(in_array($key->id, $selectedKeys)) checked @endif
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </td>
                        <td class="px-6 py-3">
                            <div class="text-sm font-medium text-gray-900">{{ $key->key }}</div>
                            @if($key->description)
                            <div class="text-xs text-gray-500 mt-1">{{ $key->description }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @php
                                $keyTranslations = $translations->get($key->id, collect());
                                $firstTranslation = $keyTranslations->first();
                            @endphp
                            @if($firstTranslation)
                            <div class="text-sm text-gray-900 truncate max-w-xs" title="{{ $firstTranslation->value }}">
                                {{ Str::limit($firstTranslation->value, 50) }}
                            </div>
                            @else
                            <span class="text-sm text-gray-400 italic">No translation</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if($selectedInterface)
                                <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">
                                    {{ str_replace('_', ' ', ucfirst($selectedInterface)) }}
                                </span>
                            @else
                                <span class="text-sm text-gray-500">Multiple</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if($selectedLanguage)
                                <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">
                                    {{ strtoupper($selectedLanguage) }}
                                </span>
                            @else
                                <span class="text-sm text-gray-500">Multiple</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-sm text-gray-500">
                            {{ $key->updated_at->diffForHumans() }}
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end space-x-2">
                                <button wire:click="editTranslation({{ $key->id }})"
                                        class="text-blue-600 hover:text-blue-900 transition-colors"
                                        title="Edit">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button wire:click="duplicateKey({{ $key->id }})"
                                        class="text-gray-600 hover:text-gray-900 transition-colors"
                                        title="Duplicate">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                                <button wire:click="$dispatch('delete-translation', { keyId: {{ $key->id }} })"
                                        class="text-red-600 hover:text-red-900 transition-colors"
                                        title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                <p class="text-gray-500 text-sm">No translations found</p>
                                <p class="text-gray-400 text-xs mt-1">Try adjusting your filters or add a new translation</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($keys->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $keys->links() }}
        </div>
        @endif
    </div>
</div>