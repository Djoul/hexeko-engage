# Analysis Workflow Program Hybride avec Multi-Agents Sélectifs
Workflow structuré pour analyser et planifier des tâches depuis Todoist, Jira, Sentry ou description directe en créant des stratégies de développement détaillées avec transition automatique vers le workflow WorkTree.

**NOUVEAUTÉ HYBRIDE :** Sub-agents déployés automatiquement pour phases complexes selon la complexité détectée.

## Structure des Fichiers Générés

```
todos/
├── analysis/
│   ├── active/
│   │   └── 2025-07-25-143022-email-validation/
│   │       ├── 01-task.md                    # Définition et suivi
│   │       ├── 02-technical-analysis.md      # Analyse technique
│   │       ├── 03-approaches-matrix.md       # Comparaison approches
│   │       ├── 04-development-strategy.md    # Stratégie retenue
│   │       ├── 05-tdd-test-plan.md          # Plan de tests détaillé
│   │       ├── 06-implementation-guide.md    # Guide pour développement
│   │       ├── 07-analysis-report.md        # Rapport consolidé
│   │       ├── multi-agent-logs/             # 🆕 Logs des sub-agents
│   │       │   ├── agent-coordination.md     # Orchestration
│   │       │   ├── agent-codebase.md         # Analyse codebase
│   │       │   ├── agent-testing.md          # Stratégie tests
│   │       │   ├── agent-architecture.md     # Impact architecture
│   │       │   └── agent-approaches.md       # Débat approches
│   │       ├── diagrams/                     # Diagrammes Mermaid/exports
│   │       └── artifacts/                    # Exports PDF, tickets, etc.
│   ├── done/                                 # Analyses archivées
│   └── templates/                            # Templates réutilisables
└── ready-for-development/                    # Tâches prêtes pour worktree
    └── email-validation-ready.md             # Spécification complète
```

## Système Multi-Agent Hybride

### **Déclenchement Automatique**
Le système détecte automatiquement la complexité et déploie des sub-agents quand nécessaire :

```markdown
## Critères de Déploiement Sub-Agents
- **Complexité Détectée :**
  - Simple (1-2 composants) → Agent unique
  - Moyen (3-5 composants) → 2-3 sub-agents sélectifs
  - Complexe (6+ composants) → 4 sub-agents complets

- **Type de Tâche :**
  - Bugfix simple → Agent unique
  - Refactoring/Architecture → Sub-agents obligatoires
  - Nouvelle feature complexe → Sub-agents obligatoires

- **Choix Utilisateur :**
  - Mode simple forcé → Agent unique même si complexe
  - Mode expert → Sub-agents même si simple
```

### **Sub-Agents Disponibles**
1. **Agent-Codebase** : Analyse app/, models, services, controllers
2. **Agent-Testing** : Stratégie tests, patterns, couverture
3. **Agent-Architecture** : Structure, dépendances, patterns
4. **Agent-Approaches** : Génération approches alternatives (BRAINSTORM)

## Workflow Optimisé

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : SELECT → INIT → REFINE → BRAINSTORM → ANALYZE → DOCUMENT → PREPARE_DEVELOPMENT
- Vous DEVEZ obtenir la confirmation de l'utilisateur à chaque STOP
- Vous NE DEVEZ PAS modifier de code, seulement analyser et planifier
- Vous DEVEZ rester sur la branche courante sans créer de nouvelle branche
- Tous les fichiers générés suivent la nomenclature numérotée pour un ordre logique
- La transition vers le workflow WorkTree doit être préparée et documentée
- **NOUVEAUTÉ :** Sub-agents déployés selon complexité avec coordination automatique

### SELECT
1. **Vérification des analyses orphelines :**
   ```bash
   mkdir -p todos/analysis/active todos/analysis/done todos/analysis/templates todos/ready-for-development

   orphaned_count=0
   for d in todos/analysis/active/*/01-task.md; do
       [ -f "$d" ] || continue
       pid=$(grep "^**Agent PID:" "$d" | cut -d' ' -f3)
       [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1 && continue
       orphaned_count=$((orphaned_count + 1))
       analysis_name=$(basename $(dirname "$d"))
       task_title=$(head -1 "$d" | sed 's/^# Analysis: //')
       status=$(grep "^**Status:" "$d" | cut -d' ' -f2)
       echo "$orphaned_count. $analysis_name [$status]: $task_title"
   done
   ```

   - Si des analyses orphelines existent :
     - Présenter la liste numérotée avec status
     - STOP → "Actions possibles :
       - [numéro] : Reprendre une analyse spécifique
       - finaliser : Finaliser toutes les orphelines et préparer pour développement
       - finaliser:[numéros] : Finaliser seulement les analyses sélectionnées (ex: finaliser:1,3,5)
       - archiver : Marquer toutes comme terminées et déplacer vers done/
       - archiver:[numéros] : Archiver seulement les analyses sélectionnées (ex: archiver:2,4)
       - supprimer : Supprimer définitivement toutes les orphelines
       - supprimer:[numéros] : Supprimer seulement les analyses sélectionnées (ex: supprimer:1,6)
       - ignorer : Continuer avec une nouvelle analyse
       Votre choix :"

     [Logique de gestion orphelines identique - non répétée pour concision]

