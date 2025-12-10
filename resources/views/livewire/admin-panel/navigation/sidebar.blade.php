<aside @class([
            'admin-panel-sidebar flex h-full flex-col border-r border-neutral-200 bg-neutral-0 transition-all duration-300',
            'sidebar--collapsed w-20' => $isCollapsed,
            'w-72' => ! $isCollapsed,
        ])
       :class="{ 'sidebar--collapsed w-20': collapsed, 'w-72': !collapsed }"
       x-data="{ collapsed: @entangle('isCollapsed') }">
    <header class="flex items-center justify-between px-4 py-3 border-b border-neutral-200">
        <span class="text-sm font-semibold uppercase tracking-wide text-neutral-500">Navigation</span>
        <button type="button"
                wire:click="toggleCollapse"
                class="flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 hover:text-orange-600 hover:border-orange-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-300"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                x-on:click="collapsed = !collapsed">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L9.586 11 7.293 8.707a1 1 0 111.414-1.414l3 3a1 1 0 010 1.414l-3 3a1 1 0 01-1.414 0z" clip-rule="evenodd" />
            </svg>
        </button>
    </header>

    @if($breadcrumbs !== [])
        <div class="border-b border-neutral-200 px-4 py-3" x-show="!collapsed">
            <nav aria-label="Fil d'Ariane" class="flex flex-wrap items-center gap-1 text-xs text-neutral-500">
                @foreach($breadcrumbs as $crumb)
                    <span class="flex items-center gap-1">
                        <span>{{ $crumb['label'] ?? '' }}</span>
                        @if(! $loop->last)
                            <svg class="h-3 w-3 text-neutral-300" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 111.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </span>
                @endforeach
            </nav>
        </div>
    @endif

    <div class="sidebar__search px-4 py-3" x-show="!collapsed">
        <label class="sr-only" for="sidebar-search">Rechercher</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-neutral-400">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.5 3a5.5 5.5 0 014.358 8.83l3.656 3.656a.75.75 0 11-1.06 1.06l-3.656-3.655A5.5 5.5 0 118.5 3zm0 1.5a4 4 0 100 8 4 4 0 000-8z" clip-rule="evenodd" />
                </svg>
            </span>
            <input id="sidebar-search"
                   type="search"
                   wire:model.debounce.300ms="search"
                   placeholder="Rechercher dans le menu"
                   class="w-full rounded-lg border border-neutral-200 bg-neutral-50 pl-9 pr-3 py-2 text-sm text-neutral-700 placeholder-neutral-400 focus:border-orange-300 focus:outline-none focus:ring-2 focus:ring-orange-300">
            @if($search !== '')
                <button type="button"
                        wire:click="clearSearch"
                        class="absolute inset-y-0 right-3 flex items-center text-neutral-400 hover:text-neutral-600">
                    <span class="sr-only">Effacer la recherche</span>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 8.586L13.95 4.636a1 1 0 111.414 1.414L11.414 10l3.95 3.95a1 1 0 01-1.414 1.414L10 11.414l-3.95 3.95a1 1 0 01-1.414-1.414L8.586 10l-3.95-3.95A1 1 0 116.05 4.636L10 8.586z" clip-rule="evenodd" />
                    </svg>
                </button>
            @endif
        </div>
    </div>

    <nav class="sidebar__navigation flex-1 overflow-y-auto px-3 py-4"
         aria-label="Navigation principale">
        @forelse($filteredTree as $node)
            <div class="mb-4 pb-4 @if(!$loop->last) border-b border-neutral-100 @endif">
                <livewire:admin-panel.navigation.menu-level-1
                    :node="$node"
                    :is-collapsed="$isCollapsed"
                    :key="'nav-l1-' . ($node['id'] ?? uniqid())" />
            </div>
        @empty
            <p class="text-center text-sm text-neutral-400 px-2">Aucun résultat trouvé</p>
        @endforelse
    </nav>
</aside>
