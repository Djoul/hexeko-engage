# Git Workflow & Branch Strategy

Guide complet pour le workflow Git et la gestion des branches dans le projet UpEngage API.

## 📝 Convention de Nommage des Branches

### Format Standard
```
type/description-courte
```

### Types de Branches

| Type | Usage | Exemple |
|------|-------|---------|
| `feature/` | Nouvelles fonctionnalités | `feature/user-authentication` |
| `fix/` | Corrections de bugs | `fix/payment-calculation` |
| `hotfix/` | Corrections urgentes en production | `hotfix/critical-security-patch` |
| `refactor/` | Refactoring de code | `refactor/service-layer-cleanup` |
| `test/` | Ajout ou modification de tests | `test/increase-coverage-orders` |
| `docs/` | Documentation uniquement | `docs/api-documentation-update` |
| `chore/` | Maintenance, configuration | `chore/update-dependencies` |

## 🔄 Workflow de Développement

### 1. Création de Branche
```bash
# Toujours partir de la branche principale à jour
git checkout main
git pull origin main

# Créer et basculer sur la nouvelle branche
git checkout -b feature/nom-feature

# Pousser la branche vide pour la tracker
git push -u origin feature/nom-feature
```

### 2. Développement avec TDD
```bash
# 1. Écrire les tests AVANT l'implémentation
make test  # Voir les tests échouer

# 2. Implémenter la fonctionnalité
# ... développement ...

# 3. Vérifier que les tests passent
make test

# 4. Vérifier la qualité du code
make quality-check  # DOIT PASSER à 100%
```

### 3. Commits Atomiques

#### Convention de Messages de Commit
```
type(scope): description courte

Description détaillée optionnelle

Fixes #issue_number
```

#### Types de Commits

| Type | Description | Exemple |
|------|-------------|---------|
| `feat` | Nouvelle fonctionnalité | `feat(auth): add JWT refresh token` |
| `fix` | Correction de bug | `fix(payment): correct tax calculation` |
| `docs` | Documentation | `docs(api): update endpoint descriptions` |
| `style` | Formatage, pas de changement de code | `style: fix indentation` |
| `refactor` | Refactoring sans changement fonctionnel | `refactor(service): extract validation logic` |
| `test` | Ajout ou modification de tests | `test(user): add integration tests` |
| `chore` | Maintenance, configuration | `chore: update composer dependencies` |
| `perf` | Amélioration de performance | `perf(query): optimize user search` |

#### Exemples de Commits
```bash
# Fonctionnalité
git commit -m "feat(voucher): add bulk creation endpoint

- Implement batch voucher creation
- Add validation for bulk operations
- Update API documentation

Resolves #234"

# Correction
git commit -m "fix(credit): prevent negative balance

Ensure credit deduction cannot result in negative balance
by adding pre-validation check

Fixes #456"

# Test
git commit -m "test(order): increase coverage to 85%

Add missing unit tests for OrderService
Add integration tests for order workflow"
```

### 4. Synchronisation avec Main
```bash
# Régulièrement, synchroniser avec main
git checkout main
git pull origin main
git checkout feature/nom-feature
git merge main  # ou git rebase main pour un historique linéaire

# Résoudre les conflits si nécessaire
# Puis continuer le développement
```

### 5. Push et Pull Request

#### Avant le Push Final
```bash
# Checklist obligatoire
make test           # ✅ Tous les tests passent
make quality-check  # ✅ 0 erreurs
make coverage       # ✅ > 80% de couverture

# Si tout est OK, pusher
git push origin feature/nom-feature
```

#### Création de Pull Request
1. Aller sur GitLab
2. Créer une Merge Request vers `main`
3. Utiliser ce template :

```markdown
## 📋 Description
Brève description des changements

## 🎯 Type de changement
- [ ] 🐛 Bug fix (changement non-breaking qui corrige un problème)
- [ ] ✨ Nouvelle fonctionnalité (changement non-breaking qui ajoute une fonctionnalité)
- [ ] 💥 Breaking change (changement qui casse la compatibilité)
- [ ] 📝 Documentation uniquement
- [ ] ♻️ Refactoring (pas de changement fonctionnel)

## ✅ Checklist
- [ ] Tests écrits AVANT l'implémentation (TDD)
- [ ] `make test` passe à 100%
- [ ] `make quality-check` passe sans erreurs
- [ ] Coverage > 80%
- [ ] Documentation mise à jour si nécessaire
- [ ] Pas de `@phpstan-ignore` ajoutés
- [ ] Pas de `TODO` ou code commenté

## 🧪 Tests
- Coverage avant : X%
- Coverage après : X%
- Nouveaux tests ajoutés : X

## 📸 Screenshots (si applicable)
[Screenshots ou GIFs des changements UI]

## 🔗 Issues liées
Fixes #(issue number)
```

## 🚀 Stratégie de Branches

### Branches Principales

| Branche | Rôle | Protection |
|---------|------|------------|
| `main` | Production | ✅ Protégée, MR obligatoire |
| `develop` | Développement | ✅ Protégée, MR obligatoire |
| `staging` | Pre-production | ✅ Protégée, MR obligatoire |

### Flux de Travail GitFlow Simplifié

```
feature/* ──┐
            ├──> develop ──> staging ──> main
fix/*    ───┘

hotfix/* ────────────────────────────> main
                                         │
                                         └──> develop
```

### Règles de Merge

1. **Feature → Develop**
   - Code review obligatoire
   - Tests passent
   - Coverage > 80%
   - CI/CD pipeline verte

