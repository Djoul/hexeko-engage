@php
    $label = (string) ($node['label'] ?? '');
    $isActive = (bool) ($node['is_active'] ?? false);
    $nodeId = (string) ($node['id'] ?? '');

    // Icon mapping for level 2 sections
    $icons = [
        // Dashboard items
        'overview' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
        'metrics' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>',
        'health' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>',
        'queue' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>',
        'services' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>',
        // Manager items
        'translations' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>',
        'roles' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'audit-logs' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'migration-manager' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>',
    ];

    $icon = $icons[$nodeId] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>';

    $triggerBase = 'sidebar-trigger flex items-center gap-3 w-full rounded-lg px-3 py-2 text-sm transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-300 border-l-4';
    $triggerState = $isActive
        ? 'bg-orange-100 text-orange-800 border-orange-500 font-semibold shadow-sm'
        : 'text-stone-600 hover:text-orange-600 hover:bg-orange-50 border-transparent';
    $hasChildren = ! empty($node['children']);
@endphp

<div data-nav-level="2"
     class="sidebar-node sidebar-node--level2 flex flex-col gap-1"
     @if($hasChildren)
         x-data="{ open: @js($isActive) }"
     @endif>
    @if($hasChildren)
        <button type="button"
                wire:click="navigate"
                x-on:click="open = !open"
                class="{{ $triggerBase }} {{ $triggerState }}"
                title="{{ $label }}">
            <!-- Icon -->
            <span class="sidebar-trigger__icon flex-shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $icon !!}
                </svg>
            </span>

            <span class="sidebar-trigger__label flex-1 truncate text-left">{{ $label }}</span>

            @if(!empty($node['children']))
                <span class="ml-auto text-current opacity-60" aria-hidden="true">
                    <svg class="h-4 w-4 transition-transform duration-200" :class="open ? 'rotate-0' : '-rotate-90'" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.107l3.71-3.876a.75.75 0 011.08 1.04l-4.24 4.432a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </span>
            @endif
        </button>

        <div class="sidebar-node__children flex flex-col gap-0.5 pl-4 mt-1"
             x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             role="group">
            @foreach($node['children'] as $child)
                <livewire:admin-panel.navigation.menu-level-3
                    :node="$child"
                    :key="'nav-l3-' . ($child['id'] ?? uniqid())" />
            @endforeach
        </div>
    @else
        <a href="{{ $node['url'] ?? '#' }}"
           wire:click.prevent="navigate"
           class="{{ $triggerBase }} {{ $triggerState }}"
           title="{{ $label }}">
            <!-- Icon -->
            <span class="sidebar-trigger__icon flex-shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $icon !!}
                </svg>
            </span>

            <span class="sidebar-trigger__label flex-1 truncate text-left">{{ $label }}</span>
        </a>
    @endif
</div>
