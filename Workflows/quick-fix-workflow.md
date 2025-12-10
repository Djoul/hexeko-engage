# Quick Fix Workflow - Corrections Rapides
Workflow optimisé pour les corrections rapides (< 30 minutes) : hotfixes urgents, typos, ajustements de configuration, petits bugs.

## Workflow

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : INIT → SELECT → QUICK_REFINE → TEST (optionnel) → FIX → COMMIT
- Ce workflow est conçu pour des corrections RAPIDES (< 30 minutes)
- Les tests sont OPTIONNELS mais recommandés
- La création de branche est OPTIONNELLE
- Vous NE DEVEZ PAS mentionner Claude ou l'assistant dans les commits
- Priorisez la rapidité tout en maintenant la qualité

### INIT
1. **Vérification rapide du contexte :**
   - Lire `CLAUDE.MD` si disponible (max 30 secondes)
   - Si absent, continuer sans bloquer
   
2. **État du repository :**
   ```bash
   git status
   git branch --show-current
   ```
   - Noter la branche actuelle
   - Vérifier qu'il n'y a pas de changements non commités

### SELECT
1. STOP → "Quelle est la correction rapide à effectuer ?
   1. Hotfix urgent en production
   2. Correction de typo/texte
   3. Ajustement de configuration
   4. Petit bug identifié
   5. Correction depuis Sentry/logs
   6. Autre correction rapide
   Entrez votre choix (1-6) :"

2. **Collecte d'informations minimales :**
   - Si Hotfix (1) : "Décrivez le problème urgent"
   - Si Typo (2) : "Où se trouve la typo ?"
   - Si Config (3) : "Quel paramètre ajuster ?"
   - Si Bug (4) : "Décrivez le bug et sa localisation"
   - Si Sentry (5) : Récupérer l'erreur via MCP
   - Si Autre (6) : "Décrivez la correction"

3. **Décision de branche :**
   - STOP → "Créer une branche pour cette correction ? (y/n)"
   - Si oui :
     ```bash
     git checkout -b hotfix/[description-courte]
     ```
   - Si non : Rester sur la branche actuelle

4. **Initialisation minimale :**
   Créer mentalement ou dans un fichier temporaire :
   ```
   Fix: [Description courte]
   Fichiers impactés: [Liste]
   Temps estimé: [X minutes]
   ```

### QUICK_REFINE
1. **Analyse rapide (max 5 minutes) :**
   - Localiser précisément le code à modifier
   - Identifier les fichiers impactés
   - Vérifier s'il y a des effets de bord évidents
   
2. **Mini brainstorm (2 minutes) :**
   - Solution la plus simple ?
   - Risque de régression ?
   - Alternative si ça ne marche pas ?

3. STOP → "Correction identifiée : [description]. Procéder ? (y/n)"

### TEST (Optionnel)
1. STOP → "Écrire un test rapide pour cette correction ? (y/n/skip)"
   
2. Si oui (approche TDD légère) :
   ```bash
   # Créer un test minimal
   php artisan make:test QuickFix[Description]Test
   ```
   
   **Test minimal exemple :**
   ```php
   public function test_[description]_is_fixed()
   {
       // Arrange
       $input = [données problématiques];
       
       // Act & Assert
       $this->assertNotEquals([comportement bugué], [nouveau comportement]);
   }
   ```
   
3. Exécuter le test pour confirmer qu'il échoue :
   ```bash
   php artisan test --filter="QuickFix"
   ```

4. Si skip : Continuer sans test

### FIX
1. **Implémentation de la correction :**
   - Faire le changement minimal nécessaire
   - Pas de refactoring majeur
   - Pas d'optimisation prématurée
   - Focus sur LA correction

2. **Changements typiques :**
   - **Typo** : Correction directe du texte
   - **Config** : Ajustement de la valeur
   - **Bug simple** : Fix de la logique défaillante
   - **Hotfix** : Patch minimal du problème

