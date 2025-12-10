@extends('admin-panel.layouts.main')

@section('title', 'Installation & Docker - UpEngage Documentation')

@section('content')
<div class="page-wrapper">
    <livewire:admin-panel.sidebar />

    <main class="main-content">
        <div class="breadcrumb">
            <a href="{{ route('admin.index') }}">Documentation</a> / Installation & Docker
        </div>

        <h1 class="page-title">Installation & Docker</h1>

        <div class="intro-section">
            <p>Ce guide vous accompagne dans l'installation complète de l'environnement UpEngage avec Docker. Suivez chaque étape pour configurer votre environnement de développement.</p>
        </div>

        <div class="installation-content">
            {{-- Prérequis --}}
            <section class="doc-section">
                <h2>🎯 Prérequis système</h2>

                <div class="prereq-grid">
                    <div class="prereq-card">
                        <h3>Docker Desktop</h3>
                        <p>Version 20.10+</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'docker --version'"
                        />
                    </div>

                    <div class="prereq-card">
                        <h3>Docker Compose</h3>
                        <p>Inclus avec Docker Desktop</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'docker-compose --version'"
                        />
                    </div>

                    <div class="prereq-card">
                        <h3>Ressources</h3>
                        <ul>
                            <li>RAM: 8GB minimum</li>
                            <li>Disque: 10GB libre</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- Installation rapide --}}
            <section class="doc-section">
                <h2>🚀 Installation rapide</h2>

                <div class="step-block">
                    <h3>1. Cloner le projet</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'git clone https://gitlab.com/Hexeko/engage/main-api.git'"
                    />
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'cd main-api'"
                    />
                </div>

                <div class="step-block">
                    <h3>2. Configuration</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'cp .env.example .env'"
                    />
                </div>

                <div class="step-block">
                    <h3>3. Démarrer Docker</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose up -d'"
                    />
                </div>

                <div class="step-block">
                    <h3>4. Installer les dépendances</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage composer install'"
                    />
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage npm install'"
                    />
                </div>

                <div class="step-block">
                    <h3>5. Initialiser l'application</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage php artisan key:generate'"
                    />
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'make migrate-fresh'"
                    />
                </div>
            </section>

            {{-- Services Docker --}}
            <section class="doc-section">
                <h2>📦 Services Docker</h2>

                <div class="services-grid">
                    <div class="service-box">
                        <h4>app_engage</h4>
                        <p>Application PHP/Laravel</p>
                    </div>
                    <div class="service-box">
                        <h4>webserver_engage</h4>
                        <p>Nginx - Port 1310</p>
                    </div>
                    <div class="service-box">
                        <h4>db_engage</h4>
                        <p>PostgreSQL - Port 5433</p>
                    </div>
                    <div class="service-box">
                        <h4>redis-cluster</h4>
                        <p>Cache Redis - 6 nœuds</p>
                    </div>
                    <div class="service-box">
                        <h4>reverb_engage</h4>
                        <p>WebSocket - Port 8080</p>
                    </div>
                    <div class="service-box">
                        <h4>queue_engage</h4>
                        <p>Worker Laravel Queue</p>
                    </div>
                </div>
            </section>

            {{-- Variables d'environnement --}}
            <section class="doc-section">
                <h2>⚙️ Configuration .env</h2>

                <div class="env-group">
                    <h3>Application</h3>
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'APP_NAME=\"UpEngage API\"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:1310'"
                    />
                </div>

                <div class="env-group">
                    <h3>Base de données</h3>
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'DB_CONNECTION=pgsql
DB_HOST=db_engage
DB_PORT=5432
DB_DATABASE=db_engage
DB_USERNAME=root
DB_PASSWORD=password'"
                    />
                </div>

                <div class="env-group">
                    <h3>Redis</h3>
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=engage_redis
REDIS_PORT=6379'"
                    />
                </div>

                <div class="env-group">
                    <h3>WebSocket (Reverb)</h3>
                    <livewire:admin-panel.code-block
                        language="env"
                        :code="'BROADCAST_CONNECTION=reverb
REVERB_APP_ID=681983
REVERB_APP_KEY=qvou8nwiyg3h4rjbp3q7
REVERB_HOST=reverb_engage
REVERB_PORT=8080'"
                    />
                </div>
            </section>

            {{-- Commandes utiles --}}
            <section class="doc-section">
                <h2>🛠️ Commandes utiles</h2>

                <div class="commands-section">
                    <h3>Gestion des conteneurs</h3>

                    <div class="command-item">
                        <p>Voir l'état des services :</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'docker-compose ps'"
                        />
                    </div>

                    <div class="command-item">
                        <p>Suivre les logs :</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'docker-compose logs -f'"
                        />
                    </div>

                    <div class="command-item">
                        <p>Redémarrer les services :</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'make docker-restart'"
                        />
                    </div>

                    <h3>Développement</h3>

                    <div class="command-item">
                        <p>Lancer les tests :</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'make test'"
                        />
                    </div>

                    <div class="command-item">
                        <p>Vérifier la qualité du code :</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'make quality-check'"
                        />
                    </div>

                    <div class="command-item">
                        <p>Démarrer le serveur de développement :</p>
                        <livewire:admin-panel.code-block
                            language="bash"
                            :code="'docker-compose exec app_engage npm run dev'"
                        />
                    </div>
                </div>
            </section>

            {{-- Résolution des problèmes --}}
            <section class="doc-section">
                <h2>🔧 Résolution des problèmes</h2>

                <div class="troubleshoot-item">
                    <h3>Port déjà utilisé</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'lsof -i :1310'"
                    />
                    <p>Puis modifiez le port dans docker-compose.yml si nécessaire.</p>
                </div>

                <div class="troubleshoot-item">
                    <h3>Problèmes de permissions</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose exec app_engage chown -R www-data:www-data storage'"
                    />
                </div>

                <div class="troubleshoot-item">
                    <h3>Rebuild complet</h3>
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose down -v'"
                    />
                    <livewire:admin-panel.code-block
                        language="bash"
                        :code="'docker-compose up -d --build'"
                    />
                </div>
            </section>
        </div>

        <style>
            .installation-content {
                max-width: 900px;
            }

            .doc-section {
                margin-bottom: 3rem;
                padding: 2rem;
                background: #f8f9fa;
                border-radius: 8px;
            }

            .doc-section h2 {
                margin-top: 0;
                margin-bottom: 1.5rem;
                color: #2c3e50;
            }

            .prereq-grid {
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
                margin-top: 1rem;
            }

            .prereq-card {
                background: white;
                padding: 1.5rem;
                border-radius: 6px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            .prereq-card h3 {
                margin-top: 0;
                color: #34495e;
            }

            .step-block {
                margin-bottom: 2rem;
                padding-left: 1rem;
                border-left: 3px solid #3498db;
            }

            .step-block h3 {
                color: #2c3e50;
                margin-bottom: 1rem;
            }

            .services-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
                margin-top: 1rem;
            }

            @media (max-width: 768px) {
                .services-grid {
                    grid-template-columns: 1fr;
                }
            }

            .service-box {
                background: white;
                padding: 1rem;
                border-radius: 6px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            .service-box h4 {
                margin: 0 0 0.5rem;
                color: #2c3e50;
                font-family: monospace;
            }

            .service-box p {
                margin: 0;
                color: #7f8c8d;
                font-size: 0.9rem;
            }

            .env-group {
                margin-bottom: 2rem;
            }

            .env-group h3 {
                color: #34495e;
                margin-bottom: 1rem;
            }

            .commands-section h3 {
                color: #2c3e50;
                margin: 2rem 0 1rem;
            }

            .command-item {
                margin-bottom: 1.5rem;
            }

            .command-item p {
                margin-bottom: 0.5rem;
                color: #555;
            }

            .troubleshoot-item {
                margin-bottom: 2rem;
                padding: 1rem;
                background: #fff3cd;
                border-radius: 6px;
                border: 1px solid #ffeaa7;
            }

            .troubleshoot-item h3 {
                margin-top: 0;
                color: #856404;
            }

            .troubleshoot-item p {
                margin-top: 0.5rem;
                color: #856404;
            }
        </style>
    </main>
</div>
@endsection