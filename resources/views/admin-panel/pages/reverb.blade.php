@extends('admin-panel.layouts.app')

@section('title', 'Laravel Reverb - Documentation')

@section('page-content')
    <div>
        <nav class="text-sm mb-4">
            <ol class="list-none p-0 inline-flex">
                <li class="flex items-center">
                    <a href="{{ route('admin.index') }}" class="text-primary-600 hover:text-primary-800">Documentation</a>
                    <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512"><path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 34.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/></svg>
                </li>
                <li>
                    <span class="text-neutral-500">Laravel Reverb</span>
                </li>
            </ol>
        </nav>

        <h1 class="text-3xl font-bold text-neutral-900 mb-6">Laravel Reverb - WebSocket Server</h1>

        <!-- Introduction -->
        <section class="mb-8">
            <h2>Vue d'ensemble</h2>
            <p>Laravel Reverb est un serveur WebSocket haute performance conçu pour Laravel. Il permet une communication bidirectionnelle en temps réel entre le serveur et les clients.</p>

            <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6 border-l-4 border-l-info-400 bg-info-50">
                <p><strong>📌 Note :</strong> Reverb nécessite PHP 8.2+ et est optimisé pour fonctionner avec Laravel 10+</p>
            </div>
        </section>

        <!-- Configuration -->
        <section class="mb-8">
            <h2>Configuration</h2>

            <h3>1. Installation</h3>
            <livewire:admin-panel.code-block
                language="bash"
                :code="'composer require laravel/reverb
php artisan reverb:install'"
            />

            <h3>2. Variables d'environnement</h3>
            <livewire:admin-panel.code-block
                language="env"
                :code="'REVERB_APP_ID=987654
