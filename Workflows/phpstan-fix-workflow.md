# PHPStan Fix Workflow Multi-Agent avec Priorités et Groupements
Workflow structuré pour analyser et corriger les erreurs PHPStan de manière collaborative avec système multi-agents adaptatif.

## Structure des Fichiers Générés

```
todos/
├── phpstan-fixes/
│   ├── active/
│   │   └── 2025-01-27-143022-fix-iterableValue/
│   │       ├── 01-task.md                    # Définition et suivi
│   │       ├── 02-error-analysis.md          # Analyse détaillée des erreurs
│   │       ├── 03-fix-strategy.md            # Stratégie de correction
│   │       ├── 04-implementation-plan.md     # Plan d'implémentation TDD
│   │       ├── 05-fixes/                     # Corrections par groupe
│   │       │   ├── group-1-iterableValue/
│   │       │   ├── group-2-return-types/
│   │       │   └── group-3-property-access/
│   │       ├── 06-validation-report.md       # Rapport de validation
│   │       ├── multi-agent-logs/             # Logs des agents
│   │       │   ├── agent-coordination.md
│   │       │   ├── agent-analyzer.md
│   │       │   ├── agent-fixer.md
│   │       │   ├── agent-validator.md
│   │       │   └── agent-documenter.md
│   │       └── artifacts/                    # Rapports, stats, exports
│   ├── done/                                 # Fixes complétées et archivées
│   ├── templates/                            # Templates de fixes réutilisables
│   │   ├── iterableValue-fix.md
│   │   ├── return-type-fix.md
│   │   ├── property-access-fix.md
│   │   └── phpstan-ignore-template.md
│   └── reports/                              # Rapports consolidés
│       ├── progress-dashboard.md
│       └── best-practices.md
```

## Système Multi-Agent Spécialisé PHPStan

### **Agents Disponibles**

1. **Agent-Analyzer** : Analyse et catégorisation des erreurs
   - Groupement par type d'erreur
   - Identification des patterns récurrents
   - Évaluation de l'impact sur le codebase
   - Détection des dépendances entre fixes
   - Priorisation selon criticité

2. **Agent-Fixer** : Stratégie et implémentation des fixes
   - Génération de solutions adaptées
   - Application de l'approche TDD
   - Respect des conventions du projet
   - Préservation du comportement existant
   - Optimisation des types

3. **Agent-Validator** : Validation et tests
   - Vérification des fixes appliqués
   - Tests de non-régression
   - Validation PHPStan niveau par niveau
   - Quality checks (PSR-12, etc.)
   - Performance impact

4. **Agent-Documenter** : Documentation des changements
   - Changelog détaillé des fixes
   - Documentation technique des patterns
   - Guide de migration pour l'équipe
   - Best practices extraites
   - Métriques et statistiques

### **Déclenchement Automatique Multi-Agent**

```markdown
## Critères de Déploiement
- **Nombre d'erreurs :**
  - 1-10 erreurs → 2 agents (Analyzer + Fixer)
  - 11-50 erreurs → 3 agents (+ Validator)
  - 51+ erreurs → 4 agents (+ Documenter)

- **Types d'erreurs :**
  - Simple (typage basique) → 2 agents
  - Moyen (logique métier) → 3 agents
  - Complexe (architecture/refactor) → 4 agents obligatoires

- **Override Utilisateur :**
  - Mode minimal → 2 agents même si nombreuses erreurs
  - Mode complet → 4 agents même si peu d'erreurs
```

## Workflow Optimisé

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : INIT → SELECT → ANALYZE → BRAINSTORM → IMPLEMENT → VALIDATE → COMMIT → DOCUMENT
- Vous DEVEZ obtenir la confirmation de l'utilisateur à chaque STOP
- Vous NE DEVEZ JAMAIS modifier le comportement fonctionnel sans validation
- Vous DEVEZ maintenir la compatibilité descendante
- Les fixes doivent être testables et réversibles
- La collaboration avec l'utilisateur est OBLIGATOIRE pour chaque décision importante

### INIT (Initialisation et Baseline)

1. **Capture de l'État Initial :**
   ```bash
   # Créer le répertoire de travail
   mkdir -p todos/phpstan-fixes/{active,done,templates,reports}

   # Capturer le baseline actuel
   echo "🔍 Analyse PHPStan en cours..."
   make stan > todos/phpstan-fixes/phpstan-baseline-$(date +%Y%m%d-%H%M%S).txt 2>&1

   # Copier pour référence
   cp todos/phpstan-fixes/phpstan-baseline-*.txt todos/phpstan-fixes/current-baseline.txt

   # Extraire les statistiques
   total_errors=$(grep -E "Found [0-9]+ errors" todos/phpstan-fixes/current-baseline.txt | grep -oE "[0-9]+")
   echo "📊 Total errors: $total_errors"

   # Vérifier configuration PHPStan
   if [[ -f phpstan.neon ]]; then
       echo "✅ Configuration PHPStan trouvée"
       current_level=$(grep -E "level:" phpstan.neon | grep -oE "[0-9]+")
       echo "📈 Niveau actuel: $current_level"
   fi
   ```

2. **Analyse Statistique Initiale :**
   ```bash
   # Analyser par type d'erreur
   echo "📊 Analyse par type d'erreur..."
   error_types=$(grep -E "🪪 [a-zA-Z.]+" todos/phpstan-fixes/current-baseline.txt | \
                 awk '{print $2}' | sort | uniq -c | sort -rn)

   # Analyser par fichier/dossier
   echo "📁 Analyse par dossier..."
   error_by_folder=$(grep -E "Line.*\.php" todos/phpstan-fixes/current-baseline.txt | \
                     awk '{print $2}' | xargs dirname | sort | uniq -c | sort -rn)

   # Identifier les patterns
   echo "🔍 Identification des patterns..."
   ```

3. **Création du Workspace :**
   ```bash
   # Générer ID unique pour cette session
   ANALYSIS_ID=$(date +%Y-%m-%d-%H%M%S)
   ERROR_TYPE="${1:-general}" # Type d'erreur si spécifié
   ANALYSIS_DIR="todos/phpstan-fixes/active/${ANALYSIS_ID}-fix-${ERROR_TYPE}"

   # Créer structure
   mkdir -p "$ANALYSIS_DIR"/{05-fixes,multi-agent-logs,artifacts}
   cd "$ANALYSIS_DIR"

   # Copier baseline dans le workspace
   cp ../../current-baseline.txt ./
   ```

