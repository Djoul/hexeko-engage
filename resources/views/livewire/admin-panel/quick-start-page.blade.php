<div>
    <div class="mb-6">
        <p class="text-gray-600 mb-4">Démarrez rapidement avec UpEngage API ! Ce guide vous permettra d'avoir une instance fonctionnelle en moins de 10 minutes.</p>

        <div class="bg-blue-50 text-blue-800 px-4 py-2 rounded-lg inline-block">
            <strong class="font-semibold">Version actuelle :</strong> 0.1.0-dev
        </div>
    </div>

    <div class="flex justify-between mb-8">
        @foreach($steps as $key => $label)
            <div
                class="flex flex-col items-center cursor-pointer {{ $activeStep === $key ? 'text-blue-600' : 'text-gray-400' }}"
                wire:click="setActiveStep('{{ $key }}')"
            >
                <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $activeStep === $key ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }}">{{ $loop->iteration }}</div>
                <div class="text-sm mt-2 font-medium">{{ $label }}</div>
            </div>
        @endforeach
    </div>

    <div class="step-content">
        @if($activeStep === 'clone')
            <div class="section">
                <h2>📥 Étape 1 : Cloner le projet</h2>

                <p>Commencez par cloner le repository depuis GitLab :</p>

                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Cloner le repository\ngit clone https://gitlab.com/Hexeko/engage/main-api.git\n\n# Accéder au dossier du projet\ncd main-api'"
                />

                <div class="tip-box">
                    <h4>💡 Astuce</h4>
                    <p>Si vous avez des problèmes d'accès au repository, vérifiez que vous avez configuré votre clé SSH GitLab correctement.</p>
                </div>
            </div>
        @elseif($activeStep === 'setup')
            <div class="section">
                <h2>⚙️ Étape 2 : Configuration initiale</h2>

                <h3>Copier le fichier d'environnement</h3>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'cp .env.example .env'"
                />

                <h3>Éditer les variables essentielles</h3>
                <p>Ouvrez le fichier <code>.env</code> et configurez au minimum :</p>

                <livewire:admin-panel.code-block
                    language="env"
                    :code="'APP_NAME=\"UpEngage API\"\nAPP_ENV=local\nAPP_KEY=\nAPP_DEBUG=true\nAPP_URL=http://localhost:1310\n\nDB_HOST=db_engage\nDB_DATABASE=db_engage\nDB_USERNAME=root\nDB_PASSWORD=password'"
                />

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Générer la clé d'application</h3>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Cette commande sera exécutée après le démarrage des conteneurs\n# docker-compose exec app_engage php artisan key:generate'"
                />

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-4">
                    <p class="text-blue-800"><strong class="font-semibold">Note :</strong> La génération de la clé sera faite après le démarrage des conteneurs Docker.</p>
                </div>
            </div>
        @elseif($activeStep === 'database')
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">🗄️ Étape 3 : Base de données</h2>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Démarrer les conteneurs</h3>
                <p class="text-gray-600 mb-4">D'abord, démarrez tous les services Docker :</p>

                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Démarrer tous les conteneurs en arrière-plan\ndocker-compose up -d\n\n# Vérifier que tous les conteneurs sont en cours d\'exécution\ndocker-compose ps'"
                />

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Générer la clé d'application</h3>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'docker-compose exec app_engage php artisan key:generate'"
                />

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Installer les dépendances</h3>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Installer les dépendances PHP\ndocker-compose exec app_engage composer install\n\n# Installer les dépendances JavaScript\ndocker-compose exec app_engage npm install'"
                />

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Exécuter les migrations</h3>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Créer les tables de la base de données\ndocker-compose exec app_engage php artisan migrate --force\n\n# Charger les données de base\ndocker-compose exec app_engage php artisan db:seed --force'"
                />

                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mt-4">
                    <p class="text-green-800">La base de données PostgreSQL sera automatiquement créée lors du premier démarrage des conteneurs.</p>
                </div>
            </div>
        @elseif($activeStep === 'services')
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">🚀 Étape 4 : Démarrer les services</h2>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Services principaux</h3>
                <p class="text-gray-600 mb-4">Assurez-vous que tous les services sont en cours d'exécution :</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex items-center">
                        <span class="text-3xl mr-3">🌐</span>
                        <div>
                            <strong class="block font-semibold text-gray-800">Application Web</strong>
                            <p class="text-gray-600">http://localhost:1310</p>
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex items-center">
                        <span class="text-3xl mr-3">🗄️</span>
                        <div>
                            <strong class="block font-semibold text-gray-800">PostgreSQL</strong>
                            <p class="text-gray-600">Port 5433</p>
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 flex items-center">
                        <span class="text-3xl mr-3">💾</span>
                        <div>
                            <strong class="block font-semibold text-gray-800">Redis Cluster</strong>
                            <p class="text-gray-600">Port 6379</p>
                        </div>
                    </div>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Services complémentaires</h3>

                <h4 class="text-lg font-semibold text-gray-700 mt-4 mb-2">Laravel Reverb (WebSocket)</h4>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Démarrer le serveur WebSocket\nmake reverb-start\n\n# Vérifier le statut\nmake reverb-status'"
                />

                <h4 class="text-lg font-semibold text-gray-700 mt-4 mb-2">Queue Worker</h4>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Démarrer le worker de queue\nmake queue\n\n# Ou directement avec Docker\ndocker-compose exec app_engage php artisan queue:work'"
                />

                <h4 class="text-lg font-semibold text-gray-700 mt-4 mb-2">Vite Dev Server (pour le développement front-end)</h4>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Démarrer le serveur de développement Vite\ndocker-compose exec app_engage npm run dev'"
                />
            </div>
        @elseif($activeStep === 'verify')
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">✅ Étape 5 : Vérifier l'installation</h2>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">1. Vérifier l'état des conteneurs</h3>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'docker-compose ps'"
                />

                <p class="text-gray-600 mb-4">Vous devriez voir tous les conteneurs avec le statut "Up" :</p>

                <div class="bg-gray-100 rounded-md p-4 overflow-x-auto mb-4">
                    <pre class="text-sm font-mono">NAME                 IMAGE                   STATUS         PORTS
