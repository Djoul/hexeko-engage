<div>
    <h1>Documentation UpEngage</h1>

    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6 border-l-4 border-l-info-400 bg-info-50">
        <div class="text-lg font-medium text-neutral-900 mb-2">Version actuelle</div>
        <p>Version API: <strong>1.5.9</strong> | Laravel: <strong>12+</strong> | PHP: <strong>8.4+</strong></p>
    </div>

    <p>Bienvenue dans la documentation technique d'UpEngage. Cette documentation couvre l'ensemble des aspects techniques du projet, de l'installation aux workflows de développement avancés.</p>

    <h2>Vue d'ensemble du projet</h2>

    <p>UpEngage est une API headless construite avec Laravel, conçue pour gérer des opérations complexes avec une architecture moderne et scalable.</p>

    <h3>Technologies principales</h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <strong>Backend</strong>
            <ul style="margin-top: 8px;">
                <li>Laravel 12+</li>
                <li>PHP 8.4+</li>
                <li>API REST</li>
                <li>Event Sourcing</li>
            </ul>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <strong>Base de données</strong>
            <ul style="margin-top: 8px;">
                <li>PostgreSQL (port 5433)</li>
                <li>Redis Cluster (port 6379)</li>
                <li>Migrations versionnées</li>
            </ul>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6">
            <strong>Infrastructure</strong>
            <ul style="margin-top: 8px;">
                <li>Docker & Docker Compose</li>
                <li>Nginx (port 1310)</li>
                <li>Laravel Reverb (WebSocket)</li>
                <li>Queue Workers</li>
            </ul>
        </div>
    </div>

    <h2>Démarrage rapide</h2>

    <livewire:admin-panel.tab-panel :active-tab="$activeTab" />

    <h2>Commandes essentielles</h2>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-neutral-200">
            <thead class="bg-neutral-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Commande</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Utilisation</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-neutral-200">
                @foreach($commands as $cmd)
                    <tr class="hover:bg-neutral-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-neutral-900">{{ $cmd['command'] }}</td>
                        <td class="px-6 py-4 text-sm text-neutral-700">{{ $cmd['description'] }}</td>
                        <td class="px-6 py-4 text-sm text-neutral-700">{{ $cmd['usage'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2>Structure du projet</h2>

    <livewire:admin-panel.code-block
        :code="'up-engage-api/
├── app/                    # Code source de l\'application
│   ├── Actions/           # Couche d\'orchestration
│   ├── Events/            # Events Laravel (WebSocket)
│   ├── Http/              # Controllers, Middleware, Requests
│   ├── Models/            # Modèles Eloquent
│   └── Services/          # Logique métier
├── database/              # Migrations et seeders
├── docker/                # Configuration Docker
├── docs/                  # Documentation
├── tests/                 # Tests unitaires et fonctionnels
└── Makefile              # Commandes automatisées'"
    />

    <h2>Principes architecturaux</h2>

    <div class="bg-white rounded-lg shadow-sm border border-neutral-200 p-6 border-l-4 border-l-warning-400 bg-warning-50">
        <div class="text-lg font-medium text-neutral-900 mb-2">⚠️ Important</div>
        <p>Ce projet suit strictement les principes TDD (Test-Driven Development). Chaque fonctionnalité doit être développée en suivant le cycle RED-GREEN-REFACTOR.</p>
    </div>

    <h3>Patterns utilisés</h3>

    <ul>
        <li><strong>Service + Action Pattern</strong> : Séparation claire entre orchestration (Actions) et logique métier (Services)</li>
        <li><strong>Repository Pattern</strong> : Abstraction de l'accès aux données</li>
        <li><strong>Event Sourcing</strong> : Pour la gestion des crédits et l'audit</li>
        <li><strong>DTO (Data Transfer Objects)</strong> : Communication typée entre les couches</li>
    </ul>

    <h2>Support et contribution</h2>

    <p>Pour toute question ou contribution :</p>
    <ul>
        <li>Consultez le <a href="{{ route('admin.under-construction') }}">guide de dépannage</a></li>
        <li>Créez une issue sur GitLab</li>
        <li>Suivez les <a href="{{ route('admin.under-construction') }}">guidelines de contribution</a></li>
    </ul>

    <div class="panel panel-success" style="margin-top: 40px;">
        <div class="panel-title">🚀 Prêt à commencer ?</div>
        <p>Consultez le <a href="{{ route('admin.under-construction') }}">guide de démarrage rapide</a> pour une prise en main immédiate du projet.</p>
    </div>
</div>