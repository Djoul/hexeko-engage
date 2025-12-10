@extends('admin-panel.layouts.main')

@section('title', 'Vérification MFA')

@section('content')
<div class="min-h-screen bg-neutral-50">
    <div class="py-10">
        <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-neutral-900">Vérification MFA</h1>
                <p class="mt-2 text-sm text-neutral-600">
                    Saisissez le code à 6 chiffres envoyé par SMS à <span class="font-medium" id="destination"></span>
                </p>
            </div>

            <!-- MFA Form -->
            <div class="bg-white shadow rounded-lg p-6">
                <form id="mfaForm" class="space-y-6">
                    @csrf

                    <div id="error-message" class="hidden rounded-md bg-error-50 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-error-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-error-800" id="error-text">
                                </h3>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="mfa_code" class="block text-sm font-medium text-neutral-700 mb-1">Code de vérification</label>
                        <input id="mfa_code"
                               name="mfa_code"
                               type="text"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               autocomplete="one-time-code"
                               required
                               class="block w-full rounded-md border-neutral-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm text-center text-2xl tracking-widest"
                               placeholder="123456">
                    </div>

                    <div>
                        <button type="submit"
                                id="verifyButton"
                                class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            <span id="button-text">Vérifier</span>
                            <span id="button-spinner" class="hidden">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Vérification...
                            </span>
                        </button>
                    </div>

                    <div class="text-center">
                        <button type="button"
                                id="backButton"
                                class="text-sm text-neutral-600 hover:text-neutral-900 underline">
                            ← Retour à la connexion
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
// Get MFA data from sessionStorage (passed from login page)
const mfaData = JSON.parse(sessionStorage.getItem('mfa_challenge') || '{}');

if (!mfaData.session || !mfaData.username) {
    // Redirect back to login if no MFA data
    window.location.href = '{{ route("admin.auth.login") }}';
}

// Display destination number
document.getElementById('destination').textContent = mfaData.destination || 'votre numéro';

// Auto-focus on MFA code input
document.getElementById('mfa_code').focus();

// Auto-format MFA code input (numbers only)
document.getElementById('mfa_code').addEventListener('input', function(e) {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);

    // Auto-submit when 6 digits are entered
    if (this.value.length === 6) {
        document.getElementById('mfaForm').dispatchEvent(new Event('submit'));
    }
});

// Handle MFA form submission
document.getElementById('mfaForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const mfaCode = document.getElementById('mfa_code').value;
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const button = document.getElementById('verifyButton');
    const buttonText = document.getElementById('button-text');
    const buttonSpinner = document.getElementById('button-spinner');

    // Validate MFA code
    if (!/^\d{6}$/.test(mfaCode)) {
        showError('Le code doit contenir exactement 6 chiffres');
        return;
    }

    // Show loading state
    button.disabled = true;
    buttonText.classList.add('hidden');
    buttonSpinner.classList.remove('hidden');
    errorMessage.classList.add('hidden');

    try {
        // Force HTTPS in production environments
        let mfaUrl = '{{ route("api.admin.auth.verify-mfa") }}';
        if (window.location.protocol === 'https:' && mfaUrl.startsWith('http://')) {
            mfaUrl = mfaUrl.replace('http://', 'https://');
        }

        const response = await fetch(mfaUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                username: mfaData.username,
                mfa_code: mfaCode,
                session: mfaData.session
            })
        });

        const data = await response.json();

        if (response.ok) {
            // Store tokens and redirect
            localStorage.setItem('admin_access_token', data.access_token);
            localStorage.setItem('admin_refresh_token', data.refresh_token);

            // Clear MFA data from sessionStorage
            sessionStorage.removeItem('mfa_challenge');

            // Redirect to admin panel
            window.location.href = '{{ route("admin.index") }}';
        } else {
            showError(data.error || 'Code de vérification invalide');

            // Reset form
            document.getElementById('mfa_code').value = '';
            document.getElementById('mfa_code').focus();
        }
    } catch (error) {
        console.error('MFA verification error:', error);
        showError('Une erreur est survenue. Veuillez réessayer.');
    } finally {
        // Reset button state
        button.disabled = false;
        buttonText.classList.remove('hidden');
        buttonSpinner.classList.add('hidden');
    }
});

// Back to login button
document.getElementById('backButton').addEventListener('click', function() {
    sessionStorage.removeItem('mfa_challenge');
    window.location.href = '{{ route("admin.auth.login") }}';
});

function showError(message) {
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');

    errorText.textContent = message;
    errorMessage.classList.remove('hidden');
}
</script>
@endpush