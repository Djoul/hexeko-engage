# Guide des Points d'Entrée

## 🎯 Vue d'Ensemble

Ce guide détaille tous les points d'entrée possibles pour initier un développement backend. Chaque point d'entrée a ses spécificités et détermine le workflow approprié.

## 🚪 Points d'Entrée Disponibles

### 1. Jira (Principal)

#### Epic
```yaml
Source: Jira Epic
Identifiant: Format [PROJECT-XXX]
Caractéristiques:
  - Contient plusieurs stories
  - Vision globale requise
  - Architecture partagée
  - Livraison progressive

Détection automatique:
  - Type "Epic" dans Jira
  - Présence de stories liées
  - Champ "Epic Link" vide

Workflow: epic-workflow-guide.md
Profile: epic-standard ou epic-complex
```

#### Story
```yaml
Source: Jira Story
Identifiant: Format [PROJECT-XXX]
Caractéristiques:
  - Unité de travail autonome
  - Peut avoir un Epic parent
  - Critères d'acceptation définis
  - Livrable indépendamment

Détection automatique:
  - Type "Story" dans Jira
  - Peut avoir "Epic Link"
  - Critères dans description

Workflow: story-workflow-guide.md
Profile: story-simple/standard/complex
```

#### Bug
```yaml
Source: Jira Bug
Identifiant: Format [PROJECT-XXX]
Caractéristiques:
  - Problème en production
  - Steps to reproduce
  - Priority/Severity définis
  - Fix rapide requis

Détection automatique:
  - Type "Bug" dans Jira
  - Priority field présent
  - Affects Version renseigné

Workflow: Minimal
Profile: bugfix ou hotfix
```

#### Task
```yaml
Source: Jira Task
Identifiant: Format [PROJECT-XXX]
Caractéristiques:
  - Tâche technique
  - Pas de valeur business directe
  - Maintenance ou tooling
  - Documentation

Détection automatique:
  - Type "Task" dans Jira
  - Labels techniques
  - Pas de story points

Workflow: Flexible
Profile: technical-story
```

### 2. Todoist

#### Project Task
```yaml
Source: Todoist Project
Caractéristiques:
  - Organisation personnelle
  - Flexibilité maximale
  - Pas de tracking Jira
  - Initiative individuelle

Récupération:
  - Via MCP Todoist
  - Liste des projets
  - Sélection de tâche
  - Import des détails

Workflow: Simplifié
Profile: Selon complexité
```

#### Personal Task
```yaml
Source: Todoist Inbox
Caractéristiques:
  - Quick wins
  - Amélioration continue
  - Expérimentation
  - Learning

Workflow: Minimal
Documentation: Optionnelle
```

### 3. Sentry

#### Production Error
```yaml
Source: Sentry Issue
Identifiant: Sentry Issue ID
Caractéristiques:
  - Erreur en production
  - Stack trace disponible
  - Impact utilisateurs
  - Urgence variable

Récupération:
  - Via MCP Sentry
  - Détails de l'erreur
  - Contexte et breadcrumbs
  - Fréquence/Impact

Workflow: Bugfix rapide
Actions:
  1. Analyse root cause
  2. Fix immédiat
  3. Test de non-régression
  4. Monitoring post-deploy

Profile: hotfix ou bugfix
```

#### Performance Issue
```yaml
Source: Sentry Performance
Caractéristiques:
  - Lenteur détectée
  - Transactions traces
  - Database queries
  - API bottlenecks

Workflow: Analyse approfondie
Profile: technical-story
```

### 4. Local Files

#### Todo File
```yaml
Source: /todos/*.md
Caractéristiques:
  - Documentation existante
  - Analyse préalable
  - Plan déjà établi
  - Context disponible

Détection:
  - Scan /todos/
  - Parse markdown
  - Extract tasks
  - Identify type

Workflow: Selon contenu
Profile: Déduit du fichier
```

