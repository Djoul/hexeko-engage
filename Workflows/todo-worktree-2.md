# Todo Implementation Program Multi-Agents avec Worktrees et MCP Integration
Workflow structuré pour transformer des tâches depuis Todoist, Jira, Sentry ou fichier local en features implémentées avec approche TDD et isolation complète par worktrees.

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : CHECK → SELECT → INIT → REFINE → BRAINSTORM → IMPLEMENT → COMMIT → DOCUMENT
- Vous DEVEZ obtenir la confirmation de l'utilisateur à chaque STOP
- Vous DEVEZ toujours utiliser l'approche TDD (Test-Driven Development)
- Vous DEVEZ utiliser des worktrees avec isolation Docker complète
- Vous NE DEVEZ PAS modifier les fichiers de configuration du projet principal
- Le dossier `/todos` est en gitignore, chercher dans le répertoire principal depuis le worktree (../../)
- **AUCUNE action automatique** : Toujours demander confirmation avant de reprendre ou démarrer une tâche
- **INTERDICTION ABSOLUE DE COMMIT** : Seul l'utilisateur peut exécuter git commit. Aucun commit automatique n'est autorisé

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

**GARANTIE SÉCURITÉ**:
- Aucune modification des fichiers de configuration du projet principal (.env, docker-compose.yml, etc.)
- Le dossier .idea du projet principal est protégé et ne sera JAMAIS écrasé lors d'un merge
- Tous les fichiers de configuration système sont exclus du versioning dans le worktree

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

### CHECK (Vérification des Tâches Orphelines)
1. **Vérification des tâches orphelines :**
   ```bash
   check_orphaned_tasks() {
       orphaned=()
       for task_dir in todos/worktrees/*/; do
           if [[ -f "$task_dir/task.md" ]]; then
               pid=$(grep "^**Agent PID:" "$task_dir/task.md" | cut -d' ' -f3)
               if ! ps -p "$pid" >/dev/null 2>&1; then
                   task_name=$(basename "$task_dir")
                   task_title=$(head -1 "$task_dir/task.md" | sed 's/^# //')
                   status=$(grep "^**Status:**" "$task_dir/task.md" | cut -d' ' -f2)
                   orphaned+=("$task_name:$task_title:$status")
               fi
           fi
       done
   }
   ```

2. **Si tâches orphelines détectées :**
   ```
   === TÂCHES ORPHELINES DÉTECTÉES ===

   Des tâches ont été abandonnées :
   1. add-email-validation-20250724: Add email validation to user registration [Status: InProgress]
   2. fix-dashboard-mobile-20250723: Fix dashboard mobile display [Status: Refining]

   Options disponibles :
   - Entrer le numéro pour reprendre une tâche spécifique
   - 'new' pour ignorer et créer une nouvelle tâche
   - 'clean' pour nettoyer toutes les orphelines
   - 'list' pour plus de détails sur chaque tâche
   ```

   STOP → "Que souhaitez-vous faire ? (numéro/new/clean/list) :"

3. **Actions selon le choix :**

   **Si numéro sélectionné (reprise) :**
   - STOP → "Vous avez choisi de reprendre : '[task_title]'. Confirmer ? (y/n)"
   - Si 'y' :
     - Afficher le statut actuel et l'analyse
     - STOP → "Ouvrir dans PHPStorm pour reprendre ? (y/n)"
     - Si 'y' : Ouvrir l'éditeur et afficher les instructions
     - Si 'n' : Proposer de continuer dans le terminal actuel
   - Si 'n' : Retour au menu des orphelines

   **Si 'new' :**
   - STOP → "Confirmer l'abandon des tâches orphelines et création d'une nouvelle ? (y/n)"
   - Si 'y' : Continuer vers SELECT
   - Si 'n' : Retour au menu

   **Si 'clean' :**
   - STOP → "⚠️ ATTENTION : Supprimer définitivement TOUTES les tâches orphelines ? (yes/no)"
   - Nécessite 'yes' complet pour confirmer
   - Si confirmé : Nettoyer et afficher le résumé

   **Si 'list' :**
   - Afficher détails complets de chaque orpheline
   - Retour au menu