4. **Initialisation Multi-Agent :**
   ```bash
   # Créer fichier de coordination
   cat > multi-agent-logs/agent-coordination.md << EOF
   # Multi-Agent Coordination Log
   **Fix Session ID:** ${ANALYSIS_ID}-fix-${ERROR_TYPE}
   **Mode:** [À déterminer selon sélection]
   **Total Errors:** $total_errors
   **Target Level:** $current_level

   ## Agent Status
   - Agent-Analyzer: Pending
   - Agent-Fixer: Pending
   - Agent-Validator: [Si déployé]
   - Agent-Documenter: [Si déployé]

   ## Coordination Events
   [$(date)] System initialized
   EOF
   ```

### SELECT (Sélection Interactive des Erreurs)

1. **Analyse et Présentation Catégorisée :**
   ```bash
   # Générer rapport d'analyse
   cat > 02-error-analysis.md << 'EOF'
   # Analyse PHPStan - Rapport Détaillé
   **Date:** $(date)
   **Total Errors:** $total_errors
   **PHPStan Level:** $current_level

   ## 🏷️ Erreurs par Type
   EOF

   # Ajouter statistiques par type
   echo "$error_types" | while read count type; do
       percentage=$((count * 100 / total_errors))
       echo "- **$type**: $count occurrences ($percentage%)"
   done >> 02-error-analysis.md
   ```

2. **Menu de Sélection Interactif :**
   ```
   📊 PHPStan Error Analysis Dashboard
   =====================================
   Total: $total_errors errors | Level: $current_level

   🏷️ GROUPEMENT PAR TYPE :
   1. missingType.iterableValue (89) - Types de tableaux manquants
   2. property.notFound (31) - Propriétés non trouvées
   3. argument.type (26) - Types d'arguments incorrects
   4. return.type (15) - Types de retour incorrects
   5. method.notFound (8) - Méthodes non définies
   6. ergebnis.* (12) - Règles de qualité Ergebnis
   7. Autres (58) - Erreurs diverses

   📁 GROUPEMENT PAR MODULE :
   8. Actions/ (24 erreurs) - Logique métier
   9. Integrations/ (98 erreurs) - Code externe
   10. Models/ (35 erreurs) - Entités
   11. Http/Controllers/ (31 erreurs) - API
   12. Services/ (28 erreurs) - Services métier

   🎯 GROUPEMENT PAR PRIORITÉ :
   13. 🔴 Critique (45) - Peut causer des bugs
   14. 🟡 Important (112) - Type safety
   15. 🟢 Amélioration (82) - Code quality

   💡 ACTIONS SPÉCIALES :
   16. 🔧 Auto-fix safe (types simples)
   17. 📋 Générer rapport détaillé
   18. 🎓 Mode apprentissage (avec explications)
   19. 📊 Dashboard interactif
   ```

   STOP → "Comment procéder ? (1-19 ou combinaisons comme '1,3,5') :"

3. **Sélection Détaillée selon Choix :**
   ```bash
   # Si sélection par type (ex: option 1)
   if [[ "$choice" == "1" ]]; then
       echo "🔍 Analyse des erreurs missingType.iterableValue..."

       # Extraire toutes les occurrences
       grep -B2 -A2 "missingType.iterableValue" current-baseline.txt > selected-errors.txt

       # Grouper par pattern
       cat > error-patterns.md << 'EOF'
   ## Patterns Identifiés pour missingType.iterableValue

   ### Pattern A: Paramètres de méthode
   **Occurrences:** 45
   **Exemple:**
   ```php
   public function execute(array $parameters)
   ```
   **Fix suggéré:**
   ```php
   public function execute(array<string, mixed> $parameters)
   ```

   ### Pattern B: Propriétés de classe
   **Occurrences:** 28
   **Exemple:**
   ```php
   /** @var array */
   private array $data;
   ```
   **Fix suggéré:**
   ```php
   /** @var array<string, mixed> */
   private array $data;
   ```
   EOF
   fi
   ```

4. **Options de Traitement Granulaire :**
   ```
   🔧 Options de correction pour [Type sélectionné] :

   1. ✅ Correction automatique (safe)
      - Applique les fixes évidents
      - Ajoute types génériques basiques
      - Préserve le comportement

   2. 🔍 Analyse approfondie
      - Examine le contexte de chaque erreur
      - Propose plusieurs solutions
      - Explique les implications

   3. 🤝 Mode collaboratif
      - Validation pour chaque fix
      - Discussion des cas ambigus
      - Learning by doing

   4. 🏗️ Refactoring guidé
      - Amélioration architecture
      - Patterns modernes
      - Breaking changes possibles

   5. 📋 Export pour équipe
      - Génère tickets/tâches
      - Assigne par expertise
      - Planning poker ready
   ```

   STOP → "Mode de traitement ? (1-5) :"

5. **Configuration des Préférences :**
   ```bash
   # Collecter les préférences utilisateur
   echo "⚙️ Configuration des préférences..."

   STOP → "Types array par défaut ?
   1. array<mixed> (le plus permissif)
   2. array<string, mixed> (compromis)
   3. Types précis obligatoires
   4. Demander à chaque fois
   Choice:"

   STOP → "Gestion des nullables ?
   1. Ajouter ? si incertain
   2. Null checks explicites
   3. Null coalescing (??)
   4. Analyse au cas par cas
   Choice:"

   STOP → "PHPDoc ou types natifs ?
   1. PHPDoc seulement (PHP 7.x compat)
   2. Types natifs quand possible (PHP 8.x)
   3. Les deux (redundant mais clair)
   4. Selon contexte
   Choice:"
   ```

### ANALYZE (Analyse Multi-Agent Approfondie)

1. **Déploiement des Agents selon Complexité :**
   ```bash
   echo "🤖 Déploiement de $agent_count agents spécialisés..."

   # Agent-Analyzer (toujours déployé)
   echo "🔍 Agent-Analyzer: Catégorisation et patterns..."
   {
       # Analyse des imports et namespaces
       find . -name "*.php" -exec grep -l "iterableValue" {} \; > affected-files.txt &

       # Analyse des dépendances
       grep -E "use |extends |implements " affected-files.txt > dependencies.txt &

       # Détection des tests existants
       find tests/ -name "*Test.php" > existing-tests.txt &

       wait
   }
   ```