2. **STOP** → "D'où voulez-vous récupérer une tâche à analyser ?
   1. 📋 Fichier local dans todos/ (todos.md, bugs.md, features.md, etc.)
   2. ✅ Todoist (tâches personnelles/projet)
   3. 🎯 Jira (issues, epics, stories)
   4. 🚨 Sentry (erreurs à analyser)
   5. ✍️ Description directe (saisie manuelle dans Claude)
   Entrez votre choix (1-5) :"

3. **Selon le choix :**
   [Options 1-5 identiques à la version actuelle - non répétées]

4. **🆕 Évaluation Complexité et Mode Multi-Agent :**
   ```bash
   # Après sélection de la tâche, évaluation automatique
   complexity_score=0

   # Analyse des mots-clés pour estimer complexité
   if echo "$task_description" | grep -qi "refactor\|architecture\|migration\|integration"; then
       complexity_score=$((complexity_score + 2))
   fi

   if echo "$task_description" | grep -qi "multiple\|several\|many\|complex\|system"; then
       complexity_score=$((complexity_score + 1))
   fi

   # Déterminer mode d'analyse
   if [ $complexity_score -ge 3 ]; then
       suggested_mode="multi-agent-full"
   elif [ $complexity_score -ge 1 ]; then
       suggested_mode="multi-agent-selective"
   else
       suggested_mode="single-agent"
   fi
   ```

   **STOP** → "📊 Complexité détectée : [score]/5
   Mode recommandé : [$suggested_mode]

   Options d'analyse :
   1. 🤖 Multi-agent complet (4 agents spécialisés - qualité maximale)
   2. 🔀 Multi-agent sélectif (2-3 agents selon phases - équilibré)
   3. 👤 Agent unique (analyse standard - rapide)
   4. 🎯 Auto (suivre la recommandation système)

   Votre choix (1-4) :"

5. **Création du dossier d'analyse structuré :**
   ```bash
   ANALYSIS_ID=$(date +%Y-%m-%d-%H%M%S)
   TASK_SLUG=$(echo "$task_title" | sed 's/[^a-zA-Z0-9]/-/g' | tr '[:upper:]' '[:lower:]')
   ANALYSIS_DIR="todos/analysis/active/${ANALYSIS_ID}-${TASK_SLUG}"

   mkdir -p "$ANALYSIS_DIR"/{diagrams,artifacts,multi-agent-logs}
   cd "$ANALYSIS_DIR"
   ```

6. **🆕 Initialisation Multi-Agent :**
   ```bash
   # Créer le fichier de coordination
   cat > multi-agent-logs/agent-coordination.md << EOF
   # Multi-Agent Coordination Log
   **Analysis ID:** ${ANALYSIS_ID}-${TASK_SLUG}
   **Mode:** [$selected_mode]
   **Agents Deployed:** [Liste selon mode]
   **Coordination Strategy:** [Définie selon mode]

   ## Agent Status
   - Agent-Coordinator: Active
   [Liste dynamique selon agents déployés]

   ## Coordination Events
   [Log des événements multi-agents]
   EOF
   ```

7. **Initialiser `01-task.md` :** [Identique + ajout section multi-agent]
   ```markdown
   # Analysis: [Task Title]
   **Status:** Refining
   **Agent PID:** [Bash(echo $PPID)]
   **Analysis ID:** [ANALYSIS_ID]-[TASK_SLUG]
   **Source:** [Todoist/Jira/Sentry/Local/Direct]
   **Source ID:** [ID si applicable, sinon "Manual Input"]
   **Current Branch:** [git branch --show-current]
   **Created:** [date]
   **🆕 Multi-Agent Mode:** [selected_mode]
   **🆕 Agents Deployed:** [liste des agents selon mode]

   [Reste identique à la version actuelle]
   ```

