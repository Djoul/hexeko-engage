@props([
    'id' => 'modal',
    'size' => 'md',
    'title' => '',
    'subtitle' => '',
    'showCloseButton' => true,
    'closeOnEscape' => true,
    'closeOnBackdrop' => true
])

@php
    $sizeClasses = [
        'sm' => 'sm:max-w-md',
        'md' => 'sm:max-w-lg',
        'lg' => 'sm:max-w-xl',
        'xl' => 'sm:max-w-2xl',
        '2xl' => 'sm:max-w-4xl',
        'full' => 'sm:max-w-7xl'
    ][$size] ?? 'sm:max-w-lg';
@endphp

<div x-data="{
        open: false,
        init() {
            // Listen for open event
            window.addEventListener('open-modal-{{ $id }}', () => {
                this.open = true;
                this.$nextTick(() => {
                    // Focus first focusable element
                    const modal = this.$refs.modal;
                    const focusable = modal?.querySelector('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])');
                    focusable?.focus();
                });
            });

            // Listen for close event
            window.addEventListener('close-modal-{{ $id }}', () => {
                this.open = false;
            });
        }
     }"
     x-show="open"
     @if($closeOnEscape) x-on:keydown.escape.window="open = false" @endif
     class="fixed inset-0 z-50 overflow-y-auto"
     aria-labelledby="modal-title-{{ $id }}"
     role="dialog"
     aria-modal="true"
     style="display: none;">

    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div x-show="open"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @if($closeOnBackdrop) @click="open = false; $dispatch('close-modal-{{ $id }}')" @endif
             class="fixed inset-0 bg-neutral-500 dark:bg-neutral-900 bg-opacity-75 dark:bg-opacity-90 transition-opacity"
             aria-hidden="true"></div>

        <!-- This element is to trick the browser into centering the modal contents. -->
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <!-- Modal panel -->
        <div x-show="open"
             x-ref="modal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             class="relative inline-block align-bottom bg-white dark:bg-neutral-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle {{ $sizeClasses }} w-full">

            <!-- Modal Header -->
            @if($title || $showCloseButton)
                <div class="bg-white dark:bg-neutral-900 px-4 py-5 border-b border-neutral-200 dark:border-neutral-700 sm:px-6">
                    <div class="flex items-start justify-between">
                        <div>
                            @if($title)
                                <h3 class="text-lg leading-6 font-medium text-neutral-900 dark:text-white" id="modal-title-{{ $id }}">
                                    {{ $title }}
                                </h3>
                            @endif
                            @if($subtitle)
                                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                                    {{ $subtitle }}
                                </p>
                            @endif
                        </div>
                        @if($showCloseButton)
                            <button type="button"
                                    @click="open = false; $dispatch('close-modal-{{ $id }}')"
                                    class="ml-3 bg-white dark:bg-neutral-800 rounded-md text-neutral-400 hover:text-neutral-500 dark:hover:text-neutral-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                <span class="sr-only">{{ __('Close') }}</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Modal Content -->
            <div class="bg-white dark:bg-neutral-800 px-4 pt-5 pb-4 sm:p-6">
                {{ $slot }}
            </div>

            <!-- Modal Footer (optional) -->
            @if(isset($footer))
                <div class="bg-neutral-50 dark:bg-neutral-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-neutral-200 dark:border-neutral-700">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>