2. **Agent-Analyzer - Rapport Détaillé :**
   ```markdown
   # Agent-Analyzer Report
   **Specialization:** Error categorization, pattern detection, impact analysis
   **Status:** Analysis complete

   ## Erreurs Groupées par Pattern

   ### Pattern 1: Array Type Specifications (89 occurrences)
   **Catégories:**
   - Paramètres de méthode: 45 cas
   - Types de retour: 28 cas
   - Propriétés: 16 cas

   **Impact:**
   - Sécurité type: MEDIUM
   - Risque régression: LOW
   - Effort correction: LOW

   **Root Causes:**
   1. Legacy code sans types stricts
   2. Arrays dynamiques from API/DB
   3. Collections non typées

   ### Pattern 2: Property Access Issues (31 occurrences)
   **Catégories:**
   - Nullable properties: 18 cas
   - Dynamic properties: 8 cas
   - Undefined properties: 5 cas

   **Impact:**
   - Sécurité type: HIGH
   - Risque régression: MEDIUM
   - Effort correction: MEDIUM

   ## Dépendances Inter-Fichiers
   - Actions/ dépend de Models/ (15 liens)
   - Services/ dépend de Repositories/ (8 liens)
   - Controllers/ dépend de Services/ (22 liens)

   ## Ordre de Fix Recommandé
   1. Models/ - Base de tout
   2. Services/ - Logique métier
   3. Controllers/ - Points d'entrée
   4. Actions/ - Orchestration
   ```

3. **Agent-Fixer - Stratégies de Correction :**
   ```markdown
   # Agent-Fixer Report
   **Specialization:** Fix strategies, code generation, best practices

   ## Stratégies de Fix par Pattern

   ### Stratégie A: Quick Wins (Auto-fixable)
   **Applicabilité:** 156/239 erreurs (65%)
   **Temps estimé:** 2-3 heures
   **Risque:** Minimal

   #### Fixes Automatiques Possibles:
   ```php
   // Pattern: array parameter
   - function process(array $data)
   + function process(array<string, mixed> $data)

   // Pattern: array return
   - function getData(): array
   + function getData(): array<int, string>

   // Pattern: property type
   - /** @var array */
   + /** @var array<string, mixed> */
   ```

   ### Stratégie B: Semi-Automatique (Review Required)
   **Applicabilité:** 65/239 erreurs (27%)
   **Temps estimé:** 4-5 heures
   **Risque:** Moyen

   #### Cas nécessitant review:
   - Union types complexes
   - Génériques imbriqués
   - Types dépendant du contexte

   ### Stratégie C: Refactoring Manuel
   **Applicabilité:** 18/239 erreurs (8%)
   **Temps estimé:** 8-10 heures
   **Risque:** Élevé

   #### Cas complexes:
   - Architecture changes needed
   - Breaking changes potentiels
   - Nouvelle abstraction requise
   ```

4. **Agent-Validator - Plan de Validation :**
   ```markdown
   # Agent-Validator Report
   **Specialization:** Test strategy, validation rules, quality assurance

   ## Plan de Validation Multi-Niveaux

   ### Niveau 1: Validation Syntaxique
   - PHPStan re-run après chaque fix
   - PHP lint check
   - PSR-12 compliance

   ### Niveau 2: Tests Unitaires
   - Tests existants doivent passer
   - Nouveaux tests pour cas edge
   - Coverage maintenue ou améliorée

   ### Niveau 3: Tests d'Intégration
   - API responses inchangées
   - Comportement métier préservé
   - Performance non dégradée

   ## Métriques de Succès
   - [ ] 0 régressions introduites
   - [ ] 100% tests passent
   - [ ] PHPStan errors -X%
   - [ ] Code coverage ≥ current
   ```

5. **Synthèse Multi-Agent :**
   ```bash
   # Consolider les rapports
   cat > 03-fix-strategy.md << 'EOF'
   # Stratégie de Fix Multi-Agent
   **Date:** $(date)
   **Agents:** $agent_count

   ## 🎯 Plan d'Action Consolidé

   ### Phase 1: Quick Wins (2-3h)
   - 156 erreurs auto-fixables
   - Agent-Fixer en mode automatique
   - Agent-Validator en mode light

   ### Phase 2: Review Required (4-5h)
   - 65 erreurs semi-automatiques
   - Collaboration utilisateur requise
   - Tests spécifiques nécessaires

   ### Phase 3: Refactoring (8-10h)
   - 18 erreurs complexes
   - Architecture review
   - Breaking changes possibles

   ## 📊 Estimation Totale
   - Temps: 14-18 heures
   - Risque: Faible à Moyen
   - ROI: Élevé (type safety++)
   EOF
   ```

   STOP → "Stratégie validée ? Commencer Phase 1 ? (y/n)"

### BRAINSTORM (Approches et Conventions)

1. **Collecte du Contexte Projet :**
   ```bash
   echo "📋 Collecte des conventions du projet..."
   ```

   STOP → "Conventions de typage actuelles ?
   1. Strict (PHP 8.x, types partout)
   2. Modéré (Mix PHPDoc + types natifs)
   3. Legacy (PHPDoc principalement)
   4. Aucune convention établie
   Choice:"

   STOP → "Gestion des types array ?
   1. array<key, value> (Génériques complets)
   2. Type[] (Notation courte)
   3. Mixed selon contexte
   4. Toujours array<mixed>
   Choice:"

   STOP → "Politique pour les nullables ?
   1. ?Type explicite partout
   2. Type|null union syntax
   3. PHPDoc seulement
   4. Analyse au cas par cas
   Choice:"

