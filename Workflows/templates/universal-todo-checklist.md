# 📋 Universal Development Checklist

**Feature**: [FEATURE-NAME]
**Type**: [Epic/Story/Bug/Hotfix/Audit]
**Jira**: [KEY]
**Started**: [DATE]
**Developer**: [NAME via Claude Code]
**Mode**: [Development/Audit] <!-- Development = nouvelle feature, Audit = évaluation rétrospective -->

---

## ✅ PHASE 0: INITIALIZATION

### Setup
- [ ] Feature initialisée avec script : `./workflows/scripts/init-feature.sh [KEY]`
- [ ] Type détecté : [Epic/Story/Bug/Task]
- [ ] Profil sélectionné : [hotfix/bugfix/story-simple/story-standard/story-complex/epic]
- [ ] Workspace créé : `/todos/[type]/[KEY]/`
- [ ] TodoWrite tracking activé

### Source & Context
- [ ] Issue récupérée depuis : [Jira/Todoist/Sentry/Local]
- [ ] Critères d'acceptation extraits
- [ ] Epic parent identifié (si applicable)
- [ ] Dépendances identifiées
- [ ] Priorité définie : [Critical/High/Medium/Low]
- [ ] Estimation complexité : [Simple/Standard/Complex]

### MCP Integration
- [ ] Jira status mis à jour : "In Progress"
- [ ] Sous-tâches techniques créées (si nécessaire)
- [ ] Documentation links ajoutés

---

## 📊 PHASE 1: DISCOVERY [OPTIONAL - Skip si specs claires]

### Requirements Gathering
- [ ] Stakeholders identifiés
- [ ] Business objectives clarifiés
- [ ] User stories validées
- [ ] Acceptance criteria confirmés
- [ ] Edge cases identifiés
- [ ] Non-functional requirements listés

### Research
- [ ] Documentation existante recherchée (Confluence)
- [ ] Solutions similaires analysées (Firecrawl)
- [ ] Best practices recherchées (Context7)
- [ ] Exemples de code trouvés

### Planning
- [ ] Scope défini et validé
- [ ] Milestones établis
- [ ] Risques documentés
- [ ] Timeline estimée

---

## 🔍 PHASE 2: ANALYSIS [RECOMMENDED]

### Code Analysis
- [ ] Codebase existant analysé
- [ ] Modules impactés identifiés : `[Liste des fichiers]`
- [ ] Patterns existants identifiés
- [ ] Technical debt évalué

### Technical Research
- [ ] Documentation Laravel consultée (Context7)
- [ ] Libraries nécessaires identifiées
- [ ] Versions compatibilité vérifiée
- [ ] External APIs documentation (Firecrawl)

### Risk Assessment
- [ ] Risques techniques identifiés
- [ ] Risques business identifiés
- [ ] Mitigation strategies définies
- [ ] Rollback plan préparé

### Approach Decision
- [ ] Minimum 3 approches analysées
- [ ] Pros/Cons documentés
- [ ] Approche finale sélectionnée
- [ ] Architecture decision record créé

### Deliverables
- [ ] `analysis.md` créé
- [ ] `risks-assessment.md` créé
- [ ] `technical-approach.md` créé

---

## 🏗️ PHASE 3: DESIGN [OPTIONAL - Skip si CRUD simple]

### Architecture Design
- [ ] Component architecture définie
- [ ] Service layer design
- [ ] Action layer design
- [ ] Data flow diagramme créé

### API Design
- [ ] Endpoints RESTful définis
- [ ] Request/Response formats spécifiés
- [ ] Error responses standardisés
- [ ] Rate limiting défini
- [ ] Authentication/Authorization planifié

### Data Design
- [ ] DTOs structure définie
- [ ] Validation rules spécifiées
- [ ] Database schema changes identifiés
- [ ] Migrations planifiées
- [ ] Indexes optimisation prévue

### Integration Design
- [ ] External services identified
- [ ] Integration points mapped
- [ ] Webhook handlers planned
- [ ] Event/Listener architecture

### Cache Strategy
- [ ] Cache keys structure définie
- [ ] TTL values déterminés
- [ ] Cache invalidation strategy
- [ ] Redis tags planifiés

### Deliverables
- [ ] `architecture.md` créé
- [ ] `api-specification.md` créé
- [ ] `database-changes.md` créé

---

## 🧪 PHASE 4: TDD [STRONGLY RECOMMENDED]

