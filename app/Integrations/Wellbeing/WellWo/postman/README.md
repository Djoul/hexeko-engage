# WellWo API - Collection Postman Complète

> **Phase 8 (POSTMAN)** - Implémentation méthodologie UP Engage
> Collection spécialisée pour l'intégration WellWo wellness/wellbeing

## 🎯 Aperçu Rapide

Cette collection implémente **60+ tests automatisés** pour l'API WellWo avec support multilingue, monitoring de production et scénarios End-to-End complets.

### ✨ Fonctionnalités Clés

- ✅ **Tests automatisés complets** (Health, Programs, Videos, Errors, E2E)
- 🌍 **Support multilingue** (7 langues supportées)
- 📊 **Monitoring continu** avec alertes Slack/Email
- 🔄 **Intégration CI/CD** (GitHub Actions, GitLab CI)
- 📈 **Rapports détaillés** (HTML, JSON, JUnit)
- ⚡ **Performance optimisée** avec cache TTL 5min

## 🚀 Quick Start

### Installation

```bash
npm install -g newman newman-reporter-htmlextra
```

### Exécution Rapide
```bash
# Test complet (depuis la racine du projet)
./app/Integrations/wellbeing/WellWo/postman/scripts/run-tests.sh dev

# Ou depuis le dossier postman WellWo
cd app/Integrations/wellbeing/WellWo/postman
./scripts/run-tests.sh dev

# Health Check seulement
newman run ./WellWo-API.postman_collection.json \
  --folder "Health Check" \
  --environment ../../../../postman/environments/dev.postman_environment.json
```

### Configuration Monitoring

```bash
# Setup automatique (depuis le dossier postman WellWo)
cd app/Integrations/wellbeing/WellWo/postman
./scripts/setup-monitoring.sh production
```

## 📁 Structure de la Collection

```
WellWo-API.postman_collection.json
├── 🏥 Health Check (3 tests)
├── 📋 Programs (8 tests + cache validation)
├── 🎥 Videos (6 tests + CDN validation)
├── ❌ Error Scenarios (8 tests d'erreurs)
├── 🔄 End-to-End Scenarios (2 parcours complets)
└── 🌍 Multi-Language Tests (4 tests multilingues)

Total: 60+ tests, 200+ assertions
```

## 🌍 Langues Supportées

| Code   | Langue         | Statut       | Tests    |
| ------ | -------------- | ------------ | -------- |
| `es` | Español       | ✅ Défaut   | Complets |
| `en` | English        | ✅ Testé    | Complets |
| `fr` | Français      | ✅ Testé    | Complets |
| `it` | Italiano       | ✅ Supporté | Basiques |
| `pt` | Português     | ✅ Supporté | Basiques |
| `ca` | Català        | ✅ Supporté | Basiques |
| `mx` | Español LATAM | ✅ Supporté | Basiques |

## 📊 Tests et Validations

### Types de Tests

- **Structure API**: Validation des réponses JSON
- **Performance**: Temps de réponse < 2000ms
- **Cache**: TTL 5min, hit rate validation
- **CDN**: URLs WellWo cnt.wellwo.es/net
- **Multilingue**: Cohérence contenu entre langues
- **Erreurs**: Gestion 404, 422, 401, 500
- **E2E**: Parcours utilisateur complets

### Exemples de Validations

```javascript
// Structure Response
pm.test('WellWo response structure', () => {
    const response = pm.response.json();
    pm.expect(response).to.have.property('data');
    pm.expect(response.meta).to.have.property('cache_status');
});

// Performance
pm.test('Response time acceptable', () => {
    pm.expect(pm.response.responseTime).to.be.below(2000);
});

// CDN Validation
pm.test('CDN URL valid', () => {
    const videoUrl = pm.response.json().data.video_url;
    pm.expect(videoUrl).to.match(/cnt\.wellwo\.(es|net)/);
});
```

## 🔧 Configuration

### Variables Collection

```json
{
  "test_program_id": "kGc9MKOJOxBe",
  "test_video_id": "32101",
  "default_lang": "es",
  "supported_langs": "[\"es\", \"en\", \"fr\", \"it\", \"pt\", \"ca\", \"mx\"]"
}
```

### Environnements

- **dev**: `http://localhost:1310/api/v1`
- **staging**: `https://staging-api.up-engage.com/api/v1`
- **production**: `https://api.up-engage.com/api/v1`

### Configuration Newman

```bash
# Avec fichier de config (depuis le dossier postman WellWo)
newman run --config-file ./newman-config.json

# Configuration personnalisée
newman run ./WellWo-API.postman_collection.json \
  --environment ../../../../postman/environments/production.postman_environment.json \
  --timeout 10000 \
  --delay-request 100 \
  --reporters cli,json,htmlextra
```

## 📈 Monitoring et Alertes

### Monitoring Continu

```bash
# Health Check toutes les 5min
*/5 * * * * cd /path/to/project/app/Integrations/wellbeing/WellWo/postman && ./scripts/monitor-health.sh

# Monitoring complet toutes les heures
0 * * * * cd /path/to/project/app/Integrations/wellbeing/WellWo/postman && ./scripts/run-monitoring.sh production

# Rapport quotidien à 8h00
0 8 * * * cd /path/to/project/app/Integrations/wellbeing/WellWo/postman && ./scripts/generate-reports.sh
```