2. **Génération d'Approches Multi-Agents :**

   **Approche Conservative (Agent-Validator) :**
   ```markdown
   ## Approche A: Sécurité Maximale
   **Philosophie:** Aucun risque de régression

   ### Principes:
   - Types mixed si moindre doute
   - @phpstan-ignore pour cas complexes
   - Pas de changement de comportement
   - Documentation de chaque décision

   ### Exemple:
   ```php
   // Si incertain sur le contenu exact
   /** @param array<mixed> $data */
   public function process(array $data): void
   {
       // Code inchangé
   }
   ```

   **Avantages:**
   - ✅ Zero régression
   - ✅ Rapide à implémenter
   - ✅ Facile à reviewer

   **Inconvénients:**
   - ❌ Type safety limitée
   - ❌ Dette technique maintenue
   ```

   **Approche Progressive (Agent-Fixer) :**
   ```markdown
   ## Approche B: Amélioration Graduelle
   **Philosophie:** Balance sécurité et modernisation

   ### Principes:
   - Types précis quand évidents
   - Refactoring léger autorisé
   - Tests pour valider
   - Migration progressive

   ### Exemple:
   ```php
   // Analyse du code pour déterminer les types
   /** @param array<string, int|float> $prices */
   public function calculateTotal(array $prices): float
   {
       return array_sum($prices);
   }
   ```

   **Avantages:**
   - ✅ Meilleure type safety
   - ✅ Code plus maintenable
   - ✅ Apprentissage équipe

   **Inconvénients:**
   - ⚠️ Plus de temps requis
   - ⚠️ Review approfondie nécessaire
   ```

   **Approche Moderne (Agent-Documenter) :**
   ```markdown
   ## Approche C: Best Practices 2025
   **Philosophie:** Code exemplaire pour le futur

   ### Principes:
   - Generics complets partout
   - Types union précis
   - Immutabilité favorisée
   - Patterns modernes (DTO, etc.)

   ### Exemple:
   ```php
   /**
    * @template T of Entity
    * @param array<int, T> $entities
    * @return Collection<int, T>
    */
   public function hydrate(array $entities): Collection
   {
       return new Collection($entities);
   }
   ```

   **Avantages:**
   - ✅ Type safety maximale
   - ✅ Code moderne et élégant
   - ✅ Préparé pour l'avenir

   **Inconvénients:**
   - ❌ Temps important requis
   - ❌ Formation équipe nécessaire
   - ❌ Possible over-engineering
   ```

3. **Matrice de Décision :**
   ```markdown
   | Critère | Conservative | Progressive | Moderne |
   |---------|--------------|-------------|---------|
   | Temps requis | 2-3h | 5-8h | 10-15h |
   | Risque régression | Très faible | Faible | Moyen |
   | Type safety gain | +20% | +60% | +90% |
   | Maintenabilité | = | ++ | +++ |
   | Learning curve | Nulle | Faible | Élevée |
   | ROI court terme | +++ | ++ | + |
   | ROI long terme | + | ++ | +++ |
   ```

   STOP → "Quelle approche choisir ? (A/B/C ou custom) :"

4. **Personnalisation de l'Approche :**
   ```bash
   if [[ "$approach" == "custom" ]]; then
       echo "🎨 Personnalisation de l'approche..."

       STOP → "Niveau de type safety souhaité (1-10) :"
       STOP → "Temps disponible (heures) :"
       STOP → "Tolérance au risque (low/medium/high) :"
       STOP → "Priorités (safety/speed/quality) :"

       # Générer approche hybride basée sur les inputs
   fi
   ```

### IMPLEMENT (Implémentation Itérative des Fixes)

1. **Setup de l'Environnement de Fix :**
   ```bash
   # Créer branche de travail
   current_branch=$(git branch --show-current)
   fix_branch="fix/phpstan-${ERROR_TYPE}-${ANALYSIS_ID}"

   echo "🌿 Création de la branche de fix..."
   git checkout -b "$fix_branch"

   # Préparer répertoires de travail
   mkdir -p 05-fixes/{group-1,group-2,group-3}

   # Copier les templates de fix
   cp ../../templates/*.md 05-fixes/
   ```

2. **Pour Chaque Groupe d'Erreurs :**

   a. **Phase EXTRACT (Extraction du Groupe) :**
   ```bash
   # Exemple pour iterableValue
   error_type="missingType.iterableValue"
   group_name="group-1-iterableValue"

   echo "📋 Extraction des erreurs $error_type..."

   # Extraire avec contexte
   grep -B3 -A3 "$error_type" current-baseline.txt > "05-fixes/$group_name/errors.txt"

   # Parser et organiser par fichier
   current_file=""
   while IFS= read -r line; do
       if [[ $line =~ "Line.*\.php" ]]; then
           current_file=$(echo "$line" | awk '{print $2}')
           echo "=== $current_file ===" >> "05-fixes/$group_name/by-file.txt"
       fi
       echo "$line" >> "05-fixes/$group_name/by-file.txt"
   done < "05-fixes/$group_name/errors.txt"
   ```

   b. **Phase ANALYZE (Analyse du Pattern) :**
   ```bash
   # Agent-Analyzer examine le pattern
   echo "🔍 Agent-Analyzer: Analyse du pattern pour $error_type..."

   cat > "05-fixes/$group_name/pattern-analysis.md" << 'EOF'
   ## Pattern Analysis: $error_type

   ### Cas Type 1: Method Parameters
   **File:** Actions/Financer/GetFinancerMetricsAction.php
   **Line:** 18
   **Current:**
   ```php
   public function execute(array $parameters)
   ```
   **Context:** Parameters viennent de l'API, structure connue
   **Suggested Fix:**
   ```php
   public function execute(array<string, mixed> $parameters)
   ```

   ### Cas Type 2: Return Types
   **File:** Same
   **Line:** 151
   **Current:**
   ```php
   public function getDateRangeForPeriod(): array
   ```
   **Context:** Retourne toujours [start, end] Carbon dates
   **Suggested Fix:**
   ```php
   public function getDateRangeForPeriod(): array{start: Carbon, end: Carbon}
   ```
   EOF
   ```

   STOP → "Pattern analysé. Voir suggestions dans $group_name/pattern-analysis.md. Continuer ? (y/n)"

   c. **Phase FIX (Application Interactive) :**
   ```bash
   # Pour chaque fichier du groupe
   for file in $(grep -l "$error_type" $(find . -name "*.php")); do
       echo "📝 Fichier: $file"
       echo "Erreurs dans ce fichier:"
       grep -n "$error_type" "$file" || grep -B5 "$file" current-baseline.txt | grep -A2 "Line"

       # Montrer le code actuel
       echo -e "\n📄 Code actuel:"
       # Extraire les lignes concernées avec contexte

       # Proposer le fix
       echo -e "\n✨ Fix suggéré:"
       case "$error_type" in
           "missingType.iterableValue")
               echo "Ajouter types array génériques"
               echo "Options:"
               echo "1. array<mixed> (plus permissif)"
               echo "2. array<string, mixed> (standard)"
               echo "3. array<int, Type> (si collection)"
               echo "4. array{key: type} (si structure fixe)"
               echo "5. Analyse manuelle du contexte"
               ;;
       esac

       STOP → "Appliquer quel type de fix ? (1-5 ou skip) :"

       if [[ "$choice" != "skip" ]]; then
           # Backup original
           cp "$file" "$file.backup"

           # Appliquer le fix (exemple simplifié)
           case "$choice" in
               1) fix_type="array<mixed>" ;;
               2) fix_type="array<string, mixed>" ;;
               3)
                   STOP → "Type des éléments ? (ex: User, string, int) :"
                   read element_type
                   fix_type="array<int, $element_type>"
                   ;;
               4)
                   STOP → "Structure ? (ex: 'start: Carbon, end: Carbon') :"
                   read structure
                   fix_type="array{$structure}"
                   ;;
               5)
                   STOP → "Entrez le type complet :"
                   read fix_type
                   ;;
           esac

           # Log le fix
           echo "$file | Line X | $error_type | $fix_type" >> "05-fixes/$group_name/fixes-applied.log"

           # Ouvrir l'éditeur pour le fix manuel
           STOP → "Éditeur ouvert. Appliquer: array → $fix_type. Fait ? (y/n)"
       fi
   done
   ```

   d. **Phase VALIDATE (Validation Immédiate) :**
   ```bash
   # Agent-Validator vérifie chaque fix
   echo "✅ Agent-Validator: Validation du fix..."

   # Re-run PHPStan sur le fichier modifié
   ./vendor/bin/phpstan analyze "$file" --level=9 --no-progress

   # Vérifier que l'erreur est bien corrigée
   if ./vendor/bin/phpstan analyze "$file" --level=9 --no-progress 2>&1 | grep -q "$error_type"; then
       echo "❌ Erreur toujours présente!"
       echo "🔧 Révision nécessaire..."
       # Restaurer backup si nécessaire
   else
       echo "✅ Erreur corrigée!"

       # Vérifier pas de nouvelles erreurs introduites
       new_errors=$(./vendor/bin/phpstan analyze "$file" --level=9 --no-progress 2>&1 | grep -c "🪪")
       if [[ $new_errors -gt 0 ]]; then
           echo "⚠️ $new_errors nouvelles erreurs introduites"
           STOP → "Continuer malgré les nouvelles erreurs ? (y/n)"
       fi
   fi

   # Tests unitaires si existent
   test_file="tests/Unit/$(basename "$file" .php)Test.php"
   if [[ -f "$test_file" ]]; then
       echo "🧪 Exécution des tests..."
       ./vendor/bin/phpunit "$test_file"
       STOP → "Tests passent ? (y/n)"
   fi
   ```

