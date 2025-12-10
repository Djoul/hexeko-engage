# Guide d'Audit Rétrospectif - Évaluation de Conformité

## 📌 Vue d'Ensemble

Ce guide permet d'**évaluer rétrospectivement** une fonctionnalité déjà développée pour vérifier si elle respecte tous les critères d'acceptation et standards de qualité définis dans la méthodologie backend-api.

### Objectifs de l'Audit
- ✅ **Conformité méthodologique** : Vérifier si toutes les phases ont été respectées
- 🔍 **Qualité du code** : Évaluer la qualité technique et architecturale
- 📚 **Complétude documentation** : S'assurer que la documentation est exhaustive
- 🧪 **Couverture tests** : Valider la stratégie de test et coverage
- 🚀 **Performance** : Contrôler les aspects performance et optimisation
- 📋 **Génération rapport** : Produire un rapport de conformité avec gaps identifiés

---

## 🚀 Points d'Entrée Audit

### 1. Depuis Story/Epic Jira
```markdown
# Prompt pour Claude Code
Effectue un audit complet de la story UE-268 :
1. Récupère les détails depuis Jira
2. Trace tous les composants développés
3. Évalue la conformité avec notre méthodologie
4. Génère un rapport de gaps avec recommandations
```

### 2. Depuis Fichier Markdown Existant
```markdown
# Prompt pour Claude Code
Audite la fonctionnalité documentée dans /todos/stories/UE-268/implementation.md :
1. Analyse le code implémenté
2. Vérifie tous les livrables attendus
3. Évalue la qualité selon nos standards
4. Identifie les améliorations possibles
```

### 3. Depuis Namespace/Dossier
```markdown
# Prompt pour Claude Code
Audite tout le module Vouchers/Amilon :
1. Parcours app/Integrations/Vouchers/Amilon/
2. Vérifie conformité architecture Service/Action
3. Évalue tests, documentation, cache
4. Compare avec checklist universelle
```

### 4. Depuis Commit/PR
```markdown
# Prompt pour Claude Code
Audite les changements du commit abc123 ou PR #45 :
1. Analyse les fichiers modifiés
2. Vérifie si tous les aspects ont été couverts
3. Évalue la qualité des changements
4. Suggère améliorations si nécessaire
```

---

## 🔍 Processus d'Audit en 6 Étapes

### Étape 1: DÉCOUVERTE DU SCOPE
**Objectif** : Identifier et cartographier tout ce qui a été développé

#### Actions Claude Code
```markdown
# Découverte automatique du scope
1. Si Jira : Récupère story/epic avec critères d'acceptation
2. Liste TOUS les fichiers créés/modifiés :
   - Models, Services, Actions, Controllers
   - Tests, Factories, Migrations
   - Configuration, Routes, Documentation
3. Identifie les intégrations externes
4. Mappe les dépendances entre composants
```

#### Livrables
- `audit-scope.md` : Liste exhaustive des composants
- `audit-timeline.md` : Chronologie des développements
- `audit-dependencies.md` : Carte des dépendances

### Étape 2: ÉVALUATION ARCHITECTURE
**Objectif** : Vérifier la conformité architecturale

#### Checklist Architecture
```markdown
□ Pattern Service/Action respecté
□ DTOs utilisés (pas d'arrays)
□ FormRequests pour validation
□ Separation of concerns respectée
□ Dependency Injection appropriée
□ Event/Listener pattern si applicable
□ Cache strategy implémentée
□ Error handling gracieux
□ Logs structurés
□ Security best practices
```

#### Prompt d'Évaluation
```markdown
Évalue l'architecture du module [Module] :
1. Vérifie le respect des patterns Service/Action
2. Contrôle l'utilisation des DTOs vs arrays
3. Évalue la séparation des responsabilités
4. Vérifie l'injection de dépendances
5. Contrôle la gestion d'erreurs
6. Évalue la stratégie de cache
Note chaque aspect sur 10 et justifie.
```

### Étape 3: AUDIT QUALITÉ CODE
**Objectif** : Évaluer la qualité technique du code

#### Tests de Qualité Automatisés
```bash
# Tests obligatoires
make test                    # Tous les tests passent ?
make quality-check          # 0 erreur PHPStan/Pint ?
make coverage              # > 80% coverage ?
```

