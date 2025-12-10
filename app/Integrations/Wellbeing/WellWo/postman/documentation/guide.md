# Guide d'utilisation WellWo API - Collection Postman

## 🎯 Vue d'ensemble

Cette collection Postman implémente **Phase 8 (POSTMAN)** de la méthodologie UP Engage pour l'intégration **WellWo Wellbeing API**. Elle fournit des tests automatisés complets, des scénarios End-to-End et un monitoring de production.

## 📁 Structure de la Collection

### 🔧 Variables de Collection
- `test_program_id`: ID de programme pour les tests (défaut: `kGc9MKOJOxBe`)
- `test_video_id`: ID de vidéo pour les tests (défaut: `32101`)
- `default_lang`: Langue par défaut (défaut: `es`)
- `supported_langs`: Langues supportées `["es", "en", "fr", "it", "pt", "ca", "mx"]`

### 📂 Dossiers Principaux

#### 1. **Health Check**
- **WellWo API Health Status**: Vérification de la santé de l'API
- Tests de connectivité et validation des services

#### 2. **Programs**
- **Programs List**: Récupération de la liste des programmes wellness
- **Program Details**: Détails d'un programme spécifique
- Tests de structure, cache, et performance

#### 3. **Videos**
- **Program Videos**: Vidéos d'un programme spécifique
- **Video Details**: Détails d'une vidéo wellness
- Validation des URLs CDN et formats HD

#### 4. **Error Scenarios**
- Tests des cas d'erreur (404, 422, 401, 500)
- Validation de la gestion d'erreurs

#### 5. **End-to-End Scenarios**
- **Complete Wellness Journey**: Parcours utilisateur complet
- **Multi-language Content Flow**: Tests de cohérence multilingue

#### 6. **Multi-Language Tests**
- Tests spécialisés pour validation multilingue
- Comparaison de contenus entre langues
- Rotation automatique des langues de test

## 🚀 Utilisation

### Prérequis
```bash
# Installation Newman
npm install -g newman newman-reporter-htmlextra

# Vérification
newman --version
```

### Exécution de Base
```bash
# Collection complète (depuis le dossier postman WellWo)
newman run ./WellWo-API.postman_collection.json \
  --environment ../../../../postman/environments/dev.postman_environment.json

# Dossier spécifique
newman run ./WellWo-API.postman_collection.json \
  --folder "Health Check" \
  --environment ../../../../postman/environments/dev.postman_environment.json
```

### Avec Configuration Newman
```bash
# Utilisation de la configuration prédéfinie
newman run --config-file ./newman-config.json

# Script automatisé
./scripts/run-tests.sh dev "Health Check"
```

## 🔍 Tests Automatisés

### Validation de Structure
```javascript
pm.test('WellWo response structure', () => {
    const response = pm.response.json();
    pm.expect(response).to.have.property('data');
    pm.expect(response).to.have.property('meta');
});
```

### Tests de Performance
```javascript
pm.test('Response time acceptable', () => {
    pm.expect(pm.response.responseTime).to.be.below(2000);
});
```

### Validation Multilingue
```javascript
pm.test('Multi-language content validation', () => {
    const currentLang = pm.request.url.query.get('lang');
    const content = pm.response.json().data;

    pm.expect(content.title).to.be.a('string');
    pm.expect(content.description).to.be.a('string');
});
```

### Tests CDN et Média
```javascript
pm.test('CDN URL validation', () => {
    const videoUrl = pm.response.json().data.video_url;
    const validCdnPatterns = [/cnt\\.wellwo\\.(es|net)/, /wellwo\\.(es|net)/];

    const isValidCdn = validCdnPatterns.some(pattern =>
        pattern.test(videoUrl)
    );

    pm.expect(isValidCdn).to.be.true;
});
```

## 📊 Monitoring et Alertes

### Configuration Monitoring
```bash
# Setup automatique du monitoring
./postman/scripts/setup-wellwo-monitoring.sh production

# Monitoring manuel
./postman/scripts/monitor-wellwo-health.sh
```

### Métriques Collectées
- **Response Time**: Temps de réponse par endpoint
- **Availability**: Disponibilité du service WellWo
- **Error Rate**: Taux d'erreur par environnement
- **Cache Performance**: Taux de hit cache
- **Language Coverage**: Couverture des tests multilingues

### Alertes Configurées
- ⚠️ **Warning**: Response time > 2000ms
- 🚨 **Critical**: Error rate > 5%
- 📢 **Notification**: 3 échecs consécutifs

## 🌍 Support Multilingue