3. **Vérification rapide :**
   ```bash
   # Si test écrit
   php artisan test --filter="QuickFix"
   
   # Sinon, test manuel rapide
   php artisan tinker
   # ou
   php artisan serve
   ```

4. STOP → "Correction appliquée et vérifiée ? (y/n)"

### COMMIT
1. **Préparer le commit :**
   ```bash
   git add -p  # Ajout interactif pour réviser les changements
   ```

2. **Message de commit (sans mention de Claude) :**
   ```bash
   git commit -m "[type]: [description courte]

   [Description plus détaillée si nécessaire]
   [Référence au ticket/issue si applicable]"
   ```
   
   **Types de commit :**
   - `fix:` pour les bugs
   - `hotfix:` pour les urgences production
   - `chore:` pour la config/maintenance
   - `typo:` pour les corrections de texte

3. **Exemples de messages :**
   ```bash
   # Bug fix
   git commit -m "fix: correct null pointer in user validation"
   
   # Hotfix
   git commit -m "hotfix: prevent division by zero in payment calculation"
   
   # Typo
   git commit -m "typo: fix spelling in error message"
   
   # Config
   git commit -m "chore: adjust Redis timeout to 30s"
   ```

4. **Finalisation :**
   - Si branche créée :
     ```bash
     git push -u origin hotfix/[description]
     ```
     - STOP → "Créer une PR express ? (y/n)"
     - Si oui : Utiliser GitHub CLI ou l'interface web
   
   - Si branche actuelle :
     ```bash
     git push
     ```

5. **Nettoyage (si branche temporaire) :**
   - STOP → "Merger et supprimer la branche ? (y/n)"
   - Si oui :
     ```bash
     git checkout main  # ou develop
     git merge hotfix/[description]
     git branch -d hotfix/[description]
     git push
     ```

6. STOP → "Quick fix terminé ! Temps total : [X] minutes"

## Cas d'usage types et exemples

### 1. **Hotfix Production**
```
Problème: L'API retourne 500 sur /api/users
Solution: Null check manquant
Temps: 10 minutes
Test: Non (urgence)
```

### 2. **Correction Typo**
```
Problème: "Successfull" au lieu de "Successful"
Solution: Rechercher/remplacer
Temps: 5 minutes
Test: Non nécessaire
```

### 3. **Ajustement Config**
```
Problème: Timeout trop court sur Redis
Solution: Passer de 10s à 30s dans config/database.php
Temps: 5 minutes
Test: Manuel seulement
```

### 4. **Petit Bug**
```
Problème: Validation email rejette les .co.uk
Solution: Ajuster la regex
Temps: 15 minutes
Test: Oui (test unitaire rapide)
```

## Checklist Quick Fix

- [ ] Problème clairement identifié
- [ ] Solution simple et directe
- [ ] Temps estimé < 30 minutes
- [ ] Impact minimal sur le reste du code
- [ ] Test manuel effectué au minimum
- [ ] Commit message clair (sans mention Claude)
- [ ] Push effectué

## Quand NE PAS utiliser ce workflow

- ❌ Changements architecturaux
- ❌ Nouvelles fonctionnalités
- ❌ Refactoring majeur
- ❌ Corrections nécessitant > 30 minutes
- ❌ Modifications de la base de données
- ❌ Changements d'API breaking

## Tips pour rester rapide

1. **Pas de perfectionnisme** : La correction doit marcher, pas être parfaite
2. **Pas de scope creep** : Résister à la tentation de "tant qu'à faire"
3. **Documentation minimale** : Le commit message suffit souvent
4. **Tests pragmatiques** : Seulement si ça ajoute de la valeur
5. **Décisions rapides** : En cas de doute, choisir la solution la plus simple

## Commandes utiles

```bash
# Recherche rapide dans le code
grep -r "terme" app/
rg "pattern" --type php

# Test rapide d'un endpoint
curl -X GET http://localhost:8000/api/endpoint

# Vérification syntaxe PHP
php -l app/Http/Controllers/MyController.php

# Clear cache si nécessaire
php artisan cache:clear
php artisan config:clear
```