#### Évaluation Manuelle
```markdown
# Prompt pour Claude Code
Évalue la qualité du code pour [Fonctionnalité] :
1. Complexité cyclomatique acceptable ?
2. Nommage des classes/méthodes cohérent ?
3. Méthodes < 20 lignes en moyenne ?
4. Duplication de code minimale ?
5. Comments appropriés (pas trop, pas trop peu) ?
6. Type hints présents partout ?
7. Respect PSR-12 ?
Donne une note globale /10 avec justifications.
```

### Étape 4: AUDIT TESTS & TDD
**Objectif** : Évaluer la stratégie de test et coverage

#### Checklist Tests
```markdown
□ Tests unitaires pour chaque Service
□ Tests Actions avec mocks appropriés
□ Tests Feature pour chaque endpoint API
□ Tests integration si intégration externe
□ Tests performance pour endpoints critiques
□ Tests sécurité (auth, validation, injection)
□ Tests edge cases et error scenarios
□ Coverage > 80% sur code métier
□ Factories appropriées utilisées
□ DatabaseTransactions utilisées (pas RefreshDatabase)
□ Mocks externes configurés
□ Assertions meaningfully named
```

#### Analyse Coverage Détaillée
```markdown
# Prompt pour Claude Code
Génère et analyse le coverage report :
1. make coverage-html
2. Identifie les zones < 80%
3. Évalue si les lignes non couvertes sont critiques
4. Propose des tests additionnels si nécessaire
5. Vérifie la qualité des assertions
```

### Étape 5: AUDIT DOCUMENTATION
**Objectif** : Vérifier l'exhaustivité de la documentation

#### Checklist Documentation
```markdown
□ Documentation OpenAPI générée et à jour
□ Endpoints API tous documentés
□ Request/Response examples présents
□ Error codes documentés
□ Guide d'intégration frontend créé
□ Architecture documented si complexe
□ README.md mis à jour si nécessaire
□ CHANGELOG.md mis à jour
□ Confluence publié si applicable
□ Code comments appropriés (minimal)
□ PHPDoc sur méthodes publiques complexes
```

#### Évaluation Complétude
```markdown
# Prompt pour Claude Code
Audite la documentation de [Module] :
1. Vérifie présence OpenAPI spec
2. Contrôle exhaustivité endpoints documentés
3. Évalue qualité examples et descriptions
4. Vérifie guide frontend disponible
5. Contrôle publication Confluence
6. Évalue si documentation permet utilisation autonome
Note /10 avec gaps identifiés.
```

### Étape 6: AUDIT PERFORMANCE & PRODUCTION
**Objectif** : Évaluer les aspects performance et production-readiness

#### Checklist Performance
```markdown
□ Response times < 500ms validés
□ Pas de N+1 queries
□ Indexes database appropriés
□ Cache Redis implémenté avec TTL
□ Cache invalidation sur write
□ Eager loading utilisé si nécessaire
□ Pagination sur listings
□ Logs structurés (pas trop verbeux)
□ Monitoring/metrics en place
□ Error tracking (Sentry) configuré
```

#### Test Performance
```bash
# Tests de performance
ab -n 100 -c 10 http://localhost:1310/api/v1/endpoint
newman run postman/collection.json --reporters cli,json
```

---

## 📊 Système de Notation

### Scoring par Catégorie
Chaque catégorie est notée sur 10 :

| Catégorie | Poids | Critères Principaux |
|-----------|-------|-------------------|
| **Architecture** | 25% | Patterns, DTOs, Separation, DI |
| **Qualité Code** | 20% | Complexité, Naming, Standards |
| **Tests** | 25% | Coverage, TDD, Quality |
| **Documentation** | 15% | API Docs, Guides, Examples |
| **Performance** | 10% | Speed, Queries, Cache |
| **Production** | 5% | Monitoring, Logs, Security |

### Grille d'Évaluation

#### 🟢 EXCELLENT (9-10/10)
- Tous les critères respectés
- Best practices appliquées
- Code exemplaire
- Documentation exhaustive

#### 🟡 SATISFAISANT (7-8/10)
- Critères principaux respectés
- Quelques améliorations mineures
- Code de qualité acceptable
- Documentation suffisante

#### 🟠 AMÉLIORATION REQUISE (5-6/10)
- Critères partiellement respectés
- Plusieurs gaps identifiés
- Refactoring nécessaire
- Documentation incomplète

