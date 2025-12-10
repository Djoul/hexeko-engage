# Guide de Workflow pour Epics

## 🎯 Vue d'Ensemble

Ce guide détaille la gestion des Epics Jira - des fonctionnalités majeures composées de plusieurs stories interconnectées. Un Epic nécessite une approche coordonnée avec une vision architecturale globale.

## 📊 Caractéristiques d'un Epic

### Définition
- **Fonctionnalité majeure** découpée en plusieurs stories
- **Architecture commune** partagée entre les stories
- **Dépendances complexes** entre les composants
- **Livraison progressive** story par story

### Exemples Typiques
- Système d'authentification complet (login, MFA, SSO)
- Module de paiement (Stripe, balance, refunds)
- Intégration tierce complète (API, webhooks, sync)
- Refactoring majeur d'un module

## 🔄 Workflow Epic Spécifique

### Phase 0: EPIC DISCOVERY
```yaml
Actions:
  1. Récupération:
     - Fetch epic depuis Jira via MCP
     - Extraire toutes les stories liées
     - Identifier les dépendances externes
  
  2. Analyse Globale:
     - Vue d'ensemble fonctionnelle
     - Objectifs business
     - Critères de succès epic
  
  3. Décomposition:
     - Ordre d'implémentation des stories
     - Matrice de dépendances
     - Planning prévisionnel

Livrables:
  - /todos/epics/[EPIC-KEY]/
    - epic-overview.md
    - dependency-matrix.md
    - implementation-plan.md
```

### Phase 1: EPIC ARCHITECTURE
```yaml
Actions:
  1. Design Global:
     - Architecture technique commune
     - Patterns à utiliser
     - Standards de code
  
  2. Ressources Partagées:
     - Services communs
     - DTOs partagés
     - Interfaces communes
  
  3. Infrastructure:
     - Base de données
     - Cache strategy
     - Configuration

Livrables:
  - /todos/epics/[EPIC-KEY]/
    - architecture.md
    - shared-components.md
    - database-schema.md
```

### Phase 2: STORY IMPLEMENTATION
```yaml
Pour chaque story:
  1. Story Analysis:
     - Context de l'epic
     - Dépendances spécifiques
     - Critères d'acceptation
  
  2. Story Development:
     - TDD si applicable
     - Implementation
     - Validation
  
  3. Integration Testing:
     - Tests avec autres stories
     - Validation de l'architecture
     - Non-régression

Structure:
  - /todos/epics/[EPIC-KEY]/stories/
    - [STORY-1-KEY]/
      - analysis.md
      - tdd-plan.md
      - implementation.md
    - [STORY-2-KEY]/
      - ...
```

### Phase 3: EPIC VALIDATION
```yaml
Actions:
  1. Tests End-to-End:
     - Scénarios complets
     - Performance globale
     - Security audit
  
  2. Documentation Consolidée:
     - Guide complet du module
     - API documentation
     - Migration guide si applicable
  
  3. Deployment Strategy:
     - Feature flags
     - Rollout progressif
     - Rollback plan

Livrables:
  - /todos/epics/[EPIC-KEY]/
    - e2e-tests.md
    - deployment-plan.md
    - documentation/
```

## 📋 Gestion des Dépendances

### Matrice de Dépendances
```markdown
| Story | Dépend de | Bloque | Parallélisable | Priorité |
|-------|-----------|--------|----------------|----------|
| UE-201 | - | UE-202, UE-203 | Non | P0 |
| UE-202 | UE-201 | UE-204 | Oui avec UE-203 | P1 |
| UE-203 | UE-201 | - | Oui avec UE-202 | P1 |
| UE-204 | UE-202 | - | Non | P2 |
```

### Gestion des Blocages
```yaml
Stratégies:
  1. Identification Précoce:
     - Analyse lors de l'Epic Discovery
     - Alertes automatiques Jira
  
  2. Mitigation:
     - Mocks pour débloquer
     - Développement en parallèle
     - Réorganisation si nécessaire
  
  3. Communication:
     - Daily updates
     - Blockers board
     - Escalation rapide
```

## 🤖 Automatisation Epic avec MCP

### Jira Operations
```bash
# Récupérer l'epic et ses stories
./workflows/scripts/epic-fetch.sh [EPIC-KEY]

# Créer la structure de dossiers
./workflows/scripts/epic-init.sh [EPIC-KEY]

# Mettre à jour le progrès
./workflows/scripts/epic-update.sh [EPIC-KEY]

# Générer le dashboard
./workflows/scripts/epic-dashboard.sh [EPIC-KEY]
```

### TodoWrite Epic Structure
```markdown
## EPIC: [KEY] - [Title]
Total Stories: 8 | Completed: 3 | In Progress: 2 | Blocked: 1

### 📊 Progress Overview
[████████░░░░░░░░░░░░] 40%

### 🏃 Active Stories

#### [STORY-1] - Authentication Base
- [x] Analysis complete
- [x] TDD tests written
- [x] Implementation done
- [ ] Integration tests
Status: IN_PROGRESS | Blockers: None

#### [STORY-2] - MFA Implementation
- [x] Analysis complete
- [ ] TDD tests written
- [ ] Implementation
- [ ] Integration tests
Status: BLOCKED | Blockers: Waiting for STORY-1 completion

### ✅ Completed Stories
- [STORY-0] Database Schema - 100%

### 📋 Upcoming Stories
- [STORY-3] SSO Integration
- [STORY-4] Password Recovery
- [STORY-5] Session Management
```

