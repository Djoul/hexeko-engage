# Todo Implementation Program Multi-Agents avec Branches et MCP Integration
Workflow structuré pour transformer des tâches depuis Todoist, Jira, Sentry ou fichier local en features implémentées avec approche TDD sur la branche courante, utilisant un système multi-agents adaptatif.

## ⚠️ AVERTISSEMENT CRITIQUE - MODIFICATIONS FICHIERS ⚠️

**!!!TRÈS IMPORTANT!!!**
- **AUCUNE** modification, ajout ou suppression de fichier ne doit être effectuée sans un **STOP** explicite
- Vous **DEVEZ** obtenir l'approbation de l'utilisateur **AVANT** toute action sur les fichiers
- Cela inclut : Edit, Write, MultiEdit, Delete, ou toute commande modifiant des fichiers
- **TOUJOURS** présenter les changements proposés et attendre la confirmation avec "Approuvez-vous ces modifications ? (y/n)"
- **STRICTEMENT INTERDIT** d'effectuer des `git commit` ou `git merge` sans autorisation explicite
- Les commits et merges doivent **TOUJOURS** être précédés d'un STOP et d'une approbation utilisateur

## Système Multi-Agent Adaptatif

### **Agents Disponibles**
1. **Agent-Coordinator** : Orchestration et synthèse (tâches complexes uniquement)
   - Coordination des autres agents
   - Résolution des conflits
   - Synthèse des analyses
   - Décisions architecturales

2. **Agent-Analyzer** : Analyse codebase et architecture
   - Recherche dans le code
   - Identification des patterns
   - Analyse des dépendances
   - Évaluation de l'impact

3. **Agent-Implementer** : Cycles TDD et développement
   - Écriture des tests
   - Implémentation du code
   - Refactoring
   - Optimisation

4. **Agent-Validator** : Tests et quality checks
   - Validation des tests
   - Quality checks (PHPStan, Pint)
   - Performance analysis
   - Security checks

### **Déploiement Automatique selon Complexité**
```markdown
## Critères de Déploiement
- **Simple** (1-2 fichiers, < 50 lignes) → 2 agents (Analyzer + Implementer)
- **Moyen** (3-5 fichiers, 50-200 lignes) → 3 agents (+ Validator)
- **Complexe** (6+ fichiers, 200+ lignes, epic) → 4 agents (+ Coordinator)

## Override Manuel
- Mode minimal forcé → 2 agents même si complexe
- Mode expert → 4 agents même si simple
```

## Workflow Optimisé

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : INIT → SELECT → REFINE → BRAINSTORM → IMPLEMENT → COMMIT → DOCUMENT
- Vous DEVEZ obtenir la confirmation de l'utilisateur à chaque STOP
- Vous DEVEZ toujours utiliser l'approche TDD (Test-Driven Development)
- Vous DEVEZ travailler sur la branche courante avec un seul commit final (pas de commits intermédiaires)
- Vous NE DEVEZ PAS vous mentionner dans les messages de commit
- **NOUVEAUTÉ :** Système multi-agents déployé automatiquement selon la complexité

### INIT
1. **Lire `CLAUDE.MD` en intégralité**
   - Ce fichier contient les instructions spécifiques au projet
   - Si absent :
     - STOP → "Créez CLAUDE.MD avec les instructions du projet"

2. **Vérification des tâches orphelines avec Multi-Agents :**
   ```bash
   mkdir -p todos/work todos/done todos/multi-agent-logs && orphaned_count=0 && for d in todos/work/*/task.md; do [ -f "$d" ] || continue; pid=$(grep "^**Agent PID:" "$d" | cut -d' ' -f3); [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1 && continue; orphaned_count=$((orphaned_count + 1)); task_name=$(basename $(dirname "$d")); task_title=$(head -1 "$d" | sed 's/^# //'); agents_used=$(grep "^**Agents Deployed:**" "$d" | cut -d' ' -f3); echo "$orphaned_count. $task_name: $task_title [Agents: $agents_used]"; done
   ```

   - Si des tâches orphelines existent :
     - Présenter la liste numérotée avec nombre d'agents utilisés
     - STOP → "Reprendre une tâche orpheline ? (numéro ou titre/ignorer/terminer)"
     - Si reprise :
       - Lire le task.md de la tâche sélectionnée
       - Mettre à jour `**Agent PID:** [Bash(echo $PPID)]`
       - Restaurer le contexte multi-agents
       - Continuer selon le statut
     - Si ignorer : Continuer vers SELECT
     - Si terminer : mark task as done and move to done