#### 🔴 NON-CONFORME (0-4/10)
- Critères majeurs non respectés
- Nombreux problèmes
- Réécriture potentiellement nécessaire
- Documentation manquante/incorrecte

---

## 📋 Templates de Rapport

### Template Rapport Complet
```markdown
# Rapport d'Audit : [FONCTIONNALITÉ]

**Date** : [DATE]
**Auditeur** : Claude Code + [DEVELOPER]
**Scope** : [DESCRIPTION]
**Version** : [TAG/COMMIT]

## 📊 Score Global : [X]/10

### Détail par Catégorie
- 🏗️ Architecture : [X]/10
- 🔧 Qualité Code : [X]/10
- 🧪 Tests : [X]/10
- 📚 Documentation : [X]/10
- ⚡ Performance : [X]/10
- 🚀 Production : [X]/10

## ✅ Points Forts
- [Liste des aspects excellents]

## ⚠️ Gaps Identifiés
- [Liste des problèmes avec priorité]

## 🔧 Recommandations
- [Actions correctives recommandées]

## 📈 Plan d'Amélioration
- [Étapes pour atteindre 10/10]
```

### Template Rapport Express
```markdown
# Audit Express : [FEATURE]

**Status** : 🟢 Conforme | 🟡 Améliorable | 🟠 Gaps | 🔴 Non-conforme

## Quick Check ✅❌
- Architecture : ✅/❌
- Tests : ✅/❌
- Documentation : ✅/❌
- Performance : ✅/❌

## Actions Requises
1. [Action prioritaire 1]
2. [Action prioritaire 2]
3. [Action prioritaire 3]
```

---

## 🤖 Prompts Optimaux pour Claude Code

### Audit Complet
```markdown
Effectue un audit complet de [FEATURE/MODULE] :

1. DÉCOUVERTE :
   - Récupère story/epic depuis Jira si applicable
   - Liste TOUS les fichiers créés/modifiés
   - Mappe les dépendances et intégrations

2. ÉVALUATION :
   - Architecture : patterns, DTOs, séparation
   - Qualité : standards, complexité, nommage
   - Tests : coverage, TDD, quality des assertions
   - Documentation : API docs, guides, examples
   - Performance : speed, queries, cache
   - Production : monitoring, logs, security

3. RAPPORT :
   - Score détaillé par catégorie (/10)
   - Points forts identifiés
   - Gaps avec priorité
   - Recommandations concrètes
   - Plan d'amélioration

Utilise la checklist universelle comme référence et sois précis dans tes évaluations.
```

### Audit Ciblé Architecture
```markdown
Audite spécifiquement l'architecture de [MODULE] :

Vérifie :
- Pattern Service/Action respecté ?
- DTOs vs arrays ?
- FormRequests utilisées ?
- Injection de dépendances ?
- Cache strategy ?
- Event/Listener pattern ?
- Error handling ?
- Security measures ?

Donne un score détaillé /10 avec justifications.
```

### Audit Tests Détaillé
```markdown
Audite la stratégie de tests pour [FEATURE] :

Analyse :
1. Coverage report (make coverage)
2. Qualité des tests unitaires
3. Tests integration présents ?
4. Mocks appropriés ?
5. Edge cases couverts ?
6. Performance tests ?
7. DatabaseTransactions utilisées ?

Score /10 avec recommandations d'amélioration.
```

### Audit Performance
```markdown
Audite les performances de [API/MODULE] :

Tests :
1. Response times < 500ms ?
2. N+1 queries détectées ?
3. Cache implémenté et efficace ?
4. Database indexes appropriés ?
5. Pagination sur listings ?
6. Monitoring en place ?

Utilise les outils de profiling disponibles.
```

---

## 🔧 Scripts et Automatisation

### Script d'Audit Automatique
```bash
#!/bin/bash
# audit-feature.sh

FEATURE=$1
TYPE=${2:-"story"}  # story|epic|module

echo "🔍 Démarrage audit de $FEATURE..."

# 1. Tests automatisés
make test
make quality-check
make coverage

# 2. Performance tests
newman run postman/collection.json --folder "$FEATURE"

# 3. Génération rapport
echo "📊 Génération rapport..."
./generate-audit-report.sh $FEATURE $TYPE

echo "✅ Audit terminé. Voir audit-reports/$FEATURE.md"
```

