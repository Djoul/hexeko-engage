# Documentation Generation Program Multi-Agent Hybride avec MCP Integration
Workflow structuré pour générer de la documentation à partir de tâches existantes depuis Todoist, Jira, Sentry ou analyse automatique, avec système multi-agents adaptatif.

## Structure des Fichiers Générés

```
todos/
├── documentation/
│   ├── active/
│   │   └── 2025-07-25-143022-api-validation/
│   │       ├── 01-task.md                    # Définition et suivi
│   │       ├── 02-source-analysis.md         # Analyse source (commits, code)
│   │       ├── 03-documentation-plan.md      # Plan structuré multi-agents
│   │       ├── 04-generated-docs/            # Documentation générée
│   │       │   ├── technical/
│   │       │   ├── user/
│   │       │   └── api/
│   │       ├── 05-review-log.md              # Revue et validation
│   │       ├── multi-agent-logs/             # 🆕 Logs des agents
│   │       │   ├── agent-coordination.md
│   │       │   ├── agent-analyzer.md
│   │       │   ├── agent-documenter.md
│   │       │   ├── agent-reviewer.md
│   │       │   └── agent-publisher.md
│   │       └── artifacts/                    # PDFs, exports, diagrammes
│   ├── published/                            # Documentation finalisée
│   ├── done/                                 # Archives complétées
│   └── templates/                            # Templates réutilisables
│       ├── api-endpoint-template.md
│       ├── database-schema-template.md
│       ├── business-logic-template.md
│       ├── troubleshooting-template.md
│       └── user-guide-template.md
```

## Système Multi-Agent Hybride

### **Agents Spécialisés Documentation**

1. **Agent-Analyzer** : Analyse code, commits, changements
   - Extraction automatique des modifications
   - Identification des patterns et conventions
   - Détection des breaking changes
   - Analyse de couverture documentation

2. **Agent-Documenter** : Génération de contenu
   - Rédaction technique précise basée UNIQUEMENT sur le code réel
   - Exemples de code extraits directement des fichiers sources
   - Diagrammes Mermaid reflétant l'architecture réelle
   - **JAMAIS** d'invention ou de supposition
   - Validation utilisateur pour CHAQUE section générée

3. **Agent-Reviewer** : Validation qualité
   - **Vérification ligne par ligne** contre le code source
   - Détection de toute incohérence ou invention
   - Validation que TOUS les exemples sont testables
   - Conformité avec CLAUDE.MD
   - **Flag immédiat** si contenu non vérifié dans le code

4. **Agent-Publisher** : Publication et distribution
   - Conversion formats (MD → PDF)
   - Mise à jour sources externes
   - Notifications stakeholders
   - Versioning et archivage

### **Déclenchement Automatique Multi-Agent**

```markdown
## Critères de Déploiement
- **Complexité Détectée :**
  - Simple (1-2 composants) → 2 agents (Analyzer + Documenter)
  - Moyen (3-5 composants) → 3 agents (+ Reviewer)
  - Complexe (6+ composants/API complète) → 4 agents (+ Publisher)

- **Type de Documentation :**
  - Quick fix/patch → 2 agents
  - Nouvelle feature → 3 agents
  - Architecture/API → 4 agents obligatoires

- **Override Utilisateur :**
  - Mode minimal → 2 agents même si complexe
  - Mode complet → 4 agents même si simple
```

## Workflow Optimisé

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : INIT → SELECT → REFINE → DOCUMENT → PUBLISH
- Vous DEVEZ obtenir la confirmation de l'utilisateur à chaque STOP et pour CHAQUE contenu généré
- Vous NE DEVEZ PAS modifier de code, seulement générer de la documentation
- Vous DEVEZ rester sur la branche courante sans créer de worktree
- **🚨 EXACTITUDE ABSOLUE** : La documentation DOIT être 100% fidèle au code réel
  - JAMAIS d'invention ou d'extrapolation
  - TOUJOURS vérifier dans le code source avant d'écrire
  - En cas de doute : STOP → "Besoin de clarification : [question précise]"
  - L'utilisateur a TOUJOURS le dernier mot sur le contenu
- **NOUVEAUTÉ :** Multi-agents déployés selon complexité avec coordination automatique

### INIT
1. **Lire `CLAUDE.MD` en intégralité**
   - Ce fichier contient les instructions spécifiques au projet et contexte
   - Si absent :
     - STOP → "Créez CLAUDE.MD avec les instructions du projet pour une meilleure documentation"

2. **Analyse du Contexte Projet :**
   ```bash
   # Analyse automatique de l'état de documentation
   echo "🔍 Analyse du contexte projet..."

   # Détecter les changements récents non documentés
   recent_changes=$(git log --since="7 days ago" --pretty=format:"%h - %s" --no-merges)
   undocumented_prs=$(gh pr list --state merged --limit 10 --json number,title,mergedAt \
     | jq -r '.[] | select(.mergedAt > (now - 604800)) | "\(.number) - \(.title)"')

   # Vérifier la couverture documentation
   total_endpoints=$(grep -r "Route::" routes/ | wc -l)
   documented_endpoints=$(grep -r "POST\|GET\|PUT\|DELETE" docs/api/ | wc -l)
   coverage=$((documented_endpoints * 100 / total_endpoints))

   # Détecter les composants non documentés
   find app/Http/Controllers -name "*.php" | while read controller; do
       controller_name=$(basename "$controller" .php)
       if ! grep -q "$controller_name" docs/; then
           undocumented_controllers+=("$controller_name")
       fi
   done
   ```

3. **Vérifier les tâches de documentation orphelines :**
   ```bash
   mkdir -p todos/documentation/active todos/documentation/done todos/documentation/published
   orphaned_count=0
   for d in todos/documentation/active/*/01-task.md; do
       [ -f "$d" ] || continue
       pid=$(grep "^**Agent PID:" "$d" | cut -d' ' -f3)
       [ -n "$pid" ] && ps -p "$pid" >/dev/null 2>&1 && continue
       orphaned_count=$((orphaned_count + 1))
       doc_name=$(basename $(dirname "$d"))
       doc_title=$(head -1 "$d" | sed 's/^# Documentation: //')
       status=$(grep "^**Status:" "$d" | cut -d' ' -f2)
       echo "$orphaned_count. $doc_name [$status]: $doc_title"
   done
   ```

   - Si des tâches orphelines existent :
     - Présenter la liste numérotée avec status
     - STOP → "Actions possibles :
       - [numéro] : Reprendre une tâche spécifique
       - finaliser : Finaliser toutes et publier
       - archiver : Déplacer vers done/
       - supprimer : Supprimer définitivement
       - ignorer : Continuer avec nouvelle documentation
       Votre choix :"

### SELECT (Enhanced Multi-Source with Branch Detection)

1. **Détection Automatique de Branche :**
   ```bash
   # Vérifier si on est sur une branche feature
   current_branch=$(git branch --show-current)

   if [[ "$current_branch" != "main" && "$current_branch" != "master" ]]; then
       echo "🌿 Branche détectée : $current_branch"

       # Analyser les changements de la branche
       commits_count=$(git rev-list --count origin/main.."$current_branch" 2>/dev/null || echo 0)
       files_changed=$(git diff --name-only origin/main.."$current_branch" 2>/dev/null | wc -l)

       echo "📊 Statistiques de la branche :"
       echo "- Commits : $commits_count"
       echo "- Fichiers modifiés : $files_changed"
       echo "- Créée : $(git log --reverse --format=%ar "$current_branch" | head -1)"

       echo ""
       echo "💡 Option rapide disponible :"
       echo "0. 🌿 Documenter cette branche ($current_branch)"
       echo ""
   fi
   ```