### Test Planning
- [ ] Test strategy définie
- [ ] Coverage target fixé : [80%+]
- [ ] Test data strategy planifiée
- [ ] Mocking strategy définie

### Unit Tests Written
- [ ] Service tests écrits : `tests/Unit/Services/[Feature]ServiceTest.php`
- [ ] Action tests écrits : `tests/Unit/Actions/[Feature]ActionTest.php`
- [ ] DTO tests écrits : `tests/Unit/DTOs/[Feature]DTOTest.php`
- [ ] Helper/Util tests écrits
- [ ] Event/Listener tests écrits

### Integration Tests Written
- [ ] API endpoint tests : `tests/Feature/[Module]/[Feature]ApiTest.php`
- [ ] Database transaction tests
- [ ] Cache integration tests
- [ ] External service integration tests (mocked)

### E2E Tests Written (si applicable)
- [ ] Complete user journey tests
- [ ] Cross-module integration tests
- [ ] Performance tests
- [ ] Load tests planned

### Test Infrastructure
- [ ] Factories créées/updatées
- [ ] Fixtures préparées
- [ ] Mocks configured (Http::fake(), etc.)
- [ ] Test helpers créés

### Deliverables
- [ ] `tdd-plan.md` créé
- [ ] All tests written (RED phase)
- [ ] Test coverage report généré

---

## 💻 PHASE 5: IMPLEMENTATION [REQUIRED]

### Infrastructure
- [ ] Directories structure créée
- [ ] Base classes/interfaces créées
- [ ] Configuration files updated

### Database Layer
- [ ] Migrations créées : `database/migrations/[timestamp]_[name].php`
- [ ] Models créés/updated : `app/Models/[Model].php`
- [ ] Factories updated : `database/factories/[Model]Factory.php`
- [ ] Seeders créés : `database/seeders/[Feature]Seeder.php`

### Service Layer
- [ ] Services implémentés : `app/Services/[Feature]Service.php`
- [ ] Repository pattern (si utilisé)
- [ ] Business logic implemented
- [ ] Error handling ajouté
- [ ] Logging ajouté

### Action Layer
- [ ] Actions créées : `app/Actions/[Feature]/[Action].php`
- [ ] Transaction management
- [ ] Event dispatching
- [ ] Complex orchestration

### API Layer
- [ ] Controllers créés : `app/Http/Controllers/[Feature]Controller.php`
- [ ] Form Requests créés : `app/Http/Requests/[Feature]Request.php`
- [ ] API Resources créés : `app/Http/Resources/[Feature]Resource.php`
- [ ] Routes registered : `routes/api.php`
- [ ] Middleware applied

### Data Transfer
- [ ] DTOs créés : `app/DTOs/[Feature]DTO.php`
- [ ] DTO validation
- [ ] Type safety enforced

### Cache Implementation
- [ ] Cache service integrated
- [ ] Redis tags implemented
- [ ] Cache warming (si nécessaire)
- [ ] Cache invalidation on write

### Events & Queues
- [ ] Events créés : `app/Events/[Feature]Event.php`
- [ ] Listeners créés : `app/Listeners/[Feature]Listener.php`
- [ ] Jobs créés : `app/Jobs/[Feature]Job.php`
- [ ] Queue configuration

### External Integrations
- [ ] Third-party API clients
- [ ] Webhook handlers
- [ ] External service adapters

### Security
- [ ] Input validation complète
- [ ] Authorization checks
- [ ] SQL injection prevention
- [ ] XSS protection
- [ ] Rate limiting implemented

### Deliverables
- [ ] All code implemented
- [ ] Tests passing (GREEN phase)
- [ ] Code refactored (REFACTOR phase)

---

## ✔️ PHASE 6: VALIDATION [REQUIRED]

### Automated Tests
- [ ] `make test` → 100% PASS
- [ ] `make test-unit` → 100% PASS
- [ ] `make test-feature` → 100% PASS
- [ ] `make coverage` → Coverage > 80%

### Code Quality
- [ ] `make quality-check` → 0 ERRORS
- [ ] `make pint` → Code styled
- [ ] `make rector` → Code modernized
- [ ] `make phpstan` → Level Max, 0 errors

### Performance
- [ ] Response time < 500ms
- [ ] Database queries optimized
- [ ] N+1 queries eliminated
- [ ] Cache hit ratio > 90%
- [ ] Memory usage acceptable

### Security Validation
- [ ] Authentication tested
- [ ] Authorization tested
- [ ] Input validation tested
- [ ] SQL injection tested
- [ ] XSS prevention tested

