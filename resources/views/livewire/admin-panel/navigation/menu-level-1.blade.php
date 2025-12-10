@php
    $label = (string) ($node['label'] ?? '');
    $isActive = (bool) ($node['is_active'] ?? false);
    $nodeId = (string) ($node['id'] ?? '');

    // Icon mapping for level 1 sections
    $icons = [
        'dashboard' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
        'manager' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'docs' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
    ];

    $icon = $icons[$nodeId] ?? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>';

    $triggerBase = 'sidebar-trigger flex items-center gap-3 w-full rounded-xl px-4 py-3 text-sm font-semibold transition-all duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400';
    $triggerState = $isActive
        ? 'bg-orange-500 text-white shadow-md hover:bg-orange-600'
        : 'text-stone-600 hover:text-orange-700 hover:bg-orange-100';
@endphp

<div data-nav-level="1"
     class="sidebar-node sidebar-node--level1 flex flex-col gap-1"
     x-data="{ open: @js($isActive) }"
     x-on:navigation-changed.window="if ($event.detail.section === @js($node['id'] ?? null)) { open = true }">
    <button type="button"
            @if(! $isCollapsed)
                x-on:click.prevent="open = !open"
            @else
                wire:click="navigate"
            @endif
            class="{{ $triggerBase }} {{ $triggerState }}"
            title="{{ $label }}">
        <!-- Icon -->
        <span class="sidebar-trigger__icon flex-shrink-0">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                {!! $icon !!}
            </svg>
        </span>

        @unless($isCollapsed)
            <span class="sidebar-trigger__label flex-1 truncate text-left">{{ $label }}</span>
        @endunless

        @unless($isCollapsed)
            @if(!empty($node['children']))
                <span class="ml-auto text-current opacity-70" aria-hidden="true">
                    <svg class="h-4 w-4 transition-transform duration-200" :class="open ? 'rotate-0' : '-rotate-90'" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.107l3.71-3.876a.75.75 0 011.08 1.04l-4.24 4.432a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                    </svg>
                </span>
            @endif
        @endunless
    </button>

    @if(! $isCollapsed && ! empty($node['children']))
        <div class="sidebar-node__children flex flex-col gap-0.5 pl-2 mt-1"
             x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             role="group">
            @foreach($node['children'] as $child)
                <livewire:admin-panel.navigation.menu-level-2
                    :node="$child"
                    :is-collapsed="$isCollapsed"
                    :key="'nav-l2-' . ($node['id'] ?? uniqid()) . '-' . ($child['id'] ?? uniqid())" />
            @endforeach
        </div>
    @endif
</div>
