@php
    $envLabel = app()->environment();
    $envColorClass = match($envLabel) {
        'production' => 'badge-danger',
        'staging' => 'badge-warning',
        'local' => 'badge-success',
        default => 'badge-neutral'
    };

    // Simplified manifest status check without ManifestService
    $manifestStatus = 'valid'; // Default to valid for now
    $manifestColorClass = match($manifestStatus) {
        'valid' => 'badge-success',
        'invalid' => 'badge-danger',
        'optional' => 'badge-warning',
        default => 'badge-neutral'
    };

    $autoApply = config('translation-migrations.auto_apply', false);
    $canApply = auth()->user()?->can('apply-migrations');
    $canSync = auth()->user()?->can('sync-migrations');

    $abilities = [];
    if ($canApply) $abilities[] = __('Apply');
    if ($canSync) $abilities[] = __('Sync');
    if (auth()->user()?->can('export-translations')) $abilities[] = __('Export');
@endphp

<div class="sticky top-0 z-40 mb-4 w-full">
    <div class="w-full bg-white dark:bg-neutral-900 border-b border-neutral-200 dark:border-neutral-700 shadow-sm backdrop-blur-sm bg-opacity-95 dark:bg-opacity-95">
        <div class="px-4 py-3">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                {{-- Environment --}}
                <div class="flex items-center gap-2">
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Environment') }}:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $envColorClass }}">
                        {{ ucfirst($envLabel) }}
                    </span>
                </div>

                {{-- Manifest Status --}}
                <div class="flex items-center gap-2">
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Manifest') }}:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $manifestColorClass }}">
                        @if($manifestStatus === 'valid')
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        @elseif($manifestStatus === 'invalid')
                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        @endif
                        {{ ucfirst($manifestStatus) }}
                    </span>
                </div>

                {{-- Auto Apply --}}
                <div class="flex items-center gap-2">
                    <span class="text-neutral-500 dark:text-neutral-400">{{ __('Auto-apply') }}:</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $autoApply ? 'badge-info' : 'badge-neutral' }}">
                        {{ $autoApply ? 'ON' : 'OFF' }}
                    </span>
                </div>

                {{-- User Abilities --}}
                @if(count($abilities) > 0)
                    <div class="flex items-center gap-2">
                        <span class="text-neutral-500 dark:text-neutral-400">{{ __('Permissions') }}:</span>
                        <div class="flex gap-1">
                            @foreach($abilities as $ability)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium badge-neutral">
                                    {{ $ability }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Policy Warnings --}}
                @if($envLabel === 'production' && !$canApply)
                    <div class="flex items-center gap-2 ml-auto">
                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-yellow-50 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-200">
                            <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ __('Read-only mode in production') }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
