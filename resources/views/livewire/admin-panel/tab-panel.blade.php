<div>
    <div class="tabs">
        <ul class="tab-list">
            <li
                class="tab @if($activeTab === 'new-install') active @endif"
                wire:click="setActiveTab('new-install')"
            >
                Nouvelle installation
            </li>
            <li
                class="tab @if($activeTab === 'existing-project') active @endif"
                wire:click="setActiveTab('existing-project')"
            >
                Projet existant
            </li>
        </ul>
    </div>

    <div id="new-install" class="tab-content @if($activeTab === 'new-install') active @endif">
        <livewire:admin-panel.code-block
            :code="'# 1. Cloner le repository
git clone https://gitlab.com/Hexeko/engage/main-api.git
cd main-api

# 2. Copier la configuration
cp .env.example .env

# 3. Démarrer Docker
docker-compose up -d

# 4. Installer les dépendances
docker-compose exec app_engage composer install

# 5. Générer la clé d\'application
docker-compose exec app_engage php artisan key:generate

# 6. Exécuter les migrations
make migrate-fresh

# 7. Démarrer Reverb (WebSocket)
make reverb-start'"
        />
    </div>

    <div id="existing-project" class="tab-content @if($activeTab === 'existing-project') active @endif">
        <livewire:admin-panel.code-block
            :code="'# 1. Mettre à jour le code
git pull origin develop

# 2. Installer les nouvelles dépendances
docker-compose exec app_engage composer install

# 3. Exécuter les migrations
make migrate

# 4. Vider les caches
make clean

# 5. Redémarrer les services si nécessaire
make docker-restart'"
        />
    </div>
</div>