### SELECT
1. **Évaluation Automatique de Complexité :**
   ```bash
   # Fonction d'évaluation de complexité
   evaluate_task_complexity() {
       local description="$1"
       local complexity_score=0

       # Mots-clés de complexité
       if echo "$description" | grep -qi "refactor\|architecture\|migration\|integration\|epic"; then
           complexity_score=$((complexity_score + 3))
       fi

       if echo "$description" | grep -qi "api\|service\|multiple\|complex"; then
           complexity_score=$((complexity_score + 2))
       fi

       if echo "$description" | grep -qi "fix\|update\|simple\|typo"; then
           complexity_score=$((complexity_score + 1))
       fi

       echo $complexity_score
   }
   ```

2. **Sélection de Source avec Pré-analyse :**
   ```
   📋 Source de la tâche :
   1. 📋 Fichier local (todos/todos.md)
   2. ✅ Todoist (projets/tâches)
   3. 🎯 Jira (issues/epics/stories)
   4. 🚨 Sentry (erreurs à corriger)

   🤖 Multi-Agent System Status:
   - Mode actuel : [Auto-adaptatif]
   - Agents disponibles : 4
   - Dernière utilisation : [stats]
   ```

   STOP → "D'où voulez-vous récupérer une tâche ? (1-4) :"

3. **Selon le choix (avec évaluation multi-agents) :**

   [Conserver les options 1-4 existantes, mais ajouter après sélection :]

   ```bash
   # Après sélection de la tâche
   task_complexity=$(evaluate_task_complexity "$task_description")

   # Déterminer le nombre d'agents
   if [ $task_complexity -le 2 ]; then
       recommended_agents=2
       agent_mode="Simple"
   elif [ $task_complexity -le 4 ]; then
       recommended_agents=3
       agent_mode="Moyen"
   else
       recommended_agents=4
       agent_mode="Complexe"
   fi
   ```

   **Présentation de l'Analyse :**
   ```
   📊 Analyse Multi-Agent de la Tâche
   ===================================
   Titre : [task_title]
   Type détecté : [Bug/Feature/Refactor/Epic]
   Complexité estimée : [score]/6 ([agent_mode])
   Agents recommandés : [recommended_agents]

   🤖 Agents qui seront déployés :
   ✅ Agent-Analyzer : Analyse du codebase
   ✅ Agent-Implementer : Développement TDD
   [✅ Agent-Validator : Quality checks] (si 3+)
   [✅ Agent-Coordinator : Orchestration] (si 4)

   Estimation temps : [30min-4h selon complexité]
   ```

   STOP → "Confirmer le déploiement de $recommended_agents agents ? (y/n/override) :"

   Si override :
   ```
   Mode override :
   1. Mode minimal (2 agents) - Rapide
   2. Mode standard (3 agents) - Équilibré
   3. Mode expert (4 agents) - Complet
   4. Mode custom - Choisir les agents
   ```

4. **Création de branche GitFlow avec contexte Multi-Agent :**
   ```bash
   # Analyser le type de tâche pour déterminer le préfixe GitFlow
   # [Code existant conservé]

   # Ajouter le contexte multi-agent au nom de branche si complexe
   if [ $recommended_agents -ge 4 ]; then
       branch_suffix="-ma" # multi-agent
   fi

   git checkout -b "[prefix]/[issue-id-if-exists]-[task-title-slug]$branch_suffix"
   ```