2. **Présentation des Recommandations Contextuelles :**
   ```markdown
   📊 État de la documentation :
   - Couverture API : [coverage]%
   - PRs non documentées : [count]
   - Controllers sans doc : [list]
   - Dernière mise à jour : [date]

   ⚠️ Documentation prioritaire détectée :
   - [ ] PR #234 "Add payment integration" - Fusionnée il y a 2 jours
   - [ ] Endpoint /api/v2/invoices non documenté
   - [ ] Migration "add_subscription_tiers" sans guide

   💡 Suggestions prioritaires :
   - Documentation API urgente (couverture < 70%)
   - Guide utilisateur pour payment integration
   - Mise à jour architecture (> 3 mois)
   ```

3. **Sélection Multi-Sources avec Priorités :**
   ```
   📋 Source de documentation :

   🌿 CURRENT CONTEXT
   0. 🌿 Branche actuelle : [current_branch] [commits_count commits, files_changed files]

   🔥 PRIORITÉ HAUTE (Impact utilisateur direct)
   1. 🎯 Jira - Issues "Done" non documentées [3 items]
   2. 🚨 Sentry - Erreurs résolues avec workaround [2 items]
   3. 🔄 GitHub - PRs mergées sans documentation [5 items]

   📝 STANDARD (Documentation planifiée)
   4. ✅ Todoist - Tâches de documentation [8 items]
   5. 📋 Backlog local - todos/documentation-needed.md [4 items]

   🏗️ PROACTIF (Amélioration continue)
   6. 🔍 Analyse automatique - Code non documenté
   7. 📊 Métriques - APIs les plus utilisées sans doc
   8. 💬 Feedback - Demandes utilisateurs/support

   ⚡ QUICK ACTIONS
   9. 📦 Mode Batch - Documentation groupée
   10. 🤖 Mode Découverte - Analyse complète auto
   11. 🌿 Branche spécifique (saisir nom)
   12. 🔍 Comparer branches (diff documentation)

   Entrez votre choix (0-12) ou raccourci :
   - 'urgent' : Tâches P1 non documentées
   - 'api:[pattern]' : Endpoints matching pattern
   - 'sprint:current' : Issues du sprint actuel
   - 'branch:[name]' : Documenter une branche spécifique
   ```

   STOP → "Votre sélection :"

