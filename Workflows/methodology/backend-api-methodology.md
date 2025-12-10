# Méthodologie de Développement Backend API RESTful

## 📌 Vue d'Ensemble

Cette méthodologie fournit un framework structuré et flexible pour le développement de fonctionnalités backend API RESTful. Elle s'adapte à différents points d'entrée (Epic, Story, Bug, Task) et permet le bypass intelligent de phases selon les besoins.

## 🎯 Principes Fondamentaux

### Flexibilité
- **Phases optionnelles** : Chaque phase peut être activée ou bypassée
- **Points d'entrée multiples** : Epic, Story, Bug, Task, Local
- **Profils adaptables** : Du hotfix minimal au développement complet

### Qualité
- **TDD par défaut** : Tests avant le code (sauf urgence)
- **Validation obligatoire** : Quality gates non négociables
- **Documentation automatique** : Générée depuis le code

### Traçabilité
- **Intégration Jira** : Suivi automatique du progrès
- **TodoWrite** : Tracking local des tâches
- **Historique complet** : Toutes les décisions documentées

## 🔄 Les 9 Phases du Développement

### Phase 0: ENTRY POINT [OBLIGATOIRE]
Détermination du contexte et sélection du workflow approprié.

### Phase 1: DISCOVERY [OPTIONNELLE]
- **Objectif** : Comprendre le besoin et récupérer les informations
- **Skip si** : Tâche déjà claire, bug fix simple
- **Actions** :
  - Récupération depuis Jira/Todoist/Sentry
  - Analyse des critères d'acceptation
  - Identification des parties prenantes

### Phase 2: ANALYSIS [OPTIONNELLE]
- **Objectif** : Analyser techniquement la solution
- **Skip si** : Solution évidente, correction mineure
- **Actions** :
  - Analyse du code existant
  - Identification des dépendances
  - Évaluation des risques
  - Documentation des contraintes

### Phase 3: DESIGN [OPTIONNELLE]
- **Objectif** : Concevoir l'architecture de la solution
- **Skip si** : CRUD simple, modification mineure
- **Actions** :
  - Architecture des composants
  - Définition des APIs
  - Schémas de données
  - Diagrammes de flux

### Phase 4: TDD [RECOMMANDÉE]
- **Objectif** : Définir les comportements attendus via tests
- **Skip si** : Hotfix urgent (déconseillé)
- **Actions** :
  - Stratégie de tests
  - Écriture des tests unitaires
  - Tests d'intégration
  - Définition des mocks

### Phase 5: TASKS / PLAN D'EXÉCUTION [OBLIGATOIRE]
- **Objectif** : Générer une liste de tâches exécutable et traçable
- **Actions** :
  - Génération du fichier `planned_task.md` (checklist détaillée et résumable)
  - Utiliser le profil courant pour pré-remplir via `todo_structure`
  - Ajouter métadonnées: clé Jira, profil, date, phase courante
  - Définir l'ordre d'exécution et les dépendances
  - Règle: chaque tâche marquée "[x]" quand terminée; reprise à la prochaine case non cochée
- **Scripts** :
  - `./workflows/scripts/generate-tasks.sh [--profile=feature-standard] [--output=./planned_task.md]`
  - Fallback sur `Workflows/templates/tasks-template.md` si profil non fourni

### Phase 6: IMPLEMENTATION [OBLIGATOIRE]
- **Objectif** : Développer la fonctionnalité
- **Actions** :
  - Cycle RED-GREEN-REFACTOR
  - Application des patterns Service/Action
  - Gestion des erreurs
  - Cache Redis

### Phase 7: VALIDATION [OBLIGATOIRE]
- **Objectif** : Garantir la qualité
- **Actions** :
  - `make test` (100% pass)
  - `make quality-check` (0 erreurs)
  - PHPStan level max
  - Performance tests

### Phase 8: DOCUMENTATION [RECOMMANDÉE]
- **Objectif** : Documenter pour l'équipe et les utilisateurs
- **Skip si** : Documentation auto-générée suffisante
- **Actions** :
  - API documentation (OpenAPI,SCRAMBLE_COMPLETE_GUIDE.md)
  - Third party api (BaseApiDoc,ThirdPartyServiceInterface, use LogsApiCalls)
  - Guide d'intégration frontend
  - Publication Confluence
  - Changelog