5. **Création du dossier de tâche avec structure Multi-Agent :**
   ```bash
   task_dir="todos/work/$(date +%Y-%m-%d-%H-%M-%S)-[task-title-slug]/"
   mkdir -p "$task_dir/multi-agent-logs"
   ```

6. **Initialiser `task.md` avec contexte Multi-Agent :**
   ```markdown
   # [Task Title]
   **Status:** Refining
   **Agent PID:** [Bash(echo $PPID)]
   **Source:** [Todoist/Jira/Sentry/Local]
   **Source ID:** [ID si applicable]
   **Branch:** [branch-name]
   **Agents Deployed:** [recommended_agents] agents
   **Agent Mode:** [Simple/Moyen/Complexe]

   ## Original Task
   [Contenu brut de la source]

   ## Multi-Agent Analysis
   ### Agent-Analyzer Findings
   - Composants impactés : [À remplir]
   - Patterns identifiés : [À remplir]
   - Complexité réelle : [À confirmer]

   ### Agent-Implementer Strategy
   - Approche TDD : [À définir]
   - Cycles estimés : [À définir]
   - Risques identifiés : [À définir]

   ### Agent-Validator Checklist (si déployé)
   - [ ] Tests unitaires complets
   - [ ] PHPStan niveau 9
   - [ ] Code coverage > 80%
   - [ ] Performance validée

   ### Agent-Coordinator Decisions (si déployé)
   - Architecture : [À définir]
   - Patterns : [À définir]
   - Trade-offs : [À définir]

   ## Description
   [Ce que nous construisons]

   ## TDD Implementation Plan
   ### Tests à écrire
   - [ ] Test unitaire : [description]
   - [ ] Test d'intégration : [description]
   - [ ] Test E2E : [description si applicable]

   ### Implementation (cycles TDD)
   - [ ] RED: Écrire test qui échoue pour [fonctionnalité]
   - [ ] GREEN: Implémenter le code minimal
   - [ ] REFACTOR: Améliorer le code

   ## Critères d'acceptation
   [Depuis source ou définis]

   ## Notes
   [Notes d'implémentation]
   ```

7. **Initialisation des Logs Multi-Agents :**
   ```bash
   # Créer les fichiers de log pour chaque agent
   cat > "$task_dir/multi-agent-logs/agent-coordination.md" << EOF
   # Multi-Agent Coordination Log
   **Task:** [task_title]
   **Started:** $(date)
   **Mode:** $agent_mode
   **Agents:** $recommended_agents

   ## Agent Status
   - Agent-Analyzer: Active
   - Agent-Implementer: Standby
   $([ $recommended_agents -ge 3 ] && echo "- Agent-Validator: Standby")
   $([ $recommended_agents -eq 4 ] && echo "- Agent-Coordinator: Active")

   ## Coordination Events
   $(date '+%H:%M:%S') - System initialized with $recommended_agents agents
   EOF
   ```

8. Si Option 4 (local) et si le fichier était `todos.md`, retirer la tâche sélectionnée

### REFINE (Analyse Multi-Agents Parallèle)