### Integration Testing
- [ ] External APIs mocked and tested
- [ ] Database transactions verified
- [ ] Cache invalidation verified
- [ ] Event dispatching verified

### Manual Testing
- [ ] Happy path tested
- [ ] Error scenarios tested
- [ ] Edge cases tested
- [ ] Concurrent access tested

### Deliverables
- [ ] All quality gates passed
- [ ] Performance benchmarks met
- [ ] Security audit passed

---

## 📚 PHASE 7: DOCUMENTATION [RECOMMENDED]

### API Documentation
- [ ] OpenAPI/Swagger specs generated
- [ ] API endpoints documented
- [ ] Request/Response examples added
- [ ] Error codes documented
- [ ] Rate limits documented

### Frontend Documentation
- [ ] Frontend integration guide created
- [ ] API usage examples
- [ ] Authentication flow documented
- [ ] WebSocket events documented (si applicable)

### Technical Documentation
- [ ] Architecture documented
- [ ] Database schema documented
- [ ] Cache strategy documented
- [ ] Event flow documented

### Confluence Publication
- [ ] Technical spec page created/updated
- [ ] API guide published
- [ ] Architecture diagrams added
- [ ] Runbook created (si applicable)

### Code Documentation
- [ ] PHPDoc comments added (complex methods only)
- [ ] README.md updated
- [ ] CHANGELOG.md updated
- [ ] Migration guide created (si breaking changes)

### External Tools
- [ ] API specs exported pour Postman

### Deliverables
- [ ] Complete API documentation
- [ ] Frontend integration guide
- [ ] Confluence pages published

---

## 📮 PHASE 8: POSTMAN TESTING [REQUIRED for APIs]

### Collection Setup
- [ ] Collection créée/mise à jour dans Postman
- [ ] Structure organisée par modules
- [ ] Variables d'environnement configurées
- [ ] Authentication flow configured
- [ ] Pre-request scripts ajoutés

### Request Documentation
- [ ] Chaque endpoint documenté
- [ ] Description et purpose ajoutés
- [ ] Paramètres documentés
- [ ] Headers configurés
- [ ] Body examples ajoutés

### Response Examples
- [ ] Success responses saved
- [ ] Error responses saved (400, 401, 403, 404, 422, 500)
- [ ] Edge cases documented
- [ ] Response schemas validated

### Automated Tests
- [ ] Tests de base pour chaque endpoint
  - [ ] Status code validation
  - [ ] Response time check (<500ms)
  - [ ] Content-Type validation
  - [ ] Response structure validation
- [ ] Tests métier spécifiques
  - [ ] Business logic validation
  - [ ] Data integrity checks
  - [ ] Calculation verification
- [ ] Tests de sécurité
  - [ ] No sensitive data exposed
  - [ ] Proper error messages
  - [ ] Security headers present

### Test Scenarios
- [ ] Happy path scenarios created
- [ ] Error scenarios created
- [ ] Edge cases scenarios created
- [ ] Performance test scenarios
- [ ] Security test scenarios

### Data-Driven Testing
- [ ] Test data CSV prepared (si applicable)
- [ ] Multiple iterations configured
- [ ] Variables properly used

### End-to-End Flows
- [ ] Complete user journeys created
- [ ] Multi-step workflows tested
- [ ] State management between requests
- [ ] Cleanup procedures included

### Newman Integration
- [ ] Collection exportée en JSON
- [ ] Environment files exported
- [ ] Newman configuration created
- [ ] CI/CD integration configured
- [ ] Reports configuration

### Newman Execution
- [ ] Local tests passed : `newman run collection.json`
- [ ] HTML report generated
- [ ] All assertions passing
- [ ] Performance acceptable

### Environments
- [ ] Local environment configured
- [ ] Staging environment configured
- [ ] Production environment configured (monitoring only)
- [ ] Variables properly scoped

### Monitoring Setup
- [ ] Health check monitors created
- [ ] Critical path monitors
- [ ] Alert notifications configured
- [ ] Schedule defined (every 15/30/60 min)

### Documentation in Postman
- [ ] Collection description complete
- [ ] Folder descriptions added
- [ ] Request descriptions detailed
- [ ] Examples well documented
- [ ] Variables documented

### Sharing & Collaboration
- [ ] Collection shared with team
- [ ] Public documentation generated (si applicable)
- [ ] Collection versioned in Git
- [ ] Frontend team access granted