4. **Si aucune orpheline :**
   - Message : "✅ Aucune tâche orpheline détectée"
   - Continuer automatiquement vers SELECT

### SELECT (Sélection de Source)
1. **Présentation des sources :**
   ```
   📋 Sélection de la source de tâche
   ===================================

   D'où voulez-vous récupérer une tâche ?

   1. 📋 Fichier local (todos/todos.md)
   2. ✅ Todoist (projets/tâches)
   3. 🎯 Jira (issues/epics/stories)
   4. 🚨 Sentry (erreurs à corriger)

   💡 Tip : Utilisez 'info' pour plus de détails sur chaque source
   ```

   STOP → "Votre choix (1-4 ou 'info') :"

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
       echo "📝 Aucun fichier trouvé. Création de todos.md..."
       STOP → "Créer un fichier todos.md par défaut ? (y/n)"

       if [[ "$response" == "y" ]]; then
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
       else
           echo "❌ Aucun fichier disponible. Retour au menu."
           # Retour à SELECT
       fi
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
   📋 Tâches dans todos.md :

   == En Cours ==
   1. Ajouter validation email sur User model
   2. Corriger bug affichage dashboard mobile

   == Ideas ==
   3. Améliorer performance des requêtes Product
   4. Ajouter système de notifications push

   💡 Format : [Section] Description
   ```
   - STOP → "Quelle tâche sélectionner ? (numéro)"
   - STOP → "Confirmer la sélection : '[task_title]' ? (y/n)"
   - Si 'y' : Marquer comme "- [x]" et commit
   - Si 'n' : Retour à la liste des tâches

   **Si création nouveau fichier (option 5) :**
   - STOP → "Nom du nouveau fichier ? (ex: 'sprint-2024', 'urgent-fixes')"
   - STOP → "Créer 'todos/[nom].md' ? (y/n)"
   - Si 'y' : Créer avec template et ouvrir éditeur
   - STOP → "Fichier créé et ouvert. Ajoutez vos tâches et sauvegardez. Continuer ? (y/n)"
   - Relire et présenter les tâches

   **Option 2 - Todoist:**
   - Utiliser MCP pour lister les projets
   - STOP → "Quel projet Todoist ? (nom ou ID)"
   - Lister les tâches avec détails
   - STOP → "Quelle tâche sélectionner ? (numéro)"
   - Afficher les détails complets
   - STOP → "Confirmer cette tâche Todoist ? (y/n)"

   **Option 3 - Jira:**
   - Utiliser MCP pour lister les projets
   - STOP → "Quel projet Jira ? (clé)"
   - STOP → "Type d'élément ? (issue/epic/story/bug/task/all)"
   - Lister avec filtres
   - STOP → "Quel élément sélectionner ? (numéro ou clé)"
   - Afficher détails complets
   - STOP → "Confirmer cet élément Jira ? (y/n)"

   **Option 4 - Sentry:**
   - Utiliser MCP pour lister les projets
   - STOP → "Quel projet Sentry ? (nom)"
   - Lister erreurs récentes
   - STOP → "Quelle erreur sélectionner ? (numéro)"
   - Afficher stack trace et contexte
   - STOP → "Confirmer cette erreur Sentry ? (y/n)"

3. **Évaluation automatique par Agent Evaluator :**
   ```
   📊 Analyse de la tâche sélectionnée
   ===================================

   Titre : [titre]
   Source : [source]
   Type détecté : [Bug/Feature/Refactor/Doc]

   Évaluation de complexité :
   - Mots-clés : [keywords identifiés]
   - Fichiers estimés : [1-2 / 3-5 / 6+]
   - Complexité : [Simple/Moyen/Complexe]
   - Agents recommandés : [2/3/4]
   - Temps estimé : [30min-1h / 1-2h / 2-4h]
   - Impact : [Low/Medium/High]

   Recommandation : Déployer [N] agents pour cette tâche
   ```

   STOP → "Procéder avec cette tâche et [N] agents ? (y/n/adjust)"

   Si 'adjust' :
   ```
   Ajustement du nombre d'agents :
   2 - Mode rapide (Analyzer + Implementer)
   3 - Mode standard (+ Validator)
   4 - Mode expert (+ Coordinator)
   ```
   STOP → "Nombre d'agents souhaité ? (2-4)"

### INIT (Initialisation Robuste)
1. **Génération ID descriptif et Ports :**
   ```bash
   # Génération d'un nom de worktree descriptif
   # Format : [task-slug]-[date]-[short-id]

   # Nettoyer le titre pour en faire un slug
   TASK_SLUG=$(echo "$task_title" | \
               sed 's/[^a-zA-Z0-9]/-/g' | \
               tr '[:upper:]' '[:lower:]' | \
               sed 's/--*/-/g' | \
               sed 's/^-//' | \
               sed 's/-$//' | \
               cut -c1-30)  # Limiter à 30 caractères

   # Date format YYMMDD pour compacité
   DATE_PART=$(date +%y%m%d)

   # ID court pour unicité (4 caractères)
   SHORT_ID=$(echo $RANDOM | md5sum | head -c 4)

   # Nom final du worktree
   WORKTREE_NAME="${TASK_SLUG}-${DATE_PART}-${SHORT_ID}"

   # Exemple : "add-email-validation-250127-a3f2"

   echo "📁 Nom du worktree : $WORKTREE_NAME"
   ```

   STOP → "Créer le worktree '$WORKTREE_NAME' ? (y/n/rename)"

   Si 'rename' :
   - STOP → "Nouveau nom (format: description-date-id) :"
   - Valider le format et redemander confirmation

2. **Configuration des Ports Dynamiques :**
   ```bash
   # Ports basés sur le hash du nom pour cohérence
   port_hash=$(echo -n "$WORKTREE_NAME" | md5sum | head -c 8)
   port_suffix=$((0x${port_hash:0:4} % 1000))

   DB_PORT_WORKTREE=$((5500 + $port_suffix))
   REDIS_PORT_WORKTREE=$((6500 + $port_suffix))
   WEB_PORT_WORKTREE=$((8000 + $port_suffix))

   echo "🔌 Ports assignés :"
   echo "   - Web : $WEB_PORT_WORKTREE"
   echo "   - DB : $DB_PORT_WORKTREE"
   echo "   - Redis : $REDIS_PORT_WORKTREE"
   ```

3. **Création Worktree avec Branche :**
   ```bash
   # Nom de branche basé sur le type et le slug
   branch_name="task/$TASK_SLUG"

   echo "🌿 Création de la branche : $branch_name"
   echo "📁 Dans le dossier : todos/worktrees/$WORKTREE_NAME"
   ```

   STOP → "Confirmer la création ? (y/n)"

   ```bash
   git worktree add -b "$branch_name" "todos/worktrees/$WORKTREE_NAME" HEAD
   cd "todos/worktrees/$WORKTREE_NAME"
   ```

4. **Configuration .env Worktree :**
   ```bash
   echo "🔧 Configuration de l'environnement isolé..."

   # Copier le gitignore principal d'abord
   cp ../../../.gitignore .gitignore

   # Copier et adapter l'environnement
   cp ../../../.env .env.base
   cp ../../../.env .env

   # Adapter pour isolation
   source ../../templates/setup-worktree-env.sh
   setup_worktree_env_minimal "$WORKTREE_NAME" "$(git rev-parse --show-toplevel)"
   ```

5. **Configuration Docker Isolée :**
   ```bash
   echo "🐳 Configuration Docker isolée..."

   # Vérification du contexte
   if [[ ! "$(pwd)" =~ todos/worktrees/ ]]; then
       echo "❌ ERREUR: Mauvais répertoire"
       exit 1
   fi

   # Copie des templates
   cp ../../templates/docker-compose.worktree.yml docker-compose.yml
   cp ../../templates/phpunit.worktree.xml phpunit.xml
   cp ../../templates/Makefile.worktree Makefile

   # Substitution des variables
   export WORKTREE_NAME DB_PORT_WORKTREE REDIS_PORT_WORKTREE WEB_PORT_WORKTREE
   envsubst < docker-compose.yml > docker-compose.yml.tmp && mv docker-compose.yml.tmp docker-compose.yml

   # Ajouter au gitignore existant (du projet principal)
   cat >> .gitignore << EOF