### Métriques Collectées

- **Response Time**: P50, P95, P99 par endpoint
- **Availability**: Disponibilité % par environnement
- **Error Rate**: Taux d'erreur par type/code
- **Cache Performance**: Hit rate par type de contenu
- **Language Coverage**: Tests par langue

### Alertes Configurées

| Condition              | Seuil    | Action                 |
| ---------------------- | -------- | ---------------------- |
| Response Time          | > 2000ms | Slack Warning          |
| Error Rate             | > 5%     | Slack + Email Critical |
| Availability           | < 99.5%  | PagerDuty Alert        |
| 3 Échecs Consécutifs | -        | Escalade équipe       |

## 🔄 Intégration CI/CD

### GitHub Actions

```yaml
name: WellWo API Tests
on: [push, pull_request]
jobs:
  wellwo-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup Newman
        run: npm install -g newman newman-reporter-htmlextra
      - name: Run WellWo Tests
        run: ./app/Integrations/wellbeing/WellWo/postman/scripts/run-tests.sh staging
      - name: Upload Reports
        uses: actions/upload-artifact@v4
        with:
          name: newman-reports
          path: reports/newman/
```

### GitLab CI

```yaml
wellwo-api-tests:
  stage: test
  script:
    - ./app/Integrations/wellbeing/WellWo/postman/scripts/run-tests.sh staging
  artifacts:
    reports:
      junit: reports/newman/junit-wellwo.xml
    paths:
      - reports/newman/
```

### Jenkins Pipeline

```groovy
stage('WellWo API Tests') {
    steps {
        sh './app/Integrations/wellbeing/WellWo/postman/scripts/run-tests.sh staging'
    }
    post {
        always {
            publishHTML([
                allowMissing: false,
                alwaysLinkToLastBuild: true,
                keepAll: true,
                reportDir: 'reports/newman',
                reportFiles: '*.html',
                reportName: 'WellWo API Report'
            ])
        }
    }
}
```

## 📋 Utilisation Avancée

### Tests Spécifiques

```bash
# Test d'une langue spécifique
newman run ./WellWo-API.postman_collection.json \
  --env-var "current_test_lang=fr" \
  --folder "Multi-Language Tests"

# Test de performance avec charge
newman run ./WellWo-API.postman_collection.json \
  --iteration-count 10 \
  --delay-request 100

# Debug avec verbosité
newman run ./WellWo-API.postman_collection.json \
  --verbose \
  --folder "Programs"
```

### Personnalisation des Tests

```javascript
// Override dans Pre-request Script
pm.collectionVariables.set('test_program_id', 'your-custom-id');
pm.collectionVariables.set('current_test_lang', 'it');

// Test personnalisé
pm.test('Custom wellness validation', () => {
    const program = pm.response.json().data;
    pm.expect(program.wellness_score).to.be.above(7);
    pm.expect(program.categories).to.include('meditation');
});
```

## 🛠️ Dépannage

### Problèmes Fréquents

**Tests qui échouent**

```bash
# Vérifier la connectivité
curl -I http://localhost:1310/api/v1/wellbeing/wellwo/health

# Debug détaillé
newman run ./WellWo-API.postman_collection.json --verbose
```

**Timeouts**

```bash
# Augmenter les timeouts
newman run ./WellWo-API.postman_collection.json --timeout 30000
```

**Cache Issues**

```bash
# Vider le cache WellWo
curl -X POST "{{base_url}}/wellbeing/wellwo/cache/clear" \
  -H "Authorization: Bearer {{auth_token}}"
```

### Logs et Debug

```bash
# Logs monitoring
tail -f ./monitoring/logs/health.log

# Rapports détaillés
ls -la ./reports/monitoring/
```

## 📚 Documentation et Ressources

### Fichiers Clés

- 📄 **Collection**: `./WellWo-API.postman_collection.json`
- ⚙️ **Config Newman**: `./newman-config.json`
- 📊 **Monitoring**: `./monitoring/config.json`
- 📖 **Guide complet**: `./documentation/guide.md`
- 💡 **Exemples**: `./examples/usage-examples.json`

### Scripts Utilitaires

- 🧪 `run-tests.sh` - Exécution tests
- 💓 `monitor-health.sh` - Health check continu
- 📈 `generate-reports.sh` - Rapport quotidien
- ⚙️ `setup-monitoring.sh` - Configuration monitoring

### Ressources Externes

- [Documentation WellWo API](https://wellwo.net/api/docs)
- [Guide Newman](https://learning.postman.com/docs/running-collections/using-newman-cli/)
- [UP Engage Methodology](../../../../workflows/methodology/)

## 🤝 Support et Contribution

### Contact

- **Slack**: `#api-monitoring`
- **Email**: `devops@up-engage.com`
- **Issues**: Repository GitLab

### Méthodologie

Cette collection suit la **Phase 8 (POSTMAN)** de la méthodologie UP Engage avec :

- ✅ Tests automatisés obligatoires
- ✅ Newman en CI/CD systématique
- ✅ Monitoring production continu
- ✅ Documentation living complète

---

**🎯 Objectif**: Garantir la qualité et fiabilité de l'intégration WellWo dans UP Engage API

*Generated by UP Engage Team - Version 1.0.0 - Last updated: 2025-08-08*