4. **Traitement selon le choix :**

   **Option 0 ou 11 - Documentation depuis Branche :**
   ```bash
   # Fonction d'analyse de branche
   analyze_branch_for_documentation() {
       branch_name="$1"

       echo "🌿 Analyse de la branche : $branch_name"

       # Vérifier que la branche existe
       if ! git show-ref --verify --quiet "refs/heads/$branch_name"; then
           echo "❌ Branche '$branch_name' introuvable"
           STOP → "Vérifier le nom ou 'git branch -a' pour lister"
           return 1
       fi

       # Checkout si nécessaire
       if [[ "$(git branch --show-current)" != "$branch_name" ]]; then
           echo "📍 Passage sur la branche $branch_name..."
           git checkout "$branch_name"
       fi

       # Analyse approfondie
       echo "🔍 Analyse des changements..."

       # 1. Extraire métadonnées
       branch_info=$(cat << EOF
   # Branch Analysis: $branch_name

   ## Metadata
   - **Branch:** $branch_name
   - **Base:** $(git merge-base origin/main "$branch_name")
   - **Commits:** $(git rev-list --count origin/main.."$branch_name")
   - **Author:** $(git log --format='%an' "$branch_name" | sort | uniq -c | sort -rn | head -1 | sed 's/^ *//')
   - **Created:** $(git log --reverse --format=%ai "$branch_name" | head -1)
   - **Last Update:** $(git log -1 --format=%ai "$branch_name")
   EOF
   )

       # 2. Analyser le type de changements
       detect_change_type() {
           # Nouvelles routes/endpoints
           new_routes=$(git diff origin/main.."$branch_name" -- routes/ | grep "^+" | grep -E "Route::|->name\(" | wc -l)

           # Nouveaux modèles
           new_models=$(git diff origin/main.."$branch_name" -- app/Models/ | grep "^+++ b" | wc -l)

           # Nouvelles migrations
           new_migrations=$(git diff origin/main.."$branch_name" -- database/migrations/ | grep "^+++ b" | wc -l)

           # Controllers modifiés
           modified_controllers=$(git diff --name-only origin/main.."$branch_name" | grep "app/Http/Controllers" | wc -l)

           # Services ajoutés/modifiés
           services_changed=$(git diff --name-only origin/main.."$branch_name" | grep "app/Services" | wc -l)

           # Déterminer le type principal
           if [[ $new_routes -gt 0 ]]; then
               change_type="API Feature"
               doc_priority="API Documentation"
           elif [[ $new_migrations -gt 0 ]]; then
               change_type="Database Schema Change"
               doc_priority="Technical + Migration Guide"
           elif [[ $services_changed -gt 2 ]]; then
               change_type="Business Logic Feature"
               doc_priority="Technical + User Guide"
           else
               change_type="General Enhancement"
               doc_priority="Technical Documentation"
           fi
       }

       # 3. Extraire les features depuis les commits
       extract_features() {
           echo "## Detected Features"

           # Parser les commit messages
           git log origin/main.."$branch_name" --pretty=format:"- %s" | grep -E "^- (feat|add|implement):" | sort -u

           echo ""
           echo "## Modified Components"

           # Grouper par type de fichier
           git diff --name-only origin/main.."$branch_name" | while IFS= read -r file; do
               case "$file" in
                   app/Http/Controllers/*)
                       echo "- Controller: $(basename "$file" .php)"
                       ;;
                   app/Models/*)
                       echo "- Model: $(basename "$file" .php)"
                       ;;
                   app/Services/*)
                       echo "- Service: $(basename "$file" .php)"
                       ;;
                   routes/*)
                       echo "- Routes: $file"
                       ;;
                   database/migrations/*)
                       echo "- Migration: $(basename "$file" .php)"
                       ;;
               esac
           done | sort -u
       }

       # 4. Détecter la documentation nécessaire
       suggest_documentation_needs() {
           echo "## Documentation Needs Analysis"

           needs=()

           # API Documentation
           if [[ $new_routes -gt 0 ]]; then
               needs+=("API Documentation (OpenAPI format)")

               # Extraire les nouveaux endpoints
               echo "### New Endpoints Detected:"
               git diff origin/main.."$branch_name" -- routes/ | grep "^+" | grep -E "Route::" | \
                   sed 's/+//' | sed 's/Route:://' | sed 's/->name.*//' | sort -u
           fi

           # User Guide
           if git log origin/main.."$branch_name" --grep="UI\|interface\|frontend" -i | grep -q .; then
               needs+=("User Guide (workflow changes)")
           fi

           # Technical Documentation
           if [[ $services_changed -gt 0 || $new_models -gt 0 ]]; then
               needs+=("Technical Architecture Documentation")
           fi

           # Migration Guide
           if [[ $new_migrations -gt 0 ]]; then
               needs+=("Database Migration Guide")
           fi

           echo ""
           echo "### Recommended Documentation Types:"
           printf '%s\n' "${needs[@]}"
       }

       # 5. Score de complexité automatique
       calculate_complexity() {
           complexity_score=0

           [[ $new_routes -gt 5 ]] && complexity_score=$((complexity_score + 2))
           [[ $new_migrations -gt 0 ]] && complexity_score=$((complexity_score + 1))
           [[ $services_changed -gt 3 ]] && complexity_score=$((complexity_score + 2))
           [[ $files_changed -gt 20 ]] && complexity_score=$((complexity_score + 1))

           echo "## Complexity Analysis"
           echo "- Score: $complexity_score/5"
           echo "- Recommended agents: $([ $complexity_score -ge 3 ] && echo "4 (full team)" || echo "3 (standard)")"
       }

       # Générer rapport complet
       detect_change_type

       cat > "branch-analysis-$branch_name.md" << EOF
   $branch_info

   ### Change Type: $change_type
   ### Documentation Priority: $doc_priority

   $(extract_features)

   $(suggest_documentation_needs)

   $(calculate_complexity)

   ## Automated Documentation Plan
   Based on the analysis, the system recommends:

   1. **Primary Documentation**: $doc_priority
   2. **Deploy Agents**: $([ $complexity_score -ge 3 ] && echo "4" || echo "3") agents
   3. **Estimated Time**: $(echo "scale=1; $files_changed * 0.5" | bc) hours
   EOF

       echo "✅ Analyse complète générée : branch-analysis-$branch_name.md"
   }

   # Traitement du choix branche
   if [[ "$choice" == "0" || "$choice" == "11" || "$choice" =~ ^branch: ]]; then
       if [[ "$choice" == "11" ]]; then
           STOP → "Nom de la branche à documenter :"
           read branch_name
       elif [[ "$choice" =~ ^branch: ]]; then
           branch_name="${choice#branch:}"
       else
           branch_name=$(git branch --show-current)
       fi

       # Analyser la branche
       analyze_branch_for_documentation "$branch_name"

       # Présenter résumé
       echo ""
       echo "📊 Résumé de l'analyse :"
       cat "branch-analysis-$branch_name.md" | grep -A 20 "## Documentation Needs Analysis"

       STOP → "Procéder avec la documentation automatique de cette branche ? (y/n)"

       if [[ "$response" == "y" ]]; then
           # Définir les variables pour le reste du workflow
           source_type="Branch"
           source_id="$branch_name"
           task_title="Documentation for $branch_name"
           task_description=$(cat "branch-analysis-$branch_name.md")

           # Le workflow continue normalement avec ces infos
           # Les agents vont utiliser l'analyse de branche
       fi
   fi
   ```

   **Option 1-3 - Sources Externes (Jira/Sentry/GitHub) :**
   ```bash
   # Filtrage avancé
   echo "🔍 Filtres disponibles :"
   echo "1. Par priorité (P1/P2/P3)"
   echo "2. Par type (bug/feature/epic)"
   echo "3. Par composant"
   echo "4. Par date (7j/30j/sprint)"
   echo "5. Recherche libre"
   echo "6. Voir tout"

   STOP → "Appliquer un filtre ? (1-6) :"

   # Enrichissement automatique
   echo "📎 Contexte additionnel détecté :"
   echo "- Pièces jointes : [count]"
   echo "- Commentaires : [count]"
   echo "- Issues liées : [list]"

   STOP → "Inclure le contexte complet ? (y/n)"
   ```

   **Option 6 - Analyse Automatique :**
   ```bash
   echo "🤖 Analyse automatique en cours..."

   # Détecter tous les besoins de documentation
   analyze_documentation_needs() {
       # Controllers non documentés
       find app/Http/Controllers -name "*.php" | while read controller; do
           if ! grep -q "$(basename $controller)" docs/; then
               analyze_controller_complexity "$controller"
           fi
       done

       # Services complexes
       find app/Services -name "*.php" -exec wc -l {} + | sort -rn | head -10

       # Migrations récentes
       find database/migrations -name "*.php" -mtime -30

       # Générer rapport priorisé
       generate_priority_report
   }
   ```

   **Option 9 - Mode Batch :**
   ```bash
   # Sélection multiple intelligente
   echo "📦 Mode Batch - Sélection groupée"
   echo "1. Par composant/module"
   echo "2. Par type (toutes les APIs)"
   echo "3. Par sprint/release"
   echo "4. Sélection manuelle multiple"

   STOP → "Type de groupement (1-4) :"
   ```

5. **Évaluation Complexité et Mode Multi-Agent :**
   ```bash
   # Analyse automatique de complexité
   complexity_score=0

   # Facteurs de complexité selon la source
   if [[ "$source_type" == "Branch" ]]; then
       # Pour les branches, utiliser l'analyse déjà faite
       complexity_score=$(grep "Score:" "branch-analysis-$source_id.md" | cut -d: -f2 | tr -d ' /5')
   else
       # Analyse standard pour autres sources
       [[ "$source_type" == "Epic" ]] && complexity_score=$((complexity_score + 2))
       [[ "$files_count" -gt 10 ]] && complexity_score=$((complexity_score + 2))
       [[ "$task_type" =~ "architecture|api|integration" ]] && complexity_score=$((complexity_score + 1))
   fi

   # Déterminer mode recommandé
   if [ $complexity_score -ge 4 ]; then
       suggested_mode="multi-agent-full"
       suggested_agents=4
   elif [ $complexity_score -ge 2 ]; then
       suggested_mode="multi-agent-selective"
       suggested_agents=3
   else
       suggested_mode="dual-agent"
       suggested_agents=2
   fi
   ```

   **STOP** → "📊 Analyse de complexité :
   - Type : [$task_type]
   - Complexité : [$complexity_score]/5
   - Fichiers impactés : [$files_count]
   - Mode recommandé : [$suggested_mode] avec [$suggested_agents] agents

   Options :
   1. 🤖 Multi-agent complet (4 agents)
   2. 🔀 Multi-agent sélectif (3 agents)
   3. 👥 Dual-agent (2 agents)
   4. 🎯 Auto (suivre recommandation)

   Votre choix (1-4) :"

5. **Validation et Enrichissement :**
   ```bash
   # Vérifier documentation existante
   check_existing_docs() {
       keywords=$(echo "$task_title" | tr '[:upper:]' '[:lower:]' | tr ' ' '|')
       existing=$(find docs/ -name "*.md" | xargs grep -l -E "$keywords")

       if [[ -n "$existing" ]]; then
           echo "📚 Documentation existante détectée :"
           echo "$existing"
           echo ""
           echo "Options :"
           echo "1. Créer nouvelle version"
           echo "2. Mettre à jour existante"
           echo "3. Merger avec existante"
           echo "4. Remplacer complètement"

           STOP → "Action (1-4) :"
       fi
   }
   ```