#### Implementation Guide
```yaml
Source: /todos/implementation-*.md
Caractéristiques:
  - Guide détaillé prêt
  - Architecture définie
  - TDD plan inclus
  - Ordre d'implémentation

Example: implementation-wellwo-proxy-api.md

Workflow: Direct implementation
Phases: [tdd, implementation, validation]
```

## 🔍 Détection Automatique du Type

### Script de Détection
```bash
./workflows/scripts/detect-type.sh [IDENTIFIER]
```

### Logique de Détection
```yaml
Processus:
  1. Parse Identifier:
     - Format Jira: [A-Z]+-[0-9]+
     - Format Sentry: Numeric ID
     - Format Local: Path
  
  2. Si Jira:
     - Fetch via MCP
     - Check issue type
     - Check Epic Link
     - Analyze labels
  
  3. Si Sentry:
     - Fetch error details
     - Check type (error/performance)
     - Evaluate severity
  
  4. Si Local:
     - Read file
     - Parse structure
     - Identify patterns

  5. Return:
     - Type detected
     - Profile suggested
     - Phases recommended
```

## 🎮 Sélection Interactive

### Mode Interactif
```bash
./workflows/scripts/init-feature.sh --interactive
```

### Flow Interactif
```markdown
1. D'où provient votre tâche?
   [1] Jira
   [2] Todoist  
   [3] Sentry
   [4] Fichier local
   [5] Nouvelle initiative

2. [Si Jira] Quel type?
   [1] Epic
   [2] Story
   [3] Bug
   [4] Task
   [5] Je ne sais pas (auto-detect)

3. Quelle est la complexité?
   [1] Simple (CRUD, fix mineur)
   [2] Standard (logique métier)
   [3] Complexe (architecture, intégration)
   [4] Critique (sécurité, performance)

4. Phases à activer?
   [ ] Discovery
   [x] Analysis
   [x] Design
   [x] TDD
   [x] Implementation (obligatoire)
   [x] Validation (obligatoire)
   [ ] Documentation

5. Confirmer et démarrer? (y/n)
```

## 📊 Mapping Point d'Entrée → Profile

### Matrice de Décision
```markdown
| Point d'Entrée | Complexité | Profile Suggéré | Phases Types |
|----------------|------------|-----------------|--------------|
| Epic Jira | - | epic-standard | Toutes |
| Story Simple | Simple | story-simple | impl, valid |
| Story Standard | Standard | story-standard | analysis, tdd, impl, valid, doc |
| Story Complex | Complexe | story-complex | Toutes |
| Bug Jira | Simple | bugfix | analysis, impl, valid |
| Bug Sentry | Urgent | hotfix | impl, valid |
| Task Todoist | Variable | flexible | Selon besoin |
| Local File | Variable | Déduit | Selon contenu |
```

## 🤖 Intégration MCP par Point d'Entrée

### Jira
```yaml
MCP Operations:
  - jira_get_issue: Récupérer détails
  - jira_get_epic_issues: Lister stories d'un epic
  - jira_create_issue: Créer sous-tâches
  - jira_transition_issue: Mettre à jour statut
  - jira_get_transitions: Obtenir transitions possibles

Automation:
  - Fetch automatique au démarrage
  - Création sous-tâches techniques
  - Update statut à chaque phase
  - Lien documentation Confluence
```

### Todoist
```yaml
MCP Operations:
  - todoist_list_projects: Lister projets
  - todoist_list_tasks: Lister tâches
  - todoist_get_task: Détails tâche
  - todoist_update_task: Marquer complété
  - todoist_create_task: Créer sous-tâches

Automation:
  - Import description
  - Sync completion status
  - Create development tasks
```

### Sentry
```yaml
MCP Operations:
  - sentry_get_issue: Détails erreur
  - sentry_list_issue_events: Historique
  - sentry_update_issue: Marquer résolu
  - sentry_create_todoist_task: Créer tâche

Automation:
  - Analyse stack trace
  - Identification root cause
  - Création bug Jira
  - Monitoring post-fix
```