### Deliverables
- [ ] Postman collection file : `/postman/[module].postman_collection.json`
- [ ] Environment files : `/postman/environments/*.json`
- [ ] Newman config : `/postman/newman-config.json`
- [ ] Test reports : `/reports/postman-*.html`
- [ ] CI integration : `.github/workflows/postman-tests.yml`

---

## 🚀 PHASE 9: DEPLOYMENT PREPARATION

### Code Review
- [ ] Self-review completed
- [ ] PR created with description
- [ ] PR linked to Jira ticket
- [ ] Code review requested
- [ ] Review feedback addressed
- [ ] PR approved by 2+ reviewers

### Pre-deployment
- [ ] Branch rebased on main/develop
- [ ] Conflicts resolved
- [ ] Final test run passed
- [ ] Database migrations reviewed
- [ ] Rollback plan documented

### Staging Deployment
- [ ] Deployed to staging environment
- [ ] Staging tests passed
- [ ] Performance validated
- [ ] Integration tests passed
- [ ] UAT completed (si applicable)

### Production Preparation
- [ ] Deployment plan created
- [ ] Feature flags configured (si applicable)
- [ ] Monitoring alerts configured
- [ ] Rollback procedure tested
- [ ] Communication plan ready

---

## 📈 PHASE 10: POST-DEPLOYMENT

### Deployment Verification
- [ ] Production deployment successful
- [ ] Smoke tests passed
- [ ] Monitoring dashboards checked
- [ ] Error rates normal
- [ ] Performance metrics normal

### Jira & Documentation
- [ ] Jira ticket moved to "Done"
- [ ] Release notes updated
- [ ] Customer documentation updated
- [ ] Internal wiki updated

### Monitoring & Metrics
- [ ] Sentry errors monitored (first 24h)
- [ ] Performance metrics collected
- [ ] Usage analytics reviewed
- [ ] User feedback collected

### Retrospective
- [ ] Lessons learned documented
- [ ] Process improvements identified
- [ ] Technical debt logged
- [ ] Success metrics reported

---

## 🔍 AUDIT MODE : ÉVALUATION RÉTROSPECTIVE [MODE AUDIT UNIQUEMENT]

### Scope Discovery
- [ ] Feature/module identifié : [Nom exact]
- [ ] Point d'entrée audit : [Jira/Fichier MD/Namespace/Commit]
- [ ] Tous les fichiers créés/modifiés listés
- [ ] Dépendances et intégrations identifiées
- [ ] Critères d'acceptation récupérés (si Jira)

