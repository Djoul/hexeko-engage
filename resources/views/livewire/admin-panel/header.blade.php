<header class="bg-white border-b border-neutral-200">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo -->
            <a href="{{ route('admin.index') }}" class="flex items-center space-x-3">
                <img src="{{ asset('icone.png') }}"
                     alt="UpEngage Logo"
                     class="w-8 h-8 rounded-md object-contain">
                <span class="text-xl font-semibold text-neutral-900">UpPlus+ Admin</span>
            </a>

        <div class="flex-1"></div>

            <!-- User Actions -->
            <div class="flex items-center space-x-2 sm:space-x-4">
                <!-- Developer Tools -->
                <div class="relative">
                    <div class="flex items-center space-x-2">
                        <!-- API Docs Link -->
                        <a href="{{ route('admin.docs.api') }}"
                           target="_blank"
                           class="p-2 text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-md transition-colors group relative"
                           title="API Documentation">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                            </svg>
                            <span class="absolute -bottom-8 left-1/2 transform -translate-x-1/2 px-2 py-1 text-xs text-white bg-neutral-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                                API Docs
                            </span>
                        </a>

                        <!-- Log Viewer Link -->
                        <a href="/log-viewer"
                           target="_blank"
                           class="p-2 text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-md transition-colors group relative"
                           title="Log Viewer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="absolute -bottom-8 left-1/2 transform -translate-x-1/2 px-2 py-1 text-xs text-white bg-neutral-800 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-10">
                                Log Viewer
                            </span>
                        </a>
                    </div>
                </div>

                <!-- Global Search (hidden on mobile) -->
                <div>
                    <livewire:admin-panel.global-search />
                </div>

                <!-- Notifications -->
                <div class="relative" x-data="{ open: @entangle('notificationsOpen') }">
                    <button @click="open = !open; $wire.set('notificationsOpen', open)"
                            class="relative p-2 text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-md transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        @if($unreadNotifications > 0)
                            <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                        @endif
                    </button>

                    <!-- Notifications Dropdown -->
                    <div x-show="open"
                         @click.away="open = false; $wire.set('notificationsOpen', false)"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg py-2 z-50 border border-neutral-200"
                         style="display: none;">
                            <div class="px-4 py-2 border-b border-neutral-100">
                                <h3 class="text-sm font-semibold text-neutral-900">Notifications</h3>
                                @if($unreadNotifications > 0)
                                    <p class="text-xs text-neutral-500 mt-1">{{ $unreadNotifications }} non lues</p>
                                @endif
                            </div>
                            <div class="max-h-96 overflow-y-auto">
                                @for($i = 0; $i < 3; $i++)
                                    <div class="px-4 py-3 hover:bg-neutral-50 cursor-pointer border-b border-neutral-100 last:border-0">
                                        <div class="flex items-start space-x-3">
                                            <div class="w-2 h-2 bg-orange-500 rounded-full mt-1.5"></div>
                                            <div class="flex-1">
                                                <p class="text-sm text-neutral-700">Nouvelle mise à jour du système disponible</p>
                                                <p class="text-xs text-neutral-500 mt-1">Il y a {{ $i + 1 }} heure(s)</p>
                                            </div>
                                        </div>
                                    </div>
                                @endfor
                            </div>
                            <div class="px-4 py-2 border-t border-neutral-100">
                                <button class="text-sm text-orange-600 hover:text-orange-700 font-medium">
                                    Voir toutes les notifications
                                </button>
                            </div>
                        </div>
                </div>

                <!-- User menu -->
                <div class="relative" x-data="{ open: @entangle('userMenuOpen') }">
                    <button @click="open = !open; $wire.set('userMenuOpen', open)"
                            class="flex items-center space-x-3 p-2 hover:bg-neutral-100 rounded-md transition-colors">
                        <div class="w-8 h-8 bg-orange-500 text-white rounded-full flex items-center justify-center shadow-sm">
                            <span class="text-sm font-medium">{{ $userInitial }}</span>
                        </div>
                        <span class="hidden md:block text-sm font-medium text-neutral-700">{{ $userName }}</span>
                        <svg class="w-4 h-4 text-neutral-600 transition-transform duration-200"
                             :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- User Dropdown -->
                    <div x-show="open"
                         @click.away="open = false; $wire.set('userMenuOpen', false)"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-1 z-50 border border-neutral-200"
                         style="display: none;">
                            @auth
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Mon Profil
                                </a>
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Paramètres
                                </a>
                                <hr class="my-1 border-neutral-200">
                                <button wire:click="logout"
                                        class="flex items-center w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Déconnexion
                                </button>
                            @else
                                <a href="{{ route('admin.auth.login') }}"
                                   class="flex items-center px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Connexion
                                </a>
                            @endauth
                        </div>
                </div>
            </div>
        </div>
    </div>
</header>