2. **Develop → Staging**
   - Tests d'intégration complets
   - Validation QA

3. **Staging → Main**
   - Approval du responsable technique
   - Tests de régression passés
   - Documentation à jour

4. **Hotfix → Main**
   - Correction critique uniquement
   - Tests minimaux passés
   - Merge back vers develop immédiat

## 🔍 Code Review

### Points de Vérification

#### Architecture & Design
- [ ] Respect du pattern Service/Action
- [ ] Pas de logique métier dans les controllers
- [ ] DTOs utilisés pour le transfert de données
- [ ] Transactions pour opérations multiples

#### Tests
- [ ] Tests écrits avant l'implémentation
- [ ] Utilisation de `#[Test]` et `#[Group()]`
- [ ] `DatabaseTransactions` utilisé (pas `RefreshDatabase`)
- [ ] `ModelFactory` pour création de données
- [ ] Focus sur la logique métier, pas l'auth

#### Code Quality
- [ ] Pas de duplication de code
- [ ] Noms explicites et clairs
- [ ] Pas de magic numbers
- [ ] Commentaires en anglais si nécessaires

#### Performance
- [ ] Queries optimisées (eager loading)
- [ ] Cache utilisé pour les lectures
- [ ] Pas de N+1 queries

## 🚫 Interdictions

### Ne JAMAIS
- Commiter directement sur `main`, `develop` ou `staging`
- Merger sans code review
- Désactiver les protections de branches
- Forcer un push (`git push --force`) sur branches partagées
- Commiter des secrets ou credentials
- Merger avec des tests qui échouent
- Merger avec coverage < 80%

### Éviter
- Commits trop larges (> 200 lignes modifiées)
- Messages de commit vagues ("fix", "update", "changes")
- Branches de longue durée (> 1 semaine)
- Conflits de merge non résolus proprement

## 🔧 Commandes Git Utiles

### Gestion des Branches
```bash
# Lister toutes les branches
git branch -a

# Supprimer branche locale
git branch -d feature/old-feature

# Supprimer branche distante
git push origin --delete feature/old-feature

# Nettoyer les références locales
git remote prune origin
```

### Gestion des Commits
```bash
# Modifier le dernier commit
git commit --amend

# Squash les N derniers commits
git rebase -i HEAD~N

# Cherry-pick un commit spécifique
git cherry-pick <commit-hash>

# Annuler le dernier commit (garde les changements)
git reset --soft HEAD~1

# Annuler le dernier commit (supprime les changements)
git reset --hard HEAD~1
```

### Résolution de Conflits
```bash
# Voir les fichiers en conflit
git status

# Accepter leurs changements
git checkout --theirs <file>

# Accepter nos changements
git checkout --ours <file>

# Après résolution
git add <file>
git commit
```

### Stash - Sauvegarde Temporaire
```bash
# Sauvegarder les changements actuels
git stash

# Lister les stash
git stash list

# Appliquer le dernier stash
git stash pop

# Appliquer un stash spécifique
git stash apply stash@{2}

# Supprimer tous les stash
git stash clear
```

## 📊 Git Hooks (Pre-commit)

### Configuration Recommandée
```bash
# .git/hooks/pre-commit
#!/bin/sh

# Vérifier le code style
make pint-check || exit 1

# Vérifier PHPStan
make phpstan || exit 1

# Vérifier qu'il n'y a pas de dump() ou dd()
grep -r "dump(\|dd(" app/ tests/ && echo "Remove dump() or dd() calls" && exit 1

# Vérifier qu'il n'y a pas de credentials
grep -r "password\|secret\|key" .env.example && echo "Check for exposed credentials" && exit 1

exit 0
```

## 🏷️ Tags et Versions

### Convention de Versioning (SemVer)
```
vMAJOR.MINOR.PATCH

v1.2.3
│ │ └── Patch: corrections de bugs
│ └──── Minor: nouvelles fonctionnalités compatibles
└────── Major: changements breaking
```

### Création de Tags
```bash
# Créer un tag annoté
git tag -a v1.2.3 -m "Release version 1.2.3"

# Pousser le tag
git push origin v1.2.3

# Pousser tous les tags
git push origin --tags

# Lister les tags
git tag -l

# Voir les détails d'un tag
git show v1.2.3
```

## 📈 Statistiques et Historique

### Commandes Utiles
```bash
# Historique graphique
git log --graph --oneline --all

# Commits par auteur
git shortlog -sn

# Fichiers les plus modifiés
git log --pretty=format: --name-only | sort | uniq -c | sort -rg | head -10

# Voir qui a modifié quoi
git blame <file>

# Chercher dans l'historique
git log -S "search term"
```

## 🆘 Récupération d'Urgence

### Retrouver des Commits Perdus
```bash
# Voir tous les mouvements de HEAD
git reflog

# Récupérer un commit perdu
git checkout <commit-hash>
git checkout -b recovery-branch
```

### Annuler une Merge
```bash
# Avant push
git reset --hard HEAD~1

# Après push (crée un nouveau commit d'annulation)
git revert -m 1 <merge-commit-hash>
```

## 📚 Ressources

- [Git Documentation](https://git-scm.com/doc)
- [GitFlow Workflow](https://www.atlassian.com/git/tutorials/comparing-workflows/gitflow-workflow)
- [Conventional Commits](https://www.conventionalcommits.org/)
- Guide interne Confluence : "Git Best Practices Hexeko"

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko  
**Version**: 1.0