6. **Template Quick-Start :**
   ```bash
   # Suggestion intelligente de template
   suggest_template() {
       if [[ "$task_type" =~ "API|endpoint" ]]; then
           echo "➡️ Recommandé : API Documentation Template"
       elif [[ "$task_type" =~ "bug|fix|error" ]]; then
           echo "➡️ Recommandé : Troubleshooting Guide Template"
       elif [[ "$task_type" =~ "feature|story" ]]; then
           echo "➡️ Recommandé : Feature Guide Template"
       fi
   }

   echo "📝 Templates disponibles :"
   suggest_template
   echo ""
   echo "1. 🎯 API Documentation (OpenAPI)"
   echo "2. 📖 User Guide (Step-by-step)"
   echo "3. 🏗️ Technical Architecture"
   echo "4. 🔧 Troubleshooting Guide"
   echo "5. 🚀 Quick Start Guide"
   echo "6. 📊 Database Schema"
   echo "7. 🔄 Integration Guide"
   echo "8. ✨ Personnalisé"

   STOP → "Template (1-8) :"
   ```

7. **Création Structure Documentation :**
   ```bash
   DOC_ID=$(date +%Y-%m-%d-%H%M%S)
   DOC_SLUG=$(echo "$task_title" | sed 's/[^a-zA-Z0-9]/-/g' | tr '[:upper:]' '[:lower:]')
   DOC_DIR="todos/documentation/active/${DOC_ID}-${DOC_SLUG}"

   mkdir -p "$DOC_DIR"/{04-generated-docs/{technical,user,api},multi-agent-logs,artifacts}
   cd "$DOC_DIR"
   ```

8. **Initialisation Multi-Agent :**
   ```bash
   # Créer fichier de coordination
   cat > multi-agent-logs/agent-coordination.md << EOF
   # Multi-Agent Coordination Log
   **Documentation ID:** ${DOC_ID}-${DOC_SLUG}
   **Mode:** [$selected_mode]
   **Agents Deployed:** [$agents_count] agents
   **Template:** [$selected_template]

   ## Agent Status
   - Agent-Analyzer: Pending
   - Agent-Documenter: Pending
   - Agent-Reviewer: $([ $agents_count -ge 3 ] && echo "Pending" || echo "Not deployed")
   - Agent-Publisher: $([ $agents_count -eq 4 ] && echo "Pending" || echo "Not deployed")

   ## Coordination Events
   [timestamp] System initialized
   EOF
   ```

9. **Initialiser `01-task.md` :**
   ```markdown
   # Documentation: [Task Title]
   **Status:** Analyzing
   **Agent PID:** [Bash(echo $PPID)]
   **Documentation ID:** [DOC_ID]-[DOC_SLUG]
   **Source:** [Todoist/Jira/Sentry/GitHub/Auto/General]
   **Source ID:** [ID si applicable]
   **Current Branch:** [git branch --show-current]
   **Created:** [date]
   **Multi-Agent Mode:** [selected_mode]
   **Agents Deployed:** [agents_count]
   **Template:** [selected_template]

   ## Documentation Scope
   [Ce qui doit être documenté]

   ## Source Information
   [Détails complets de la source]

   ## Multi-Agent Analysis Plan
   ### Agent-Analyzer Tasks
   - [ ] Analyser commits et changements
   - [ ] Identifier patterns et conventions
   - [ ] Détecter breaking changes
   - [ ] Évaluer couverture actuelle

   ### Agent-Documenter Tasks
   - [ ] Générer contenu selon template
   - [ ] Créer exemples contextuels
   - [ ] Produire diagrammes
   - [ ] Adapter ton pour audience

   ### Agent-Reviewer Tasks (si déployé)
   - [ ] Valider exhaustivité
   - [ ] Vérifier cohérence CLAUDE.MD
   - [ ] Tester exemples de code
   - [ ] Contrôler terminologie

   ### Agent-Publisher Tasks (si déployé)
   - [ ] Convertir formats
   - [ ] Mettre à jour sources
   - [ ] Notifier stakeholders
   - [ ] Archiver et versionner

   ## Documentation Type
   [Type choisi avec justification]

   ## Notes
   [Notes et découvertes durant l'analyse]
   ```

### REFINE (Multi-Agent Analysis)

1. **Déploiement Parallèle des Agents :**
   ```bash
   echo "🤖 Déploiement de $agents_count agents spécialisés..."

   # Agent-Analyzer (toujours déployé)
   echo "🔍 Agent-Analyzer: Analyse du code et des changements..."

   # Recherches parallèles
   {
       # Commits liés
       git log --grep="$source_id" --oneline > multi-agent-logs/commits.tmp &

       # Fichiers modifiés
       git diff $(git merge-base main HEAD) --name-only > multi-agent-logs/files.tmp &

       # Patterns existants
       find docs/ -name "*.md" | xargs grep -l "similar" > multi-agent-logs/patterns.tmp &

       wait
   }

   # Générer rapport Agent-Analyzer
   cat > multi-agent-logs/agent-analyzer.md << 'EOF'
   # Agent-Analyzer Report
   **Specialization:** Code analysis, change detection, pattern recognition
   **Status:** Analysis complete

   ## Changes Detected
   ### Modified Files
   [Liste avec catégorisation]

   ### New Endpoints (si applicable)
   [Routes ajoutées/modifiées]

   ### Database Changes
   [Migrations détectées]

   ### Breaking Changes
   [Si détectés avec impact]

   ## Pattern Analysis
   ### Existing Documentation Patterns
   [Patterns trouvés dans docs/]

   ### Code Conventions
   [Conventions identifiées]

   ## Recommendations for Documentation
   - Priority sections: [list]
   - Required examples: [list]
   - Diagrams needed: [list]
   EOF
   ```

2. **Agent-Documenter (toujours déployé) :**
   ```bash
   echo "📝 Agent-Documenter: Planification du contenu..."

   # Analyser le template choisi
   template_file="../../templates/${selected_template}.md"

   # Adapter selon contexte
   cat > multi-agent-logs/agent-documenter.md << 'EOF'
   # Agent-Documenter Report
   **Specialization:** Content generation, examples, diagrams
   **Template:** [selected_template]

   ## Content Structure Plan
   ### Main Sections
   1. [Section avec estimation lignes]
   2. [Section avec estimation lignes]

   ### Code Examples Needed
   - [Example 1: contexte]
   - [Example 2: contexte]

   ### Diagrams Planning
   - [Diagram 1: type et purpose]
   - [Diagram 2: type et purpose]

   ## Tone and Audience
   - Primary audience: [developers/users/admins]
   - Technical level: [beginner/intermediate/expert]
   - Style guide: [formal/casual/technical]
   EOF
   ```

3. **Agent-Reviewer (si 3+ agents) :**
   ```bash
   if [[ $agents_count -ge 3 ]]; then
       echo "✅ Agent-Reviewer: Préparation des critères de validation..."

       cat > multi-agent-logs/agent-reviewer.md << 'EOF'
   # Agent-Reviewer Report
   **Specialization:** Quality assurance, validation, consistency

   ## Review Criteria
   ### Completeness Checklist
   - [ ] All endpoints documented
   - [ ] All parameters described
   - [ ] Examples for each use case
   - [ ] Error scenarios covered

   ### CLAUDE.MD Compliance
   - [ ] Naming conventions
   - [ ] Code style examples
   - [ ] Security considerations

   ### Technical Accuracy
   - [ ] Code examples tested
   - [ ] API responses verified
   - [ ] Database schema current
   EOF
   fi
   ```