1. **Déploiement des Agents :**
   ```bash
   echo "🤖 Déploiement de $recommended_agents agents pour l'analyse..."

   # Agent-Analyzer toujours actif
   {
       echo "🔍 Agent-Analyzer: Scan du codebase..." >> multi-agent-logs/agent-analyzer.log

       # Recherches parallèles
       find ../../app -name "*.php" | xargs grep -l "similar_pattern" > temp/analyzer-files.txt &
       find ../../tests -name "*Test.php" | xargs grep -l "test.*similar" > temp/analyzer-tests.txt &
       find ../../database/migrations -name "*.php" -mtime -30 > temp/analyzer-migrations.txt &

       wait
   } &
   analyzer_pid=$!

   # Agent-Implementer analyse les patterns de test
   {
       echo "🧪 Agent-Implementer: Analyse des patterns de test..." >> multi-agent-logs/agent-implementer.log

       grep -r "test" ../../tests --include="*Test.php" | head -20 > temp/test-patterns.txt &
       find ../../tests -name "*TestCase.php" > temp/test-bases.txt &

       wait
   } &
   implementer_pid=$!

   # Agent-Validator si déployé
   if [ $recommended_agents -ge 3 ]; then
       {
           echo "✅ Agent-Validator: Analyse des standards..." >> multi-agent-logs/agent-validator.log

           cat ../../phpstan.neon > temp/phpstan-config.txt &
           cat ../../.github/workflows/*.yml | grep -E "test|quality" > temp/ci-config.txt &

           wait
       } &
       validator_pid=$!
   fi

   # Agent-Coordinator si déployé
   if [ $recommended_agents -eq 4 ]; then
       {
           echo "🎯 Agent-Coordinator: Analyse architecturale..." >> multi-agent-logs/agent-coordinator.log

           find ../../app -type d -name "Services" -o -name "Repositories" | head -10 > temp/architecture.txt &
           grep -r "interface\|abstract" ../../app --include="*.php" | head -20 > temp/contracts.txt &

           wait
       } &
       coordinator_pid=$!
   fi

   # Attendre tous les agents
   wait $analyzer_pid $implementer_pid ${validator_pid:-} ${coordinator_pid:-}
   ```

2. **Consolidation des Analyses Multi-Agents :**
   ```bash
   # Créer le rapport d'analyse consolidé
   cat > analysis.md << 'EOF'
   # Analyse Multi-Agent Consolidée

   ## 🔍 Agent-Analyzer Report
   ### Composants Impactés
   EOF

   cat temp/analyzer-files.txt >> analysis.md

   cat >> analysis.md << 'EOF'

   ### Tests Existants Liés
   EOF

   cat temp/analyzer-tests.txt >> analysis.md

   # Ajouter les autres rapports d'agents...
   ```

3. **Résolution des Conflits entre Agents :**
   ```markdown
   ## 🤝 Coordination Multi-Agent

   ### Conflits Détectés
   - **Agent-Analyzer** suggère pattern Repository
   - **Agent-Implementer** préfère pattern Service
   - **Agent-Coordinator** arbitre : Service + Repository (clean architecture)

   ### Consensus
   - Utiliser Service pour logique métier
   - Utiliser Repository pour accès données
   - Tests sur les deux couches
   ```

4. **Planification TDD Collaborative :**
   ```markdown
   ## 📋 Plan TDD Multi-Agent

   ### Agent-Implementer - Stratégie de Tests
   1. **Unit Tests** (priorité haute)
      - Test Repository isolation
      - Test Service avec mocks
      - Test validation rules

   2. **Integration Tests** (priorité moyenne)
      - Test API endpoints
      - Test database transactions

   ### Agent-Validator - Critères de Qualité
   - Coverage minimum : 85%
   - PHPStan : 0 erreurs niveau 9
   - Pint : PSR-12 compliant
   - Performance : < 100ms par endpoint
   ```

5. **Validation du Plan :**
   STOP → "Plan multi-agent validé ? Voir analysis.md pour détails. (y/n)"

6. **Mise à jour du task.md avec Synthèse :**
   ```bash
   # Mettre à jour les sections Multi-Agent Analysis dans task.md
   sed -i '/### Agent-Analyzer Findings/,/### Agent-Implementer Strategy/{
       /### Agent-Analyzer Findings/!{
           /### Agent-Implementer Strategy/!d
       }
   }' task.md

   # Insérer les vrais findings
   # [Code pour insérer le contenu de l'analyse]
   ```

### BRAINSTORM (Génération d'Approches Multi-Agents)

1. **Collecte d'informations contextuelles :**
   [Code existant conservé]