app_engage          upengage-app_engage     Up             9000/tcp
webserver_engage    nginx:alpine           Up             0.0.0.0:1310->80/tcp
db_engage           postgres:16            Up             0.0.0.0:5433->5432/tcp
redis-cluster       redis:7.2-alpine       Up             0.0.0.0:6379->6379/tcp
reverb_engage       upengage-app_engage    Up             0.0.0.0:8080->8080/tcp</pre>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">2. Accéder à l'application</h3>
                <p class="text-gray-600 mb-4">Ouvrez votre navigateur et accédez à :</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center mb-4">
                    <a href="http://localhost:1310" target="_blank" class="text-blue-600 hover:text-blue-800 text-lg font-semibold">http://localhost:1310</a>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">3. Vérifier l'API</h3>
                <p class="text-gray-600 mb-4">Testez que l'API répond correctement :</p>

                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Test de santé de l\'API\ncurl http://localhost:1310/api/v1/health\n\n# Réponse attendue :\n# {\"status\":\"ok\",\"timestamp\":\"2025-08-03T10:00:00.000Z\"}"'
                />

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">4. Documentation API</h3>
                <p class="text-gray-600 mb-4">Accédez à la documentation interactive de l'API :</p>

                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center mb-4">
                    <a href="http://localhost:1310/admin-panel/docs/api" target="_blank" class="text-blue-600 hover:text-blue-800 text-lg font-semibold">http://localhost:1310/admin-panel/docs/api</a>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">5. Tester WebSocket (optionnel)</h3>
                <livewire:admin-panel.code-block
                    language="bash"
                    :code="'# Envoyer un événement de test\nmake reverb-test\n\n# Voir les logs Reverb\nmake reverb-logs'"
                />

                <div class="bg-green-50 border border-green-200 rounded-lg p-6 mt-6 mb-6">
                    <h4 class="text-xl font-semibold text-green-800 mb-2">🎉 Félicitations !</h4>
                    <p class="text-green-700">Votre environnement UpEngage est maintenant opérationnel. Vous pouvez commencer à développer !</p>
                </div>

                <h3 class="text-xl font-semibold text-gray-800 mt-6 mb-3">Prochaines étapes</h3>
                <ul class="list-disc list-inside space-y-2 text-gray-600">
                    <li><a href="{{ route('admin.make-commands') }}" class="text-blue-600 hover:text-blue-800 hover:underline">Explorer les commandes Make disponibles</a></li>
                    <li><a href="{{ route('admin.development') }}" class="text-blue-600 hover:text-blue-800 hover:underline">Configurer votre environnement de développement</a></li>
                    <li><a href="{{ route('admin.testing') }}" class="text-blue-600 hover:text-blue-800 hover:underline">Apprendre à exécuter les tests</a></li>
                    <li><a href="{{ route('admin.docs.api') }}" class="text-blue-600 hover:text-blue-800 hover:underline">Explorer la documentation API</a></li>
                </ul>
            </div>
        @endif
    </div>

</div>