## 🚀 Initialisation par Point d'Entrée

### Commandes Spécifiques

#### Jira Epic
```bash
./workflows/scripts/init-feature.sh UE-250 --type=epic
# → Détecte epic, fetch stories, crée structure
```

#### Jira Story
```bash
./workflows/scripts/init-feature.sh UE-268
# → Auto-détecte story, propose profile
```

#### Sentry Error
```bash
./workflows/scripts/init-feature.sh --sentry=12345
# → Fetch error, analyse, crée bugfix
```

#### Todoist Task
```bash
./workflows/scripts/init-feature.sh --todoist --project="Backend"
# → Liste tâches, sélection, import
```

#### Local File
```bash
./workflows/scripts/init-feature.sh --local=/todos/ma-feature.md
# → Parse fichier, extrait tâches
```

## 📋 Structure Créée selon Point d'Entrée

### Epic Structure
```
/todos/epics/[EPIC-KEY]/
├── epic-overview.md
├── architecture.md
├── dependency-matrix.md
├── stories/
│   ├── [STORY-1]/
│   └── [STORY-2]/
└── dashboard.md
```

### Story Structure
```
/todos/stories/[STORY-KEY]/
├── story-brief.md
├── analysis.md
├── tdd-plan.md
├── implementation.md
└── documentation.md
```

### Bug Structure
```
/todos/bugs/[BUG-KEY]/
├── bug-analysis.md
├── root-cause.md
├── fix-plan.md
└── test-regression.md
```

### Sentry Structure
```
/todos/sentry/[ISSUE-ID]/
├── error-details.md
├── stack-trace.md
├── fix-applied.md
└── monitoring.md
```

## 🔄 Conversion entre Points d'Entrée

### Sentry → Jira
```bash
./workflows/scripts/sentry-to-jira.sh [SENTRY-ID]
# → Crée bug Jira depuis Sentry
```

### Todoist → Jira
```bash
./workflows/scripts/todoist-to-jira.sh [TODOIST-ID]
# → Crée story Jira depuis Todoist
```

### Local → Jira
```bash
./workflows/scripts/local-to-jira.sh /todos/feature.md
# → Crée epic/story depuis fichier
```

## 🎯 Best Practices par Point d'Entrée

### Jira
- **Toujours** vérifier les critères d'acceptation
- **Maintenir** le statut à jour
- **Lier** la documentation
- **Commenter** les décisions importantes

### Sentry
- **Analyser** la root cause
- **Tester** la non-régression
- **Monitorer** après le fix
- **Documenter** la solution

### Todoist
- **Convertir** en Jira si devient complexe
- **Tracker** le temps passé
- **Documenter** si réutilisable

### Local
- **Structurer** selon les templates
- **Migrer** vers Jira si collaboratif
- **Versionner** les changements

## 📚 Templates par Point d'Entrée

- Epic : `/workflows/templates/epic-analysis-template.md`
- Story : `/workflows/templates/story-analysis-template.md`
- Bug : `/workflows/templates/bug-analysis-template.md`
- Sentry : `/workflows/templates/sentry-fix-template.md`
- Todoist : `/workflows/templates/todoist-task-template.md`

## 🚨 Cas Spéciaux

### Multi-Source
Quand une tâche provient de plusieurs sources :
- Sentry error + Jira bug
- Todoist task + devient Story
- Local analysis + Epic Jira

### Urgences
Pour les cas critiques :
- Skip discovery/analysis
- Direct implementation
- Documentation post-mortem
- Validation renforcée

### Initiative Personnelle
Sans point d'entrée formel :
- Créer dans Todoist d'abord
- Ou fichier local
- Convertir en Jira si approuvé

---

*Guide Points d'Entrée v1.0*
*Flexibilité maximale pour tous les contextes de développement*