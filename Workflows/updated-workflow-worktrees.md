# Todo Implementation Program Multi-Agents avec Worktrees et MCP Integration
Workflow structuré pour transformer des tâches depuis Todoist, Jira, Sentry ou fichier local en features implémentées avec approche TDD et isolation complète par worktrees.

## Environnement Docker

**IMPORTANT**: Ce projet utilise une architecture Docker multi-environnements :
- **Projet principal**: `docker-compose.yml` (dev principal)
- **Worktrees**: Stack Docker complètement isolée avec ports dynamiques (`docker-compose.worktree.yml`)

### Architecture Worktree avec Isolation Complète

Chaque worktree dispose de sa propre stack Docker isolée :
```bash
# Stack isolée du worktree (pas d'impact sur le projet principal)
docker compose exec app_worktree_[ID] [commande]

# Exemples dans un worktree
make test                    # Tests avec DB dédiée
make migrate                 # Migration sur DB worktree
make quality-check          # PHPStan + Pint + Tests
```

**GARANTIE SÉCURITÉ**: Aucune modification des fichiers de configuration du projet principal (.env, docker-compose.yml, etc.)

## Setup Initial

### Structure Requise
```bash
# Dans le projet principal
mkdir -p todos/{templates,worktrees,done}
echo "todos/" >> .gitignore
git add .gitignore && git commit -m "Add todos directory to gitignore"

# Fichier de tâches locales
touch todos/todos.md
```

### Templates de Configuration
Créer dans `todos/templates/` :
- `docker-compose.worktree.yml` (stack isolée)
- `Makefile.worktree` (commandes sécurisées)
- `phpunit.worktree.xml` (config tests)
- `setup-worktree-env.sh` (script .env)

## Multi-Agent Configuration

**Agents Disponibles :**
- **Coordinator** : Orchestration (tâches complexes uniquement)
- **Analyzer** : Analyse codebase et architecture
- **Implementer** : Cycles TDD et développement
- **Validator** : Tests et quality checks

**Nombre d'Agents selon Complexité :**
- **Simple** (1-2 fichiers) : 2 agents (Analyzer + Implementer)
- **Moyen** (3-5 fichiers) : 3 agents (+ Validator)
- **Complexe** (6+ fichiers, epic) : 4 agents (+ Coordinator)

## Workflow

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : RESUME → SELECT → INIT → REFINE → BRAINSTORM → IMPLEMENT → COMMIT → DOCUMENT
- Vous DEVEZ obtenir la confirmation de l'utilisateur à chaque STOP
- Vous DEVEZ toujours utiliser l'approche TDD (Test-Driven Development)
- Vous DEVEZ utiliser des worktrees avec isolation Docker complète
- Vous NE DEVEZ PAS modifier les fichiers de configuration du projet principal
- Le dossier `/todos` est en gitignore, chercher dans le répertoire principal depuis le worktree (../../)

### RESUME (Gestion des Orphelines)
1. **Vérification automatique des tâches orphelines :**
   ```bash
   check_orphaned_tasks() {
       orphaned=()
       for task_dir in todos/worktrees/*/; do
           if [[ -f "$task_dir/task.md" ]]; then
               pid=$(grep "^**Agent PID:" "$task_dir/task.md" | cut -d' ' -f3)
               if ! ps -p "$pid" >/dev/null 2>&1; then
                   task_name=$(basename "$task_dir")
                   task_title=$(head -1 "$task_dir/task.md" | sed 's/^# //')
                   orphaned+=("$task_name:$task_title")
               fi
           fi
       done
   }
   ```

2. **Si tâches orphelines détectées :**
   ```
   === TÂCHES ORPHELINES DÉTECTÉES ===
   1. 20250724_143022_email_validation: Add email validation to user registration
   2. 20250723_091545_fix_dashboard: Fix dashboard mobile display
   
   Options :
   0. Ignorer et créer nouvelle tâche
   99. Nettoyer toutes les orphelines (suppression définitive)
   ```
   
   - STOP → "Reprendre tâche orpheline ? (numéro/0/99)"
   
3. **Si reprise de tâche orpheline :**
   - Ouvrir l'éditeur : `phpstorm /absolute/path/to/todos/worktrees/[task-name]/`
   - STOP → "Éditeur ouvert. Dans le terminal PHPStorm, exécutez : `claude "/todo --resume"`"
   - Terminer le workflow actuel

