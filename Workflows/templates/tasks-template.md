# Plan d'Exécution — Tasks Checklist

Meta:
- Date: {{DATE}}
- Contexte: {{KEY}} / {{TITLE}}
- Profil: {{PROFILE}}
- Phase courante: TASKS

## Instructions
- Cochez chaque tâche lorsqu'elle est terminée: [x]
- Reprenez une session à la première case non cochée
- Ajoutez des sous-tâches si nécessaire sous la tâche parente

---

## Tracking Global
- [ ] Initialiser le plan de tâches
- [ ] Valider l'ordre et les dépendances
- [ ] Synchroniser avec Jira (si applicable)
- [ ] Mettre à jour le statut après chaque étape

## Phases & Tâches

### Analysis
- [ ] Revoir les exigences
- [ ] Analyser le code existant
- [ ] Identifier dépendances et risques
- [ ] Documenter l'approche

### TDD
- [ ] Écrire tests unitaires (services)
- [ ] Écrire tests d'intégration (API)
- [ ] Définir jeux de données et mocks

### Implementation
- [ ] Créer services
- [ ] Créer actions
- [ ] Adapter contrôleurs (minimaux)
- [ ] Implémenter cache (TTL/invalidations)

### Validation
- [ ] make test (100% pass)
- [ ] make quality-check (0 erreurs)
- [ ] Vérifier performance (< 500ms)

### Documentation
- [ ] OpenAPI/Swagger à jour
- [ ] Guide frontend mis à jour
- [ ] Changelog

---

Notes:
- Liens utiles: {{LINKS}}
- Décisions: {{DECISIONS}}