3. **Gestion des Cas Complexes :**
   ```markdown
   ## Cas Complexe Détecté

   **File:** Actions/Vouchers/PurchaseVoucherWithBalanceAction.php
   **Line:** 58
   **Error:** Return type mismatch - union types complexes

   ### Analyse du Problème:
   La méthode retourne 3 structures différentes selon le cas:
   1. Paiement par balance uniquement
   2. Paiement mixte balance + Stripe
   3. Paiement Stripe uniquement

   ### Options de Fix:

   #### Option 1: Union Type Complet
   ```php
   public function execute(): BalancePayment|MixedPayment|StripePayment
   ```

   #### Option 2: DTO Unique
   ```php
   public function execute(): PaymentResult
   {
       return new PaymentResult(
           orderId: $order->id,
           method: $paymentMethod,
           // ... autres champs
       );
   }
   ```

   #### Option 3: Interface Commune
   ```php
   public function execute(): PaymentInterface
   ```

   #### Option 4: @phpstan-ignore avec TODO
   ```php
   /** @phpstan-ignore-next-line */
   // TODO: Refactorer pour retourner un type unique
   public function execute(): array
   ```
   ```

   STOP → "Approche pour ce cas complexe ? (1-4 ou discussion) :"

4. **Suivi de Progression :**
   ```bash
   # Après chaque batch de fixes
   echo "📊 Rapport de Progression"
   echo "========================"

   # Re-run PHPStan et comparer
   make stan > phpstan-current.txt 2>&1

   current_errors=$(grep -c "🪪" phpstan-current.txt)
   fixed_count=$((total_errors - current_errors))

   echo "✅ Corrigées: $fixed_count/$total_errors ($((fixed_count * 100 / total_errors))%)"
   echo "⏳ Restantes: $current_errors"

   # Breakdown par type
   echo -e "\n📊 Erreurs restantes par type:"
   grep "🪪" phpstan-current.txt | awk '{print $2}' | sort | uniq -c | sort -rn | head -10

   # Générer graphique ASCII
   echo -e "\n📈 Progression:"
   printf "["
   for ((i=0; i<$((fixed_count * 40 / total_errors)); i++)); do printf "█"; done
   for ((i=0; i<$((current_errors * 40 / total_errors)); i++)); do printf "░"; done
   printf "] %d%%\n" $((fixed_count * 100 / total_errors))

   # Temps estimé restant
   elapsed=$SECONDS
   rate=$((fixed_count / (elapsed / 3600 + 1)))
   eta=$((current_errors / rate))
   echo -e "\n⏱️ Temps écoulé: $((elapsed / 3600))h $((elapsed % 3600 / 60))m"
   echo "⏱️ ETA: ${eta}h"
   ```

5. **Checkpoints et Sauvegarde :**
   ```bash
   # Tous les 25 fixes
   if [[ $((fixed_count % 25)) -eq 0 ]]; then
       echo "💾 Checkpoint - Sauvegarde progression..."

       # Commit intermédiaire
       git add -A
       git commit -m "fix(phpstan): Progress checkpoint - $fixed_count errors fixed

       - Fixed $error_type errors in multiple files
       - Current progress: $fixed_count/$total_errors
       - No regressions introduced"

       # Mettre à jour rapport
       cat >> 06-validation-report.md << EOF

   ## Checkpoint $(date +%H:%M)
   - Errors fixed: $fixed_count
   - Time elapsed: $((elapsed / 60)) minutes
   - Files modified: $(git diff --name-only HEAD~1 | wc -l)
   - Tests status: ✅ All passing
   EOF
   fi
   ```

### VALIDATE (Validation Globale Multi-Niveaux)

