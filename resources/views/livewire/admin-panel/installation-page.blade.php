<div>
    <div class="mb-6">
        <p class="text-neutral-600">Ce guide vous accompagne dans l'installation complète de l'environnement UpEngage avec Docker. Suivez chaque étape pour configurer votre environnement de développement.</p>
    </div>

    <livewire:admin-panel.tab-panel :tabs="$tabs" :activeTab="$activeTab" />

    <div>
        @if($activeTab === 'prerequisites')
            <div>
                <h2 class="text-2xl font-bold text-neutral-800 mb-4 pb-2 border-b border-neutral-200">Prérequis système</h2>
                <p class="text-neutral-600 mb-4">Avant de commencer, assurez-vous d'avoir installé :</p>

                <div class="space-y-4">
                    <div class="bg-neutral-50 rounded-lg p-5">
                        <h3 class="text-lg font-semibold text-neutral-800 mb-2">🐳 Docker Desktop</h3>
                        <p class="text-neutral-600 mb-2">Version 20.10 ou supérieure</p>
                        <a href="https://www.docker.com/products/docker-desktop" target="_blank" class="inline-block mt-2 text-primary-600 hover:text-primary-800 hover:underline">Télécharger Docker Desktop</a>
                    </div>

                    <div class="bg-neutral-50 rounded-lg p-5">
                        <h3 class="text-lg font-semibold text-neutral-800 mb-2">🔧 Docker Compose</h3>
                        <p class="text-neutral-600 mb-2">Généralement inclus avec Docker Desktop</p>
                        <p class="text-neutral-600 mb-2">Vérifier l'installation :</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'docker --version'"
                        />
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'docker-compose --version'"
                        />
                    </div>

                    <div class="bg-neutral-50 rounded-lg p-5">
                        <h3 class="text-lg font-semibold text-neutral-800 mb-2">💾 Espace disque</h3>
                        <p class="text-neutral-600">Minimum 10 GB d'espace libre pour les images Docker et les volumes</p>
                    </div>

                    <div class="bg-neutral-50 rounded-lg p-5">
                        <h3 class="text-lg font-semibold text-neutral-800 mb-2">🖥️ Mémoire RAM</h3>
                        <p class="text-neutral-600">Minimum 8 GB de RAM (16 GB recommandé)</p>
                    </div>
                </div>

                <div class="bg-info-50 border border-info-200 rounded-lg p-4 mt-6">
                    <p class="text-info-800"><strong class="font-semibold">Note :</strong> Sur macOS et Windows, Docker Desktop gère automatiquement la configuration de la machine virtuelle Linux nécessaire.</p>
                </div>
            </div>
        @elseif($activeTab === 'docker')
            <div>
                <h2 class="text-2xl font-bold text-neutral-800 mb-4 pb-2 border-b border-neutral-200">Installation avec Docker</h2>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">📦 1. Cloner le repository</h3>
                <div class="ml-6 mb-8">
                    <p class="text-neutral-600 mb-3">Clonez le projet depuis GitLab :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'git clone https://gitlab.com/Hexeko/engage/main-api.git'"
                    />

                    <p class="text-neutral-600 mb-3 mt-4">Accédez au dossier :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'cd main-api'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">⚙️ 2. Configuration de l'environnement</h3>
                <div class="ml-6 mb-8">
                    <p class="text-neutral-600 mb-3">Copiez le fichier d'environnement :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'cp .env.example .env'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">📦 3. Structure du projet Docker</h3>
                <p class="text-neutral-600 mb-4">Le projet utilise plusieurs services Docker orchestrés par docker-compose :</p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 my-6">
                    <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-5 text-center">
                        <h4 class="text-lg font-semibold text-neutral-800 mb-2">app_engage</h4>
                        <p class="text-neutral-600 mb-2">Application PHP/Laravel</p>
                        <span class="inline-block bg-neutral-200 px-3 py-1 rounded text-sm text-neutral-700">Container PHP-FPM</span>
                    </div>

                    <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-5 text-center">
                        <h4 class="text-lg font-semibold text-neutral-800 mb-2">webserver_engage</h4>
                        <p class="text-neutral-600 mb-2">Serveur web Nginx</p>
                        <span class="inline-block bg-neutral-200 px-3 py-1 rounded text-sm text-neutral-700">Port 1310</span>
                    </div>

                    <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-5 text-center">
                        <h4 class="text-lg font-semibold text-neutral-800 mb-2">db_engage</h4>
                        <p class="text-neutral-600 mb-2">Base de données PostgreSQL</p>
                        <span class="inline-block bg-neutral-200 px-3 py-1 rounded text-sm text-neutral-700">Port 5433</span>
                    </div>

                    <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-5 text-center">
                        <h4 class="text-lg font-semibold text-neutral-800 mb-2">redis-cluster</h4>
                        <p class="text-neutral-600 mb-2">Cache Redis en cluster</p>
                        <span class="inline-block bg-neutral-200 px-3 py-1 rounded text-sm text-neutral-700">6 nœuds (3 masters, 3 replicas)</span>
                    </div>

                    <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-5 text-center">
                        <h4 class="text-lg font-semibold text-neutral-800 mb-2">reverb_engage</h4>
                        <p class="text-neutral-600 mb-2">WebSocket Server</p>
                        <span class="inline-block bg-neutral-200 px-3 py-1 rounded text-sm text-neutral-700">Port 8080</span>
                    </div>

                    <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-5 text-center">
                        <h4 class="text-lg font-semibold text-neutral-800 mb-2">queue_engage</h4>
                        <p class="text-neutral-600 mb-2">Worker de queue</p>
                        <span class="inline-block bg-neutral-200 px-3 py-1 rounded text-sm text-neutral-700">Process background</span>
                    </div>
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">🚀 4. Démarrer les conteneurs</h3>
                <div class="ml-6 mb-8">
                    <p class="text-neutral-600 mb-3">Démarrez tous les services Docker :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose up -d'"
                    />

                    <p class="text-neutral-600 mb-3 mt-4">Alternative avec Make :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'make docker-restart'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">📦 5. Installer les dépendances</h3>
                <div class="ml-6 mb-8">
                    <p class="text-neutral-600 mb-3">Installez les dépendances PHP :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage composer install'"
                    />

                    <p class="text-neutral-600 mb-3 mt-4">Installez les dépendances JavaScript :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage npm install'"
                    />
                </div>
            </div>
        @elseif($activeTab === 'environment')
            <div>
                <h2 class="text-2xl font-bold text-neutral-800 mb-4 pb-2 border-b border-neutral-200">Variables d'environnement</h2>
                <p class="text-neutral-600 mb-4">Configurez votre fichier <code class="bg-neutral-100 px-1 py-0.5 rounded text-sm font-mono">.env</code> avec les valeurs suivantes :</p>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">🔧 Configuration de base</h3>
                <div class="ml-6 mb-8">
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'APP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://localhost:1310'"
                    />

                    <p class="text-neutral-600 mt-2">Générez la clé d'application après le démarrage des conteneurs :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage php artisan key:generate'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">🗄️ Base de données PostgreSQL</h3>
                <div class="ml-6 mb-8">
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'DB_CONNECTION=pgsql\nDB_HOST=db_engage\nDB_PORT=5432\nDB_DATABASE=db_engage\nDB_USERNAME=root\nDB_PASSWORD=password'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">💾 Redis Cluster</h3>
                <div class="ml-6 mb-8">
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'CACHE_DRIVER=redis\nSESSION_DRIVER=redis\nQUEUE_CONNECTION=redis'"
                    />

                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'REDIS_HOST=engage_redis\nREDIS_PORT=6379\nREDIS_CLUSTER=true\nREDIS_PASSWORD=null'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">🔌 Laravel Reverb (WebSocket)</h3>
                <div class="ml-6 mb-8">
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'BROADCAST_CONNECTION=reverb'"
                    />

                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'REVERB_APP_ID=681983\nREVERB_APP_KEY=qvou8nwiyg3h4rjbp3q7\nREVERB_APP_SECRET=your-secret-key\nREVERB_HOST=reverb_engage\nREVERB_PORT=8080\nREVERB_SCHEME=http'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">🔐 AWS Cognito</h3>
                <div class="ml-6 mb-8">
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'AWS_COGNITO_REGION=your-region\nAWS_COGNITO_USER_POOL_ID=your-user-pool-id\nAWS_COGNITO_CLIENT_ID=your-client-id\nAWS_COGNITO_CLIENT_SECRET=your-client-secret'"
                    />
                </div>

                <div class="bg-warning-50 border border-warning-200 rounded-lg p-4 mt-6">
                    <p class="text-warning-800"><strong class="font-semibold">Important :</strong> Ne jamais commiter le fichier <code class="bg-warning-100 px-1 py-0.5 rounded text-sm font-mono">.env</code> dans Git. Il contient des informations sensibles.</p>
                </div>
            </div>
        @elseif($activeTab === 'containers')
            <div>
                <h2 class="text-2xl font-bold text-neutral-800 mb-4 pb-2 border-b border-neutral-200">Gestion des conteneurs</h2>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">Commandes essentielles</h3>

                <h4 class="text-lg font-semibold text-neutral-700 mt-4 mb-2">🟢 Démarrage</h4>
                <div class="ml-6 mb-6">
                    <p class="text-neutral-600 mb-2">Démarrer tous les conteneurs :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose up -d'"
                    />
                </div>

                <h4 class="text-lg font-semibold text-neutral-700 mt-4 mb-2">🔴 Arrêt</h4>
                <div class="ml-6 mb-6">
                    <p class="text-neutral-600 mb-2">Arrêter tous les conteneurs :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose down'"
                    />

                    <p class="text-neutral-600 mb-2">Arrêter et supprimer les volumes :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose down -v'"
                    />
                </div>

                <h4 class="text-lg font-semibold text-neutral-700 mt-4 mb-2">📊 Surveillance</h4>
                <div class="ml-6 mb-6">
                    <p class="text-neutral-600 mb-2">État des conteneurs :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose ps'"
                    />

                    <p class="text-neutral-600 mb-2">Logs en temps réel :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose logs -f'"
                    />

                    <p class="text-neutral-600 mb-2">Logs d'un service spécifique :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose logs -f app_engage'"
                    />
                </div>

                <h4 class="text-lg font-semibold text-neutral-700 mt-4 mb-2">🖥️ Accès aux conteneurs</h4>
                <div class="ml-6 mb-6">
                    <p class="text-neutral-600 mb-2">Accéder au conteneur PHP :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage bash'"
                    />

                    <p class="text-neutral-600 mb-2">Exécuter une commande Artisan :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage php artisan migrate'"
                    />

                    <p class="text-neutral-600 mb-2">Lancer le worker de queue :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage php artisan queue:work'"
                    />
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">Commandes Make utiles</h3>
                <p class="text-neutral-600 mb-4">Le projet inclut un Makefile avec des raccourcis pratiques :</p>

                <div class="my-6">
                    <table class="w-full bg-white border border-neutral-200 rounded-lg overflow-hidden">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-neutral-700 border-b">Commande</th>
                                <th class="px-4 py-3 text-left text-sm font-semibold text-neutral-700 border-b">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3"><code class="bg-neutral-100 px-2 py-1 rounded text-sm font-mono">make docker-restart</code></td>
                                <td class="px-4 py-3 text-neutral-600">Redémarrage complet (supprime les conteneurs orphelins)</td>
                            </tr>
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3"><code class="bg-neutral-100 px-2 py-1 rounded text-sm font-mono">make docker-clean</code></td>
                                <td class="px-4 py-3 text-neutral-600">Nettoyage sûr (préserve les bases de données)</td>
                            </tr>
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3"><code class="bg-neutral-100 px-2 py-1 rounded text-sm font-mono">make docker-clean-worktree</code></td>
                                <td class="px-4 py-3 text-neutral-600">Nettoie les conteneurs worktree</td>
                            </tr>
                            <tr class="hover:bg-neutral-50">
                                <td class="px-4 py-3"><code class="bg-neutral-100 px-2 py-1 rounded text-sm font-mono">make logs</code></td>
                                <td class="px-4 py-3 text-neutral-600">Affiche les logs de tous les services</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h3 class="text-xl font-semibold text-neutral-800 mt-6 mb-3">Résolution des problèmes</h3>

                <h4 class="text-lg font-semibold text-neutral-700 mt-4 mb-2">⚠️ Port déjà utilisé</h4>
                <div class="ml-6 mb-6">
                    <p class="text-neutral-600 mb-2">Vérifier quel processus utilise le port :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'lsof -i :1310'"
                    />

                    <p class="text-neutral-600 mb-2">Si besoin, modifier le port dans <code class="bg-neutral-100 px-1 py-0.5 rounded text-sm font-mono">docker-compose.yml</code> :</p>
                    <livewire:admin-panel.code-block
                        language="yaml"
                        :code="'ports:\n  - \"1311:80\"'"
                    />
                </div>

                <h4 class="text-lg font-semibold text-neutral-700 mt-4 mb-2">🔒 Problèmes de permissions</h4>
                <div class="ml-6 mb-6">
                    <p class="text-neutral-600 mb-2">Corriger les permissions des dossiers :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage chown -R www-data:www-data storage'"
                    />

                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage chmod -R 775 storage bootstrap/cache'"
                    />
                </div>

                <h4 class="text-lg font-semibold text-neutral-700 mt-4 mb-2">🔨 Rebuild des conteneurs</h4>
                <div class="ml-6 mb-6">
                    <p class="text-neutral-600 mb-2">Reconstruire après modification du Dockerfile :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose up -d --build'"
                    />

                    <p class="text-neutral-600 mb-2">Forcer la reconstruction complète :</p>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose build --no-cache'"
                    />
                </div>
            </div>
        @endif
    </div>

</div>