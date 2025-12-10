# Guide de Workflow pour Stories

## 🎯 Vue d'Ensemble

Ce guide détaille la gestion des Stories Jira - des fonctionnalités ou tâches individuelles qui peuvent être développées de manière autonome ou faire partie d'un Epic plus large.

## 📋 Caractéristiques d'une Story

### Définition
- **Unité de travail** avec valeur business définie
- **Scope limité** et livrable indépendamment  
- **Critères d'acceptation** clairs et mesurables
- **Testable** de manière isolée

### Types de Stories
```yaml
Standalone Story:
  - Fonctionnalité indépendante
  - Architecture autonome
  - Pas de dépendances externes

Story dans Epic:
  - Partie d'un ensemble plus large
  - Hérite de l'architecture Epic
  - Dépendances avec autres stories

Bug Story:
  - Correction d'un problème
  - Inclut test de non-régression
  - Documentation du fix

Technical Story:
  - Amélioration technique
  - Refactoring
  - Dette technique
```

## 🔄 Workflow Story Standard

### Phase 0: STORY DISCOVERY
```yaml
Actions:
  1. Récupération:
     - Fetch story depuis Jira via MCP
     - Vérifier si partie d'un Epic
     - Extraire critères d'acceptation
     - Identifier les pièces jointes
  
  2. Contexte:
     - Si dans Epic : récupérer architecture
     - Si standalone : définir scope
     - Identifier stakeholders
  
  3. Classification:
     - Complexité (Simple/Standard/Complex)
     - Profil adapté
     - Phases nécessaires

Livrables:
  - /todos/stories/[STORY-KEY]/
    - story-brief.md
    - acceptance-criteria.md
```

### Phase 1: STORY ANALYSIS [OPTIONAL]
```yaml
Skip si:
  - Solution technique évidente
  - Bug fix simple
  - Story très bien définie

Actions:
  1. Analyse Technique:
     - Code existant impacté
     - Dépendances à modifier
     - Patterns à utiliser
  
  2. Recherche Documentation:
     - Context7 pour librairies
     - Firecrawl pour APIs tierces
     - Documentation interne
  
  3. Évaluation:
     - Complexité réelle
     - Risques identifiés
     - Approches possibles

Livrables:
  - /todos/stories/[STORY-KEY]/
    - technical-analysis.md
    - risks-assessment.md
```

### Phase 2: STORY DESIGN [OPTIONAL]
```yaml
Skip si:
  - CRUD basique
  - Modification mineure
  - Architecture déjà définie (Epic)

Actions:
  1. Architecture:
     - Services nécessaires
     - Actions à créer
     - DTOs et validations
  
  2. API Design:
     - Endpoints RESTful
     - Request/Response format
     - Error handling
  
  3. Database:
     - Migrations nécessaires
     - Indexes optimisation
     - Cache strategy

Livrables:
  - /todos/stories/[STORY-KEY]/
    - architecture.md
    - api-design.md
    - database-changes.md
```

### Phase 3: TDD [RECOMMENDED]
```yaml
Skip si:
  - Hotfix urgent uniquement

Actions:
  1. Test Strategy:
     - Identifier les comportements
     - Définir les cas limites
     - Prévoir les erreurs
  
  2. Write Tests:
     - Tests unitaires d'abord
     - Tests d'intégration
     - Tests de validation API
  
  3. Mock Setup:
     - Services externes
     - Données de test
     - Fixtures

Livrables:
  - /todos/stories/[STORY-KEY]/
    - tdd-plan.md
    - test-scenarios.md
```

### Phase 4: IMPLEMENTATION [REQUIRED]
```yaml
Actions:
  1. RED Phase:
     - Run tests → doivent échouer
     - Confirmer les expectations
  
  2. GREEN Phase:
     - Code minimal pour passer
     - Focus sur fonctionnalité
     - Pas d'optimisation
  
  3. REFACTOR Phase:
     - Améliorer la qualité
     - Appliquer les patterns
     - Optimiser performance

Patterns:
  - Service/Action pattern
  - DTOs pour data transfer
  - FormRequests pour validation
  - Cache Redis obligatoire

Livrables:
  - Code source
  - Tests passants
  - /todos/stories/[STORY-KEY]/
    - implementation-notes.md
```

