@props([
    'id' => 'drawer',
    'position' => 'right',
    'size' => 'lg',
    'title' => ''
])

@php
    $sizeClasses = [
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-4xl',
        'full' => 'max-w-full'
    ][$size] ?? 'max-w-2xl';

    $positionClasses = match($position) {
        'left' => 'left-0',
        'right' => 'right-0',
        default => 'right-0'
    };

    $transitionEnter = match($position) {
        'left' => 'transition ease-out duration-300 transform',
        'right' => 'transition ease-out duration-300 transform',
        default => 'transition ease-out duration-300 transform'
    };

    $transitionEnterStart = match($position) {
        'left' => '-translate-x-full',
        'right' => 'translate-x-full',
        default => 'translate-x-full'
    };

    $transitionEnterEnd = 'translate-x-0';

    $transitionLeave = 'transition ease-in duration-200 transform';

    $transitionLeaveStart = 'translate-x-0';

    $transitionLeaveEnd = match($position) {
        'left' => '-translate-x-full',
        'right' => 'translate-x-full',
        default => 'translate-x-full'
    };
@endphp

<div x-data="{
        open: false,
        focusTrap: null,
        init() {
            // Listen for open event
            window.addEventListener('open-drawer-{{ $id }}', () => {
                this.open = true;
                this.$nextTick(() => {
                    this.trapFocus();
                });
            });

            // Listen for close event
            window.addEventListener('close-drawer-{{ $id }}', () => {
                this.open = false;
                this.releaseFocus();
            });
        },
        trapFocus() {
            // Focus trap implementation
            const drawer = this.$refs.drawer;
            if (!drawer) return;

            const focusableElements = drawer.querySelectorAll(
                'a[href], button, textarea, input[type=\"text\"], input[type=\"radio\"], input[type=\"checkbox\"], select'
            );

            const firstFocusableElement = focusableElements[0];
            const lastFocusableElement = focusableElements[focusableElements.length - 1];

            this.focusTrap = (e) => {
                if (e.key === 'Tab') {
                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusableElement) {
                            lastFocusableElement.focus();
                            e.preventDefault();
                        }
                    } else {
                        if (document.activeElement === lastFocusableElement) {
                            firstFocusableElement.focus();
                            e.preventDefault();
                        }
                    }
                }

                if (e.key === 'Escape') {
                    this.open = false;
                    this.releaseFocus();
                    $dispatch('close-drawer-{{ $id }}');
                }
            };

            document.addEventListener('keydown', this.focusTrap);
            firstFocusableElement?.focus();
        },
        releaseFocus() {
            if (this.focusTrap) {
                document.removeEventListener('keydown', this.focusTrap);
                this.focusTrap = null;
            }
        }
     }"
     x-on:keydown.escape.window="open = false"
     class="relative z-50"
     aria-labelledby="drawer-title-{{ $id }}">

    <!-- Backdrop -->
    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false; $dispatch('close-drawer-{{ $id }}')"
         class="fixed inset-0 bg-neutral-500 bg-opacity-75 transition-opacity"
         style="display: none;"></div>

    <!-- Drawer panel -->
    <div class="fixed inset-0 overflow-hidden" x-show="open" style="display: none;">
        <div class="absolute inset-0 overflow-hidden">
            <div class="pointer-events-none fixed inset-y-0 {{ $positionClasses }} flex {{ $sizeClasses }}">
                <div x-show="open"
                     x-ref="drawer"
                     x-transition:enter="{{ $transitionEnter }}"
                     x-transition:enter-start="{{ $transitionEnterStart }}"
                     x-transition:enter-end="{{ $transitionEnterEnd }}"
                     x-transition:leave="{{ $transitionLeave }}"
                     x-transition:leave-start="{{ $transitionLeaveStart }}"
                     x-transition:leave-end="{{ $transitionLeaveEnd }}"
                     class="pointer-events-auto w-screen {{ $sizeClasses }}"
                     style="display: none;">
                    <div class="flex h-full flex-col overflow-y-scroll bg-white dark:bg-neutral-800 shadow-xl">
                        <!-- Header -->
                        <div class="bg-white dark:bg-neutral-900 px-4 py-6 sm:px-6 border-b border-neutral-200 dark:border-neutral-700">
                            <div class="flex items-start justify-between">
                                <h2 class="text-lg font-medium text-neutral-900 dark:text-white" id="drawer-title-{{ $id }}">
                                    {{ $title }}
                                </h2>
                                <div class="ml-3 flex h-7 items-center">
                                    <button type="button"
                                            @click="open = false; $dispatch('close-drawer-{{ $id }}')"
                                            class="rounded-md bg-white dark:bg-neutral-800 text-neutral-400 hover:text-neutral-500 dark:hover:text-neutral-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        <span class="sr-only">{{ __('Close panel') }}</span>
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="relative flex-1 px-4 py-6 sm:px-6">
                            {{ $slot }}
                        </div>

                        <!-- Footer (optional) -->
                        @if(isset($footer))
                            <div class="flex flex-shrink-0 justify-end px-4 py-4 border-t border-neutral-200 dark:border-neutral-700">
                                {{ $footer }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>