4. **Agent-Publisher (si 4 agents) :**
   ```bash
   if [[ $agents_count -eq 4 ]]; then
       echo "📤 Agent-Publisher: Configuration de publication..."

       cat > multi-agent-logs/agent-publisher.md << 'EOF'
   # Agent-Publisher Report
   **Specialization:** Format conversion, distribution, notifications

   ## Publishing Plan
   ### Target Formats
   - [ ] Markdown (primary)
   - [ ] PDF with diagrams
   - [ ] HTML for web
   - [ ] OpenAPI spec (if API)

   ### Distribution Channels
   - [ ] Git repository (docs/)
   - [ ] Confluence/Wiki
   - [ ] Developer portal
   - [ ] Email stakeholders

   ### Update Requirements
   - [ ] Jira: Add doc link
   - [ ] Todoist: Mark complete
   - [ ] Sentry: Note resolution
   EOF
   fi
   ```

5. **Synthèse Multi-Agent :**
   ```bash
   echo "🔄 Coordination des analyses..."

   # Consolider les rapports
   cat > 02-source-analysis.md << 'EOF'
   # Multi-Agent Source Analysis
   **Date:** [date]
   **Agents:** [agents_count]
   **Coordination:** Complete

   ## 🔍 Agent-Analyzer Findings
   [Intégration du rapport analyzer]

   ## 📝 Agent-Documenter Plan
   [Intégration du rapport documenter]

   ## ✅ Agent-Reviewer Criteria (si applicable)
   [Intégration du rapport reviewer]

   ## 📤 Agent-Publisher Strategy (si applicable)
   [Intégration du rapport publisher]

   ## Consolidated Recommendations
   ### Documentation Structure
   [Structure finale recommandée]

   ### Priority Content
   1. [Section critique 1]
   2. [Section critique 2]

   ### Effort Estimation
   - Total sections: [count]
   - Estimated time: [heures]
   - Review cycles: [nombre]
   EOF
   ```

6. **Génération du Plan Détaillé :**
   ```bash
   cat > 03-documentation-plan.md << 'EOF'
   # Documentation Plan - [Task Title]
   **Generated by:** Multi-Agent System
   **Template:** [selected_template]
   **Target Audience:** [identified]

   ## Table of Contents (Planned)
   1. Overview
      - Purpose and Scope
      - Key Features/Changes
      - Prerequisites

   2. [Main Section 1]
      - [Subsection 1.1]
      - [Subsection 1.2]
      - Code Examples

   3. [Main Section 2]
      - [Details]
      - Diagrams

   4. [Additional Sections selon template]

   ## Diagrams to Generate
   1. [Diagram 1 - Type: Mermaid sequence]
   2. [Diagram 2 - Type: Mermaid flowchart]

   ## Code Examples Plan
   1. [Example 1 - Language: PHP]
   2. [Example 2 - Language: JavaScript]

   ## Review Checkpoints
   - [ ] After section 2: Technical accuracy
   - [ ] After examples: Code functionality
   - [ ] Final: CLAUDE.MD compliance
   EOF
   ```

7. **Mise à jour Status :**
   ```bash
   # Mettre à jour 01-task.md
   sed -i 's/**Status:** Analyzing/**Status:** Documenting/' 01-task.md

   # Commit d'analyse
   git add . && git commit -m "docs($DOC_SLUG): Multi-agent analysis complete - $agents_count agents"
   ```

8. **STOP** → "Analyse multi-agent terminée.
   Agents déployés : $agents_count
   Sections planifiées : [count]
   Temps estimé : [duration]

   Plan validé ? (y/n)"

### DOCUMENT (Cycles Itératifs Multi-Agents)

0. **Prérequis - User Stories Validées :**
   ```bash
   # Vérifier que les User Stories sont validées
   if [[ ! -f "02-user-stories-validated.md" ]]; then
       echo "❌ ERREUR : User Stories non validées !"
       echo "La documentation ne peut pas commencer sans validation des User Stories."
       STOP → "Retourner à l'étape REFINE pour valider les User Stories ? (y/n)"
       return 1
   fi

   echo "✅ User Stories validées trouvées"
   echo "📋 La documentation sera basée sur ce document de référence"
   ```

1. **Génération par Cycles (basée sur User Stories) :**

   Pour chaque section du plan :

   a. **Cycle DRAFT (Agent-Documenter) :**
   ```bash
   echo "📝 Agent-Documenter: Génération section [$section_name]..."

   # Génération selon template et contexte
   generate_section() {
       section_type="$1"
       target_file="$2"

       case "$section_type" in
           "api-endpoint")
               generate_api_documentation "$target_file"
               ;;
           "user-guide")
               generate_user_guide "$target_file"
               ;;
           "technical")
               generate_technical_doc "$target_file"
               ;;
       esac
   }

   # Log progression
   echo "[$(date)] Draft generated for $section_name" >> multi-agent-logs/documenter-progress.log
   ```

   STOP → "Section '$section_name' rédigée. Valider le draft ? (y/n)"

   b. **Cycle REVIEW (Agent-Reviewer si déployé) :**
   ```bash
   if [[ $agents_count -ge 3 ]]; then
       echo "✅ Agent-Reviewer: Validation section [$section_name]..."

       review_section() {
           # Vérifications automatiques
           check_code_examples
           verify_api_accuracy
           validate_terminology

           # Générer rapport de review
           cat > "multi-agent-logs/review-$section_name.md" << EOF
   ## Review Report: $section_name
   ### Checklist
   - [x] Structure correcte
   - [x] Exemples fonctionnels
   - [ ] Terminology consistante

   ### Issues Found
   1. [Issue 1 avec suggestion]

   ### Recommendations
   - [Amélioration suggérée]
   EOF
       }
   fi
   ```

   STOP → "Review complétée. Corrections à apporter : [liste]. Appliquer ? (y/n)"

   c. **Cycle ENHANCE (Multi-Agents) :**
   ```bash
   echo "🎨 Enhancement collaboratif..."

   # Agent-Documenter ajoute diagrammes
   if [[ "$needs_diagrams" == "true" ]]; then
       generate_mermaid_diagrams
   fi

   # Agent-Analyzer enrichit exemples
   enrich_code_examples

   # Agent-Reviewer optimise lisibilité
   optimize_readability
   ```

   STOP → "Section enrichie et finalisée. Continuer ? (y/n)"

2. **Génération des Types de Documentation :**

   **API Documentation :**
   ```markdown
   # API Documentation - [Feature Name]

   ## Overview
   [Description générée par Agent-Documenter]

   ## Authentication
   All API requests require authentication using Bearer tokens.

   ```http
   Authorization: Bearer {token}
   x-financers-id: {financers-id}
   x-origin-interface: {interface-name}
   ```

   ## Endpoints

   ### Create [Resource]
   **[POST]** `/api/v1/[resources]`

   Creates a new [resource] with the specified attributes.

   #### Request
   ```json
   {
     "field1": "value1",
     "field2": "value2"
   }
   ```

   #### Response
   ```json
   {
     "id": 123,
     "field1": "value1",
     "field2": "value2",
     "created_at": "2025-07-25T10:00:00Z"
   }
   ```

   #### Error Codes
   | Code | Description | Resolution |
   |------|------------|------------|
   | 400 | Invalid request | Check required fields |
   | 401 | Unauthorized | Verify authentication |
   | 422 | Validation failed | See error details |
   ```

   **Technical Documentation :**
   ```markdown
   # Technical Documentation - [Component Name]

   ## Architecture Overview

   ```mermaid
   graph TB
       A[Client] -->|HTTP Request| B[API Gateway]
       B --> C[Controller]
       C --> D[Service Layer]
       D --> E[Repository]
       E --> F[(Database)]
   ```

   ## Components

   ### [Component 1]
   **Purpose:** [Description]
   **Location:** `app/Services/[Name]Service.php`

   #### Key Methods
   - `processData()`: [Description]
   - `validateInput()`: [Description]

   ### Design Patterns
   - **Repository Pattern**: Data access abstraction
   - **Service Layer**: Business logic encapsulation
   ```