### INIT
[Identique à la version actuelle - pas de changement]

### REFINE (🆕 Multi-Agent Hybride)

1. **Déploiement des Agents selon Mode :**

   **Mode Single-Agent (choix 3) :**
   ```bash
   # Fonctionnement classique - un seul agent
   echo "🤖 Mode Agent Unique - Analyse séquentielle classique"
   ```

   **Mode Multi-Agent Sélectif (choix 2) :**
   ```bash
   echo "🔀 Déploiement Multi-Agent Sélectif..."

   # Déployer 2-3 agents selon le type de tâche
   if [[ "$task_type" =~ "architecture|refactor" ]]; then
       agents=("Agent-Codebase" "Agent-Architecture")
   elif [[ "$task_type" =~ "test|quality" ]]; then
       agents=("Agent-Codebase" "Agent-Testing")
   else
       agents=("Agent-Codebase" "Agent-Architecture")
   fi
   ```

   **Mode Multi-Agent Complet (choix 1) :**
   ```bash
   echo "🤖 Déploiement Multi-Agent Complet..."
   agents=("Agent-Codebase" "Agent-Testing" "Agent-Architecture")
   ```

2. **🆕 Orchestration Multi-Agent :**

   **Agent-Coordinator (Orchestrateur Principal) :**
   ```markdown
   ## Rôle : Coordination et Synthèse
   - Distribuer les tâches aux agents spécialisés
   - Collecter et synthétiser les résultats
   - Résoudre les conflits entre recommandations
   - Générer le rapport consolidé final
   ```

   **Agent-Codebase (Toujours déployé) :**
   ```bash
   # Analyse codebase en parallèle avec focus spécialisé
   echo "🔍 Agent-Codebase: Analyse du code..."

   # Recherche spécialisée dans le codebase
   find app/ -name "*.php" | xargs grep -l "relevant_pattern" | head -10
   find tests/ -name "*Test.php" | xargs grep -l "similar_functionality" | head -10
   find database/migrations/ -name "*.php" | xargs grep -l "related_table"
   grep -r "api.*related" routes/

   # Générer rapport spécialisé
   cat > multi-agent-logs/agent-codebase.md << 'EOF'
   # Agent-Codebase Analysis Report
   **Specialization:** Code structure, models, controllers, APIs
   **Focus:** Impact sur le code existant et nouvelles implémentations

   ## Composants Identifiés
   [Analyse détaillée des composants code]

   ## Dependencies Analysis
   [Analyse des dépendances techniques]

   ## Recommendations
   [Recommandations spécifiques code]
   EOF
   ```

   **Agent-Testing (Si déployé) :**
   ```bash
   echo "🧪 Agent-Testing: Analyse stratégie tests..."

   # Analyse spécialisée tests
   find tests/ -name "*.php" -exec grep -l "TestCase\|RefreshDatabase" {} \;
   find database/factories/ -name "*.php"

   cat > multi-agent-logs/agent-testing.md << 'EOF'
   # Agent-Testing Analysis Report
   **Specialization:** Tests strategy, TDD approach, quality assurance
   **Focus:** Plan de tests optimal et patterns TDD

   ## Current Test Patterns
   [Analyse patterns existants]

   ## TDD Strategy Recommendations
   [Stratégie TDD spécialisée]

   ## Test Infrastructure Needs
   [Besoins infrastructure tests]
   EOF
   ```

   **Agent-Architecture (Si déployé) :**
   ```bash
   echo "🏗️ Agent-Architecture: Analyse architecture..."

   # Analyse spécialisée architecture
   find app/Services/ -name "*.php"
   find app/Http/Middleware/ -name "*.php"
   find config/ -name "*.php"

   cat > multi-agent-logs/agent-architecture.md << 'EOF'
   # Agent-Architecture Analysis Report
   **Specialization:** System architecture, patterns, scalability
   **Focus:** Impact architectural et design patterns

   ## Architecture Impact
   [Impact sur l'architecture existante]

   ## Design Patterns
   [Patterns recommandés]

   ## Scalability Considerations
   [Considérations montée en charge]
   EOF
   ```

