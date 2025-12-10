<header class="bg-white border-b border-neutral-200">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <!-- Logo and Sidebar Toggle -->
            <div class="flex items-center">
                <!-- Logo -->
                <a href="{{ route('admin.index') }}" class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-md flex items-center justify-center">
                        <span class="text-white font-bold text-lg">U</span>
                    </div>
                    <span class="text-xl font-semibold text-neutral-900">UpEngage Admin-Panel</span>
                </a>
            </div>

            <!-- Three Pillar Navigation -->
            <nav class="hidden lg:flex items-center space-x-2 flex-1 justify-center">
                @php
                    $navigationBuilder = app(\App\Services\AdminPanel\NavigationBuilder::class);
                    $pillars = $navigationBuilder->build(
                        request()->segment(2) ?? 'dashboard',
                        request()->segment(3) ?? null
                    )['pillars'] ?? [];
                @endphp

                @foreach($pillars as $pillar)
                    <a href="{{ $pillar['route'] }}"
                       class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200
                              {{ $pillar['active'] ? 'bg-indigo-600 text-white' : 'text-neutral-700 hover:bg-neutral-100' }}"
                       @if($pillar['active'])
                           aria-current="page"
                       @endif>
                        @if(isset($pillar['icon']))
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                @switch($pillar['key'])
                                    @case('dashboard')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        @break
                                    @case('manager')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        @break
                                    @case('docs')
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                                        @break
                                @endswitch
                            </svg>
                        @endif
                        {{ $pillar['label'] }}
                    </a>
                @endforeach
            </nav>

            <!-- User Actions -->
            <div class="flex items-center space-x-2 sm:space-x-4 lg:flex-1 lg:justify-end">
                <!-- Global Search (hidden on mobile) -->
                <div class="hidden sm:block w-64 lg:w-80">
                    <livewire:admin-panel.global-search />
                </div>

                <!-- Notifications -->
                <button class="relative p-2 text-neutral-600 hover:text-neutral-900 hover:bg-neutral-100 rounded-md">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-error-500 rounded-full"></span>
                </button>

                <!-- User menu -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-3 p-2 hover:bg-neutral-100 rounded-md">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center">
                            <span class="text-sm font-medium">
                                @auth
                                    {{ substr(auth()->user()->name ?? 'A', 0, 1) }}
                                @else
                                    A
                                @endauth
                            </span>
                        </div>
                        <span class="hidden md:block text-sm font-medium text-neutral-700">
                            @auth
                                {{ auth()->user()->name ?? 'Admin' }}
                            @else
                                Admin
                            @endauth
                        </span>
                        <svg class="w-4 h-4 text-neutral-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- Dropdown menu -->
                    <div x-show="open"
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 border border-neutral-200"
                         style="display: none;">
                        @auth
                            <a href="#" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Profil</a>
                            <a href="#" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">Paramètres</a>
                            <hr class="my-1 border-neutral-200">
                            @if(Route::has('admin.logout'))
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                        Déconnexion
                                    </button>
                                </form>
                            @else
                                <a href="#" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                    Déconnexion
                                </a>
                            @endif
                        @else
                            <a href="{{ route('admin.auth.login') }}" class="block px-4 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                                Connexion
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