### Configuration TodoWrite pour Audit
```markdown
# Création todos audit
Crée une todo list d'audit pour [FEATURE] :
- [ ] Découverte scope et composants
- [ ] Évaluation architecture
- [ ] Audit qualité code
- [ ] Audit tests et coverage
- [ ] Audit documentation
- [ ] Audit performance
- [ ] Génération rapport final
- [ ] Plan d'amélioration
```

---

## 🎯 Cas d'Usage Courants

### 1. Code Review Pre-Merge
```markdown
# Avant merge d'une PR
Audite les changements de la PR #123 :
1. Vérifie conformité avec méthodologie
2. Contrôle qualité du code ajouté
3. Valide tests appropriés
4. Confirme documentation mise à jour
```

### 2. Technical Debt Assessment
```markdown
# Évaluation dette technique
Audite le module [Legacy Module] pour identifier :
1. Code smells et refactoring needed
2. Tests manquants
3. Documentation obsolète
4. Performance issues
5. Security vulnerabilities
Priorise les améliorations.
```

### 3. Onboarding New Developer
```markdown
# Formation développeur
Utilise l'audit de [Feature] comme exemple :
1. Montre ce qui est conforme
2. Identifie les gaps
3. Explique pourquoi c'est important
4. Propose exercice de correction
```

### 4. Quality Assurance Milestone
```markdown
# QA avant release
Audite toutes les features du milestone v2.1 :
1. Score global par feature
2. Identification des risques
3. Priorisation des corrections
4. Validation release readiness
```

---

## 📈 Métriques et KPIs

### Métriques par Audit
- **Score global** : Moyenne pondérée toutes catégories
- **Conformité rate** : % features > 7/10
- **Critical gaps** : Nombre de problèmes critiques
- **Documentation coverage** : % APIs documentées
- **Test coverage** : % code couvert
- **Performance score** : % endpoints < 500ms

### Tracking dans le Temps
- **Trend score** : Évolution qualité dans le temps
- **Gap resolution rate** : Vitesse de correction des gaps
- **Technical debt** : Évolution de la dette technique
- **Best practices adoption** : Respect des standards

### Dashboard Recommandé
```markdown
# Quality Dashboard
- 🎯 Score Global Projet : [X]/10
- 📊 Répartition Features :
  - 🟢 Excellent (9-10) : X%
  - 🟡 Satisfaisant (7-8) : X%
  - 🟠 À améliorer (5-6) : X%
  - 🔴 Non-conforme (0-4) : X%
- 🔥 Top 5 Gaps à Corriger
- 📈 Évolution Qualité (30j)
```

---

## 🚀 Best Practices Audit

### Pour l'Auditeur
1. **Objectivité** : Base-toi sur des critères mesurables
2. **Exhaustivité** : N'oublie aucun aspect important
3. **Constructivité** : Propose des solutions, pas seulement des critiques
4. **Priorisation** : Distingue le critique du nice-to-have
5. **Documentation** : Justifie chaque note donnée

### Pour le Développeur Audité
1. **Ouverture** : Accepte les retours constructifs
2. **Apprentissage** : Utilise l'audit pour progresser
3. **Action** : Planifie la correction des gaps
4. **Communication** : Discute les points d'amélioration
5. **Suivi** : Vérifie que les corrections sont efficaces

### Pour l'Équipe
1. **Standardisation** : Utilise les mêmes critères
2. **Amélioration continue** : Fait évoluer les standards
3. **Partage** : Communique les bonnes pratiques
4. **Formation** : Utilise les audits comme outil pédagogique
5. **Culture qualité** : Intègre l'audit dans le processus

---

## 📚 Ressources Additionnelles

### Checklist de Référence
- [Universal Todo Checklist](/workflows/templates/universal-todo-checklist.md)
- [Backend API Methodology](/workflows/methodology/backend-api-methodology.md)
- [CLAUDE.md Conventions](/CLAUDE.md)

### Outils d'Analyse
- PHPStan pour analyse statique
- PHP_CodeSniffer pour standards
- PHPUnit pour coverage
- Telescope/Debugbar pour performance
- Newman pour tests API

### Templates
- `audit-report-template.md`
- `gap-analysis-template.md`
- `improvement-plan-template.md`

---

*Guide Audit v1.0 - Évaluation Rétrospective Complète*
*Compatible avec méthodologie backend-api-methodology.md*