1. **Validation Complète du Codebase :**
   ```bash
   echo "🔍 Validation finale en cours..."

   # Niveau 1: PHPStan complet
   echo "📊 PHPStan analyse complète..."
   make stan > phpstan-final.txt 2>&1

   final_errors=$(grep -c "🪪" phpstan-final.txt || echo 0)
   echo "✅ Erreurs restantes: $final_errors (était: $total_errors)"

   # Niveau 2: Tous les tests
   echo "🧪 Exécution de tous les tests..."
   ./vendor/bin/phpunit --testdox > test-results.txt 2>&1

   if grep -q "FAILURES" test-results.txt; then
       echo "❌ Des tests échouent!"
       grep -A5 "FAILURES" test-results.txt
       STOP → "Investiguer les échecs ? (y/n)"
   else
       echo "✅ Tous les tests passent!"
   fi

   # Niveau 3: Quality checks
   echo "🎨 Quality checks..."
   ./vendor/bin/pint --test

   # Niveau 4: Performance
   echo "⚡ Vérification performance..."
   # Comparer temps d'exécution des tests avant/après
   ```

2. **Rapport de Validation Détaillé :**
   ```markdown
   # Validation Report - PHPStan Fixes
   **Date:** $(date)
   **Session ID:** $ANALYSIS_ID

   ## 📊 Résumé Exécutif
   - **Erreurs initiales:** $total_errors
   - **Erreurs corrigées:** $fixed_count
   - **Erreurs restantes:** $final_errors
   - **Taux de succès:** $((fixed_count * 100 / total_errors))%
   - **Temps total:** $((elapsed / 3600))h $((elapsed % 3600 / 60))m

   ## ✅ Validations Passées
   - [x] PHPStan niveau $current_level
   - [x] Tous les tests unitaires (XXX tests)
   - [x] Tests d'intégration
   - [x] Code style (PSR-12)
   - [x] Pas de régression de performance

   ## 📈 Métriques d'Amélioration
   | Métrique | Avant | Après | Gain |
   |----------|-------|-------|------|
   | Type coverage | 65% | 89% | +24% |
   | Erreurs PHPStan | 239 | 15 | -94% |
   | Code clarity | B | A | ⬆️ |

   ## 🔍 Analyse des Erreurs Restantes
   Les 15 erreurs restantes nécessitent:
   - Refactoring architectural (8)
   - Décision métier (4)
   - Investigation approfondie (3)
   ```

3. **Validation par l'Équipe :**
   ```bash
   echo "👥 Préparation pour review équipe..."

   # Générer diff summary
   git diff --stat > diff-summary.txt

   # Créer PR description
   cat > pr-description.md << 'EOF'
   ## 🎯 PHPStan Fixes - Level 9 Compliance

   ### 📊 Summary
   This PR fixes 224 out of 239 PHPStan errors, bringing us to 94% compliance at level 9.

   ### 🔧 Changes
   - Added array type specifications (156 files)
   - Fixed return type declarations (45 files)
   - Added null safety checks (28 files)
   - Updated PHPDoc blocks for clarity

   ### ✅ Testing
   - All existing tests pass
   - No regressions detected
   - Performance unchanged

   ### 📝 Notes
   - 15 errors remain that require architectural decisions
   - No breaking changes introduced
   - Follows project typing conventions

   ### 📸 Before/After
   ```
   Before: Found 239 errors
   After:  Found 15 errors (-94%)
   ```
   EOF
   ```

### COMMIT (Stratégie de Commits Atomiques)

1. **Organisation des Commits par Type :**
   ```bash
   echo "📦 Organisation des commits..."

   # Séparer les changements par type d'erreur
   git reset HEAD~$checkpoint_count  # Défaire les checkpoints

   # Commit 1: Array type specifications
   git add $(grep -l "iterableValue" 05-fixes/*/fixes-applied.log | xargs grep -h "file" | cut -d'|' -f1)
   git commit -m "fix(phpstan): Add array type specifications

   - Add generic types to all array parameters and returns
   - Fix 89 missingType.iterableValue errors
   - Use array<string, mixed> as default for unknown structures
   - Preserve behavior with permissive types where needed

   PHPStan: iterableValue errors 89 → 0"

   # Commit 2: Return types
   git add $(grep -l "return.type" 05-fixes/*/fixes-applied.log | xargs grep -h "file" | cut -d'|' -f1)
   git commit -m "fix(phpstan): Correct return type declarations

   - Fix return type mismatches in 15 methods
   - Add union types where multiple returns possible
   - Ensure consistency with PHPDoc blocks

   PHPStan: return.type errors 15 → 0"

   # Commit 3: Property access
   git add $(grep -l "property" 05-fixes/*/fixes-applied.log | xargs grep -h "file" | cut -d'|' -f1)
   git commit -m "fix(phpstan): Add null safety for property access

   - Add null checks before property access
   - Fix undefined property warnings
   - Use null coalescing where appropriate

   PHPStan: property.* errors 31 → 12"
   ```

2. **Documentation des Changements :**
   ```bash
   # Générer CHANGELOG
   cat > CHANGELOG-phpstan.md << 'EOF'
   # PHPStan Fixes Changelog

   ## [2025-01-27] - PHPStan Level 9 Compliance Sprint

   ### 🎯 Objective
   Achieve maximum PHPStan level 9 compliance by fixing type-related errors.

   ### ✅ Fixed (224 errors)

   #### Array Type Specifications (89 errors)
   - Added generic array types to all methods
   - Specified array shapes where structure is known
   - Used `array<string, mixed>` for flexible structures

   #### Return Types (15 errors)
   - Corrected method return type declarations
   - Added union types for multiple return possibilities
   - Aligned with actual method implementations

   #### Property Access (31 errors)
   - Added null checks before property access
   - Fixed dynamic property warnings
   - Implemented null-safe operators where applicable

   #### Type Mismatches (26 errors)
   - Fixed argument type inconsistencies
   - Corrected parameter types in method calls
   - Updated type casts where necessary

   ### ⏳ Remaining (15 errors)

   #### Requires Refactoring (8 errors)
   - `method.notFound` - Methods called on wrong types
   - Complex union type scenarios
   - Architectural improvements needed

   #### Business Logic Decisions (4 errors)
   - Ambiguous types requiring domain knowledge
   - Breaking changes if fixed naively

   #### Investigation Needed (3 errors)
   - Edge cases in third-party integrations
   - Dynamic behavior hard to type

   ### 📊 Impact
   - **Type Coverage:** 65% → 89% (+24%)
   - **Developer Experience:** Significantly improved
   - **Bug Prevention:** High - catches type errors at analysis time
   - **Performance:** No impact
   - **Breaking Changes:** None

   ### 🛠️ Tools & Configuration
   - PHPStan version: [version]
   - Level: 9 (maximum)
   - Custom rules: Ergebnis enabled

   ### 👥 Contributors
   - Multi-Agent System (Analyzer, Fixer, Validator, Documenter)
   - Human oversight and validation throughout

   ### 📚 Lessons Learned
   1. Array types should always be specified
   2. Null safety is critical for property access
   3. Union types can model complex returns
   4. Some architectural debt revealed by strict typing
   EOF
   ```