3. **Génération de Diagrammes :**
   ```bash
   # Agent-Documenter génère diagrammes Mermaid
   generate_diagrams() {
       echo "🎨 Génération des diagrammes..."

       # Selon le type de documentation
       if [[ "$doc_type" == "api" ]]; then
           cat >> "$target_file" << 'EOF'
   ## API Flow Diagram

   ```mermaid
   sequenceDiagram
       participant C as Client
       participant A as API
       participant S as Service
       participant D as Database

       C->>A: POST /api/resource
       A->>A: Validate headers
       A->>S: Process request
       S->>D: Save data
       D-->>S: Return result
       S-->>A: Format response
       A-->>C: 201 Created
   ```
   EOF
       fi
   }
   ```

   STOP → "Diagrammes générés. Types : [list]. Valider ? (y/n)"

4. **Consolidation Multi-Format :**
   ```bash
   # Rassembler toutes les sections
   echo "📚 Consolidation de la documentation..."

   consolidate_documentation() {
       doc_type="$1"
       output_dir="04-generated-docs/$doc_type"

       # Header commun
       cat > "$output_dir/complete-$DOC_SLUG.md" << EOF
   # $doc_title

   **Version:** 1.0.0
   **Date:** $(date +%Y-%m-%d)
   **Authors:** Multi-Agent System
   **Status:** $status

   ---

   EOF

       # Ajouter toutes les sections dans l'ordre
       for section in $(ls $output_dir/sections/); do
           cat "$output_dir/sections/$section" >> "$output_dir/complete-$DOC_SLUG.md"
           echo -e "\n---\n" >> "$output_dir/complete-$DOC_SLUG.md"
       done
   }
   ```

5. **Quality Check Final :**
   ```bash
   if [[ $agents_count -ge 3 ]]; then
       echo "✅ Agent-Reviewer: Validation finale..."

       # Vérifications complètes
       final_review() {
           errors=0
           warnings=0

           # Check tous les exemples de code
           echo "Vérification des exemples..."
           check_code_examples || ((errors++))

           # Vérifier cohérence
           echo "Vérification cohérence..."
           check_consistency || ((warnings++))

           # Rapport final
           cat > 05-review-log.md << EOF
   # Final Review Report
   **Date:** $(date)
   **Reviewer:** Agent-Reviewer

   ## Summary
   - Errors: $errors
   - Warnings: $warnings
   - Status: $([ $errors -eq 0 ] && echo "PASSED" || echo "FAILED")

   ## Details
   [Détails des vérifications]
   EOF
       }
   fi
   ```

   STOP → "Review finale : Errors: $errors, Warnings: $warnings. Continuer ? (y/n)"

### PUBLISH (Multi-Channel Distribution)

1. **Sélection des Canaux de Publication :**
   ```bash
   echo "📤 Sélection des canaux de publication..."

   STOP → "Où publier la documentation ? (plusieurs choix possibles)

   📁 REPOSITORY
   1. Git Repository (docs/)
   2. GitHub Wiki
   3. GitLab Pages

   🌐 PLATFORMS
   4. Notion (via MCP)
   5. Confluence
   6. SharePoint

   📄 FORMATS
   7. PDF Export
   8. HTML Static Site
   9. OpenAPI Spec (si API)

   📧 NOTIFICATIONS
   10. Email Stakeholders
   11. Slack Channel
   12. Teams Channel

   Entrez vos choix séparés par des virgules (ex: 1,4,7,10) :"

   # Parser les choix
   IFS=',' read -ra PUBLISH_CHANNELS <<< "$choice"
   ```

2. **Publication vers Notion (si sélectionné) :**
   ```bash
   publish_to_notion() {
       echo "📝 Publication vers Notion via MCP..."

       # Configuration Notion
       STOP → "Configuration Notion :
       1. 📄 Créer une nouvelle page
       2. 📋 Ajouter à une page existante
       3. 🗂️ Créer dans une base de données

       Votre choix (1-3) :"

       case "$choice" in
           "1")
               # Nouvelle page
               STOP → "Informations pour la nouvelle page :
               - Titre de la page : "
               read page_title

               STOP → "Parent de la page :
               1. 🏠 Workspace root
               2. 📁 Page existante (fournir l'ID)
               3. 🗂️ Dans une database

               Votre choix (1-3) :"

               case "$parent_choice" in
                   "2")
                       STOP → "ID de la page parent (ou URL Notion) :"
                       read parent_id
                       ;;
                   "3")
                       STOP → "ID de la database (ou URL) :"
                       read database_id
                       ;;
               esac

               # Créer la structure Notion
               create_notion_structure() {
                   echo "🏗️ Création de la structure Notion..."

                   # Créer page principale
                   main_page_id=$(mcp notion create-page \
                       --title "$page_title" \
                       --parent "$parent_id" \
                       --content "Documentation générée automatiquement")

                   echo "✅ Page principale créée : $main_page_id"

                   # Organiser par sections
                   STOP → "Organisation du contenu :
                   1. 📄 Une seule page avec tout le contenu
                   2. 📑 Une page parent + sous-pages par section
                   3. 🗂️ Database avec une entrée par User Story

                   Votre choix (1-3) :"

                   case "$org_choice" in
                       "1")
                           # Tout sur une page
                           publish_single_notion_page "$main_page_id"
                           ;;
                       "2")
                           # Sous-pages
                           publish_notion_subpages "$main_page_id"
                           ;;
                       "3")
                           # Database
                           create_notion_database_entries "$database_id"
                           ;;
                   esac
               }
               ;;

           "2")
               # Page existante
               STOP → "ID ou URL de la page Notion existante :"
               read existing_page_id

               append_to_notion_page "$existing_page_id"
               ;;

           "3")
               # Database
               STOP → "ID ou URL de la database Notion :"
               read database_id

               create_notion_database_entries "$database_id"
               ;;
       esac
   }

   # Publier une seule page Notion
   publish_single_notion_page() {
       local page_id="$1"

       echo "📄 Publication du contenu sur une seule page..."

       # Convertir le markdown en blocs Notion
       for doc_file in 04-generated-docs/**/*.md; do
           echo "📎 Ajout de : $doc_file"

           # Extraire le contenu et le convertir
           content=$(cat "$doc_file")

           # Utiliser MCP pour ajouter le contenu
           mcp notion append-blocks \
               --page-id "$page_id" \
               --content "$content" \
               --format "markdown"
       done

       # Ajouter table des matières
       mcp notion add-toc --page-id "$page_id"

       echo "✅ Publication Notion terminée"
       echo "🔗 Lien : https://notion.so/$page_id"
   }

   # Publier avec sous-pages
   publish_notion_subpages() {
       local parent_id="$1"

       echo "📑 Création de sous-pages par section..."

       # User Stories
       if [[ -f "02-user-stories-validated.md" ]]; then
           us_page_id=$(mcp notion create-page \
               --title "📋 User Stories" \
               --parent "$parent_id" \
               --content-file "02-user-stories-validated.md")
           echo "✅ Page User Stories : $us_page_id"
       fi

       # Documentation technique
       if [[ -d "04-generated-docs/technical" ]]; then
           tech_page_id=$(mcp notion create-page \
               --title "🔧 Documentation Technique" \
               --parent "$parent_id")

           for tech_file in 04-generated-docs/technical/*.md; do
               section_title=$(basename "$tech_file" .md)
               mcp notion create-page \
                   --title "$section_title" \
                   --parent "$tech_page_id" \
                   --content-file "$tech_file"
           done
       fi

       # API Documentation
       if [[ -d "04-generated-docs/api" ]]; then
           api_page_id=$(mcp notion create-page \
               --title "🌐 API Documentation" \
               --parent "$parent_id")

           for api_file in 04-generated-docs/api/*.md; do
               endpoint_name=$(basename "$api_file" .md)
               mcp notion create-page \
                   --title "$endpoint_name" \
                   --parent "$api_page_id" \
                   --content-file "$api_file"
           done
       fi
   }

   # Créer entrées database
   create_notion_database_entries() {
       local db_id="$1"

       echo "🗂️ Création d'entrées dans la database..."

       # Parser les User Stories
       extract_user_stories_for_notion() {
           # Extraire chaque US comme entrée séparée
           awk '/^### US-/' RS= "02-user-stories-validated.md" | while read -r story; do
               story_id=$(echo "$story" | grep -oE "US-[A-Z]+-[0-9]+")
               story_title=$(echo "$story" | head -1 | sed 's/### //')

               # Créer l'entrée
               mcp notion create-database-entry \
                   --database-id "$db_id" \
                   --properties "{
                       \"Name\": \"$story_title\",
                       \"ID\": \"$story_id\",
                       \"Type\": \"User Story\",
                       \"Status\": \"Documented\",
                       \"Content\": \"$story\"
                   }"
           done
       }

       extract_user_stories_for_notion
       echo "✅ Entrées database créées"
   }
   ```