2. **Génération d'Approches par Agents Spécialisés :**

   **Si 2 agents (mode simple) :**
   ```markdown
   ## Approches Générées (Mode Simple - 2 Agents)

   ### Approche A : Direct Implementation (Agent-Implementer)
   - Description : Implementation directe avec tests basiques
   - Avantages : Rapide, simple, facile à comprendre
   - Inconvénients : Peut manquer des cas edge
   - Complexité : Faible
   - Recommandé par : Agent-Implementer

   ### Approche B : Refactor First (Agent-Analyzer)
   - Description : Nettoyer le code existant avant d'ajouter
   - Avantages : Meilleure base, moins de dette technique
   - Inconvénients : Plus de temps initial
   - Complexité : Moyenne
   - Recommandé par : Agent-Analyzer
   ```

   **Si 3+ agents (mode avancé) :**
   ```markdown
   ## Approches Générées (Mode Avancé - [N] Agents)

   ### Approche A : Architecture Hexagonale (Agent-Coordinator)
   - Description : Séparation claire des couches
   - Avantages : Testabilité maximale, évolutivité
   - Inconvénients : Plus complexe initialement
   - Validation : ✅ Agent-Validator approuve
   - Support : ⚠️ Agent-Implementer préfère plus simple

   ### Approche B : Service Pattern (Agent-Analyzer + Implementer)
   - Description : Logique dans services dédiés
   - Avantages : Balance complexité/maintenabilité
   - Inconvénients : Peut devenir monolithique
   - Validation : ✅ Tous les agents approuvent
   - Consensus : ⭐ Meilleur compromis

   ### Approche C : CQRS Light (Agent-Coordinator)
   - Description : Séparer lectures/écritures
   - Avantages : Performance, scalabilité
   - Inconvénients : Overkill pour cas simple ?
   - Validation : ⚠️ Agent-Validator inquiet de la complexité
   - Support : ❌ Agent-Implementer contre
   ```

3. **Débat Multi-Agents :**
   ```bash
   # Log du débat entre agents
   cat >> multi-agent-logs/agent-debate.md << 'EOF'
   # Débat Multi-Agents sur les Approches

   ## Round 1 : Positions Initiales
   [14:32:15] Agent-Implementer: "Je préfère l'approche B, plus pragmatique"
   [14:32:18] Agent-Analyzer: "L'approche A offre une meilleure architecture long-terme"
   [14:32:21] Agent-Validator: "L'approche B sera plus facile à tester"
   [14:32:24] Agent-Coordinator: "Considérons un hybride A+B ?"

   ## Round 2 : Arguments
   [14:32:45] Agent-Implementer: "Temps de dev estimé: A=8h, B=4h, C=12h"
   [14:32:52] Agent-Analyzer: "Dette technique évitée: A=high, B=medium, C=low"
   [14:33:01] Agent-Validator: "Testabilité: A=excellent, B=good, C=complex"

   ## Round 3 : Consensus
   [14:33:30] Agent-Coordinator: "Proposition: Approche B avec éléments de A"
   [14:33:35] ALL: "Approuvé ✅"

   ## Décision Finale
   **Approche retenue :** B (Service Pattern) avec Repository pour data access
   **Justification :** Meilleur équilibre temps/qualité/maintenabilité
   **Vote :** 3/4 pour B, 1/4 pour hybride
   EOF
   ```

4. **Raffinement Collaboratif :**
   STOP → "Approche B retenue par consensus des agents. D'accord ? (y/n/discuss)"

   Si "discuss" :
   ```
   Points de discussion possibles :
   1. Complexité de l'architecture
   2. Temps de développement
   3. Maintenabilité future
   4. Patterns alternatifs
   5. Contraintes spécifiques

   Quel point discuter ? (1-5) :
   ```

### IMPLEMENT (TDD Multi-Agents Coordonné)

