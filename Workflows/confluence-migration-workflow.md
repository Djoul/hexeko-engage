# Documentation Migration to Confluence avec MCP Integration
Workflow structuré pour analyser, classifier et migrer des documents Markdown vers Confluence en respectant la structure de la base de connaissance.

## Workflow

**CRITICAL**
- Vous DEVEZ suivre les phases dans l'ordre : INIT → SELECT → ANALYZE → CLASSIFY → NOTION_CHECK → PREPARE → MIGRATE → VERIFY
- Vous DEVEZ obtenir la confirmation de l'utilisateur à chaque STOP
- Vous DEVEZ préserver la structure et le formatage lors de la migration
- Vous DEVEZ respecter la hiérarchie Confluence existante
- La classification doit être cohérente avec l'organisation UpEngage

### INIT
1. **Configuration Confluence :**
   - Vérifier la connexion MCP à Confluence
   - STOP → "Quel espace Confluence utiliser ? (clé de l'espace)"
   - Récupérer la structure de l'espace via MCP :
     ```bash
     # Liste des pages racines et leur hiérarchie
     mcp confluence list-pages --space [SPACE_KEY] --expand children
     ```

2. **Cartographie de la base UpEngage :**
   - Analyser la structure existante dans Confluence
   - Identifier les catégories principales :
     - Documentation Technique
     - Guides Utilisateur
     - Architecture
     - API & Intégrations
     - Processus & Workflows
     - Standards & Conventions
     - Formation & Onboarding
     - [Autres catégories spécifiques]

3. **Règles de classification :**
   - STOP → "Voulez-vous utiliser les règles de classification par défaut ou les personnaliser ? (default/custom)"
   - Si custom :
     - Présenter les règles actuelles
     - STOP → "Quelles règles ajouter/modifier ?"
   - Sauvegarder les règles dans `confluence-migration-rules.json`

### SELECT
1. STOP → "Comment sélectionner les documents à migrer ?
   1. Un fichier spécifique
   2. Plusieurs fichiers (sélection manuelle)
   3. Tous les fichiers d'un dossier
   4. Pattern de recherche (ex: *api*.md)
   Entrez votre choix (1-4) :"

2. Selon le choix :

   **Option 1 - Fichier unique :**
   - STOP → "Chemin du fichier MD :"
   - Vérifier l'existence et la lisibilité

   **Option 2 - Sélection multiple :**
   - Lister les fichiers MD disponibles :
     ```bash
     find . -name "*.md" -type f | grep -v node_modules | sort
     ```
   - STOP → "Sélectionnez les fichiers (numéros séparés par des virgules) :"

   **Option 3 - Dossier complet :**
   - STOP → "Chemin du dossier :"
   - Scanner récursivement tous les .md

   **Option 4 - Pattern :**
   - STOP → "Pattern de recherche :"
   - Exécuter la recherche avec le pattern

3. Créer le dossier de travail :
   ```bash
   mkdir -p todos/confluence-migration/$(date +%Y-%m-%d-%H-%M-%S)
   cd todos/confluence-migration/[timestamp]
   ```

4. Initialiser `migration-task.md` :
   ```markdown
   # Migration Confluence
   **Status:** Analyzing
   **Date:** [timestamp]
   **Space:** [SPACE_KEY]
   **Files Count:** [nombre]

   ## Files to Migrate
   [Liste des fichiers sélectionnés]

   ## Classification Rules
   [Règles appliquées]

   ## Migration Log
   [Sera rempli pendant la migration]
   ```