3. **Création de la PR :**
   ```bash
   # Push la branche
   git push -u origin "$fix_branch"

   # Créer PR avec GitHub CLI
   gh pr create \
     --title "fix(phpstan): Fix 224/239 PHPStan level 9 errors" \
     --body "$(cat pr-description.md)" \
     --assignee "@me" \
     --label "phpstan,type-safety,technical-debt" \
     --milestone "Q1 2025 Tech Debt"
   ```

### DOCUMENT (Documentation et Guides)

1. **Guide des Best Practices :**
   ```markdown
   # PHPStan Best Practices Guide
   *Extrait de l'expérience de fix de 200+ erreurs*

   ## 🎯 Conventions de Typage Adoptées

   ### 1. Array Types

   #### ❌ À éviter
   ```php
   public function process(array $data)
   /** @var array */
   private $items;
   ```

   #### ✅ Recommandé
   ```php
   public function process(array<string, mixed> $data)
   /** @var array<int, Item> */
   private array $items;
   ```

   #### 🌟 Best Practice
   ```php
   // Structure connue : utiliser array shapes
   /** @return array{status: string, data: mixed, errors: array<string>} */

   // Collections : utiliser generics
   /** @param array<int, User> $users */

   // Flexible : être explicite sur mixed
   /** @param array<string, mixed> $config */
   ```

   ### 2. Null Safety

   #### Pattern 1: Null Coalescing
   ```php
   // Au lieu de
   $value = $object->property; // Peut être null!

   // Utiliser
   $value = $object->property ?? $default;
   ```

   #### Pattern 2: Early Return
   ```php
   public function process(?User $user): void
   {
       if ($user === null) {
           return;
       }

       // $user est maintenant non-null
       $user->doSomething();
   }
   ```

   #### Pattern 3: Type Narrowing
   ```php
   if ($model instanceof Product) {
       // PHPStan sait que c'est un Product ici
       $model->getPrice();
   }
   ```

   ### 3. Return Types

   #### Union Types pour Cas Multiples
   ```php
   public function find(int $id): User|null
   public function process(): Success|Error
   ```

   #### Never pour Non-Retour
   ```php
   public function abort(): never
   {
       throw new RuntimeException('Aborted');
   }
   ```

   ### 4. Generics et Templates

   ```php
   /**
    * @template T of Model
    * @param class-string<T> $class
    * @param int $id
    * @return T|null
    */
   public function findModel(string $class, int $id): ?Model
   {
       return $class::find($id);
   }
   ```

   ## 📋 Checklist Avant Commit

   - [ ] Tous les arrays ont des types spécifiés
   - [ ] Null safety vérifiée pour chaque propriété access
   - [ ] Return types déclarés sur toutes les méthodes
   - [ ] PHPDoc aligné avec les types natifs
   - [ ] Pas de @phpstan-ignore sans justification
   - [ ] Tests passent après les changements
   - [ ] Pas de régression PHPStan

   ## 🚨 Pièges Courants

   ### 1. Over-Specification
   ```php
   // Trop spécifique, difficile à maintenir
   /** @param array{id: int, name: string, email: string, age?: int, ...} $user */

   // Mieux : utiliser une classe/interface
   public function process(UserData $user)
   ```

   ### 2. Mixed Abuse
   ```php
   // Éviter mixed quand on peut être plus précis
   /** @return mixed */ // ❌
   /** @return string|int|null */ // ✅
   ```

   ### 3. Ignorer Sans Comprendre
   ```php
   // ❌ Mauvais
   /** @phpstan-ignore-next-line */

   // ✅ Bon
   /** @phpstan-ignore-next-line -- Dynamic property from API response */
   ```
   ```

2. **Dashboard de Métriques :**
   ```markdown
   # PHPStan Compliance Dashboard

   ## 📊 Métriques Globales

   | Metric | Before | After | Change |
   |--------|--------|-------|--------|
   | Total Errors | 239 | 15 | -94% |
   | Files Affected | 89 | 89 | 0 |
   | Type Coverage | ~65% | ~89% | +24% |
   | Strict Types | 12% | 67% | +55% |

   ## 📈 Progression par Module

   ```
   Actions/       ████████████████████ 100% (24/24 fixed)
   Models/        ████████████████░░░░  85% (30/35 fixed)
   Services/      ███████████████░░░░░  78% (22/28 fixed)
   Controllers/   ████████████████████  100% (31/31 fixed)
   Integrations/  ████████░░░░░░░░░░░░  43% (42/98 fixed)
   ```

   ## 🏆 Top Fixes par Impact

   1. **Array Type Specifications** - 89 fixes
      - Impact: High (type safety across codebase)
      - Effort: Low (mostly automated)
      - ROI: Excellent

   2. **Null Safety** - 31 fixes
      - Impact: High (prevents runtime errors)
      - Effort: Medium (requires analysis)
      - ROI: Very Good

   3. **Return Types** - 15 fixes
      - Impact: Medium (API clarity)
      - Effort: Low
      - ROI: Good

   ## 🎯 Next Steps

   ### Quick Wins (1-2h)
   - [ ] Fix remaining isset() usage (12 errors)
   - [ ] Add types to Support/ classes (8 errors)

   ### Medium Tasks (3-5h)
   - [ ] Refactor dynamic properties (11 errors)
   - [ ] Fix method existence issues (8 errors)

   ### Major Refactoring (1-2 days)
   - [ ] Voucher system return types (3 errors)
   - [ ] Translation system types (5 errors)

   ## 📅 Maintenance Plan

   - **Weekly:** Run PHPStan in CI
   - **Monthly:** Review new errors
   - **Quarterly:** Level upgrade evaluation
   - **Yearly:** Full type system audit
   ```

