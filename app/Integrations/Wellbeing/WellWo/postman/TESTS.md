# WellWo API - Tests Postman

## 🚀 Démarrage rapide

### 1. Configuration initiale

```bash
# Se placer dans le répertoire postman
cd app/Integrations/Wellbeing/WellWo/postman

# Configurer l'environnement de test (génère un token JWT)
./scripts/setup-test-env.sh

# Exécuter les tests
./scripts/run-tests.sh local
```

### 2. Prérequis

- Docker et Docker Compose installés
- Newman installé (`npm install -g newman newman-reporter-htmlextra`)
- jq installé (optionnel, pour la mise à jour automatique du token)
- L'application Laravel doit être en cours d'exécution

## 📁 Structure des fichiers

```
postman/
├── WellWo-API.postman_collection.json    # Collection de tests
├── environments/
│   └── local.postman_environment.json    # Environnement local
├── scripts/
│   ├── run-tests.sh                      # Script d'exécution des tests
│   └── setup-test-env.sh                 # Script de configuration
├── reports/                              # Rapports générés
│   └── newman/
│       ├── newman-wellwo-report.html     # Rapport HTML
│       ├── newman-wellwo-report.json     # Rapport JSON
│       └── junit-wellwo.xml              # Rapport JUnit
└── newman-config.json                    # Configuration Newman
```

## 🔧 Configuration de l'environnement

### Variables d'environnement

Le fichier `environments/local.postman_environment.json` contient :

- `base_url` : URL de base de l'API (http://localhost:1310)
- `auth_token` : Token JWT pour l'authentification
- `test_program_id` : ID de programme pour les tests
- `test_video_id` : ID de vidéo pour les tests
- `default_lang` : Langue par défaut (es)
- `e2e_program_id` : ID pour les tests end-to-end
- `e2e_video_id` : ID vidéo pour les tests end-to-end

### Génération du token JWT

Le script `setup-test-env.sh` génère automatiquement un token JWT valide :

```bash
./scripts/setup-test-env.sh
```

Si vous devez générer un token manuellement :

```bash
docker compose exec app_engage php artisan tinker
>>> use Tymon\JWTAuth\Facades\JWTAuth;
>>> $user = \App\Models\User::first();
>>> $token = JWTAuth::fromUser($user);
>>> echo $token;
```

## 🧪 Exécution des tests

### Tests complets

```bash
./scripts/run-tests.sh local
```

### Tests par dossier

```bash
# Tests de santé uniquement
./scripts/run-tests.sh local "Health Check"

# Tests des programmes
./scripts/run-tests.sh local "Programs"

# Tests des vidéos
./scripts/run-tests.sh local "Videos"

# Tests d'erreur
./scripts/run-tests.sh local "Error Scenarios"
```

### Options disponibles

- Premier argument : environnement (local, dev, staging, prod)
- Deuxième argument : dossier spécifique de la collection

## 📊 Rapports

Les rapports sont générés dans `reports/newman/` :

- **HTML** : Rapport visuel détaillé avec graphiques
- **JSON** : Données brutes pour traitement automatisé
- **JUnit XML** : Pour intégration CI/CD

Pour consulter le rapport HTML :

```bash
open reports/newman/newman-wellwo-report.html
```

## 🔍 Scénarios de test

### 1. Health Check
- Vérification de la disponibilité du service
- Test de performance (temps de réponse < 1000ms)
- Validation de la structure de réponse

### 2. Programs (Programmes)
- Liste des programmes
- Détails d'un programme
- Support multi-langues
- Validation des champs requis

### 3. Videos
- Liste des vidéos par programme
- Détails d'une vidéo
- Vérification des URLs de streaming
- Test de qualité HD

### 4. Error Scenarios
- 404 : Ressource non trouvée
- 422 : Paramètres invalides
- 401 : Non authentifié
- 500 : Erreur serveur

### 5. End-to-End
- Parcours utilisateur complet
- Tests de cache
- Performance multi-requêtes

## 🐛 Dépannage

### Erreur 401 : Token expiré ou invalide

```bash
# Régénérer le token
./scripts/setup-test-env.sh
```

### Erreur de connexion

```bash
# Vérifier que Docker est en cours d'exécution
docker compose ps

# Vérifier l'accès à l'API
curl http://localhost:1310/api/v1/health
```

### Newman non trouvé

```bash
# Installer Newman globalement
npm install -g newman newman-reporter-htmlextra
```

## 🔄 Intégration CI/CD

Pour utiliser dans un pipeline CI/CD :

```yaml
# Exemple GitLab CI
test:wellwo:
  script:
    - cd app/Integrations/Wellbeing/WellWo/postman
    - npm install newman newman-reporter-htmlextra
    - ./scripts/setup-test-env.sh
    - ./scripts/run-tests.sh local
  artifacts:
    paths:
      - app/Integrations/Wellbeing/WellWo/postman/reports/
    reports:
      junit: app/Integrations/Wellbeing/WellWo/postman/reports/newman/junit-wellwo.xml
```

## 📝 Notes

- Les tests utilisent l'API locale par défaut
- Le cache est testé avec des délais entre les requêtes
- Les tests multilingues vérifient es, en, fr, it, pt
- Les URLs CDN sont validées pour l'accessibilité