1. **Pour chaque cycle TDD :**

   a. **Phase RED (Agent-Implementer Lead) :**
   ```bash
   echo "🔴 Agent-Implementer: Écriture du test qui doit échouer..."

   # L'agent génère le test
   cat > test_suggestion.php << 'EOF'
   /** @test */
   public function it_validates_user_email()
   {
       // Arrange
       $userData = ['email' => 'invalid-email'];

       // Act & Assert
       $this->expectException(ValidationException::class);
       $this->userService->createUser($userData);
   }
   EOF

   echo "📝 Test suggéré par Agent-Implementer"
   cat test_suggestion.php
   ```

   STOP → "Test proposé correct ? (y/n/edit)"

   ```bash
   # Exécuter le test
   docker compose exec app_engage php artisan test --filter="it_validates_user_email"

   # Agent-Validator vérifie
   if [ $recommended_agents -ge 3 ]; then
       echo "✅ Agent-Validator: Vérification que le test échoue correctement..."
       # Vérifier que c'est bien un échec et pas une erreur
   fi
   ```

   b. **Phase GREEN (Multi-Agents Collaboration) :**
   ```bash
   # Agent-Implementer propose l'implémentation
   echo "💚 Agent-Implementer: Implémentation minimale..."

   # Agent-Analyzer vérifie la cohérence avec le codebase
   echo "🔍 Agent-Analyzer: Vérification patterns existants..."

   # Si Agent-Coordinator présent
   if [ $recommended_agents -eq 4 ]; then
       echo "🎯 Agent-Coordinator: Validation architecture..."
       # Vérifier que l'implémentation respecte l'architecture
   fi
   ```

   STOP → "Implementation proposée validée par les agents. Appliquer ? (y/n)"

   c. **Phase REFACTOR (Agent-Validator Focus) :**
   ```bash
   if [ $recommended_agents -ge 3 ]; then
       echo "♻️ Agent-Validator: Analyse pour refactoring..."

       # Checks automatiques
       docker compose exec app_engage ./vendor/bin/phpstan analyze [files]
       docker compose exec app_engage ./vendor/bin/pint --test [files]

       # Suggestions de refactoring
       echo "Suggestions de refactoring :"
       echo "1. Extraire méthode validateEmail()"
       echo "2. Utiliser EmailValidator service"
       echo "3. Ajouter type hints manquants"
   fi
   ```

   STOP → "Appliquer refactoring suggéré ? (1-3/none)"

   d. **Validation Multi-Agents :**
   ```bash
   echo "🤝 Validation collaborative du cycle..."

   # Chaque agent valide selon sa spécialité
   echo "[OK] Agent-Implementer: Code fonctionne"
   echo "[OK] Agent-Analyzer: Patterns respectés"
   [ $recommended_agents -ge 3 ] && echo "[OK] Agent-Validator: Quality checks passed"
   [ $recommended_agents -eq 4 ] && echo "[OK] Agent-Coordinator: Architecture cohérente"
   ```

2. **Gestion des Désaccords entre Agents :**
   ```bash
   # Si conflit détecté
   if [[ "$agent_implementer_opinion" != "$agent_analyzer_opinion" ]]; then
       echo "⚠️ Désaccord entre agents détecté !"
       echo ""
       echo "Agent-Implementer : $agent_implementer_opinion"
       echo "Agent-Analyzer : $agent_analyzer_opinion"

       if [ $recommended_agents -eq 4 ]; then
           echo ""
           echo "🎯 Agent-Coordinator arbitre..."
           # Logique d'arbitrage basée sur les critères du projet
       else
           echo ""
           STOP → "Résoudre le conflit ? (implementer/analyzer/custom)"
       fi
   fi
   ```

3. **Métriques et Suivi Multi-Agents :**
   ```bash
   # Après chaque cycle
   cat >> multi-agent-logs/metrics.md << EOF

   ## Cycle $cycle_number Metrics
   - Duration: $cycle_duration
   - Tests written: $tests_count
   - Code lines: $code_lines
   - Coverage: $coverage%
   - Agent consensus: $consensus_level/4
   - Conflicts resolved: $conflicts_count
   EOF
   ```

