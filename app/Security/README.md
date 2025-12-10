## Contexte & Concepts

- **AuthorizationContext** centralise l'état d'autorisation pour chaque requête (mode, financers/divisions accessibles, rôles de l'acteur).
- **Modes** : `self` (scope = financers de l'utilisateur), `global` (GOD/Hexeko voient tout), `take_control` (scope réduit explicitement via `financer_id`).
- **Hydratation** : effectuée dans `CognitoAuthMiddleware` juste après l'authentification pour garantir un contexte cohérent et disponible via le helper `authorizationContext()`.

## Migration depuis activeFinancerID() (DEPRECATED)

**⚠️ `activeFinancerID()` est déprécié** - Utiliser à la place :

```php
// ❌ Ancien (déprécié)
$financerId = activeFinancerID();

// ✅ Nouveau - Pour un financer unique
$financerId = authorizationContext()->currentFinancerId();

// ✅ Nouveau - Pour tous les financers accessibles
$financerIds = authorizationContext()->financerIds();

// ✅ Nouveau - Vérifier l'accès à un financer
if (authorizationContext()->canAccessFinancer($financerId)) { ... }

// ✅ Nouveau - Vérifier l'accès à une division
if (authorizationContext()->canAccessDivision($divisionId)) { ... }
```

**Raison** : Toute la logique d'autorisation est maintenant centralisée dans `AuthorizationContext`.

## Ce qui vient d'être modifié

1. **Validation stricte des filtres**
   - Seuls GOD/Hexeko peuvent activer `take_control` globalement.
   - Division admins/super admins peuvent "take control" uniquement sur les financers de leurs divisions.
   - Les autres utilisateurs ne peuvent filtrer que sur leurs propres financers actifs.

2. **Règles de visibilité unifiées**
   - `getAccessibleFinancersFor` permet aux modes global/take_control de voir l'intégralité du scope, tout en conservant le filtrage partagé pour les autres.

3. **Middleware sécurisé**
   - `CognitoAuthMiddleware` renvoie maintenant un 403 dès qu'un `financer_id` invalide est demandé, avec journalisation explicite.

4. **Migration Context → AuthorizationContext (9 fichiers)**
   - `IsAdminFilter.php`, `Invoice.php`, `UserSoftDeleteController.php`, `ToggleUserActivationController.php`
   - `ArticleTranslationController.php`, `FinancerPolicy.php`, `GenerateArticleRequest.php`
   - `InvoicePolicy.php`, `MeResource.php`
   - Tous utilisent maintenant `authorizationContext()` au lieu de `Context::get('accessible_*')`

5. **Helper `authorizationContext()` créé**
   - Accès global au singleton via `app(AuthorizationContext::class)`
   - `activeFinancerID()` simplifié et marqué `#[Deprecated]`

## À faire / pistes restantes

- **Propagation** : remplacer les derniers `Context::get('accessible_*')` résiduels par `authorizationContext()` dans tout le code (policies, filtres personnalisés, tests).  
- **Prise de contrôle** : ajouter un endpoint explicite (“activer take control”) afin d’éviter d’exposer la fonctionnalité via un simple paramètre query.  
- **Tests d’intégration** : couvrir un flow HTTP complet (middleware → contrôleur) incluant un division admin qui filtre sur un financer légitime puis illégitime.  
- **Observabilité** : brancher des métriques / alertes sur les 403 liés à l’autorisation pour détecter les abus ou les mauvaises configurations.