3. **🆕 Coordination et Synthèse :**
   ```bash
   echo "🔄 Agent-Coordinator: Synthèse multi-agent..."

   # Collecte des rapports d'agents
   consolidate_agent_reports() {
       # Lire tous les rapports d'agents
       codebase_report=$(cat multi-agent-logs/agent-codebase.md 2>/dev/null || echo "Non déployé")
       testing_report=$(cat multi-agent-logs/agent-testing.md 2>/dev/null || echo "Non déployé")
       architecture_report=$(cat multi-agent-logs/agent-architecture.md 2>/dev/null || echo "Non déployé")

       # Identifier conflits et synergies
       detect_conflicts_and_synergies

       # Générer synthèse coordonnée
       generate_coordinated_synthesis
   }
   ```

4. **Créer `02-technical-analysis.md` (Synthèse Multi-Agent) :**
   ```markdown
   # Analyse Technique Multi-Agent : [Task Title]
   **Date:** [date]
   **Mode Analysis:** [mode sélectionné]
   **Agents Contributeurs:** [liste agents déployés]

   ## 🤖 Synthèse Coordonnée Multi-Agent

   ### Agent-Codebase - Impact Code
   [Intégration rapport agent-codebase]

   ### Agent-Testing - Stratégie Tests (si déployé)
   [Intégration rapport agent-testing]

   ### Agent-Architecture - Impact Architectural (si déployé)
   [Intégration rapport agent-architecture]

   ## 🔄 Coordination Agent-Coordinator

   ### Synergies Identifiées
   - [Point de convergence 1 entre agents]
   - [Point de convergence 2 entre agents]

   ### Conflits Résolus
   - **Conflit:** [Agent A] vs [Agent B] sur [point]
   - **Résolution:** [Decision coordonnée avec justification]

   ### Recommandations Consolidées
   [Recommandations finales intégrant tous les agents]

   ## Estimation de Complexité Validée Multi-Agent
   ### Métrique Consolidée
   - **Agent-Codebase:** [Complexité code]
   - **Agent-Testing:** [Complexité tests] (si applicable)
   - **Agent-Architecture:** [Complexité architecture] (si applicable)
   - **Consensus Final:** [Simple/Moyen/Complexe/Expert]

   [Reste des sections identiques à la version actuelle]
   ```

5. **STOP** → "Analyse technique multi-agent terminée.
   Mode utilisé : [mode]
   Agents déployés : [liste]
   Conflits résolus : [nombre]
   Complexité finale : [niveau]
   Continuer vers BRAINSTORM ? (y/n)"

### BRAINSTORM (🆕 Multi-Agent pour Approches)

1. **Collecte d'informations approfondies :** [Identique à version actuelle]

2. **🆕 Génération d'Approches Multi-Agent :**

   **Mode Single-Agent :**
   ```bash
   # Génération classique d'approches par simulation
   echo "Génération d'approches par agent unique (simulation multi-perspectives)"
   ```

   **Mode Multi-Agent :**
   ```bash
   echo "🎭 Déploiement Agent-Approaches pour perspectives réelles..."

   # Déployer 3 sub-agents avec vraies spécialisations
   deploy_approach_agents() {
       echo "Agent-Conservative: Perspective sécurité/stabilité"
       echo "Agent-Innovative: Perspective moderne/cutting-edge"
       echo "Agent-Pragmatic: Perspective équilibre/réalisme"
   }
   ```

3. **🆕 Débat Multi-Agent d'Approches :**

   **Agent-Conservative :**
   ```markdown
   ## Approche Conservative (Agent-Conservative)
   **Spécialisation:** Stabilité, sécurité, maintenabilité
   **Philosophie:** "Minimize risk, maximize reliability"

   ### Approche Recommandée : Évolution Incrémentale
   - **Description :** [Approche sûre et éprouvée]
   - **Justification Conservative :**
     - Réutilise patterns existants éprouvés
     - Minimise les risques de régression
     - Facilite rollback si problème
   - **Score Conservatisme :** 9/10
   ```

   **Agent-Innovative :**
   ```markdown
   ## Approche Innovative (Agent-Innovative)
   **Spécialisation:** Technologies émergentes, performance, modernité
   **Philosophie:** "Embrace change, leverage latest tech"

   ### Approche Recommandée : Refactoring Moderne
   - **Description :** [Approche avec technologies récentes]
   - **Justification Innovative :**
     - Utilise patterns modernes optimisés
     - Prépare l'évolutivité future
     - Améliore performance significativement
   - **Score Innovation :** 9/10
   ```

   **Agent-Pragmatic :**
   ```markdown
   ## Approche Pragmatic (Agent-Pragmatic)
   **Spécialisation:** Équilibre, réalisme, budget
   **Philosophie:** "Best value, realistic timelines"

   ### Approche Recommandée : Hybride Équilibrée
   - **Description :** [Compromis optimal]
   - **Justification Pragmatique :**
     - Balance innovation et stabilité
     - Respecte contraintes temps/budget
     - Maximise ROI à court et moyen terme
   - **Score Pragmatisme :** 9/10
   ```

