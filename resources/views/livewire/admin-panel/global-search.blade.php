<div class="relative" @click.away="showResults = false">
    <!-- Search Input -->
    <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
        <input
            wire:model.live.debounce.300ms="searchQuery"
            wire:keydown.arrow-down="selectNext"
            wire:keydown.arrow-up="selectPrevious"
            wire:keydown.enter="selectResult"
            wire:keydown.escape="handleEscape"
            @focus="$wire.showRecentSearches()"
            type="text"
            class="block w-full pl-10 pr-10 py-2 border border-neutral-300 rounded-lg leading-5 bg-white placeholder-neutral-500 focus:outline-none focus:placeholder-neutral-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
            placeholder="Search documentation, APIs, guides..."
            autocomplete="off"
        >
        @if($searchQuery)
            <button
                wire:click="clearSearch"
                class="absolute inset-y-0 right-0 pr-3 flex items-center"
            >
                <svg class="h-5 w-5 text-neutral-400 hover:text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        @endif
    </div>

    <!-- Search Results Dropdown -->
    @if($showResults)
        <div class="absolute z-50 mt-2 w-full bg-white rounded-lg shadow-lg border border-neutral-200 max-h-96 overflow-y-auto">
            @if($isSearching)
                <!-- Loading State -->
                <div class="px-4 py-8 text-center">
                    <div class="inline-flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-neutral-600">Searching...</span>
                    </div>
                </div>
            @elseif(empty($results))
                <!-- No Results -->
                <div class="px-4 py-8 text-center text-neutral-500">
                    @if($searchQuery)
                        <svg class="mx-auto h-12 w-12 text-neutral-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p>No results found for "<span class="font-medium">{{ $searchQuery }}</span>"</p>
                        <p class="text-sm text-neutral-400 mt-1">Try different keywords or check spelling</p>
                    @else
                        <p>Start typing to search...</p>
                    @endif
                </div>
            @else
                <!-- Results List -->
                <ul class="divide-y divide-neutral-200">
                    @foreach($results as $index => $result)
                        <li>
                            <button
                                wire:click="navigateToResult({{ $index }})"
                                class="w-full text-left px-4 py-3 hover:bg-neutral-50 focus:bg-neutral-50 focus:outline-none transition-colors {{ $selectedIndex === $index ? 'bg-neutral-50' : '' }}"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <!-- Category Badge -->
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @if($result['category'] === 'api') bg-blue-100 text-blue-800
                                            @elseif($result['category'] === 'development') bg-green-100 text-green-800
                                            @elseif($result['category'] === 'integrations') bg-purple-100 text-purple-800
                                            @elseif($result['category'] === 'reference') bg-yellow-100 text-yellow-800
                                            @elseif($result['category'] === 'recent') bg-neutral-100 text-neutral-700
                                            @else bg-neutral-100 text-neutral-700
                                            @endif
                                            mb-1">
                                            {{ ucfirst($result['category']) }}
                                        </span>

                                        <!-- Title -->
                                        <h4 class="text-sm font-medium text-neutral-900">
                                            {!! $result['title'] !!}
                                        </h4>

                                        <!-- Description -->
                                        @if(!empty($result['description']))
                                            <p class="text-sm text-neutral-500 mt-1">
                                                {!! Str::limit($result['description'], 150) !!}
                                            </p>
                                        @endif
                                    </div>

                                    <!-- Arrow Icon -->
                                    <svg class="ml-3 h-5 w-5 text-neutral-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <!-- Keyboard Navigation Hint -->
                <div class="px-4 py-2 bg-neutral-50 border-t border-neutral-200">
                    <div class="flex items-center justify-between text-xs text-neutral-500">
                        <div class="flex items-center space-x-4">
                            <span class="flex items-center">
                                <kbd class="px-1.5 py-0.5 text-xs font-semibold text-neutral-800 bg-neutral-100 border border-neutral-300 rounded">↑</kbd>
                                <kbd class="ml-1 px-1.5 py-0.5 text-xs font-semibold text-neutral-800 bg-neutral-100 border border-neutral-300 rounded">↓</kbd>
                                <span class="ml-1">Navigate</span>
                            </span>
                            <span class="flex items-center">
                                <kbd class="px-1.5 py-0.5 text-xs font-semibold text-neutral-800 bg-neutral-100 border border-neutral-300 rounded">Enter</kbd>
                                <span class="ml-1">Select</span>
                            </span>
                            <span class="flex items-center">
                                <kbd class="px-1.5 py-0.5 text-xs font-semibold text-neutral-800 bg-neutral-100 border border-neutral-300 rounded">Esc</kbd>
                                <span class="ml-1">Close</span>
                            </span>
                        </div>
                        @if(count($results) > 0)
                            <span>{{ count($results) }} {{ Str::plural('result', count($results)) }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Category Filter (Optional) -->
    @if($filterCategory)
        <div class="mt-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                Filtering: {{ ucfirst($filterCategory) }}
                <button wire:click="$set('filterCategory', '')" class="ml-1">
                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </span>
        </div>
    @endif
</div>