4. **Si aucune orpheline ou choix d'ignorer :** Continuer vers SELECT

### SELECT (4 Sources)
1. **Présentation des sources :**
   ```
   📋 Source de la tâche :
   1. 📋 Fichier local (todos/todos.md)
   2. ✅ Todoist (projets/tâches)
   3. 🎯 Jira (issues/epics/stories)
   4. 🚨 Sentry (erreurs à corriger)
   ```
   
   STOP → "Choix (1-4) :"

2. **Selon le choix :**

   **Option 1 - Fichier Local:**
   ```bash
   # Découvrir tous les fichiers .md dans le dossier todos/
   available_files=()
   for file in todos/*.md; do
       [ -f "$file" ] && available_files+=("$file")
   done
   
   # Si aucun fichier trouvé, créer todos.md par défaut
   if [[ ${#available_files[@]} -eq 0 ]]; then
       cat > todos/todos.md << 'EOF'
   # Todos Locaux
   
   ## En Cours
   - [ ] Exemple : Ajouter validation email sur User model
   - [ ] Exemple : Corriger bug affichage dashboard mobile
   
   ## Ideas  
   - [ ] Améliorer performance des requêtes Product
   - [ ] Ajouter système de notifications push
   EOF
       available_files=("todos/todos.md")
       git add todos/todos.md && git commit -m "Initialize default todos.md"
   fi
   ```
   
   - Présenter la liste des fichiers disponibles :
   ```
   📁 Fichiers de tâches disponibles :
   1. todos/todos.md (12 tâches)
   2. todos/bugs.md (5 tâches) 
   3. todos/features.md (8 tâches)
   4. todos/refactoring.md (3 tâches)
   5. Créer un nouveau fichier
   ```
   
   - STOP → "Quel fichier utiliser ? (numéro ou 5 pour nouveau)"
   
   **Si fichier existant sélectionné :**
   - Lire le fichier choisi et extraire toutes les tâches "- [ ]"
   - Présenter liste numérotée avec contexte :
   ```
   📋 Tâches dans [nom-fichier] :
   1. [Section] Ajouter validation email sur User model
   2. [Section] Corriger bug affichage dashboard mobile  
   3. [Section] Améliorer performance des requêtes Product
   ```
   - STOP → "Quelle tâche ? (numéro)"
   - Marquer comme "- [x]" dans le fichier et commit : `git commit -am "Mark todo as started: [task-title] from [filename]"`
   
   **Si création nouveau fichier (option 5) :**
   - STOP → "Nom du nouveau fichier ? (ex: 'sprint-2024', 'urgent-fixes')"
   - Créer `todos/[nom].md` avec template :
   ```markdown
   # [Nom Formaté]
   
   ## À Faire
   - [ ] [Première tâche à ajouter]
   
   ## En Cours
   
   ## Terminé
   ```
   - Ouvrir dans l'éditeur : `[editor-command] todos/[nom].md`
   - STOP → "Ajoutez vos tâches et sauvegardez. Appuyez sur Entrée pour continuer"
   - Relire le fichier et présenter les tâches "- [ ]"
   - STOP → "Quelle tâche ? (numéro)"

   **Option 2 - Todoist:**
   - Utiliser MCP full-productivity-server pour lister les projets
   - STOP → "Quel projet Todoist ? (nom ou ID)"
   - Lister les tâches du projet avec priorités et dates
   - STOP → "Quelle tâche sélectionner ? (numéro)"
   - Récupérer détails complets (description, sous-tâches, commentaires)

   **Option 3 - Jira:**
   - Utiliser MCP Jira pour lister les projets disponibles
   - STOP → "Quel projet Jira ? (clé du projet)"
   - STOP → "Quel type ? (issue/epic/story/bug/task)"
   - Lister les éléments filtrés par type et statut (!= Done)
   - STOP → "Quel élément sélectionner ? (numéro ou clé)"
   - Récupérer tous les détails (description, critères d'acceptation, commentaires, sous-tâches)

   **Option 4 - Sentry:**
   - Utiliser MCP Sentry pour lister les projets
   - STOP → "Quel projet Sentry ? (nom)"
   - Lister les erreurs récentes non résolues (7 derniers jours)
   - STOP → "Quelle erreur corriger ? (numéro)"
   - Récupérer stack trace complète, contexte, user impact, fréquence

3. **Évaluation automatique par Agent Evaluator :**
   ```
   📊 Analyse de la tâche :
   
   Tâche : [titre]
   Type : [Bug/Feature/Refactor/Doc]
   Complexité : [Simple/Moyen/Complexe]
   Fichiers estimés : [1-2 / 3-5 / 6+]
   Agents recommandés : [2/3/4]
   Temps estimé : [30min-1h / 1-2h / 2-4h]
   Impact : [Low/Medium/High]
   ```
   
   STOP → "Continuer avec cette tâche ? (y/n)"

### INIT (Initialisation Robuste)
1. **Génération ID et Ports Dynamiques :**
   ```bash
   # ID unique pour isolation complète
   WORKTREE_ID=$(date +%Y%m%d_%H%M%S)_$(echo $RANDOM | md5sum | head -c 8)
   TASK_SLUG=$(echo "$task_title" | sed 's/[^a-zA-Z0-9]/-/g' | tr '[:upper:]' '[:lower:]')
   
   # Ports dynamiques calculés à partir de l'ID
   port_suffix=$(echo $WORKTREE_ID | tail -c 4)
   DB_PORT_WORKTREE=$((5500 + $port_suffix))
   REDIS_PORT_WORKTREE=$((6500 + $port_suffix))
   WEB_PORT_WORKTREE=$((8000 + $port_suffix))
   ```

2. **Création Worktree avec Branche :**
   ```bash
   git worktree add -b "task/$TASK_SLUG" "todos/worktrees/$WORKTREE_ID" HEAD
   cd "todos/worktrees/$WORKTREE_ID"
   ```

3. **Configuration .env Worktree :**
   ```bash
   # Copier l'original et adapter
   cp ../../.env .env.base          # Sauvegarde originale
   cp ../../.env .env               # Version de travail
   
   # Script d'adaptation automatique
   source ../../templates/setup-worktree-env.sh
   setup_worktree_env "$WORKTREE_ID" "$(git rev-parse --show-toplevel)"
   ```

4. **Configuration Docker Isolée :**
   ```bash
   # Templates avec substitution de variables
   cp ../../templates/docker-compose.worktree.yml docker-compose.yml
   cp ../../templates/phpunit.worktree.xml phpunit.xml
   cp ../../templates/Makefile.worktree Makefile
   
   # Substitution des variables dans les templates
   envsubst < docker-compose.yml > docker-compose.yml.tmp && mv docker-compose.yml.tmp docker-compose.yml
   envsubst < phpunit.xml > phpunit.xml.tmp && mv phpunit.xml.tmp phpunit.xml
   ```

5. **Initialisation task.md avec Multi-Agents :**
   ```markdown
   # [Task Title]
   **Status:** InProgress
   **Agent PID:** [Bash(echo $PPID)]
   **Source:** [Todoist/Jira/Sentry/Local]
   **Source ID:** [ID si applicable]
   **Worktree ID:** [WORKTREE_ID]
   **Agents:** [2/3/4] agents selon complexité
   **Ports:** Web:[WEB_PORT] DB:[DB_PORT] Redis:[REDIS_PORT]

   ## Original Task
   [Contenu brut complet de la source]

   ## Multi-Agent Analysis
   ### Agent Analyzer - Codebase Impact
   [À remplir lors de REFINE]

   ### Agent Implementer - TDD Strategy  
   [À remplir lors de REFINE]

   ### Agent Validator - Test Strategy
   [À remplir lors de REFINE]

   ### Agent Coordinator - Orchestration (si 4 agents)
   [À remplir lors de REFINE]

   ## Description Raffinée
   [Ce que nous construisons - défini lors de REFINE]

   ## TDD Implementation Plan
   ### Tests à écrire
   - [ ] Test unitaire : [description]
   - [ ] Test d'intégration : [description]
   - [ ] Test API/E2E : [description si applicable]

   ### Implementation (cycles TDD)
   - [ ] Cycle 1 - RED: [test qui échoue] | GREEN: [code minimal] | REFACTOR: [amélioration]
   - [ ] Cycle 2 - RED: [test qui échoue] | GREEN: [code minimal] | REFACTOR: [amélioration]
   - [ ] Cycle 3 - RED: [test qui échoue] | GREEN: [code minimal] | REFACTOR: [amélioration]

   ## Critères d'acceptation
   [Depuis source ou définis lors de BRAINSTORM]

   ## Approche Technique (définie lors de BRAINSTORM)
   [Approche choisie avec justification]

   ## Notes d'Implémentation
   [Notes techniques et découvertes]
   ```

6. **Setup de la Stack Worktree :**
   ```bash
   # Démarrage stack isolée
   make setup
   
   # Vérification que tout fonctionne
   make test
   ```

7. **Commit initial :**
   ```bash
   git add . && git commit -m "task($TASK_SLUG): Initialize worktree with isolated Docker stack

   - Worktree ID: $WORKTREE_ID
   - Ports: Web:$WEB_PORT_WORKTREE DB:$DB_PORT_WORKTREE Redis:$REDIS_PORT_WORKTREE  
   - Agents: $recommended_agents
   - Source: $source"
   
   git push -u origin "task/$TASK_SLUG"
   ```

8. **Transition PHPStorm :**
   ```
   🏗️ Worktree configuré et opérationnel !
   
   Stack Docker :
   - Web: http://localhost:[WEB_PORT_WORKTREE]
   - Base de données: localhost:[DB_PORT_WORKTREE]
   - Redis: localhost:[REDIS_PORT_WORKTREE]
   
   Options :
   1. Mettre en stand-by et ouvrir dans PHPStorm (recommandé)
   2. Continuer dans le terminal actuel
   ```
   
   STOP → "Choix (1-2) :"

   **Si option 1 (recommandé) :**
   - Créer script d'ouverture PHPStorm avec workspace pré-configuré
   - Afficher chemin complet : `/absolute/path/to/todos/worktrees/[WORKTREE_ID]/`
   - Instructions détaillées :
     ```
     1. PHPStorm s'ouvre automatiquement dans 3 secondes...
        Chemin : /absolute/path/to/todos/worktrees/[WORKTREE_ID]/
     
     2. Dans le terminal PHPStorm :
        cd /absolute/path/to/todos/worktrees/[WORKTREE_ID]/
        make status  # Vérifier que la stack fonctionne
        claude "/todo --resume"
     
     3. URLs de développement :
        - App : http://localhost:[WEB_PORT_WORKTREE]
        - DB : localhost:[DB_PORT_WORKTREE] (worktree_user/worktree_pass)
     ```
   - Exécuter : `phpstorm "/absolute/path/to/todos/worktrees/[WORKTREE_ID]/" &`
   - STOP → "PHPStorm ouvert. Suivez les instructions ci-dessus pour reprendre."
   - Terminer le workflow actuel

   **Si option 2 :**
   - Mettre à jour Status vers "InProgress"
   - Continuer directement vers REFINE

### REFINE (Multi-Agents Parallèles)
1. **Déploiement des Agents Spécialisés :**
   ```
   🤖 Déploiement de [N] agents spécialisés...
   
   Agent Analyzer : Analyse du codebase principal (../../)
   Agent Implementer : Planification TDD détaillée
   Agent Validator : Stratégie de tests et quality
   [Agent Coordinator : Orchestration] (si 4 agents)
   ```

2. **Agent Analyzer - Recherche Parallèle dans le Codebase :**
   ```bash
   # IMPORTANT: Chercher depuis le répertoire principal (../../) 
   # Car le worktree est isolé mais partage les sources
   
   # Modèles et services concernés
   find ../../app -name "*.php" -path "*/Models/*" | xargs grep -l "relevant_pattern" &
   find ../../app -name "*.php" -path "*/Services/*" | xargs grep -l "business_logic" &
   
   # Contrôleurs et requests
   find ../../app/Http -name "*.php" | xargs grep -l "similar_endpoint" &
   
   # Migrations et database
   find ../../database -name "*.php" | xargs grep -l "related_table" &
   
   wait  # Attendre toutes les recherches parallèles
   ```

3. **Agent Implementer - Analyse des Patterns de Tests :**
   ```bash
   # Tests existants pour patterns similaires
   find ../../tests -name "*Test.php" | xargs grep -l "similar_functionality" &
   
   # Factories et seeders disponibles
   find ../../database/factories -name "*.php" &
   find ../../database/seeders -name "*.php" &
   
   # Configuration de tests (mocks, traits)
   find ../../tests -name "*.php" | xargs grep -l "TestCase\|RefreshDatabase" &
   
   wait
   ```

4. **Agent Validator - Analyse Quality & Config :**
   ```bash
   # Configuration PHPStan et quality tools
   cat ../../phpstan.neon &
   cat ../../pint.json &
   
   # CI/CD et checks automatiques
   find ../../.github -name "*.yml" | xargs grep -l "test\|quality" &
   
   wait
   ```

5. **Synthèse Multi-Agents dans analysis.md :**
   ```markdown
   # Multi-Agent Analysis Report
   
   ## Agent Analyzer - Application Layer
   ### Modèles Concernés
   - `../../app/Models/[Model].php` - [Description impact]
   - `../../app/Services/[Service].php` - [Logic métier]
   
   ### Contrôleurs & API
   - `../../app/Http/Controllers/[Controller].php` - [Endpoints]
   - `../../app/Http/Requests/[Request].php` - [Validation]
   
   ### Base de Données
   - Migration nécessaire : `add_[field]_to_[table]`
   - Index à ajouter pour performance
   - Contraintes à implémenter
   
   ## Agent Implementer - TDD Strategy
   ### Patterns de Tests Identifiés
   - Feature tests : `tests/Feature/[Feature]Test.php`
   - Unit tests : `tests/Unit/[Unit]Test.php`
   - Factories : `database/factories/[Model]Factory.php`
   
   ### Cycles TDD Proposés
   1. **Cycle 1 - Validation** : [Description]
   2. **Cycle 2 - Logic** : [Description]  
   3. **Cycle 3 - Integration** : [Description]
   
   ## Agent Validator - Quality Strategy
   ### Outils de Qualité
   - PHPStan niveau [X] - [Configuration actuelle]
   - Pint - [Rules PSR-12]
   - Tests - [Coverage target]
   
   ### Checks Spécifiques à la Tâche
   - [Check 1] : [Justification]
   - [Check 2] : [Justification]
   ```

6. **Consolidation et Validation :**
   - Présenter l'analyse consolidée des 3-4 agents
   - STOP → "Analyse multi-agents complète. Continuer vers BRAINSTORM ? (y/n)"
   
7. **Mise à jour task.md :**
   - Remplir les sections "Multi-Agent Analysis"
   - Définir `**Status**: Refining`
   - Commit : `git add -A && git commit -m "task($TASK_SLUG): Multi-agent analysis complete"`

### BRAINSTORM (Approches Collaboratives)
1. **Collecte d'Informations Contextuelles :**
   - STOP → "Documentation technique disponible ? (liens/description)"
   - STOP → "Contraintes spécifiques ? (performance/sécurité/compatibilité)"
   - STOP → "Exemples similaires dans le codebase ou références externes ?"

2. **Analyse Multi-Agents des Approches :**
   
   **Agent Analyzer** propose approches architecturales :
   **Agent Implementer** évalue la complexité d'implémentation :
   **Agent Validator** analyse les risques et testabilité :
   
   Résultat : 2-3 approches avec analyse comparative :

   ```markdown
   ## Approches Identifiées par Multi-Agents
   
   ### Approche A : [Nom descriptif]
   **Proposée par :** Agent Analyzer + Implementer
   - **Description :** [Comment implémenter]
   - **Avantages :**
     - [Avantage architectural - Analyzer]
     - [Avantage développement - Implementer]
   - **Inconvénients :**
     - [Risque identifié - Validator]
     - [Complexité - Implementer]
   - **Complexité :** [Faible/Moyenne/Élevée]
   - **Impact codebase :** [Minimal/Modéré/Important]
   - **Testabilité :** [Score Validator]
   
   ### Approche B : [Alternative]
   [Même structure avec perspective multi-agents]
   
   ### Approche C : [Si pertinente]
   [Même structure]
   ```

3. **Recommandation Consensus Multi-Agents :**
   - Présenter l'approche recommandée par consensus
   - Justification basée sur l'analyse de chaque agent
   - STOP → "Quelle approche choisir ? (A/B/C) ou proposer alternative"

4. **Raffinement Collaboratif :**
   - Si alternative proposée : agents analysent et adaptent
   - **Agent Coordinator** (si présent) orchestre les ajustements
   - STOP → "Plan d'implémentation final validé ? (y/n)"

5. **Mise à jour Plan TDD Multi-Agents :**
   - **Agent Implementer** adapte les cycles TDD
   - **Agent Validator** définit les tests spécifiques aux risques
   - **Agent Analyzer** identifie les points d'intégration
   - Mise à jour `task.md` avec l'approche finale
   - Commit : `git add -A && git commit -m "task($TASK_SLUG): Collaborative approach defined"`

### IMPLEMENT (TDD Strict Multi-Agents)
1. **Pour chaque cycle TDD :**

   a. **Phase RED (Agent Implementer) :**
      - Écrire le test qui doit échouer
      - Exécuter avec la stack worktree :
        ```bash
        make test-filter FILTER="nom_du_test"
        ```
      - STOP → "Test écrit et échoue comme prévu ? (y/n)"

   b. **Phase GREEN (Agent Implementer) :**
      - Implémenter le code MINIMAL pour passer le test
      - Exécuter les tests :
        ```bash
        make test-filter FILTER="nom_du_test"
        ```
      - STOP → "Test passe avec implémentation minimale ? (y/n)"

   c. **Phase REFACTOR (Agent Implementer + Validator) :**
      - **Agent Implementer** : Améliorer le code
      - **Agent Validator** : Vérifier quality et performance
      - Exécuter quality check :
        ```bash
        make quality-check  # Tests + PHPStan + Pint
        ```
      - STOP → "Refactoring terminé, tous les checks passent ? (y/n)"

   d. **Validation Cycle (Agent Validator) :**
      - Marquer checkbox complète dans `task.md`
      - Commit du cycle : `git add -A && git commit -m "task($TASK_SLUG): TDD cycle [N] - [description]"`

2. **Gestion du Travail Imprévu :**
   - **Agent Coordinator** (si présent) détecte les déviations
   - Proposer nouveau cycle TDD si nécessaire
   - STOP → "Travail supplémentaire détecté. Ajouter cycle TDD ? (y/n)"

3. **Validation Finale Multi-Agents :**
   ```bash
   # Agent Validator exécute la suite complète
   make test                    # Tous les tests
   make stan                    # PHPStan analyse
   make pint                    # Code style
   
   # Vérifications spécifiques
   docker compose exec app_worktree php artisan route:list  # Si nouvelles routes
   docker compose exec app_worktree php artisan config:cache  # Test config
   ```

4. **Quality Check Obligatoire :**
   - **Agent Validator** exécute `make quality-check`
   - Si erreurs PHPStan détectées :
     
     STOP → "PHPStan : [X] erreurs détectées. Actions :
     1. Corriger automatiquement (erreurs liées au fix)
     2. Ajouter annotations @phpstan-ignore (faux positifs)
     3. Reporter (erreurs pré-existantes non critiques)
     Choix (1-3) :"
     
     **Si choix 1 :** Agent Implementer corrige uniquement les erreurs liées
     **Si choix 2 :** Ajouter annotations documentées
     **Si choix 3 :** Documenter dans task.md pourquoi reporté

5. **Tests Utilisateur/Intégration :**
   - STOP → "Tests manuels/intégration passent ? Vérifiez : http://localhost:[WEB_PORT_WORKTREE]"

6. **Proposition Mise à jour CLAUDE.MD :**
   - Si changements architecturaux significatifs
   - **Agent Analyzer** propose les ajouts à CLAUDE.MD
   - STOP → "Mettre à jour CLAUDE.MD avec nouvelles conventions ? (y/n)"

7. **Finalisation Phase :**
   - Définir `**Status**: AwaitingCommit`
   - STOP → "Implémentation terminée et validée. Continuer vers COMMIT ? (y/n)"

### COMMIT (Validation et PR)
1. **Résumé Multi-Agents des Changements :**
   ```markdown
   ## Résumé des Changements
   
   ### Agent Analyzer - Architecture
   - Fichiers modifiés : [liste avec impact]
   - Patterns ajoutés : [nouveaux patterns]
   - Dépendances : [nouvelles/modifiées]
   
   ### Agent Implementer - Code
   - Fonctionnalités ajoutées : [détail]
   - Tests créés : [Feature/Unit counts]
   - Méthodes publiques : [nouvelles APIs]
   
   ### Agent Validator - Qualité
   - Couverture tests : [pourcentage]
   - PHPStan : [niveau/erreurs]
   - Performance : [impact estimé]
   ```

2. **Création PR Multi-Agents :**
   - STOP → "Créer la Pull Request ? (y/n)"
   
   **Agent Coordinator** génère le commit message structuré :
   ```bash
   git add -A && git commit -m "feat($TASK_SLUG): [titre descriptif]

   [Description de la fonctionnalité]

   **Multi-Agent Implementation:**
   - Architecture: [Agent Analyzer findings]
   - Development: [Agent Implementer summary]  
   - Quality: [Agent Validator metrics]

   **Changes:**
   - [liste des changements principaux]

   **Tests:** [X]/[Y] passing (Feature: [A], Unit: [B])
   **Coverage:** [Z]%
   **PHPStan:** Level [N], 0 errors
   
   Closes [Source-ID] (if applicable)"
   ```

3. **Push et PR :**
   ```bash
   git push origin "task/$TASK_SLUG"
   
   # Création PR avec GitHub CLI
   gh pr create \
     --title "feat: [task title]" \
     --body "$(cat task.md | sed -n '/## Description/,/## Critères/p')" \
     --assignee "@me" \
     --label "multi-agent,tdd"
   ```

4. **Mise à jour Source Externe :**
   - **Si Todoist** : Marquer tâche comme complétée via MCP
   - **Si Jira** : Transitionner vers "Done" avec lien PR
   - **Si Sentry** : Marquer "Resolved" avec commit hash
   - **Si Local** : Déplacer vers section "Done" dans todos.md

5. **Archivage Sécurisé :**
   ```bash
   # Définir Status Done
   sed -i 's/**Status**: AwaitingCommit/**Status**: Done/' task.md
   
   # Archiver dans done/
   mkdir -p ../../done/
   cp task.md "../../done/${WORKTREE_ID}_${TASK_SLUG}.md"
   cp analysis.md "../../done/${WORKTREE_ID}_${TASK_SLUG}_analysis.md" 2>/dev/null || true
   
   # Commit d'archivage
   git add . && git commit -m "task($TASK_SLUG): Archive completed task"
   ```

6. **Nettoyage du Worktree :**
   STOP → "PR créée et tâche archivée. Options de nettoyage :
   1. Supprimer le worktree maintenant (recommandé)
   2. Garder pour review/debug (suppression manuelle plus tard)
   Choix (1-2) :"
   
   **Si choix 1 :**
   ```bash
   # Nettoyage complet et sécurisé
   make cleanup  # Arrêt stack Docker
   cd ../../..   # Retour projet principal
   
   # Vérification intégrité projet principal
   git status    # Doit être clean
   docker compose ps  # Stack principale doit tourner
   
   # Suppression worktree
   git worktree remove "todos/worktrees/$WORKTREE_ID"
   
   echo "✅ Worktree supprimé, projet principal intact"
   ```

### DOCUMENT (Documentation Multi-Agents)
1. **Types de Documentation :**
   ```
   📚 Génération de documentation :
   1. Documentation technique (architecture, API, patterns)
   2. Manuel utilisateur (fonctionnalités, workflows)  
   3. Guide développeur front-end (API, intégration)
   4. Guide DevOps (déploiement, monitoring)
   5. Aucune documentation
   ```
   
   STOP → "Type de documentation ? (1-5)"

2. **Génération Collaborative :**

   **Option 1 - Documentation Technique :**
   - **Agent Analyzer** : Architecture et patterns
   - **Agent Implementer** : APIs et structures de données  
   - **Agent Validator** : Métriques et contraintes
   - Génération dans `docs/technical/[TASK_SLUG].md`

   **Option 2 - Manuel Utilisateur :**
   - **Agent Analyzer** : Changements visibles
   - **Agent Implementer** : Workflows utilisateur
   - Génération dans `docs/user/[TASK_SLUG].md`

   **Option 3 - Guide Développeur Front-End :**
   - **Agent Implementer** : Endpoints et formats
   - **Agent Validator** : Validation et gestion d'erreurs
   - Génération dans `docs/frontend/[TASK_SLUG].md`

3. **Diagrammes Multi-Agents :**
   - STOP → "Ajouter diagrammes ? (y/n)"
   - **Agent Analyzer** : Diagrammes d'architecture
   - **Agent Implementer** : Flux de données et séquences
   - **Agent Validator** : Diagrammes de test et validation
   - Génération en Mermaid intégrée

4. **Validation Documentation :**
   - STOP → "Documentation générée. La committer ? (y/n)"
   - Commit : `git add docs/ && git commit -m "docs($TASK_SLUG): Add [type] documentation"`

5. **Finalisation :**
   STOP → "Tâche complètement terminée ! Options :
   1. Continuer avec une autre tâche
   2. Retourner au projet principal
   3. Voir les statistiques des tâches accomplies
   Choix (1-3) :"

## Commandes de Maintenance

### Vérification Système
```bash
# Status général
claude "/todo --status"          # Worktrees actifs, orphelines, stats

# Nettoyage
claude "/todo --cleanup"         # Suppression orphelines et nettoyage

# Statistiques  
claude "/todo --stats"           # Métriques des tâches accomplies
```

### Commandes Worktree (dans PHPStorm)
```bash
# Vérification santé worktree
make status                      # Ports, conteneurs, connectivité

# Tests et qualité
make test                        # Suite complète  
make test-filter FILTER="MyTest" # Test spécifique
make quality-check               # PHPStan + Pint + Tests

# Base de données
make migrate                     # Migration fraîche avec seed
make db-reset                    # Reset complet DB worktree

# Debug
make logs                        # Logs des conteneurs
make shell                       # Shell dans conteneur app
```

## Sécurité et Isolation

### Règles Strictes
1. **JAMAIS modifier** les fichiers du projet principal depuis un worktree
2. **Ports dynamiques** calculés automatiquement pour éviter conflits  
3. **Base de données temporaire** en mémoire (tmpfs)
4. **Stack Docker isolée** avec nom unique par worktree
5. **Nettoyage automatique** obligatoire en fin de tâche
6. **Vérification d'intégrité** du projet principal avant suppression

### Validation de Sécurité
```bash
# Exécuté automatiquement avant nettoyage final
verify_main_project_integrity() {
    cd "$(git rev-parse --show-toplevel)"
    
    # Vérifier que le projet principal n'a pas été modifié
    if [[ -n "$(git status --porcelain | grep -v '^?? todos/')" ]]; then
        echo "❌ ERREUR: Projet principal modifié !"
        git status --short
        echo "🛑 Nettoyage annulé pour sécurité"
        return 1
    fi
    
    echo "✅ Projet principal intact"
    return 0
}
```

## Troubleshooting

### Problèmes Courants

**Tests ne passent pas dans le worktree :**
```bash
make status          # Vérifier stack
make db-reset        # Reset DB si nécessaire  
docker compose logs  # Examiner les erreurs
```

**Port déjà utilisé :**
```bash
# Les ports sont dynamiques, mais si conflit :
make cleanup && make setup  # Régénère de nouveaux ports
```

**Worktree corrompu :**
```bash
# Nettoyage forcé
make cleanup
cd ../../../
git worktree remove --force "todos/worktrees/$WORKTREE_ID"
```

**Configuration Docker incohérente :**
```bash
# Vérifier qu'on utilise la bonne stack
pwd  # Doit être dans le worktree
ls -la docker-compose.yml  # Doit exister et être basé sur le template
make status  # Vérifier les conteneurs uniques
```

### Support Multi-Agents
- Chaque agent maintient son propre log dans `agent-[role].log`
- Synchronisation via fichiers partagés dans le worktree
- **Agent Coordinator** résout les conflits entre agents (si présent)
- Rollback automatique en cas d'erreur d'un agent

Ce workflow garantit une isolation parfaite, une approche TDD rigoureuse et une collaboration efficace entre agents spécialisés, tout en préservant l'intégrité du projet principal.