REVERB_APP_KEY=qvou8nwiyg3h4rjbp3q7
REVERB_APP_SECRET=4h8nwsr2fsp45nksxdhz
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http'"
            />

            <h3>3. Démarrage du serveur</h3>
            <livewire:admin-panel.code-block
                language="bash"
                :code="'php artisan reverb:start --debug'"
            />
        </section>

        <!-- Features -->
        <section class="mb-8">
            <h2>Fonctionnalités</h2>
            <ul>
                <li><strong>Canaux publics</strong> : Pour diffuser des informations à tous les clients connectés</li>
                <li><strong>Canaux privés</strong> : Nécessitent une authentification pour accéder</li>
                <li><strong>Canaux de présence</strong> : Permettent de savoir qui est connecté</li>
                <li><strong>Events Laravel</strong> : Intégration native avec le système d'événements Laravel</li>
                <li><strong>Horizontal scaling</strong> : Support Redis pour la mise à l'échelle</li>
            </ul>
        </section>

        <!-- Interactive Test Section -->
        <section class="mb-8">
            <h2>🧪 Tests interactifs</h2>

            <!-- Status Bar -->
            <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>État de la connexion</h3>
                    <div id="connectionStatus" class="status disconnected">
                        <span>Déconnecté</span>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tab-container">
                <div class="tab-list">
                    <button class="tab-item active" onclick="switchTab(event, 'connection')">Test de connexion</button>
                    <button class="tab-item" onclick="switchTab(event, 'public')">Canal public</button>
                    <button class="tab-item" onclick="switchTab(event, 'private')">Canal privé</button>
                    <button class="tab-item" onclick="switchTab(event, 'presence')">Canal de présence</button>
                </div>

                <!-- Connection Test Tab -->
                <div id="connection-tab" class="tab-content active">
                    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
                        <h3>Test de connexion basique</h3>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label>Host</label>
                                <input type="text" id="wsHost" value="localhost">
                            </div>
                            <div class="form-group">
                                <label>Port</label>
                                <input type="text" id="wsPort" value="8080">
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <button class="button button-primary" onclick="testConnection()">Tester la connexion</button>
                            <button class="button button-secondary" onclick="disconnect()">Déconnecter</button>
                            <button class="button button-secondary" onclick="clearLog()">Effacer les logs</button>
                        </div>
                    </div>
                </div>

                <!-- Public Channel Tab -->
                <div id="public-tab" class="tab-content">
                    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
                        <h3>Test de canal public</h3>

                        <div class="form-group">
                            <label>Nom du canal</label>
                            <input type="text" id="publicChannel" value="test-channel" placeholder="test-channel">
                        </div>

                        <div style="display: flex; gap: 0.75rem; margin-bottom: 1rem;">
                            <button class="button button-primary" onclick="subscribePublicChannel()">S'abonner au canal</button>
                            <button class="button button-secondary" onclick="unsubscribeChannel('public')">Se désabonner</button>
                        </div>

                        <div class="form-group">
                            <label>Envoyer un message</label>
                            <input type="text" id="publicMessage" placeholder="Tapez votre message...">
                            <button class="button button-primary" style="margin-top: 0.5rem;" onclick="sendPublicMessage()">Envoyer</button>
                        </div>
                    </div>
                </div>

                <!-- Private Channel Tab -->
                <div id="private-tab" class="tab-content">
                    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
                        <h3>Test de canal privé</h3>

                        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-4 border-l-4 border-l-warning-400 bg-warning-50 mb-4">
                            <p><strong>⚠️ Important :</strong> Les canaux privés nécessitent une authentification. Assurez-vous d'être connecté à l'application.</p>
                        </div>

                        <div class="form-group">
                            <label>Nom du canal privé</label>
                            <input type="text" id="privateChannel" value="private-user.1" placeholder="private-user.1">
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <button class="button button-primary" onclick="subscribePrivateChannel()">S'abonner au canal privé</button>
                            <button class="button button-secondary" onclick="unsubscribeChannel('private')">Se désabonner</button>
                        </div>
                    </div>
                </div>

                <!-- Presence Channel Tab -->
                <div id="presence-tab" class="tab-content">
                    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
                        <h3>Test de canal de présence</h3>

                        <div class="form-group">
                            <label>Nom du canal de présence</label>
                            <input type="text" id="presenceChannel" value="presence-room.1" placeholder="presence-room.1">
                        </div>

                        <div style="display: flex; gap: 0.75rem;">
                            <button class="button button-primary" onclick="subscribePresenceChannel()">Rejoindre le canal</button>
                            <button class="button button-secondary" onclick="unsubscribeChannel('presence')">Quitter</button>
                        </div>

                        <div style="margin-top: 1rem;">
                            <h4>Membres connectés :</h4>
                            <div id="presenceMembers" class="panel" style="background: #f5f5f5;">
                                <span style="color: #999;">Aucun membre connecté</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log Output -->
            <div style="margin-top: 1.5rem;">
                <h3>Console de logs</h3>
                <div id="logContainer" class="log-container"></div>
            </div>
        </section>

        <!-- Code Examples -->
        <section class="doc-section">
            <h2>Exemples de code</h2>

            <h3>Côté serveur (Laravel)</h3>
            <livewire:admin-panel.code-block
                language="php"
                :code="'// Événement broadcastable
class OrderShipped implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel(\'orders\'),
            new PrivateChannel(\'user.\'.$this->order->user_id),
        ];
    }
}'"
            />

            <h3>Côté client (JavaScript)</h3>
            <livewire:admin-panel.code-block
                language="javascript"
                :code="'// Écouter un événement
Echo.channel(\'orders\')
    .listen(\'OrderShipped\', (e) => {
        console.log(\'Commande expédiée:\', e.order);
    });

// Canal privé
Echo.private(\'user.\' + userId)
    .listen(\'OrderShipped\', (e) => {
        console.log(\'Votre commande a été expédiée!\');
    });'"
            />
        </section>
    </main>
</div>

@push('styles')
<style>
.status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 500;
}

.status.connected {
    background: #d4edda;
    color: #155724;
}

.status.disconnected {
    background: #f8d7da;
    color: #721c24;
}

.tab-container {
    margin-top: 24px;
}

.tab-list {
    display: flex;
    border-bottom: 2px solid #dfe1e6;
    margin-bottom: 16px;
}

.tab-item {
    padding: 8px 16px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #6b778c;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s;
}

.tab-item:hover {
    color: #172b4d;
}

.tab-item.active {
    color: #0052cc;
    border-bottom-color: #0052cc;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.log-container {
    background: #091e42;
    color: #b3d4ff;
    padding: 16px;
    border-radius: 3px;
    font-family: monospace;
    font-size: 12px;
    height: 300px;
    overflow-y: auto;
    margin-top: 16px;
}

.log-entry {
    margin-bottom: 4px;
}

.log-entry.error {
    color: #ff5630;
}

.log-entry.success {
    color: #36b37e;
}

.log-entry.info {
    color: #4c9aff;
}

.form-group {
    margin-bottom: 12px;
}

.form-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    margin-bottom: 4px;
}