# === WORKTREE SPECIFIC IGNORES ===
# Configuration worktree (NE JAMAIS COMMITTER)
.env
.env.base
.env.backup
.env.worktree
docker-compose.yml
docker-compose.*.yml
phpunit.xml
phpunit.*.xml
Makefile
Makefile.*

# Fichiers de config du projet principal (INTERDICTION DE MODIFICATION)
# Ces fichiers ne doivent JAMAIS être modifiés dans le worktree
rector.php
phpstan.neon
pint.json
.php-cs-fixer.php
.php-cs-fixer.dist.php

# Fichiers Docker du projet principal (INTERDICTION DE MODIFICATION)
Dockerfile
Dockerfile.*
docker/
.docker/

# Scripts et configs système
*.sh
scripts/
bin/

# Fichiers de travail worktree
analysis.md
multi-agent-logs/
task.md

# Environnements et secrets
.env
.env.*
!.env.example
*.key
*.pem
*.crt

# IDE et OS - PROTECTION ABSOLUE
# Le dossier .idea du projet principal NE DOIT JAMAIS être modifié/écrasé
.idea/
.idea/*
*.iml
*.ipr
*.iws
.vscode/
.vscode/*
*.swp
*.swo
.DS_Store
Thumbs.db

# Builds et caches
build/
dist/
public/build/
public/hot
public/mix-manifest.json
storage/*.key
bootstrap/cache/*
!bootstrap/cache/.gitignore
EOF

   # NE PAS ajouter .gitignore automatiquement - l'utilisateur décidera
   echo "📝 Gitignore mis à jour. L'utilisateur devra l'ajouter manuellement si nécessaire."

   # Configuration du .git/info/exclude pour éviter de committer les fichiers de config
   echo "🔒 Configuration de l'exclusion locale des fichiers de configuration..."

   # Trouver le vrai dossier .git pour ce worktree
   GIT_DIR=$(cat .git | sed 's/gitdir: //')
   EXCLUDE_FILE="$GIT_DIR/info/exclude"

   # Ajouter les exclusions locales pour ce worktree
   cat >> "$EXCLUDE_FILE" << 'EOF'

# Worktree-specific excludes
.idea/
.env
.env.base
.env.worktree
.gitignore
Makefile
docker-compose.yml
docker-compose.*.yml
phpunit.xml
phpunit.*.xml
rector.php
phpstan.neon
pint.json
.php-cs-fixer.php
.php-cs-fixer.dist.php
Dockerfile
Dockerfile.*
docker/
.docker/
*.sh
scripts/
EOF

   echo "✅ Fichiers de configuration exclus localement via .git/info/exclude"
   ```

6. **Initialisation task.md :**
   ```markdown
   # [Task Title]
   **Status:** InProgress
   **Agent PID:** [Bash(echo $PPID)]
   **Source:** [Todoist/Jira/Sentry/Local]
   **Source ID:** [ID si applicable]
   **Worktree:** [WORKTREE_NAME]
   **Branch:** [branch_name]
   **Created:** [date]
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

7. **Setup de la Stack Worktree :**
   ```bash
   echo "🚀 Démarrage de la stack Docker isolée..."
   ```

   STOP → "Lancer la stack Docker ? (y/n)"

   ```bash
   make setup

   # Vérification
   make status
   ```

8. **Préparation pour commit (MANUEL UNIQUEMENT) :**
   ```bash
   echo "📝 Fichiers prêts pour le commit initial :"
   git status --short

   echo ""
   echo "💡 Suggestion de message de commit :"
   echo "task($TASK_SLUG): Initialize worktree

   - Task: $task_title
   - Worktree: $WORKTREE_NAME
   - Ports: Web:$WEB_PORT_WORKTREE DB:$DB_PORT_WORKTREE Redis:$REDIS_PORT_WORKTREE
   - Agents: $recommended_agents
   - Source: $source"

   echo ""
   echo "⚠️  RAPPEL: Seul l'utilisateur peut committer."
   echo "Pour committer, l'utilisateur peut exécuter :"
   echo "git add ."
   echo "git commit -m \"[message ci-dessus]\""
   echo "git push -u origin \"$branch_name\""
   ```

9. **Transition vers l'Éditeur :**
   ```
   🏗️ Worktree configuré avec succès !
   =====================================

   📁 Worktree : $WORKTREE_NAME
   🌿 Branche : $branch_name
   🔒 Isolation : Garantie

   Stack Docker :
   - Web : http://localhost:$WEB_PORT_WORKTREE
   - DB : localhost:$DB_PORT_WORKTREE
   - Redis : localhost:$REDIS_PORT_WORKTREE

   Options :
   1. Ouvrir dans PHPStorm (recommandé)
   2. Continuer dans le terminal actuel
   3. Afficher les instructions détaillées
   ```

   STOP → "Votre choix (1-3) :"

   **Si option 1 :**
   ```
   📝 Instructions PHPStorm
   =======================

   1. Ouverture automatique dans 3 secondes...
      Chemin : /absolute/path/to/todos/worktrees/$WORKTREE_NAME/

   2. Dans le terminal PHPStorm :
      cd /absolute/path/to/todos/worktrees/$WORKTREE_NAME/
      make status  # Vérifier la stack
      claude "/todo --resume"

   3. URLs de développement :
      - App : http://localhost:$WEB_PORT_WORKTREE
      - DB : localhost:$DB_PORT_WORKTREE

   4. Commandes utiles :
      - make test : Lancer les tests
      - make quality-check : Vérifier la qualité
      - make shell : Accéder au conteneur
   ```

   STOP → "PHPStorm va s'ouvrir. Prêt ? (y/n)"

   Si 'y' :
   - Exécuter : `phpstorm "/absolute/path/to/todos/worktrees/$WORKTREE_NAME/" &`
   - Message : "✅ PHPStorm ouvert. Suivez les instructions ci-dessus."
   - Terminer le workflow

   **Si option 2 :**
   - Message : "✅ Continuation dans le terminal actuel"
   - Continuer vers REFINE

   **Si option 3 :**
   - Afficher guide complet d'utilisation
   - Retour au menu

### REFINE (Multi-Agents Parallèles)
1. **Confirmation avant déploiement :**
   ```
   🤖 Déploiement Multi-Agents
   ===========================

   Prêt à déployer [N] agents pour analyser la tâche :
   - Agent Analyzer : Analyse du codebase
   - Agent Implementer : Stratégie TDD
   [- Agent Validator : Quality checks]
   [- Agent Coordinator : Orchestration]

   Cette phase va :
   1. Scanner le codebase principal
   2. Identifier les patterns existants
   3. Planifier l'implémentation TDD
   4. Définir les critères de qualité
   ```

   STOP → "Lancer l'analyse multi-agents ? (y/n)"

2. **Agent Analyzer - Recherche Parallèle :**
   ```bash
   echo "🔍 Agent Analyzer : Démarrage de l'analyse..."

   # Recherches parallèles depuis ../../
   find ../../app -name "*.php" -path "*/Models/*" | xargs grep -l "pattern" &
   find ../../app -name "*.php" -path "*/Services/*" | xargs grep -l "logic" &
   find ../../app/Http -name "*.php" | xargs grep -l "endpoint" &
   find ../../database -name "*.php" | xargs grep -l "table" &

   wait

   echo "✅ Agent Analyzer : Analyse terminée"
   ```

3. **Agent Implementer - Analyse des Tests :**
   ```bash
   echo "🧪 Agent Implementer : Analyse des patterns de test..."

   # Patterns de tests
   find ../../tests -name "*Test.php" | xargs grep -l "functionality" &
   find ../../database/factories -name "*.php" &
   find ../../database/seeders -name "*.php" &

   wait

   echo "✅ Agent Implementer : Stratégie TDD définie"
   ```

4. **Synthèse et Validation :**
   - Générer analysis.md avec tous les findings
   - STOP → "Analyse complète. Voir analysis.md. Valider ? (y/n/review)"

   Si 'review' :
   - Afficher le contenu section par section
   - Permettre des ajustements

5. **Mise à jour task.md :**
   - Remplir les sections Multi-Agent Analysis
   - Préparer pour commit : `git add task.md analysis.md`
   - Message suggéré : `"task($TASK_SLUG): Multi-agent analysis"`
   - STOP → "Fichiers prêts. Voulez-vous committer maintenant ? (Commande : git commit -m '[message]')"

### BRAINSTORM (Approches Collaboratives)
1. **Collecte d'informations :**
   - STOP → "Documentation technique disponible ? (y/n)"
   - Si 'y' : STOP → "Décrivez ou fournissez les liens :"
   - STOP → "Contraintes spécifiques ? (performance/sécurité/compatibilité/none)"
   - Si contraintes : STOP → "Détaillez les contraintes :"
   - STOP → "Exemples similaires dans le codebase ? (y/n)"
   - Si 'y' : Proposer de rechercher ou demander références

2. **Génération d'approches par les agents :**
   ```
   🎯 Approches Proposées par les Agents
   =====================================
   ```

   [Présenter 2-3 approches détaillées]

   STOP → "Quelle approche préférez-vous ? (A/B/C/discuss)"

   Si 'discuss' :
   - STOP → "Quels aspects voulez-vous discuter ?"
   - Dialogue interactif sur l'approche

3. **Validation finale :**
   - Présenter l'approche raffinée
   - STOP → "Approche finale validée ? (y/n)"
   - Si 'n' : Retour aux options

### IMPLEMENT (TDD Strict Multi-Agents)
Pour chaque cycle TDD :

1. **Annonce du cycle :**
   ```
   🔄 Cycle TDD #[N] : [Description]
   =================================

   Objectif : [Ce que ce cycle va accomplir]
   Agent lead : [Agent responsable]
   ```

   STOP → "Démarrer ce cycle ? (y/n/skip)"

2. **Phase RED :**
   - Agent Implementer propose le test
   - STOP → "Test proposé correct ? (y/n/edit)"
   - Exécuter le test
   - STOP → "Le test échoue comme prévu ? (y/n)"

3. **Phase GREEN :**
   - Agent Implementer propose l'implémentation minimale
   - STOP → "Implémentation proposée ? (y/n/edit)"
   - Exécuter les tests
   - STOP → "Tests passent ? (y/n)"

4. **Phase REFACTOR :**
   - Suggestions de refactoring par les agents
   - STOP → "Appliquer refactoring ? (y/n/custom)"
   - Validation finale
   - STOP → "Cycle terminé. Préparer les fichiers pour commit ? (y/n)"
   - Si 'y' : `git add [fichiers modifiés]` et suggérer message de commit
   - Rappel : "Seul l'utilisateur peut exécuter git commit"

5. **Quality Checks :**
   - Après tous les cycles
   - STOP → "Lancer quality check complet ? (y/n)"
   - Si erreurs : proposer corrections avec confirmation

### REVIEW (Validation et Préparation PR)
1. **Résumé des changements :**
   ```
   📝 Résumé de l'Implémentation
   =============================

   Tâche : [task_title]
   Durée : [duration]
   Cycles TDD : [count]

   Changements :
   - [Liste des changements principaux]

   Tests :
   - Ajoutés : [count]
   - Modifiés : [count]
   - Coverage : [percent]%

   Quality :
   - PHPStan : [status]
   - Tests : [status]
   - Code style : [status]
   ```

   STOP → "Informations correctes ? (y/n)"

2. **Préparation de la PR :**
   - STOP → "Préparer les fichiers pour la Pull Request ? (y/n)"
   - Si 'y' :
     - Afficher `git status`
     - Suggérer message de commit final
     - Afficher commandes pour l'utilisateur :
       ```
       git add -A
       git commit -m "[message suggéré]"
       git push
       gh pr create --title "[titre]" --body "[description]"
       ```
   - STOP → "L'utilisateur peut maintenant créer la PR avec les commandes ci-dessus"

3. **Mise à jour sources :**
   - STOP → "Mettre à jour [source] comme complété ? (y/n)"
   - Si 'y' : Update via MCP ou local

4. **Nettoyage :**
   ```
   🏁 Tâche Terminée !
   ===================

   Worktree : $WORKTREE_NAME
   PR : [URL]

   Options de nettoyage :
   1. Supprimer le worktree maintenant
   2. Garder pour review (suppression manuelle)
   3. Archiver et garder
   ```

   STOP → "Votre choix (1-3) :"

   Si option 1 :
   - STOP → "⚠️ Confirmer suppression du worktree ? (yes/no)"
   - Nécessite 'yes' complet
   - Vérifier d'abord : `git status`
   - Si modifications non committées :
     - STOP → "⚠️ Modifications non committées détectées. L'utilisateur doit d'abord les committer. Continuer ?"
   - Effectuer nettoyage complet avec validations

### DOCUMENT (Documentation)
1. **Proposition de documentation :**
   ```
   📚 Documentation Suggérée
   ========================

   Basée sur les changements, je recommande :
   - [Type de documentation recommandé]
   - Raison : [Justification]

   Options disponibles :
   1. Documentation technique
   2. Manuel utilisateur
   3. Guide API
   4. Guide DevOps
   5. Aucune documentation
   6. Documentation custom
   ```

   STOP → "Quel type générer ? (1-6) :"

2. **Génération :**
   - Si choix fait : Générer avec les agents spécialisés
   - STOP → "Documentation générée. La valider ? (y/n/edit)"
   - Si validée :
     - Préparer : `git add [fichiers documentation]`
     - STOP → "Documentation prête. L'utilisateur peut la committer avec : git commit -m '[message]'"

3. **Finalisation :**
   ```
   ✅ Workflow Terminé !
   ====================

   Résumé :
   - Tâche : [title]
   - Worktree : $WORKTREE_NAME
   - PR : [URL]
   - Documentation : [Status]

   Options :
   1. Nouvelle tâche
   2. Retour au projet principal
   3. Statistiques
   4. Terminer
   ```

   STOP → "Votre choix (1-4) :"

## Commandes de Maintenance

### Vérification Système
```bash
# Status général avec confirmation
claude "/todo --status"          # Affiche status, demande action

# Nettoyage avec confirmation
claude "/todo --cleanup"         # Liste et demande confirmation

# Statistiques
claude "/todo --stats"           # Métriques des tâches
```

### Commandes Worktree
```bash
# Toujours dans le worktree
make status                      # État de la stack
make test                        # Tests
make quality-check              # Qualité complète
make shell                      # Shell dans conteneur
```

## Règles de Sécurité

1. **Confirmation obligatoire** pour toute action
2. **Noms descriptifs** pour les worktrees
3. **Isolation garantie** entre worktrees
4. **Validation** avant suppression
5. **Traçabilité** complète des actions
6. **INTERDICTION ABSOLUE** de commits automatiques - Seul l'utilisateur commit
7. **Protection des configs** - Fichiers système du projet principal en gitignore
8. **Copie du gitignore principal** - Héritage des exclusions du projet
9. **Protection .idea** - Le dossier .idea du projet principal ne peut JAMAIS être modifié/écrasé lors d'un merge

Ce workflow garantit un contrôle total de l'utilisateur avec des confirmations à chaque étape critique et des noms de worktree descriptifs pour une meilleure organisation !
