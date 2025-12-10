@php
    $label = (string) ($node['label'] ?? '');
    $isActive = (bool) ($node['is_active'] ?? false);
    $linkBase = 'sidebar-leaf flex items-center gap-2.5 rounded-md px-3 py-2 text-sm transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-200 pl-6 border-l-3';
    $linkState = $isActive
        ? 'text-orange-800 border-orange-500 font-semibold'
        : 'text-stone-500 hover:text-orange-700 hover:bg-orange-50/50 border-transparent';
    $href = $node['url'] ?? '#';
@endphp

<div data-nav-level="3" class="sidebar-node sidebar-node--level3">
    <a href="{{ $href }}"
       wire:click.prevent="navigate"
       class="{{ $linkBase }} {{ $linkState }}"
       title="{{ $label }}"
       aria-current="{{ $isActive ? 'page' : 'false' }}">
        @if($isActive)
            <span class="sidebar-leaf__indicator h-1.5 w-1.5 rounded-full bg-orange-500 flex-shrink-0"></span>
        @else
            <span class="sidebar-leaf__placeholder h-1.5 w-1.5 flex-shrink-0"></span>
        @endif
        <span class="sidebar-leaf__label truncate">{{ $label }}</span>
    </a>
</div>