.form-group input {
    width: 100%;
    padding: 6px 12px;
    border: 2px solid #dfe1e6;
    border-radius: 3px;
    font-size: 14px;
}

.form-group input:focus {
    outline: none;
    border-color: #4c9aff;
}
</style>
@endpush

<!-- Pusher JS for WebSocket connection -->
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0-rc2/dist/web/pusher.min.js"></script>

<script>
let pusher = null;
let channels = {
    public: null,
    private: null,
    presence: null
};

// Tab switching
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });

    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

// Logging functions
function log(message, type = 'info') {
    const logContainer = document.getElementById('logContainer');
    const timestamp = new Date().toLocaleTimeString();
    const entry = document.createElement('div');
    entry.className = `log-entry ${type}`;
    entry.textContent = `[${timestamp}] ${message}`;
    logContainer.appendChild(entry);
    logContainer.scrollTop = logContainer.scrollHeight;
}

function clearLog() {
    document.getElementById('logContainer').innerHTML = '';
    log('Logs effacés', 'info');
}

// Connection status
function updateConnectionStatus(connected) {
    const statusEl = document.getElementById('connectionStatus');
    if (connected) {
        statusEl.className = 'status-indicator connected';
        statusEl.innerHTML = '<span class="status-dot"></span><span>Connecté</span>';
    } else {
        statusEl.className = 'status-indicator disconnected';
        statusEl.innerHTML = '<span class="status-dot"></span><span>Déconnecté</span>';
    }
}

// Connection test
function testConnection() {
    const host = document.getElementById('wsHost').value;
    const port = document.getElementById('wsPort').value;

    log('Tentative de connexion à Reverb...', 'info');

    // First check if server is accessible
    fetch(`http://${host}:${port}/apps/987654`)
        .then(response => response.json())
        .then(data => {
            log('✓ Serveur Reverb accessible', 'success');
            connectWebSocket(host, port);
        })
        .catch(error => {
            log('✗ Serveur Reverb inaccessible. Vérifiez qu\'il est démarré avec: php artisan reverb:start --debug', 'error');
            updateConnectionStatus(false);
        });
}

function connectWebSocket(host, port) {
    if (pusher) {
        pusher.disconnect();
    }

    const config = {
        wsHost: host,
        wsPort: port,
        forceTLS: false,
        disableStats: true,
        enabledTransports: ['ws'],
        cluster: 'mt1',
        authEndpoint: '/broadcasting/auth'
    };

    log('Configuration: ' + JSON.stringify(config), 'info');

    try {
        pusher = new Pusher('qvou8nwiyg3h4rjbp3q7', config);

        pusher.connection.bind('connecting', () => {
            log('Connexion en cours...', 'info');
        });

        pusher.connection.bind('connected', () => {
            log('✓ Connecté avec succès!', 'success');
            updateConnectionStatus(true);
        });

        pusher.connection.bind('failed', () => {
            log('✗ Échec de connexion', 'error');
            updateConnectionStatus(false);
        });

        pusher.connection.bind('disconnected', () => {
            log('Déconnecté', 'info');
            updateConnectionStatus(false);
        });

        pusher.connection.bind('error', (err) => {
            log('✗ Erreur: ' + JSON.stringify(err), 'error');
        });

    } catch (error) {
        log('✗ Erreur: ' + error.message, 'error');
    }
}

function disconnect() {
    if (pusher) {
        pusher.disconnect();
        pusher = null;
        log('Déconnexion manuelle', 'info');
    }
}