4. **Quality Check Multi-Niveaux :**
   ```bash
   echo "🏁 Quality Check Multi-Agents..."

   # Niveau 1 : Agent-Implementer
   echo "1️⃣ Agent-Implementer: Tests fonctionnels"
   make test

   # Niveau 2 : Agent-Validator
   if [ $recommended_agents -ge 3 ]; then
       echo "2️⃣ Agent-Validator: Analyse statique"
       make stan
       make pint
   fi

   # Niveau 3 : Agent-Analyzer
   echo "3️⃣ Agent-Analyzer: Impact analysis"
   # Vérifier les dépendances impactées

   # Niveau 4 : Agent-Coordinator
   if [ $recommended_agents -eq 4 ]; then
       echo "4️⃣ Agent-Coordinator: Architecture review"
       # Vérifier la cohérence architecturale
   fi
   ```

### COMMIT (Synthèse Multi-Agents)

1. **Génération du Message de Commit Collaboratif :**
   ```bash
   # Chaque agent contribue au message
   echo "📝 Génération du message de commit multi-agents..."

   commit_msg="[branch-type]/[task-title]: [résumé]

   "

   # Contribution Agent-Implementer
   commit_msg+="Implementation:
   - $(git diff --name-only | grep -E '\.(php|js)$' | wc -l) files modified
   - $(grep -r "test" --include="*Test.php" | wc -l) tests added
   - TDD cycles completed: $cycles_count

   "

   # Contribution Agent-Analyzer
   commit_msg+="Architecture:
   - Pattern used: $pattern_name
   - Dependencies: $dependencies_added
   - Coupling: $coupling_level

   "

   # Contribution Agent-Validator (si présent)
   if [ $recommended_agents -ge 3 ]; then
       commit_msg+="Quality:
   - PHPStan: ✅ Level 9
   - Tests: ✅ 100% passing
   - Coverage: $coverage%

   "
   fi

   # Contribution Agent-Coordinator (si présent)
   if [ $recommended_agents -eq 4 ]; then
       commit_msg+="Decisions:
   - Trade-offs: $tradeoffs
   - Future considerations: $future_notes

   "
   fi

   commit_msg+="Multi-Agent: $recommended_agents agents collaborated"
   ```

2. **Validation Finale Multi-Agents :**
   ```
   🤖 Validation Finale Multi-Agents
   =================================

   ✅ Agent-Implementer : Code complet et fonctionnel
   ✅ Agent-Analyzer : Architecture respectée
   [✅ Agent-Validator : Qualité validée]
   [✅ Agent-Coordinator : Cohérence globale]

   Consensus : 100%
   Conflits résolus : 3
   Temps total : 2h 34m
   ```

3. **Rapport Multi-Agents Final :**
   ```bash
   cat > multi-agent-logs/final-report.md << 'EOF'
   # Rapport Final Multi-Agents

   ## Résumé d'Exécution
   - Tâche : [task_title]
   - Agents déployés : $recommended_agents
   - Mode : $agent_mode
   - Durée totale : $total_duration

   ## Contributions par Agent

   ### Agent-Analyzer
   - Fichiers analysés : 156
   - Patterns identifiés : 8
   - Recommandations appliquées : 6/8

   ### Agent-Implementer
   - Cycles TDD : 12
   - Tests écrits : 18
   - Code coverage : 94%

   ### Agent-Validator
   - Quality checks : 45
   - Issues détectées : 3
   - Issues résolues : 3/3

   ### Agent-Coordinator
   - Décisions architecturales : 4
   - Conflits arbitrés : 2
   - Patterns appliqués : Repository + Service

   ## Métriques Finales
   - Complexité estimée vs réelle : 4/6 vs 5/6
   - Temps estimé vs réel : 2h vs 2h34m
   - Qualité code : A (était B+)

   ## Apprentissages
   1. Le pattern Service + Repository bien adapté
   2. Tests d'intégration critiques pour ce type de feature
   3. La coordination multi-agents a évité 2 erreurs d'architecture

   ## Recommandations Futures
   - Utiliser 4 agents pour features similaires
   - Documenter le pattern pour réutilisation
   - Considérer extraction en package
   EOF
   ```

### DOCUMENT (Documentation Enrichie Multi-Agents)