### Phase 5: VALIDATION [REQUIRED]
```yaml
Actions:
  1. Quality Checks:
     - make test → 100% pass
     - make quality-check → 0 erreurs
     - PHPStan level max
  
  2. Performance:
     - Response time < 500ms
     - Query optimization
     - Cache effectiveness
  
  3. Security:
     - Input validation
     - Authorization checks
     - SQL injection prevention

Livrables:
  - Test reports
  - Coverage report
  - Performance metrics
```

### Phase 6: DOCUMENTATION [RECOMMENDED]
```yaml
Skip si:
  - Documentation auto-générée suffisante
  - Fix mineur

Actions:
  1. API Documentation:
     - OpenAPI/Swagger specs
     - Examples requests/responses
     - Error codes
  
  2. Frontend Guide:
     - Endpoints disponibles
     - Authentication required
     - Response formats
  
  3. Confluence:
     - Publier via MCP
     - Lier à la story Jira
     - Ajouter aux guides existants

Livrables:
  - /todos/stories/[STORY-KEY]/
    - api-documentation.md
    - frontend-guide.md
  - Publication Confluence
```

## 📊 Profils Story Prédéfinis

### 🔧 Story Simple (CRUD)
```yaml
profile: story-simple
phases:
  - implementation
  - validation
example: "Ajouter un champ à un modèle"
```

### 📦 Story Standard
```yaml
profile: story-standard  
phases:
  - analysis
  - tdd
  - implementation
  - validation
  - documentation
example: "Nouvelle API endpoint avec logique métier"
```

### 🏗️ Story Complex
```yaml
profile: story-complex
phases:
  - analysis
  - design
  - tdd
  - implementation
  - validation
  - documentation
example: "Intégration système de paiement"
```

### 🐛 Bug Story
```yaml
profile: bug-story
phases:
  - analysis
  - implementation
  - validation
example: "Corriger erreur 500 sur endpoint"
```

### 🔨 Technical Story
```yaml
profile: technical-story
phases:
  - analysis
  - design
  - implementation
  - validation
example: "Refactoring service legacy"
```

## 🤖 Automatisation Story avec MCP

### Commandes Jira
```bash
# Récupérer une story
./workflows/scripts/story-fetch.sh [STORY-KEY]

# Initialiser workspace
./workflows/scripts/story-init.sh [STORY-KEY]

# Mettre à jour status
./workflows/scripts/story-update.sh [STORY-KEY] [STATUS]

# Créer sous-tâches
./workflows/scripts/story-subtasks.sh [STORY-KEY]
```

### TodoWrite Story Structure
```markdown
## STORY: [KEY] - [Title]
Type: Feature | Epic: [EPIC-KEY] (si applicable)
Status: IN_PROGRESS

### 📋 Acceptance Criteria
- [ ] Critère 1
- [ ] Critère 2
- [ ] Critère 3

### 🔄 Phases Progress

#### ANALYSIS ✅
- [x] Technical analysis complete
- [x] Risks identified
- [x] Approach validated

#### TDD 🔄
- [x] Unit tests written
- [x] Integration tests written
- [ ] E2E tests written

#### IMPLEMENTATION 📝
- [ ] Service layer
- [ ] Action layer
- [ ] API endpoint
- [ ] Cache implementation

#### VALIDATION ⏳
- [ ] make test
- [ ] make quality-check
- [ ] Performance tests

#### DOCUMENTATION 📚
- [ ] API docs
- [ ] Frontend guide
- [ ] Confluence published
```

## 🎯 Best Practices Story

### DO's ✅
- **Clarifier** les critères d'acceptation avant de commencer
- **Vérifier** si la story fait partie d'un Epic
- **Réutiliser** l'architecture Epic si applicable
- **Tester** chaque comportement défini
- **Documenter** les décisions importantes