// Public channel functions
function subscribePublicChannel() {
    if (!pusher || pusher.connection.state !== 'connected') {
        log('✗ Veuillez d\'abord établir une connexion', 'error');
        return;
    }

    const channelName = document.getElementById('publicChannel').value;
    if (!channelName) {
        log('✗ Veuillez entrer un nom de canal', 'error');
        return;
    }

    log(`Abonnement au canal public: ${channelName}`, 'info');

    channels.public = pusher.subscribe(channelName);

    channels.public.bind('pusher:subscription_succeeded', () => {
        log(`✓ Abonné au canal: ${channelName}`, 'success');
    });

    channels.public.bind('pusher:subscription_error', (error) => {
        log(`✗ Erreur d'abonnement: ${error}`, 'error');
    });

    // Listen for all events on this channel
    channels.public.bind_global((eventName, data) => {
        if (!eventName.startsWith('pusher:')) {
            log(`📨 Événement reçu [${eventName}]: ${JSON.stringify(data)}`, 'info');
        }
    });
}

function sendPublicMessage() {
    const message = document.getElementById('publicMessage').value;
    if (!message) return;

    // Note: Sending client events requires server-side implementation
    log(`💬 Message envoyé: ${message}`, 'info');
    document.getElementById('publicMessage').value = '';
}

// Private channel functions
function subscribePrivateChannel() {
    if (!pusher || pusher.connection.state !== 'connected') {
        log('✗ Veuillez d\'abord établir une connexion', 'error');
        return;
    }

    const channelName = document.getElementById('privateChannel').value;
    if (!channelName) {
        log('✗ Veuillez entrer un nom de canal privé', 'error');
        return;
    }

    log(`Abonnement au canal privé: ${channelName}`, 'info');

    channels.private = pusher.subscribe(channelName);

    channels.private.bind('pusher:subscription_succeeded', () => {
        log(`✓ Abonné au canal privé: ${channelName}`, 'success');
    });

    channels.private.bind('pusher:subscription_error', (error) => {
        log(`✗ Erreur d'authentification. Assurez-vous d'être connecté à l'application.`, 'error');
    });
}

// Presence channel functions
function subscribePresenceChannel() {
    if (!pusher || pusher.connection.state !== 'connected') {
        log('✗ Veuillez d\'abord établir une connexion', 'error');
        return;
    }

    const channelName = document.getElementById('presenceChannel').value;
    if (!channelName) {
        log('✗ Veuillez entrer un nom de canal de présence', 'error');
        return;
    }

    log(`Connexion au canal de présence: ${channelName}`, 'info');

    channels.presence = pusher.subscribe(channelName);

    channels.presence.bind('pusher:subscription_succeeded', (members) => {
        log(`✓ Rejoint le canal: ${channelName}`, 'success');
        updatePresenceMembers(members);
    });

    channels.presence.bind('pusher:member_added', (member) => {
        log(`👤 ${member.info.name || member.id} a rejoint le canal`, 'info');
        updatePresenceMembers(channels.presence.members);
    });

    channels.presence.bind('pusher:member_removed', (member) => {
        log(`👤 ${member.info.name || member.id} a quitté le canal`, 'info');
        updatePresenceMembers(channels.presence.members);
    });
}

function updatePresenceMembers(members) {
    const container = document.getElementById('presenceMembers');
    if (members.count === 0) {
        container.innerHTML = '<span class="text-gray-500">Aucun membre connecté</span>';
    } else {
        let html = `<div>Total: ${members.count} membre(s)</div>`;
        members.each((member) => {
            html += `<div>• ${member.info?.name || 'Utilisateur ' + member.id}</div>`;
        });
        container.innerHTML = html;
    }
}

function unsubscribeChannel(type) {
    if (channels[type]) {
        pusher.unsubscribe(channels[type].name);
        channels[type] = null;
        log(`Désabonné du canal ${type}`, 'info');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    log('Page chargée. Prêt pour les tests.', 'info');
    log('Pour commencer, cliquez sur "Tester la connexion"', 'info');

    // Initialize tab buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.add('tab-button');
    });
});
</script>
    </div>
@endsection