4. **🆕 Simulation Débat Multi-Agent :**
   ```bash
   # Log du débat entre agents dans multi-agent-logs/
   cat > multi-agent-logs/agent-approaches.md << 'EOF'
   # Multi-Agent Approaches Debate Log

   ## Round 1: Initial Positions
   **Agent-Conservative:** "L'approche innovative est trop risquée pour..."
   **Agent-Innovative:** "L'approche conservative manque d'ambition et..."
   **Agent-Pragmatic:** "Vous avez tous deux raison, mais dans ce contexte..."

   ## Round 2: Critiques Croisées
   **Agent-Conservative → Innovative:** "Performance ne justifie pas le risque"
   **Agent-Innovative → Conservative:** "Dette technique s'accumule"
   **Agent-Pragmatic:** "Proposons une voie médiane..."

   ## Round 3: Consensus Building
   **Agent-Coordinator:** "Synthèse basée sur contraintes projet..."
   EOF
   ```

5. **Créer `03-approaches-matrix.md` (Multi-Agent Enhanced) :**
   ```markdown
   # Matrice des Approches Multi-Agent : [Task Title]
   **Mode Analysis:** [mode multi-agent]
   **Perspectives Agents:** Conservative, Innovative, Pragmatic

   ## 🎭 Approches Générées par Agents Spécialisés

   ### Approche A : [Nom - Recommandée par Agent-Conservative]
   **Génée par:** Agent-Conservative (Expertise: Stabilité/Sécurité)
   [Contenu approche conservative]

   ### Approche B : [Nom - Recommandée par Agent-Innovative]
   **Générée par:** Agent-Innovative (Expertise: Innovation/Performance)
   [Contenu approche innovative]

   ### Approche C : [Nom - Recommandée par Agent-Pragmatic]
   **Générée par:** Agent-Pragmatic (Expertise: Équilibre/Réalisme)
   [Contenu approche pragmatique]

   ## 🤝 Débat Multi-Agent et Consensus

   ### Points de Convergence
   - [Accord entre Agent-Conservative et Agent-Pragmatic]
   - [Accord entre Agent-Innovative et Agent-Pragmatic]

   ### Points de Divergence
   - **Risque vs Innovation:** Agent-Conservative vs Agent-Innovative
   - **Timeline vs Qualité:** Débat résolu par Agent-Pragmatic

   ### Synthèse Agent-Coordinator
   [Synthèse finale intégrant toutes les perspectives]

   [Matrice de décision quantifiée identique]

   ## ✅ APPROCHE SÉLECTIONNÉE : [Résultat Consensus Multi-Agent]
   **Agents Contributeurs :** [liste]
   **Score Consensus :** [X.X/5]
   **Perspective Dominante :** [Conservative/Innovative/Pragmatic/Hybride]
   ```

### ANALYZE, DOCUMENT, PREPARE_DEVELOPMENT
[Identiques à la version actuelle - aucun changement]

## 🎯 Avantages Version Hybride

### **Flexibilité Totale**
- **Simple tasks** → Agent unique (rapidité)
- **Complex tasks** → Multi-agents (qualité)
- **User choice** → Override automatique

### **Qualité Graduée**
- **Single-agent** → Rapide, efficace pour cas simples
- **Multi-agent sélectif** → Équilibre qualité/coût
- **Multi-agent complet** → Qualité maximale pour cas critiques

### **Traçabilité Complète**
- **Logs multi-agents** dans dossier dédié
- **Débats documentés** avec positions de chaque agent
- **Coordination visible** avec résolution conflits

### **Backwards Compatible**
- **Toutes les fonctionnalités** actuelles préservées
- **Même structure** de fichiers + logs multi-agents
- **Même workflow** avec enrichissement optionnel

### **Cost-Effective**
- **Déploiement intelligent** selon besoins réels
- **Pas de surcoût** pour tâches simples
- **ROI optimisé** selon complexité

Cette version hybride vous donne **le meilleur des deux mondes** : simplicité pour les cas standards et puissance multi-agent pour les analyses complexes ! 🚀