3. **Conversion Multi-Format (Agent-Publisher) :**
   ```bash
   if [[ $agents_count -eq 4 ]]; then
       echo "📤 Agent-Publisher: Conversion et distribution..."

       # Installation dépendances si nécessaire
       check_and_install_deps() {
           deps=("mmdc" "pandoc" "wkhtmltopdf")
           for dep in "${deps[@]}"; do
               command -v "$dep" &> /dev/null || {
                   echo "Installation de $dep..."
                   npm install -g "@mermaid-js/mermaid-cli"
               }
           done
       }

       # Conversion PDF avec diagrammes
       convert_to_pdf() {
           input="$1"
           output="${input%.md}.pdf"

           # Convertir diagrammes Mermaid en images
           mmdc -i "$input" -o "${input}.tmp.md" -e png

           # Générer PDF
           pandoc "${input}.tmp.md" -o "$output" \
               --pdf-engine=wkhtmltopdf \
               --css=../../templates/pdf-style.css \
               --highlight-style=github \
               --toc \
               --toc-depth=3

           # Cleanup
           rm "${input}.tmp.md"
           rm -f *.png

           echo "✅ PDF généré : $output"
       }
   fi
   ```

   STOP → "Formats à générer :
   1. PDF avec diagrammes
   2. HTML pour portail web
   3. OpenAPI spec (si API)
   4. Markdown only
   Choix (multiples possibles) :"

2. **Publication vers Destinations :**
   ```bash
   # Copier vers répertoire docs/ du projet
   publish_to_repo() {
       doc_type="$1"
       source_file="$2"

       target_dir="$(git rev-parse --show-toplevel)/docs/$doc_type"
       mkdir -p "$target_dir"

       # Copier avec versioning
       cp "$source_file" "$target_dir/"

       # Si mise à jour, archiver ancienne version
       if [[ -f "$target_dir/$(basename $source_file)" ]]; then
           mv "$target_dir/$(basename $source_file)" \
              "$target_dir/archive/$(basename $source_file).$(date +%Y%m%d)"
       fi

       # Commit
       cd "$(git rev-parse --show-toplevel)"
       git add "docs/$doc_type"
       git commit -m "docs: Add $doc_type documentation for $DOC_SLUG

       Generated by Multi-Agent Documentation System
       - Agents used: $agents_count
       - Source: $source_type
       - Template: $selected_template"
   }
   ```

3. **Mise à jour Sources Externes :**
   ```bash
   # Update selon la source originale
   update_external_sources() {
       case "$source_type" in
           "Todoist")
               echo "✅ Mise à jour Todoist..."
               mcp todoist complete-task --id "$source_id" \
                   --comment "Documentation complétée : $doc_url"
               ;;

           "Jira")
               echo "🎯 Mise à jour Jira..."
               mcp jira add-comment --issue "$source_id" \
                   --comment "Documentation générée : $doc_url"
               mcp jira transition --issue "$source_id" --to "Documented"
               ;;

           "Sentry")
               echo "🚨 Mise à jour Sentry..."
               mcp sentry add-note --issue "$source_id" \
                   --note "Documentation : $doc_url"
               ;;
       esac
   }
   ```

4. **Notifications Stakeholders :**
   ```bash
   # Notifications multi-canal
   notify_stakeholders() {
       # Email
       if [[ -n "$stakeholder_emails" ]]; then
           send_email_notification
       fi

       # Slack
       if [[ -n "$SLACK_WEBHOOK" ]]; then
           curl -X POST "$SLACK_WEBHOOK" \
               -H 'Content-Type: application/json' \
               -d "{
                   \"text\": \"📚 New documentation published!\",
                   \"attachments\": [{
                       \"title\": \"$doc_title\",
                       \"text\": \"Type: $doc_type\nSource: $source_type\",
                       \"color\": \"good\",
                       \"fields\": [{
                           \"title\": \"View Documentation\",
                           \"value\": \"$doc_url\",
                           \"short\": false
                       }]
                   }]
               }"
       fi
   }
   ```

5. **Archivage et Versioning :**
   ```bash
   # Déplacer vers published/
   finalize_documentation() {
       # Créer archive complète
       archive_name="${DOC_ID}-${DOC_SLUG}-complete.tar.gz"
       tar -czf "artifacts/$archive_name" .

       # Mettre à jour status
       sed -i 's/**Status:** Documenting/**Status:** Published/' 01-task.md

       # Ajouter métadonnées de publication
       cat >> 01-task.md << EOF

   ## Publication Details
   **Published:** $(date)
   **Formats:** [list]
   **Locations:** [list]
   **Notifications Sent:** [count]
   EOF

       # Déplacer vers published/
       cd ../..
       mv "active/${DOC_ID}-${DOC_SLUG}" "published/"

       # Log dans historique
       echo "$(date)|$DOC_ID|$DOC_SLUG|$source_type|$agents_count|Published" >> documentation-history.log
   }
   ```