## 📊 Dashboard et Reporting

### Epic Dashboard Template
```markdown
# Epic Dashboard: [EPIC-KEY] - [Title]

## Executive Summary
Brief description of the epic's goal and current status.

## Progress Metrics
- **Overall Progress**: 40% (3/8 stories)
- **Current Sprint**: 2 stories in progress
- **Velocity**: 1.5 stories/sprint
- **Projected Completion**: Sprint 12

## Story Status
| Story | Status | Progress | Assignee | Notes |
|-------|--------|----------|----------|-------|
| Auth Base | In Progress | 75% | Dev1 | On track |
| MFA | Blocked | 25% | Dev2 | Waiting for Auth |
| SSO | Not Started | 0% | - | Next sprint |

## Risks & Blockers
1. **[HIGH]** MFA blocked by Auth Base completion
2. **[MEDIUM]** SSO vendor documentation incomplete

## Architecture Decisions
- Decision 1: Use Redis for session storage
- Decision 2: Implement JWT with refresh tokens

## Next Steps
1. Complete Auth Base integration tests
2. Unblock MFA development
3. Start SSO vendor research
```

## 🔄 Cycle de Vie d'un Epic

### 1. Initiation
- PM crée l'epic dans Jira
- Breaking down en stories
- Prioritisation initiale

### 2. Planning
- Architecture workshop
- Dependency mapping
- Sprint planning

### 3. Execution
- Story par story
- Integration continue
- Tests progressifs

### 4. Validation
- Tests end-to-end
- Performance validation
- Security review

### 5. Deployment
- Feature flags
- Progressive rollout
- Monitoring

### 6. Closure
- Documentation finale
- Retrospective
- Metrics analysis

## 🎯 Best Practices Epic

### DO's ✅
- **Maintenir** une vision globale constante
- **Communiquer** régulièrement sur le progrès
- **Tester** l'intégration après chaque story
- **Documenter** l'architecture dès le début
- **Paralléliser** quand possible

### DON'Ts ❌
- **Ne pas** commencer sans architecture claire
- **Ne pas** ignorer les dépendances
- **Ne pas** reporter les tests d'intégration
- **Ne pas** modifier l'architecture sans consensus
- **Ne pas** livrer sans documentation complète

## 📚 Templates Epic

### Epic Analysis Template
Voir : `/workflows/templates/epic-analysis-template.md`

### Dependency Matrix Template
Voir : `/workflows/templates/dependency-matrix-template.md`

### Epic Dashboard Template
Voir : `/workflows/templates/epic-dashboard-template.md`

## 🔧 Scripts Spécifiques Epic

```bash
# Initialisation complète d'un epic
./workflows/scripts/epic-init.sh [EPIC-KEY]
# → Crée structure, fetch stories, génère matrices

# Analyse des dépendances
./workflows/scripts/epic-dependencies.sh [EPIC-KEY]
# → Génère graphe de dépendances, identifie critical path

# Progress report
./workflows/scripts/epic-report.sh [EPIC-KEY]
# → Génère rapport complet, publie sur Confluence

# Story picker
./workflows/scripts/epic-next-story.sh [EPIC-KEY]
# → Suggère la prochaine story à implémenter
```

## 📈 Métriques Epic

### KPIs Suivis
- **Velocity** : Stories complétées par sprint
- **Cycle Time** : Temps moyen par story
- **Blockers** : Nombre et durée des blocages
- **Integration Failures** : Taux d'échec des tests d'intégration
- **Documentation Coverage** : % de stories documentées

### Reporting Automatique
- Dashboard Jira temps réel
- Rapport hebdomadaire Confluence
- Alertes Slack sur blocages
- Métriques Grafana

## 🚨 Gestion des Problèmes

### Epic Trop Large
- Identifier les sous-epics possibles
- Créer des milestones intermédiaires
- Livrer par phases

### Dépendances Circulaires
- Revoir l'architecture
- Introduire des interfaces
- Découpler les composants

### Retards Accumulés
- Réduire le scope
- Prioriser le MVP
- Négocier les deadlines

## 🎓 Exemple Concret : Module Paiement

```yaml
Epic: UE-200 - Système de Paiement Complet

Stories:
  UE-201: Infrastructure Stripe
  UE-202: Paiement par carte
  UE-203: Gestion de la balance
  UE-204: Paiement mixte
  UE-205: Remboursements
  UE-206: Webhooks Stripe
  UE-207: Reporting financier
  UE-208: Tests E2E

Dépendances:
  - UE-201 → Tous
  - UE-202 → UE-204, UE-205
  - UE-203 → UE-204
  - UE-206 → UE-205

Architecture:
  - Services: StripeService, BalanceService, PaymentService
  - Actions: ProcessPaymentAction, RefundAction
  - Events: PaymentProcessed, RefundIssued
  - Jobs: SyncStripeWebhooks, GenerateReports
```

## 📖 Ressources Complémentaires

- [backend-api-methodology.md](backend-api-methodology.md) : Méthodologie générale
- [story-workflow-guide.md](story-workflow-guide.md) : Guide pour stories isolées
- [mcp-integration-guide.md](mcp-integration-guide.md) : Intégration MCP détaillée
- Exemple WellWo : `/todos/implementation-wellwo-proxy-api.md`

---

*Guide Epic Workflow v1.0*
*Optimisé pour les développements complexes multi-stories*