### Langues Supportées
| Code | Langue | Statut |
|------|--------|---------|
| `es` | Español | ✅ Défaut |
| `en` | English | ✅ Testé |
| `fr` | Français | ✅ Testé |
| `it` | Italiano | ✅ Supporté |
| `pt` | Português | ✅ Supporté |
| `ca` | Català | ✅ Supporté |
| `mx` | Español (LATAM) | ✅ Supporté |

### Test de Rotation Automatique
```javascript
// Setup multi-language testing
const languages = ['es', 'en', 'fr', 'it', 'pt'];
const currentIndex = parseInt(pm.collectionVariables.get('lang_test_index') || '0');
const currentLang = languages[currentIndex % languages.length];

pm.collectionVariables.set('current_test_lang', currentLang);
```

## 📈 Métriques et Rapports

### Rapports Newman
- **CLI**: Sortie console avec résumé
- **JSON**: Rapport détaillé pour intégration CI
- **HTML**: Rapport visuel avec graphiques
- **JUnit**: Format pour systèmes de CI/CD

### Rapports de Monitoring
```bash
# Rapport quotidien
./postman/scripts/generate-wellwo-report.sh

# Localisation des rapports
ls -la ./reports/monitoring/
```

### Dashboard Métriques
- **Overview**: Vue d'ensemble performance WellWo
- **Details**: Métriques détaillées par endpoint
- **Languages**: Performance par langue
- **Alerts**: Historique des alertes

## 🔧 Configuration Avancée

### Variables d'Environnement
```json
{
  "SLACK_WEBHOOK_URL": "https://hooks.slack.com/...",
  "DISCORD_WEBHOOK_URL": "https://discord.com/api/webhooks/...",
  "SMTP_SERVER": "smtp.gmail.com",
  "SMTP_USERNAME": "monitoring@up-engage.com"
}
```

### Personnalisation des Tests
```javascript
// Test personnalisé pour contenu spécifique
pm.test('Custom wellness content validation', () => {
    const program = pm.response.json().data;

    // Validation métier spécifique
    pm.expect(program.category).to.be.oneOf(['meditation', 'fitness', 'nutrition']);
    pm.expect(program.difficulty_level).to.be.within(1, 5);
});
```

### Cache et Performance
```javascript
// Validation cache WellWo
pm.test('Cache headers present', () => {
    pm.expect(pm.response.headers.get('Cache-Control')).to.exist;
    pm.expect(pm.response.headers.get('X-Cache-Status')).to.exist;
});
```

## 🚨 Dépannage

### Problèmes Communs

#### Tests qui échouent
```bash
# Debug avec verbosité
newman run ./postman/WellWo-API.postman_collection.json \
  --verbose \
  --environment ./postman/environments/dev.postman_environment.json
```

#### Timeout de Requête
```javascript
// Augmenter le timeout dans les tests
setTimeout(() => {
    pm.expect(pm.response.code).to.equal(200);
}, 5000);
```

#### Problèmes de Cache
```bash
# Forcer le refresh du cache
curl -X POST "{{base_url}}/wellbeing/wellwo/cache/clear" \
  -H "Authorization: Bearer {{auth_token}}"
```

### Logs et Debug
```bash
# Logs de monitoring
tail -f ./postman/monitoring/logs/health.log

# Logs détaillés
tail -f ./postman/monitoring/logs/full.log
```

## 🔗 Intégration CI/CD

### GitHub Actions
```yaml
- name: Run WellWo API Tests
  run: |
    newman run ./postman/WellWo-API.postman_collection.json \
      --environment ./postman/environments/staging.postman_environment.json \
      --reporters cli,junit \
      --reporter-junit-export ./reports/newman/junit-wellwo.xml
```

### GitLab CI
```yaml
wellwo-api-tests:
  script:
    - ./postman/scripts/run-wellwo-tests.sh staging
  artifacts:
    reports:
      junit: reports/newman/junit-wellwo.xml
```

## 📚 Ressources Supplémentaires

### Documentation WellWo
- [API WellWo Documentation](https://wellwo.net/api/docs)
- [CDN Content Guidelines](https://cnt.wellwo.es/docs)

### UP Engage Resources
- [Méthodologie Phase 8](./workflows/methodology/)
- [Templates Postman](./postman/templates/)
- [Guide Newman](./postman/scripts/)

### Support
- **Slack**: `#api-monitoring`
- **Email**: `devops@up-engage.com`
- **Issues**: Créer un ticket sur le repository

---

**Generated by UP Engage Team - Phase 8 (POSTMAN) Implementation**
*Last updated: 2025-08-08*