### DON'Ts ❌
- **Ne pas** commencer sans critères clairs
- **Ne pas** ignorer le contexte Epic
- **Ne pas** skip les tests sauf urgence
- **Ne pas** oublier la validation
- **Ne pas** merger sans documentation

## 📋 Story dans un Epic

### Héritage Epic
```yaml
Éléments hérités:
  - Architecture globale
  - Patterns définis
  - Services partagés
  - Standards de code

Éléments spécifiques:
  - Logique métier
  - Tests propres
  - Documentation API
```

### Coordination
```yaml
Avant de commencer:
  - Vérifier les dépendances
  - Consulter l'architecture Epic
  - Identifier les impacts

Pendant le développement:
  - Communiquer les blocages
  - Tester l'intégration
  - Maintenir la cohérence

Après completion:
  - Mettre à jour Epic dashboard
  - Vérifier non-régression
  - Documenter les changements
```

## 🔧 Scripts Spécifiques Story

```bash
# Analyse automatique de complexité
./workflows/scripts/story-analyze.sh [STORY-KEY]
# → Suggère le profil approprié

# Génération de tests
./workflows/scripts/story-generate-tests.sh [STORY-KEY]
# → Crée structure de tests depuis les critères

# Validation pré-merge
./workflows/scripts/story-validate.sh [STORY-KEY]
# → Vérifie tous les quality gates

# Documentation auto
./workflows/scripts/story-document.sh [STORY-KEY]
# → Génère et publie la documentation
```

## 📈 Métriques Story

### KPIs Suivis
- **Cycle Time** : Discovery → Deployment
- **Test Coverage** : % de code couvert
- **Defect Rate** : Bugs post-deployment
- **Documentation** : Complétude

### Quality Metrics
```yaml
Minimum Requirements:
  - Test Coverage: 80%
  - PHPStan: 0 errors
  - Response Time: < 500ms
  - Documentation: API specs minimum

Excellence Targets:
  - Test Coverage: 95%+
  - Performance: < 200ms
  - Documentation: Complete with examples
```

## 🚨 Troubleshooting

### Story Bloquée
```yaml
Causes communes:
  - Dépendances non résolues
  - Specs peu claires
  - Architecture non définie

Solutions:
  - Escalader au PM
  - Clarifier avec stakeholders
  - Consulter Epic owner
```

### Tests Échouent
```yaml
Diagnostic:
  - Vérifier les mocks
  - Database état
  - Cache pollution

Actions:
  - Reset test environment
  - Update fixtures
  - Clear cache
```

### Performance Issues
```yaml
Analyse:
  - Profiler queries
  - Check N+1 problems
  - Cache effectiveness

Optimisation:
  - Add indexes
  - Implement eager loading
  - Optimize cache strategy
```

## 🎓 Exemple Concret : API Merchants

```yaml
Story: UE-204 - API listing des merchants

Profile: story-standard

Phases exécutées:
  1. ANALYSIS:
     - Étude API Amilon
     - Pattern de cache identifié
  
  2. TDD:
     - 5 tests unitaires
     - 3 tests d'intégration
     - Tests de cache
  
  3. IMPLEMENTATION:
     - AmilonMerchantService
     - GetMerchantsAction
     - MerchantController
     - Cache Redis 5min
  
  4. VALIDATION:
     - Coverage: 92%
     - Response: 230ms
     - PHPStan: clean
  
  5. DOCUMENTATION:
     - API guide créé
     - Confluence publié
     - Frontend notifié

Résultat: Livré en production avec succès
```

## 📖 Ressources Complémentaires

- [backend-api-methodology.md](backend-api-methodology.md) : Méthodologie complète
- [epic-workflow-guide.md](epic-workflow-guide.md) : Guide pour Epics
- [entry-points-guide.md](entry-points-guide.md) : Tous les points d'entrée
- Templates : `/workflows/templates/`

---

*Guide Story Workflow v1.0*
*Optimisé pour le développement agile de fonctionnalités*