1. **Génération Collaborative de Documentation :**
   ```bash
   echo "📚 Génération de documentation multi-agents..."

   STOP → "Type de documentation ? (1-4) :
   1. Documentation technique (Agent-Analyzer lead)
   2. Manuel utilisateur (Agent-Implementer lead)
   3. Guide API (Agent-Validator lead)
   4. Architecture Decision Record (Agent-Coordinator lead)"
   ```

2. **Documentation Technique (si choix 1) :**
   ```markdown
   # Documentation Technique - [Feature]
   *Générée collaborativement par 4 agents*

   ## Vue d'Ensemble (Agent-Coordinator)
   [Architecture globale et décisions]

   ## Analyse du Code (Agent-Analyzer)
   ### Structure
   [Arbre des composants]

   ### Patterns Utilisés
   [Liste et justification]

   ### Dépendances
   [Graphe de dépendances]

   ## Implémentation (Agent-Implementer)
   ### Flux de Données
   [Diagrammes de flux]

   ### APIs Internes
   [Signatures et comportements]

   ## Validation (Agent-Validator)
   ### Tests
   [Stratégie et couverture]

   ### Performance
   [Benchmarks et optimisations]

   ## Décisions d'Architecture (Agent-Coordinator)
   ### ADR-001: [Titre]
   **Contexte:** [...]
   **Décision:** [...]
   **Conséquences:** [...]
   **Agents votants:** 4/4 ✅
   ```

3. **Méta-Documentation Multi-Agents :**
   ```markdown
   ## 🤖 Process Multi-Agents

   Cette documentation a été générée par collaboration entre :
   - **Agent-Analyzer** : Structure et patterns (35% contribution)
   - **Agent-Implementer** : Détails implementation (30% contribution)
   - **Agent-Validator** : Tests et qualité (20% contribution)
   - **Agent-Coordinator** : Vision globale (15% contribution)

   ### Points de Synergie
   - Analyzer + Implementer : Identification pattern Repository
   - Validator + Coordinator : Standards de qualité
   - Tous : Consensus sur architecture hexagonale light

   ### Conflits Résolus
   1. Niveau d'abstraction : Résolu par vote (3-1)
   2. Nommage services : Résolu par Coordinator
   ```

## Commandes de Suivi Multi-Agents

```bash
# Status multi-agents
claude "/todo --agents-status"

# Replay d'une décision
claude "/todo --replay-decision [decision-id]"

# Analyse de performance des agents
claude "/todo --agents-metrics"

# Mode debug multi-agents
claude "/todo --debug-agents"
```

## Dashboard Multi-Agents

```
🤖 Multi-Agent Analytics Dashboard
==================================

Performance par Agent (dernières 10 tâches) :
Agent-Analyzer    : ████████████ 92% précision
Agent-Implementer : ███████████░ 88% efficacité
Agent-Validator   : █████████████ 95% détection
Agent-Coordinator : ██████████░░ 85% décisions

Collaboration Metrics :
- Consensus moyen : 87%
- Conflits/tâche : 2.3
- Résolution time : 4.2 min

Complexité vs Agents :
Simple  (2 agents) : ████████ 45% des tâches
Moyen   (3 agents) : █████░░░ 35% des tâches
Complexe (4 agents) : ███░░░░░ 20% des tâches

ROI Multi-Agents :
- Bugs évités : 12
- Temps gagné : 18h
- Dette technique évitée : High
```

## Notes sur le Système Multi-Agents

### Avantages
- **Analyse approfondie** : Chaque agent apporte son expertise
- **Détection d'erreurs** : Les conflits révèlent les problèmes
- **Apprentissage** : Le système s'améliore avec le temps
- **Parallélisation** : Analyses simultanées plus rapides

### Quand Utiliser 4 Agents
- Refactoring majeur
- Nouvelle architecture
- Intégrations complexes
- Features critiques
- Epic/User Story large

### Quand Rester à 2 Agents
- Bug fixes simples
- Typos/corrections mineures
- Updates de config
- Petites features isolées
- Tâches < 30 minutes

Ce système multi-agents transforme le workflow de développement en un processus collaboratif intelligent qui améliore la qualité et réduit les erreurs ! 🚀