### Architecture Evaluation (/10)
- [ ] Pattern Service/Action respecté
- [ ] DTOs utilisés (pas d'arrays)
- [ ] FormRequests pour validation
- [ ] Separation of concerns ok
- [ ] Dependency Injection appropriée
- [ ] Event/Listener pattern si applicable
- [ ] Cache strategy implémentée
- [ ] Error handling gracieux
- [ ] Logs structurés présents
- [ ] Security best practices
**Score Architecture**: [X]/10

### Code Quality Evaluation (/10)
- [ ] `make test` → 100% pass
- [ ] `make quality-check` → 0 erreurs
- [ ] `make phpstan` → Level max, 0 erreurs
- [ ] Complexité cyclomatique acceptable
- [ ] Nommage cohérent et expressif
- [ ] Méthodes < 20 lignes moyenne
- [ ] Duplication minimale
- [ ] Type hints présents partout
- [ ] Respect PSR-12 strict
- [ ] Comments appropriés (minimal)
**Score Qualité**: [X]/10

### Tests Evaluation (/10)
- [ ] Tests unitaires Services présents
- [ ] Tests Actions avec mocks appropriés
- [ ] Tests Feature pour API endpoints
- [ ] Tests integration si externe
- [ ] Coverage > 80% sur code métier
- [ ] Edge cases couverts
- [ ] Error scenarios testés
- [ ] DatabaseTransactions utilisées
- [ ] Factories appropriées
- [ ] Performance tests < 500ms
- [ ] Assertions meaningful et complètes
**Score Tests**: [X]/10

### Documentation Evaluation (/10)
- [ ] OpenAPI/Swagger specs générés et à jour
- [ ] Tous endpoints API documentés
- [ ] Request/Response examples complets
- [ ] Error codes documentés
- [ ] Guide intégration frontend créé
- [ ] Architecture documentée si complexe
- [ ] README.md mis à jour
- [ ] CHANGELOG.md mis à jour
- [ ] Confluence publié si applicable
- [ ] PHPDoc sur méthodes publiques complexes
**Score Documentation**: [X]/10

### Performance Evaluation (/10)
- [ ] Response times < 500ms validés
- [ ] Aucune N+1 query détectée
- [ ] Database indexes appropriés
- [ ] Cache Redis implémenté avec TTL
- [ ] Cache invalidation sur write operations
- [ ] Eager loading utilisé correctement
- [ ] Pagination sur listings
- [ ] Memory usage acceptable
- [ ] Profiling fait avec outils appropriés
**Score Performance**: [X]/10

### Production Readiness (/10)
- [ ] Monitoring/metrics en place
- [ ] Error tracking Sentry configuré
- [ ] Logs structurés (pas trop verbeux)
- [ ] Security validé (auth, injection, XSS)
- [ ] Rate limiting si applicable
- [ ] Rollback plan documenté
- [ ] Health checks configurés
- [ ] Feature flags si nécessaire
**Score Production**: [X]/10

### Postman Evaluation (si API) (/10)
- [ ] Collection Postman créée/mise à jour
- [ ] Tous endpoints présents et documentés
- [ ] Tests automatiques pour chaque endpoint
- [ ] Scénarios E2E complets
- [ ] Newman tests passants
- [ ] Monitors configurés
- [ ] Environments (local, staging, prod)
- [ ] Variables et auth flow appropriés
**Score Postman**: [X]/10

### AUDIT SCORING FINAL
| Catégorie | Score | Poids | Score Pondéré |
|-----------|-------|-------|---------------|
| Architecture | [X]/10 | 25% | [X.X] |
| Qualité Code | [X]/10 | 20% | [X.X] |
| Tests | [X]/10 | 25% | [X.X] |
| Documentation | [X]/10 | 15% | [X.X] |
| Performance | [X]/10 | 10% | [X.X] |
| Production | [X]/10 | 5% | [X.X] |
| **TOTAL** | | **100%** | **[X.X]/10** |

### Conformité Status
- 🟢 **EXCELLENT** (9-10/10) : Tous critères respectés, code exemplaire
- 🟡 **SATISFAISANT** (7-8/10) : Critères principaux ok, améliorations mineures
- 🟠 **AMÉLIORATION REQUISE** (5-6/10) : Gaps identifiés, refactoring nécessaire
- 🔴 **NON-CONFORME** (0-4/10) : Critères majeurs non respectés

### Gaps Identifiés (Priorités)
- 🔴 **CRITIQUE** : [Liste des problèmes bloquants]
- 🟠 **IMPORTANT** : [Liste des améliorations importantes]
- 🟡 **MINEUR** : [Liste des optimisations souhaitables]

### Recommendations
- [ ] **Actions correctives immédiates** : [Liste prioritaire]
- [ ] **Plan d'amélioration** : [Étapes pour atteindre 9-10/10]
- [ ] **Technical debt** : [Dette technique identifiée]
- [ ] **Best practices** : [Recommandations futures]

### Audit Deliverables
- [ ] `audit-report.md` créé avec scoring détaillé
- [ ] `gap-analysis.md` avec priorisation
- [ ] `improvement-plan.md` avec actions
- [ ] Jira ticket audit créé (si applicable)
- [ ] Présentation équipe (si gaps critiques)

---

## 🎯 FINAL CHECKLIST

### Must-Have (Blocking)
- [ ] All tests passing
- [ ] Code quality checks passed
- [ ] Security validated
- [ ] API documented
- [ ] Jira updated

### Should-Have (Important)
- [ ] Coverage > 80%
- [ ] Performance < 500ms
- [ ] Frontend guide created
- [ ] Confluence updated
- [ ] Monitoring configured

### Nice-to-Have (Optional)
- [ ] Postman collection
- [ ] Video demo
- [ ] Blog post
- [ ] Team presentation

---

## 📝 NOTES & DECISIONS

### Architecture Decisions
-

### Technical Debt Created
-

### Follow-up Tasks
-

### Lessons Learned
-

---

## 🔗 LINKS

- **Jira**: [URL]
- **PR**: [URL]
- **Confluence**: [URL]
- **API Docs**: [URL]
- **Monitoring**: [URL]

---

**Status**: [In Progress/Completed/Blocked]
**Completion**: [XX]%
**Last Updated**: [DATE]

---

*This checklist ensures nothing is forgotten. Skip sections not applicable to your feature type.*