### Phase 9: POSTMAN [OBLIGATOIRE pour API]
- **Objectif** : Créer collections et tests automatisés pour validation API
- **Skip si** : Endpoint interne uniquement (rare)
- **Actions** :
  - Création/mise à jour de la collection
  - Tests automatisés (Newman)
  - Documentation des exemples
  - Export et versioning
  - Intégration CI/CD

### Phase 10: QUALITY CONTROL [OBLIGATOIRE]
- **Objectif** : Exécuter les correctifs automatiques de qualité et normaliser le code
- **Actions** :
  - `make quality-check` (corrige/valide style, Rector, analyse statique PHPStan)
  - Vérifier 0 erreur PHPStan (niveau 9) et conformité PSR-12 (Pint +rector)
  - docs/phpstan/phpstan-guide.md
  - Si nécessaire, appliquer les correctifs restants puis relancer `make quality-check`

## 🚀 Points d'Entrée

### Epic Jira
- Contient plusieurs stories
- Nécessite une analyse globale
- Architecture partagée entre stories
- Voir : [epic-workflow-guide.md](epic-workflow-guide.md)

### Story Jira
- Tâche unique bien définie
- Peut faire partie d'un epic
- Workflow standard
- Voir : [story-workflow-guide.md](story-workflow-guide.md)

### Bug (Jira/Sentry)
- Correction rapide
- Phases minimales
- Focus sur la résolution

### Task (Todoist/Local)
- Initiative personnelle
- Flexibilité maximale
- Documentation optionnelle

### 🔍 AUDIT Rétrospectif (NOUVEAU)
- **Évaluation post-développement** d'une fonctionnalité existante
- **Vérification de conformité** avec les critères d'acceptation
- **Analyse de complétude** selon la méthodologie
- **Points d'entrée** : Story/Epic/Task/Fichier MD existant
- **Workflow inversé** : du code vers la validation méthodologique
- Voir : [audit-workflow-guide.md](audit-workflow-guide.md)

Détails complets : [entry-points-guide.md](entry-points-guide.md)

## 📊 Profils Prédéfinis

### 🔥 Hotfix
```yaml
phases: [implementation, validation]
validation: minimal
documentation: auto
```

### 🐛 Bugfix
```yaml
phases: [analysis, implementation, validation]
validation: standard
documentation: auto
```

### ⭐ Feature Simple
```yaml
phases: [tdd, implementation, validation, documentation]
validation: standard
documentation: basic
```

### 📦 Feature Standard
```yaml
phases: [analysis, design, tdd, implementation, validation, documentation]
validation: standard
documentation: complete
```

### 🏗️ Feature Complex
```yaml
phases: ALL
validation: strict
documentation: extensive
```

### 🔍 Audit Rétrospectif
```yaml
phases: [audit-discovery, audit-analysis, audit-validation, audit-reporting]
mode: retrospective
source: existing-code
validation: compliance-check
documentation: gap-analysis
```

## 🤖 Intégration MCP Servers

### Jira (Full Productivity Server)
- Récupération automatique des tâches
- Création de sous-tâches techniques
- Mise à jour des statuts
- Tracking du progrès

### Context7
- Documentation des librairies
- Vérification des versions
- Exemples de code officiels
- Best practices

### Firecrawl
- Scraping de documentation externe
- Recherche de solutions similaires
- Analyse de patterns
- Veille technologique

### Confluence
- Publication automatique de documentation
- Rapports de tests
- Architecture Decision Records
- Guides techniques

## 🛠️ Utilisation Pratique

### Démarrage Rapide
```bash
# Initialisation automatique avec détection du type
./workflows/scripts/init-feature.sh [JIRA-KEY]

# Sélection manuelle du profil
./workflows/scripts/init-feature.sh [JIRA-KEY] --profile=feature-standard

# Sans Jira
./workflows/scripts/init-feature.sh --local --name="Ma feature"
```

### Structure des Livrables

Lors du développement, les documents sont créés dans `/todos/` :

