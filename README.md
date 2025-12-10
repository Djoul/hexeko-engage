# UpEngage API test MR
 
Laravel 12+ Headless API avec PostgreSQL, Redis Cluster, et authentification AWS Cognito.

## 🚀 Quick Start

### Prerequisites
- Docker Desktop
- Make (via Xcode sur macOS)
- Accès 1Password équipe Hexeko

### Installation (5 minutes)

1. **Clone et configuration**
   ```bash
   git clone https://gitlab.com/Hexeko/engage/main-api.git
   cd main-api
   ```

2. **⚠️ Configuration environnement (CRITIQUE)**
   ```bash
   # Le .env.example n'est PAS suffisant !
   # Obtenir le .env complet depuis 1Password :
   # → Rechercher "UpEngage API .env Production"
   cp .env.example .env
   # Puis remplacer par le contenu de 1Password
   ```
   
   **Variables critiques manquantes dans .env.example :**
   - AWS Cognito (`AWS_COGNITO_*`)
   - Stripe (`STRIPE_*`)
   - Amilon (`AMILON_*`)
   - Apideck (`APIDECK_*`)
   - Redis Cluster (`REDIS_CLUSTER_*`)

3. **Démarrer les services**
   ```bash
   docker-compose up -d
   make migrate-fresh  # DB + seed data
   ```

4. **Vérifier l'installation**
   - API : http://localhost:1310
   - Docs : http://localhost:1310/docs/api
   - Logs : http://localhost:1310/log-viewer
   - Health : http://localhost:1310/health

## 🛠️ Commandes Essentielles

### Développement
```bash
make test           # Lancer les tests
make quality-check  # Vérification complète (OBLIGATOIRE avant commit)
make queue         # Démarrer le worker
make reverb-start  # WebSocket server
make help
```

### Base de données
```bash
make migrate       # Migrations
make migrate-fresh # Reset + seed
make seed-amilon   # Seed données Amilon test
```

### Docker
```bash
make docker-restart     # Redémarrage complet
make docker-clean       # Nettoyage safe
docker-compose logs -f  # Logs en temps réel
```

### Tests
```bash
make test                      # Suite complète
make test-group GROUPS="user"  # Tests par groupe
make test-failed               # Rejouer les tests échoués
make coverage                  # Rapport de couverture
```

## 📋 Standards du Projet

- **PHP 8.4+** avec typage strict
- **TDD obligatoire** (coverage > 80% ⚠️ in progress)
- **Service/Action Pattern** pour la logique métier
- **Event Sourcing** pour les crédits
- **PHPStan level 9** (0 erreurs tolérées ⚠️ in progress)
- **Code style PSR-12** via Laravel Pint

## 🏗️ Architecture

```
app/
├── Actions/        # Orchestration de la logique métier
├── Services/       # Logique métier
├── Http/
│   ├── Controllers/    # Minimal, délègue aux Actions
│   └── Requests/       # Validation des requêtes
├── Models/         # Eloquent avec traits de cache
├── Events/         # Event Sourcing
└── Integrations/   # Modules externes (Amilon, Apideck, etc.)
```

## 📚 Documentation Complète

- [Architecture & Patterns](documentation/ARCHITECTURE.md) ⚠️ in progress
- [Guide des Tests](documentation/TESTING.md) ⚠️ in progress
- [WebSocket/Reverb](documentation/REVERB.md)
- [API Metrics](documentation/METRICS.md)
- [Docker Setup](documentation/DOCKER.md)
- [API Documentation](http://localhost:1310/docs/api)
- [Troubleshooting](documentation/TROUBLESHOOTING.md)

## ⚠️ Points d'Attention

1. **Toujours** utiliser Docker pour les commandes PHP
   ```bash
   # ✅ CORRECT
   docker-compose exec app_engage php artisan migrate
   
   # ❌ INCORRECT
   php artisan migrate
   ```

2. **Jamais** commiter le .env complet
   - Utiliser `.env.example` pour les nouvelles variables
   - Documenter dans 1Password

3. **Obligatoire** avant chaque push :
   ```bash
   make quality-check  # Doit passer à 100%
   ```

4. **TDD** : Tests AVANT implémentation
   ```bash
   # 1. Écrire le test
   # 2. Voir le test échouer
   # 3. Implémenter
   # 4. Voir le test passer
   ```

## 🔧 Ports & Services

| Service | Port | Container | Description |
|---------|------|-----------|-------------|
| API/Nginx | 1310 | webserver_engage | API REST principale |
| PostgreSQL | 5433 | db_engage | Base de données |
| Redis Cluster | 6379 | redis-cluster | Cache & sessions |
| Reverb WebSocket | 8080 | reverb_engage | Temps réel |

## 🚦 Debugging & Monitoring

- **Logs viewer** : http://localhost:1310/log-viewer
- **API docs** : http://localhost:1310/docs/api
- **Health check** : http://localhost:1310/health
- **Sentry** : Erreurs en temps réel (voir 1Password)
- **Logs Docker** : `docker-compose logs -f [service]`

## 🔄 Workflow Git

```bash
# Nouvelle feature
git checkout -b feature/nom-feature

# Développement avec TDD
make test  # Écrire tests d'abord

# Avant commit
make quality-check  # MUST PASS

# Commit
git add .
git commit -m "feat: description"

# Push
git push origin feature/nom-feature
```

## 🤝 Support

- **Issues** : GitLab project issues
- **Monitoring** : Sentry (erreurs temps réel)
- **Docs internes** : Confluence Hexeko
- **Secrets** : 1Password équipe
- **CI/CD** : GitLab pipelines

## 📦 Dépendances Principales

- **Laravel 12+** - Framework PHP
- **PostgreSQL 15** - Base de données
- **Redis 7** - Cache cluster
- **AWS Cognito** - Authentification
- **Stripe** - Paiements
- **Amilon** - Vouchers
- **Apideck** - Intégrations unifiées
- **Sentry** - Monitoring erreurs

---

**Version** : 0.1.0-dev  
**Dernière mise à jour** : 2025-09-06  
**Mainteneur** : Équipe Hexeko