3. **Scripts et Outils :**
   ```bash
   # Créer scripts utilitaires
   cat > scripts/phpstan-check.sh << 'EOF'
   #!/bin/bash
   # PHPStan Progressive Check

   # Colors
   RED='\033[0;31m'
   GREEN='\033[0;32m'
   YELLOW='\033[1;33m'
   NC='\033[0m'

   echo "🔍 Running PHPStan analysis..."

   # Run PHPStan
   output=$(make stan 2>&1)
   exit_code=$?

   # Extract error count
   if [[ $output =~ "Found "([0-9]+)" error" ]]; then
       error_count=${BASH_REMATCH[1]}
   else
       error_count=0
   fi

   # Load baseline
   baseline_file="phpstan-baseline.txt"
   if [[ -f $baseline_file ]]; then
       baseline_count=$(grep -oE "Found [0-9]+ error" $baseline_file | grep -oE "[0-9]+")
   else
       baseline_count=$error_count
   fi

   # Compare
   if [[ $error_count -gt $baseline_count ]]; then
       echo -e "${RED}❌ Regression detected!${NC}"
       echo -e "Errors increased: $baseline_count → $error_count (+$((error_count - baseline_count)))"
       exit 1
   elif [[ $error_count -lt $baseline_count ]]; then
       echo -e "${GREEN}✅ Improvement!${NC}"
       echo -e "Errors reduced: $baseline_count → $error_count (-$((baseline_count - error_count)))"

       # Update baseline
       echo "$output" > $baseline_file
       echo "Baseline updated."
   else
       echo -e "${YELLOW}➖ No change${NC}"
       echo -e "Errors: $error_count"
   fi

   # Show breakdown
   echo -e "\n📊 Error breakdown:"
   echo "$output" | grep "🪪" | awk '{print $2}' | sort | uniq -c | sort -rn | head -10

   exit $exit_code
   EOF

   chmod +x scripts/phpstan-check.sh
   ```

4. **Templates pour Fixes Futurs :**
   ```markdown
   # Template: Fix Array Type Specifications

   ## Pattern Recognition
   Look for: `array` without type specifications

   ## Quick Fix Guide

   ### 1. Analyze Usage
   - Check what goes into the array
   - Check what comes out
   - Look for foreach loops
   - Check array_* function usage

   ### 2. Determine Type

   | Usage Pattern | Suggested Type |
   |---------------|----------------|
   | Numeric keys only | `array<int, Type>` |
   | String keys only | `array<string, Type>` |
   | Mixed keys | `array<array-key, Type>` |
   | Known structure | `array{key: Type, ...}` |
   | From JSON/API | `array<string, mixed>` |
   | Collection of objects | `array<int, Object>` |

   ### 3. Apply Fix

   #### In Parameters:
   ```php
   - public function process(array $items)
   + public function process(array<int, Item> $items)
   ```

   #### In Returns:
   ```php
   - public function getConfig(): array
   + public function getConfig(): array<string, mixed>
   ```

   #### In Properties:
   ```php
   - /** @var array */
   - private array $cache;
   + /** @var array<string, CacheItem> */
   + private array $cache;
   ```

   ### 4. Validate
   - Run PHPStan on the file
   - Run related tests
   - Check for new errors
   ```

## Commandes de Maintenance

### Commandes Principales
```bash
# Analyse complète
claude "/phpstan --analyze"

# Fix automatique des erreurs simples
claude "/phpstan --auto-fix safe"

# Analyse d'un type d'erreur spécifique
claude "/phpstan --analyze-type iterableValue"

# Générer rapport de progression
claude "/phpstan --progress"

# Dashboard interactif
claude "/phpstan --dashboard"
```

### Commandes Avancées
```bash
# Analyser un module spécifique
claude "/phpstan --analyze-path app/Services"

# Proposer fixes pour un fichier
claude "/phpstan --suggest-fixes path/to/file.php"

# Valider tous les fixes
claude "/phpstan --validate-all"

# Rollback dernier batch de fixes
claude "/phpstan --rollback-last"
```

## Configuration CI/CD

### GitHub Actions
```yaml
name: PHPStan Progressive

on:
  pull_request:
    types: [opened, synchronize]

jobs:
  phpstan:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install

      - name: Run PHPStan Progressive Check
        run: ./scripts/phpstan-check.sh

      - name: Comment PR
        if: always()
        uses: actions/github-script@v6
        with:
          script: |
            const output = '${{ steps.phpstan.outputs.stdout }}';
            const errors = output.match(/Found (\d+) error/)[1];
            const baseline = // load from file

            github.rest.issues.createComment({
              issue_number: context.issue.number,
              owner: context.repo.owner,
              repo: context.repo.repo,
              body: `## PHPStan Check

              Errors: ${errors} (Baseline: ${baseline})
              ${errors < baseline ? '✅ Improvement!' : ''}
              ${errors > baseline ? '❌ Regression!' : ''}
              `
            });
```

### Pre-commit Hook
```bash
#!/bin/bash
# .git/hooks/pre-commit

# Run PHPStan on changed files only
changed_files=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')

if [[ -n "$changed_files" ]]; then
    echo "Running PHPStan on changed files..."
    ./vendor/bin/phpstan analyze $changed_files --level=9

    if [[ $? -ne 0 ]]; then
        echo "❌ PHPStan errors found. Please fix before committing."
        exit 1
    fi
fi
```

## Troubleshooting

### Problèmes Courants

**1. False Positives**
```php
// Solution: Utiliser assertions PHPStan
assert($value instanceof ExpectedType);

// Ou type narrowing
if (!$value instanceof ExpectedType) {
    throw new UnexpectedValueException();
}
```

**2. Dynamic Properties**
```php
// Solution 1: Utiliser @property
/** @property string $dynamicProperty */

// Solution 2: Utiliser __get/__set avec types
public function __get(string $name): mixed
```

**3. Mixed Content Arrays**
```php
// Au lieu de forcer un type
/** @var array<string, mixed> $data */

// Considérer une structure
/** @var array{
 *   status: string,
 *   data?: mixed,
 *   errors?: array<string>
 * } $response
 */
```

## Notes Importantes

- **Pas de régression** : Chaque fix doit maintenir les tests verts
- **Commits atomiques** : Un commit par type d'erreur
- **Documentation** : Chaque pattern découvert doit être documenté
- **Collaboration** : Décisions importantes validées avec l'utilisateur
- **Progressif** : Mieux vaut 90% corrigé que 0%
- **Type safety** : L'objectif est d'améliorer la sécurité du code

Ce workflow garantit une amélioration progressive et sûre de la conformité PHPStan tout en maintenant la qualité et la stabilité du code ! 🚀