### ANALYZE
1. **Analyse parallèle des documents :**
   Pour chaque document, extraire :
   - Titre principal (# header)
   - Structure (headers hierarchy)
   - Type de contenu (guide, API, architecture, etc.)
   - Mots-clés significatifs
   - Liens internes/externes
   - Images et assets référencés
   - Diagrammes (Mermaid, PlantUML)
   - Tableaux et formatage spécial

2. **Détection des relations :**
   - Documents liés entre eux
   - Références croisées
   - Dépendances d'assets
   - Hiérarchie suggérée

3. **Génération du rapport d'analyse :**
   Créer `analysis-report.md` avec :
   ```markdown
   # Rapport d'Analyse

   ## Résumé
   - Documents analysés : [nombre]
   - Types détectés : [liste]
   - Assets référencés : [nombre]
   - Liens internes : [nombre]

   ## Détail par document
   ### [Nom du document]
   - Type détecté : [type]
   - Catégorie suggérée : [catégorie]
   - Parent suggéré : [page parent]
   - Complexité : [simple/moyenne/complexe]
   - Assets : [liste]
   ```

### CLASSIFY
1. **Classification automatique :**
   Appliquer les règles pour suggérer :
   - Catégorie Confluence cible
   - Page parent
   - Labels/tags
   - Ordre dans la hiérarchie

2. **Présentation de la classification :**
   ```
   Document: guide-api-authentication.md
   → Catégorie: API & Intégrations
   → Parent: REST API Documentation
   → Labels: api, authentication, security
   → Position: Après "API Overview"
   ```

3. **Validation et ajustements :**
   - STOP → "Valider la classification automatique ? (y/n/adjust)"
   - Si adjust :
     - Présenter document par document
     - STOP → "Catégorie pour [document] ? (garder '[suggestion]' ou nouvelle)"
     - Permettre de modifier parent, labels, position

4. **Gestion des conflits :**
   - Détecter les pages existantes avec même nom
   - STOP → "Page '[nom]' existe déjà. Action ? (replace/rename/skip/merge)"

5. Sauvegarder la classification dans `classification-map.json`

### NOTION_CHECK
1. **Recherche de contenu existant dans Notion :**
   - STOP → "Rechercher du contenu similaire dans Notion ? (y/n)"
   - Si oui, pour chaque catégorie de documents :

2. **Recherche par thème :**
   ```bash
   # Utiliser MCP pour rechercher dans Notion
   mcp notion search --query "[keywords from document]"
   ```
   - Présenter les résultats groupés par thème
   - Afficher titre, dernière modification, et extrait

3. **Analyse des doublons et relations :**
   Pour chaque groupe thématique :
   ```
   Thème: Authentication API
   Documents locaux:
   - api-auth-guide.md
   - authentication-flow.md
   - oauth-implementation.md

   Pages Notion trouvées:
   - "API Authentication Overview" (modifié: 2024-01-15)
   - "OAuth2 Implementation Guide" (modifié: 2024-02-20)
   ```

4. **Gestion des documents similaires :**
   - STOP → "Actions pour le thème '[thème]' ?
     1. Fusionner tous les documents en un seul
     2. Migrer séparément avec liens croisés
     3. Supprimer certains documents
     4. Garder tel quel
     Choix (peut combiner, ex: 1,3) :"

   - Si fusion (1) :
     - STOP → "Quel document utiliser comme base ? (numéro)"
     - STOP → "Ordre de fusion des autres documents ?"
     - Créer `merged/[theme]-merged.md`

   - Si suppression (3) :
     - STOP → "Quels documents supprimer ? (numéros)"
     - Marquer pour exclusion de la migration

5. **Ajout de liens Notion :**
   - STOP → "Ajouter des liens vers les pages Notion ? (y/n)"
   - Si oui, pour chaque document :
     - STOP → "Liens Notion pour '[document]' ? (URLs séparées par des virgules)"
     - Ajouter section "Related Notion Pages" au document

6. **Génération du rapport de consolidation :**
   Créer `consolidation-report.md` :
   ```markdown
   # Rapport de Consolidation

   ## Documents fusionnés
   - [Thème] : [liste] → [document final]

   ## Documents supprimés
   - [Document] : Raison: [doublon avec...]

   ## Liens Notion ajoutés
   - [Document] : [nombre] liens

   ## Recommandations
   [Suggestions d'organisation]
   ```

7. Mettre à jour `classification-map.json` avec les décisions

### PREPARE
1. **Préparation du contenu :**
   Pour chaque document :

   a. **Conversion du Markdown :**
   - Adapter la syntaxe MD pour Confluence
   - Convertir les liens relatifs en liens Confluence
   - Préparer les macros Confluence (code blocks, tables, etc.)

   b. **Traitement des assets :**
   - Identifier tous les assets (images, fichiers)
   - Vérifier leur existence
   - Préparer pour l'upload

   c. **Gestion des diagrammes :**
   - Convertir Mermaid en images ou macros Confluence
   - Adapter PlantUML si supporté
   - STOP → "Comment gérer les diagrammes ? (image/macro/both)"

2. **Création de la structure :**
   - Générer l'ordre de création des pages
   - Identifier les pages parent à créer
   - Préparer les métadonnées

3. **Plan de migration :**
   Créer `migration-plan.md` :
   ```markdown
   # Plan de Migration

   ## Ordre d'exécution
   1. Créer pages parent manquantes
   2. Migrer par niveau hiérarchique
   3. Upload des assets
   4. Mise à jour des liens

   ## Détail par étape
   [Liste ordonnée des actions]
   ```

4. STOP → "Plan de migration validé ? Procéder ? (y/n)"

### MIGRATE
1. **Exécution de la migration :**

   a. **Création des pages parent :**
   ```bash
   mcp confluence create-page \
     --space [SPACE_KEY] \
     --title "[Parent Title]" \
     --parent-id [PARENT_ID]
   ```

   b. **Migration document par document :**
   - Créer la page Confluence
   - Upload du contenu converti
   - Ajouter les labels
   - Upload des attachments
   - Logger chaque action

   c. **Mise à jour des liens :**
   - Remplacer les liens MD par liens Confluence
   - Mettre à jour les références d'images
   - Corriger les liens cross-documents

2. **Gestion des erreurs :**
   - Si erreur : logger et continuer
   - Maintenir une liste des échecs
   - STOP → "Erreur sur [document]. Réessayer ? (y/n/skip)"

3. **Progress tracking :**
   - Afficher une barre de progression
   - Logger dans `migration-log.txt`
   - Mettre à jour `migration-task.md`

### VERIFY
1. **Vérification automatique :**
   - Vérifier que toutes les pages sont créées
   - Contrôler les liens internes
   - Valider les attachments
   - Vérifier la hiérarchie

2. **Rapport de migration :**
   Générer `migration-report.md` :
   ```markdown
   # Rapport de Migration

   ## Résumé
   - Documents migrés : [X/Y]
   - Succès : [nombre]
   - Échecs : [nombre]
   - Durée : [temps]

   ## Pages créées
   [Liste avec liens Confluence]

   ## Erreurs rencontrées
   [Si applicable]

   ## Actions recommandées
   [Vérifications manuelles suggérées]
   ```

3. **Génération des liens :**
   - Créer `confluence-links.md` avec tous les liens directs
   - Format : `[Titre Original](URL Confluence)`

4. **Nettoyage optionnel :**
   - STOP → "Archiver les fichiers de travail ? (y/n)"
   - Si oui : créer archive zip et nettoyer

5. **Actions post-migration :**
   - STOP → "Envoyer notification de migration complétée ? (y/n)"
   - STOP → "Créer une page index dans Confluence ? (y/n)"
   - Si oui : générer page avec table des matières

6. **Finalisation :**
   - Mettre à jour `**Status:** Completed` dans migration-task.md
   - Archiver dans `confluence-migration/done/`

7. **Gestion des sources :**
   - STOP → "La migration est complète. Supprimer les fichiers sources ? (y/n/backup)"
   - Si 'y' :
     - Lister tous les fichiers sources migrés
     - STOP → "Confirmer la suppression de [nombre] fichiers ? (yes/no)"
     - Si yes : `rm [fichiers sources]`
   - Si 'backup' :
     - Créer archive : `tar -czf migration-backup-[timestamp].tar.gz [fichiers sources]`
     - STOP → "Archive créée. Supprimer les originaux ? (y/n)"
   - Logger les actions dans `deletion-log.txt`

8. STOP → "Migration terminée ! Voir le rapport ? (y/n)"

## Fonctionnalités avancées

### Templates de classification
- Possibilité de sauvegarder des templates
- Réutilisation pour migrations futures
- Export/import de règles

### Mode batch
- Migration planifiée (cron)
- Surveillance d'un dossier
- Auto-migration de nouveaux documents

### Rollback
- Sauvegarde des IDs de pages créées
- Possibilité de rollback complet
- Restauration de l'état précédent

### Synchronisation Notion-Confluence
- Maintien d'une table de correspondance
- Synchronisation bidirectionnelle possible
- Détection automatique des mises à jour