```
/todos/
├── epics/                     # Pour les epics Jira
│   └── [EPIC-KEY]/
│       ├── analysis.md
│       ├── architecture.md
│       └── stories/
├── stories/                   # Pour les stories isolées
│   └── [STORY-KEY]/
│       ├── analysis.md
│       └── implementation.md
├── bugs/                      # Pour les corrections
│   └── [BUG-KEY]/
└── analysis/                  # Analyses générales
    ├── active/
    └── done/
```

## ✅ Quality Gates

### Obligatoires (toutes features)
- Tests passants : 100%
- PHPStan : 0 erreurs
- Code style : PSR-12

### Recommandées
- Coverage : > 80%
- Performance : < 500ms
- Documentation : Complète

### Strictes (features critiques)
- Security audit : Pass
- Load testing : Validé
- Code review : 2 approbations

## 📈 Métriques et Reporting

### Tracking Automatique
- Vélocité par phase
- Taux de bugs post-déploiement
- Coverage moyen
- Documentation completeness

### Dashboards
- Jira : Burndown et progrès
- Confluence : Rapports hebdomadaires
- Grafana : Performance metrics
- Sentry : Error tracking

## 🔧 Scripts et Automatisation

### Scripts Disponibles
```bash
# Initialisation
./workflows/scripts/init-feature.sh    # Démarre une nouvelle feature
./workflows/scripts/select-phases.sh    # Sélection interactive des phases

# Progression
./workflows/scripts/validate-phase.sh   # Valide la phase courante
./workflows/scripts/next-phase.sh       # Passe à la phase suivante

# Reporting
./workflows/scripts/status.sh           # Statut actuel
./workflows/scripts/dashboard.sh        # Vue d'ensemble

# Documentation
./workflows/scripts/generate-docs.sh    # Génère la documentation
./workflows/scripts/publish-confluence.sh # Publie sur Confluence
```

## 📚 Templates

Les templates sont disponibles dans `/workflows/templates/` :

- `epic-analysis-template.md` : Analyse d'epic
- `story-analysis-template.md` : Analyse de story
- `tdd-strategy-template.md` : Plan de tests TDD
- `api-documentation-template.md` : Documentation API
- `dependency-matrix-template.md` : Matrice de dépendances

## 🎓 Exemples et Cas d'Usage

### Exemple 1 : Bug Fix Simple
```bash
./workflows/scripts/init-feature.sh UE-301 --profile=bugfix
# → Phases: analysis, implementation, validation
```

### Exemple 2 : Nouvelle Feature API
```bash
./workflows/scripts/init-feature.sh UE-268 --profile=feature-standard
# → Phases: analysis, design, tdd, implementation, validation, documentation
```

### Exemple 3 : Epic Multi-Stories
```bash
./workflows/scripts/init-feature.sh UE-250 --type=epic
# → Analyse globale + workflow par story
```

## 🚨 Gestion des Cas Spéciaux

### Hotfix en Production
- Profile : `hotfix`
- Validation minimale
- Documentation post-fix
- Rollback préparé

### Dette Technique
- Analysis approfondie
- Refactoring progressif
- Tests de non-régression
- Documentation des changements

### Intégration Tierce
- Discovery étendue avec Firecrawl
- Context7 pour documentation
- Tests d'intégration complets
- Monitoring renforcé

## 📖 Références

- [CLAUDE.md](/CLAUDE.md) : Instructions spécifiques au projet
- [epic-workflow-guide.md](epic-workflow-guide.md) : Guide détaillé pour les epics
- [story-workflow-guide.md](story-workflow-guide.md) : Guide détaillé pour les stories
- [entry-points-guide.md](entry-points-guide.md) : Tous les points d'entrée
- [mcp-integration-guide.md](mcp-integration-guide.md) : Configuration MCP

## 🔄 Évolution de la Méthodologie

Cette méthodologie est vivante et s'enrichit avec chaque utilisation. Les retours d'expérience sont documentés et intégrés pour améliorer continuellement le processus.

Pour proposer une amélioration :
1. Créer une issue dans Jira
2. Documenter le cas d'usage
3. Proposer la modification
4. Valider avec l'équipe

---

*Dernière mise à jour : 2025*
*Version : 1.0.0*