6. **Métriques et Analytics :**
   ```bash
   # Capturer métriques
   capture_metrics() {
       end_time=$(date +%s)
       duration=$((end_time - start_time))

       cat > "published/${DOC_ID}-${DOC_SLUG}/metrics.json" << EOF
   {
     "documentation_id": "${DOC_ID}-${DOC_SLUG}",
     "metrics": {
       "generation_time_seconds": $duration,
       "agents_deployed": $agents_count,
       "sections_generated": $(find 04-generated-docs -name "*.md" | wc -l),
       "diagrams_created": $(grep -c "```mermaid" 04-generated-docs/**/*.md),
       "code_examples": $(grep -c "```[a-z]" 04-generated-docs/**/*.md),
       "total_lines": $(wc -l 04-generated-docs/**/*.md | tail -1 | awk '{print $1}'),
       "review_iterations": $(grep -c "Review completed" multi-agent-logs/*.log),
       "formats_generated": ["markdown", "pdf", "html"]
     },
     "source": {
       "type": "$source_type",
       "id": "$source_id"
     },
     "quality": {
       "errors": $errors,
       "warnings": $warnings,
       "coverage": "$coverage%"
     }
   }
   EOF
   }
   ```

7. **Rapport Final :**
   ```
   📊 Documentation publiée avec succès !

   📍 Locations :
   - Markdown : docs/[type]/[filename].md
   - PDF : docs/[type]/[filename].pdf
   - Archive : todos/documentation/published/[ID]/

   📈 Statistiques :
   - Temps total : [duration]
   - Agents utilisés : [count]
   - Sections créées : [count]
   - Diagrammes : [count]

   🔗 Mises à jour :
   - [Source] : ✅ Mis à jour
   - Notifications : [count] envoyées

   Actions suivantes :
   1. Générer autre documentation
   2. Voir dashboard métriques
   3. Retour au menu principal
   ```

   STOP → "Choix (1-3) :"

## Principes Fondamentaux de Précision

### 🚨 Règles d'Or pour l'Exactitude

1. **User Stories AVANT Documentation**
   - TOUJOURS générer un document User Stories en premier
   - Ce document DOIT être validé par l'utilisateur
   - La documentation technique se base UNIQUEMENT sur les US validées
   - Aucune documentation sans US approuvées

2. **JAMAIS d'invention ou de supposition**
   - Si une information n'est pas dans le code → NE PAS LA DOCUMENTER
   - Si un comportement n'est pas clair → DEMANDER À L'UTILISATEUR
   - Si un exemple n'est pas vérifiable → NE PAS L'INCLURE

3. **TOUJOURS vérifier avant d'écrire**
   - Chaque endpoint → Vérifier dans routes/
   - Chaque méthode → Vérifier dans le controller
   - Chaque paramètre → Vérifier dans le FormRequest
   - Chaque réponse → Vérifier dans le Resource/JsonResponse

4. **L'utilisateur a le contrôle total**
   - Validation des User Stories OBLIGATOIRE
   - Validation à CHAQUE section générée
   - Possibilité d'éditer à tout moment
   - Droit de veto sur tout contenu
   - Clarifications demandées en cas de doute

5. **Traçabilité complète**
   - User Stories → Source de vérité validée
   - Chaque affirmation → Référence au fichier/ligne
   - Chaque exemple → Extrait du code réel
   - Chaque diagramme → Basé sur architecture vérifiée

### 🔍 Points de Vérification Obligatoires

- **Avant génération** : User Stories validées ?
- **Pendant génération** : Cohérent avec les US ?
- **Après génération** : L'utilisateur valide-t-il ?
- **Review finale** : Tout correspond aux US validées ?

### 📋 Workflow User Stories First

```
1. Analyse du code → Génération User Stories
2. Présentation à l'utilisateur → Validation/Correction
3. User Stories validées → Base pour documentation
4. Documentation technique → Basée sur US uniquement
5. Validation finale → Cohérence US ↔ Documentation
```

## Utilisation

### Cas d'usage typiques

1. **Documentation depuis une branche feature :**
   ```bash
   # Depuis votre branche
   git checkout feature/payment-integration
   claude "/doc"
   # Choisir option 0 pour documenter la branche courante

   # Ou depuis n'importe où
   claude "/doc"
   # Choisir option 11 et saisir : feature/payment-integration

   # Ou en raccourci direct
   claude "/doc"
   # Saisir : branch:feature/payment-integration
   ```

2. **Documentation de tâche Jira complétée :**
   ```bash
   claude "/doc"
   # Choisir option 1 (Jira)
   # Sélectionner le projet et l'issue
   ```

3. **Documentation automatique du code non documenté :**
   ```bash
   claude "/doc"
   # Choisir option 6 (Analyse automatique)
   ```

4. **Documentation batch de plusieurs éléments :**
   ```bash
   claude "/doc"
   # Choisir option 9 (Mode Batch)
   # Sélectionner par composant ou multiples tâches
   ```

### Commandes de Maintenance

### Vérification Système
```bash
# Status général
claude "/doc --status"           # Documentation en cours, stats

# Nettoyage
claude "/doc --cleanup"          # Archives et nettoyage

# Métriques
claude "/doc --metrics"          # Dashboard documentation

# Couverture
claude "/doc --coverage"         # Analyse couverture code/doc
```

### Templates Management
```bash
# Lister templates
claude "/doc --templates"        # Templates disponibles

# Créer template
claude "/doc --create-template"  # Assistant création

# Mettre à jour template
claude "/doc --update-template [name]"
```

## Configuration CI/CD

### GitHub Actions Integration
```yaml
# .github/workflows/auto-documentation.yml
name: Auto Documentation

on:
  pull_request:
    types: [closed]

jobs:
  generate-docs:
    if: github.event.pull_request.merged == true
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Extract Issue ID
        id: extract
        run: |
          # Extraire Jira ID du titre PR
          JIRA_ID=$(echo "${{ github.event.pull_request.title }}" | grep -oE '[A-Z]+-[0-9]+')
          echo "jira_id=$JIRA_ID" >> $GITHUB_OUTPUT

      - name: Run Documentation Workflow
        run: |
          # Lancer workflow documentation
          ./scripts/generate-doc.sh --source jira --id "${{ steps.extract.outputs.jira_id }}"

      - name: Create Documentation PR
        uses: peter-evans/create-pull-request@v5
        with:
          title: "docs: Auto-generated documentation for ${{ steps.extract.outputs.jira_id }}"
          body: "Documentation générée automatiquement par Multi-Agent System"
          branch: docs/auto-${{ steps.extract.outputs.jira_id }}
```

## Notes Importantes

- **Pas de modification de code** : Ce workflow est read-only
- **Multi-agent adaptatif** : 2-4 agents selon complexité
- **Templates intelligents** : Sélection automatique selon contexte
- **Traçabilité complète** : Logs détaillés de chaque agent
- **Distribution multi-canal** : Repo, PDF, Web, notifications
- **Métriques détaillées** : Pour amélioration continue
- **CI/CD ready** : Automatisation complète possible

Ce workflow transforme la génération de documentation en un processus intelligent, collaboratif et hautement automatisé ! 🚀