@extends('admin-panel.layouts.main')

@section('title', 'Admin-panel')

@section('content')
<div class="min-h-screen bg-neutral-50">
    <div class="py-10">
        <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-neutral-900">403: Mortels Forbidden - Gods Only</h1>
                <p class="mt-2 text-sm text-neutral-600">Accédez à la documentation technique d'UpEngage</p>
            </div>

            <!-- Login Form -->
            <div class="bg-white shadow rounded-lg p-6">
                <form id="loginForm" class="space-y-6">
                    @csrf

                    @if ($errors->any())
                        <div class="rounded-md bg-error-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-error-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-error-800">
                                        @foreach ($errors->all() as $error)
                                            {{ $error }}
                                        @endforeach
                                    </h3>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="rounded-md bg-error-50 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-error-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-error-800">
                                        {{ session('error') }}
                                    </h3>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div>
                        <label for="username" class="block text-sm font-medium text-neutral-700 mb-1">Adresse email</label>
                        <input id="username"
                               name="username"
                               type="email"
                               autocomplete="email"
                               required
                               class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                               placeholder="utilisateur@exemple.com"
                               value="{{ old('username') }}">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-neutral-700 mb-1">Mot de passe</label>
                        <input id="password"
                               name="password"
                               type="password"
                               autocomplete="current-password"
                               required
                               class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                               placeholder="••••••••">
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember"
                                   name="remember"
                                   type="checkbox"
                                   class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-neutral-300 rounded">
                            <label for="remember" class="ml-2 block text-sm text-neutral-900">
                                Se souvenir de moi
                            </label>
                        </div>
                    </div>

                    <div>
                        <button type="submit"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Se connecter
                        </button>
                    </div>
                </form>
            </div>


        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const email = document.getElementById('username').value;
    const password = document.getElementById('password').value;

    try {
        // Force HTTPS in production environments
        let loginUrl = '{{ route("api.admin.auth.login") }}';
        if (window.location.protocol === 'https:' && loginUrl.startsWith('http://')) {
            loginUrl = loginUrl.replace('http://', 'https://');
        }

        const response = await fetch(loginUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

        const data = await response.json();

        if (response.ok) {
            // Check if MFA is required
            if (data.requires_mfa) {
                // Store MFA challenge data in sessionStorage
                sessionStorage.setItem('mfa_challenge', JSON.stringify({
                    challenge_name: data.challenge_name,
                    session: data.session,
                    destination: data.destination,
                    username: data.username
                }));

                // Redirect to MFA page
                window.location.href = '{{ route("admin.auth.mfa") }}';
                return;
            }

            // Store tokens in localStorage for subsequent API calls
            localStorage.setItem('admin_access_token', data.access_token);
            localStorage.setItem('admin_refresh_token', data.refresh_token);

            // Redirect to admin panel
            window.location.href = '{{ route("admin.index") }}';
        } else {
            // Show error message
            alert(data.error || 'Authentication failed');
        }
    } catch (error) {
        console.error('Login error:', error);
        alert('An error occurred. Please try again.');
    }
});
</script>
@endpush
