# Collection Postman - Cognito Notifications V3

Collection complète pour tester les endpoints Cognito avec authentification HMAC, throttling granulaire et audit chiffré.

## 📦 Installation

### 1. Importer la collection dans Postman

1. Ouvrir Postman
2. Cliquer sur **Import** (en haut à gauche)
3. Sélectionner le fichier `Cognito_Notifications_V3.postman_collection.json`
4. Importer également l'environnement `Cognito_Local.postman_environment.json`

### 2. Configurer l'environnement

1. Sélectionner l'environnement **Cognito Local** dans le dropdown en haut à droite
2. Vérifier les variables d'environnement:
   - `base_url`: `http://localhost:1310` (ou votre URL)
   - `webhook_secret`: **IMPORTANT** - Doit correspondre à `COGNITO_WEBHOOK_SECRET` dans votre `.env`

**Pour récupérer votre webhook_secret:**

```bash
# Dans le projet
grep COGNITO_WEBHOOK_SECRET .env
```

Si absent, ajoutez-le dans `.env`:

```env
COGNITO_WEBHOOK_SECRET=test-webhook-secret-key-12345
```

## 🚀 Utilisation

### Authentification HMAC automatique

**Tous les endpoints sont protégés par HMAC.** La collection génère automatiquement la signature via un pre-request script qui:

1. Récupère le timestamp actuel
2. Génère la signature: `HMAC-SHA256(timestamp + body, webhook_secret)` avec implémentation JavaScript pure
3. Ajoute les headers `X-Cognito-Signature` et `X-Cognito-Timestamp`

**Vous n'avez rien à faire manuellement!** ✨

> **Note:** La collection utilise la bibliothèque `node-forge` (incluse dans Postman) pour générer les signatures HMAC-SHA256, garantissant une compatibilité parfaite avec le serveur.

### Structure de la collection

#### 📁 **Notifications**
- `Send SMS - Valid Request`: Envoie un SMS avec email
- `Send SMS - Phone Number`: Envoie un SMS avec numéro de téléphone
- `Send Email - Valid Request`: Envoie un email (reset password)
- `Send Email - Verification`: Envoie un email de vérification

#### 📁 **Webhook**
- `Post-Signup - Invited User to User`: Conversion USER
- `Post-Signup - Invited User to Admin`: Conversion ADMIN avec financer

#### 📁 **Error Cases**
- `Missing HMAC Signature`: Test 401 Unauthorized
- `Invalid HMAC Signature`: Test 403 Forbidden
- `Throttle Exceeded - SMS`: Test 429 Too Many Requests
- `Missing Required Field - Email`: Test 422 Validation Error

#### 📁 **Multi-Locale Tests**
- Tests pour toutes les locales: `fr-FR`, `en-GB`, `de-DE`, `es-ES`

## 🧪 Scénarios de test

### 1. Test de notification SMS basique

```
1. Sélectionner "Send SMS - Valid Request"
2. Cliquer sur "Send"
3. Vérifier: Status 200, message "SMS notification queued"
```

### 2. Test de throttling SMS (10/min)

```
1. Sélectionner "Throttle Exceeded - SMS"
2. Lancer la requête 11 fois rapidement avec "Runner"
3. La 11ème requête doit retourner 429 avec header "Retry-After"
```

**Pour lancer en boucle:**
- Ouvrir le **Runner** (Collection Runner)
- Sélectionner "Throttle Exceeded - SMS"
- Iterations: 11
- Delay: 0ms
- Run

### 3. Test de throttling Email (5/min)

```
1. Modifier "Send Email - Valid Request"
2. Utiliser le même email: "throttle-email@example.com"
3. Lancer 6 fois rapidement
4. La 6ème requête doit retourner 429
```

### 4. Test des buckets throttle séparés

```
1. Lancer "Throttle Exceeded - SMS" 11 fois (épuise bucket SMS)
2. Lancer "Send Email - Valid Request" immédiatement
3. L'email doit passer (bucket séparé) ✅
```

### 5. Test HMAC strict mode

```
1. Lancer "Invalid HMAC Signature" → 403 Forbidden
2. Lancer "Missing HMAC Signature" → 401 Unauthorized
3. Attendre 6 minutes, relancer une requête valide → 401 (timestamp expiré)
```

### 6. Test webhook post-signup

```
1. Créer un InvitedUser dans la BDD:
   INSERT INTO invited_users (email, ...) VALUES ('user@example.com', ...);

2. Lancer "Post-Signup - Invited User to User"
3. Vérifier: User créé avec rôle BENEFICIARY
4. InvitedUser supprimé
```

## 📊 Tests automatisés

Chaque requête inclut des tests automatiques qui vérifient:

```javascript
// Example: Send SMS - Valid Request
pm.test('Status code is 200', function () {
    pm.response.to.have.status(200);
});

pm.test('Response has success message', function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData.message).to.include('queued');
});

pm.test('Response has audit_log_id', function () {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('audit_log_id');
});
```

**Pour voir les résultats:**
1. Lancer une requête
2. Onglet "Test Results" en bas
3. Vérifier que tous les tests passent ✅

## 🔍 Monitoring & Debugging

### Vérifier les audit logs

```bash
# Via Docker
docker compose exec app_engage php artisan tinker

# Dans tinker:
\App\Models\CognitoAuditLog::latest()->limit(10)->get(['id', 'type', 'status', 'locale', 'created_at']);
```

### Vérifier les jobs en queue

```bash
# Lister les jobs en attente
docker compose exec app_engage php artisan queue:work --once --queue=default

# Logs Redis (si utilisé)
docker compose exec redis redis-cli
> KEYS cognito:throttle:*
> TTL cognito:throttle:sms:<hash>
```

### Dashboard monitoring

```bash
# Lancer le monitoring command
docker compose exec app_engage php artisan cognito:monitor-fallback --no-alert

# Output example:
# ┌──────┬───────┬──────┬────────┬──────────────┐
# │ Type │ Total │ Sent │ Failed │ Failure Rate │
# ├──────┼───────┼──────┼────────┼──────────────┤
# │ SMS  │   100 │   98 │      2 │ 2.00%        │
# │ Email│   100 │   99 │      1 │ 1.00%        │
# │ TOTAL│   200 │  197 │      3 │ 1.50%        │
# └──────┴───────┴──────┴────────┴──────────────┘
```

## 🌍 Locales supportées

La collection teste toutes les locales supportées:

| Locale | Langue         | Dossier               |
|--------|----------------|-----------------------|
| fr-FR  | Français       | French (France)       |
| en-GB  | Anglais        | English (UK)          |
| de-DE  | Allemand       | German (Germany)      |
| es-ES  | Espagnol       | Spanish (Spain)       |
| it-IT  | Italien        | Italian (Italy)       |
| nl-NL  | Néerlandais    | Dutch (Netherlands)   |
| pt-PT  | Portugais      | Portuguese (Portugal) |

**Variants régionaux préservés:**
- `fr-BE`, `fr-CA` → restent tels quels (pas de mapping vers `fr-FR`)
- `en-US`, `en-CA` → restent tels quels

## 🔐 Sécurité

### HMAC SHA256

```javascript
// Pre-request Script (automatique)
// Utilise node-forge (inclus dans Postman)

const forge = require('node-forge');
const webhookSecret = pm.environment.get('webhook_secret');
const payload = pm.request.body.raw;
const timestamp = Math.floor(Date.now() / 1000).toString();
const message = timestamp + payload;

const hmac = forge.hmac.create();
hmac.start('sha256', webhookSecret);
hmac.update(message);
const signature = hmac.digest().toHex();

pm.environment.set('current_timestamp', timestamp);
pm.environment.set('current_signature', signature);
```

> **Implémentation:** Le script utilise `node-forge`, une bibliothèque cryptographique fiable incluse dans Postman, garantissant une compatibilité parfaite avec le calcul HMAC-SHA256 du serveur Laravel.

### Contraintes temporelles

- Timestamp max: **5 minutes** dans le passé
- Timestamp ne peut pas être dans le futur
- Après 5 min: `401 Timestamp expired`

### Throttling

| Type  | Limite | Fenêtre | Bucket séparé |
|-------|--------|---------|---------------|
| SMS   | 10 req | 1 min   | ✅            |
| Email | 5 req  | 1 min   | ✅            |

**Header de réponse:**
```
Retry-After: 45  // secondes restantes
```

## 🐛 Troubleshooting

### Erreur: "webhook_secret environment variable is not set"

**Cause:** L'environnement Postman n'est pas configuré ou sélectionné.

**Solution:**
1. Vérifier que l'environnement "Cognito Local" est bien **sélectionné** (dropdown en haut à droite)
2. Vérifier que la variable `webhook_secret` existe dans l'environnement
3. Valeur doit correspondre exactement à `COGNITO_WEBHOOK_SECRET` dans `.env`

### Erreur: "Invalid signature"

1. Vérifier que `webhook_secret` correspond à `.env`
2. Vérifier que le pre-request script s'exécute
3. Vérifier Console Postman (View → Show Postman Console)

### Erreur: "Timestamp expired"

1. Vérifier l'heure système (timezone)
2. Ne pas réutiliser une ancienne requête (> 5 min)
3. Toujours générer un nouveau timestamp

### Throttle ne fonctionne pas

1. Vérifier que Redis fonctionne: `docker compose ps redis`
2. Flush cache: `docker compose exec app_engage php artisan cache:clear`
3. Utiliser le **même identifiant** (email/phone) pour déclencher le throttle

### Job ne se traite pas

1. Vérifier queue worker: `docker compose exec app_engage php artisan queue:work`
2. Vérifier logs: `docker compose logs -f app_engage`
3. Vérifier failed_jobs: `docker compose exec app_engage php artisan queue:failed`

## 📚 Ressources

- **Documentation**: `/docs/cognito-notifications-v3.md`
- **Plan de migration**: `/planned_task_cognito_v3.md`
- **Tests PHPUnit**: `/tests/Feature/Http/Controllers/V1/CognitoNotificationControllerTest.php`
- **Artisan command**: `php artisan cognito:monitor-fallback --help`

## 🎯 Checklist de validation

Avant de valider l'implémentation, exécuter tous les scénarios:

- [ ] ✅ SMS notification (email)
- [ ] ✅ SMS notification (phone number)
- [ ] ✅ Email notification (reset password)
- [ ] ✅ Email notification (verification)
- [ ] ✅ Webhook post-signup (USER)
- [ ] ✅ Webhook post-signup (ADMIN)
- [ ] ✅ Throttle SMS (11 requêtes)
- [ ] ✅ Throttle Email (6 requêtes)
- [ ] ✅ Buckets séparés SMS/Email
- [ ] ✅ HMAC signature invalide → 403
- [ ] ✅ HMAC signature manquante → 401
- [ ] ✅ Timestamp expiré (> 5 min) → 401
- [ ] ✅ Validation champs requis → 422
- [ ] ✅ Multi-locale (fr-FR, en-GB, de-DE, es-ES)
- [ ] ✅ Audit log créé et chiffré
- [ ] ✅ Jobs traités en queue

**All green?** 🎉 L'implémentation est validée!

## 🚀 Environnements

### Local
```
base_url: http://localhost:1310
webhook_secret: test-webhook-secret-key-12345
```

### Staging
```
base_url: https://staging.example.com
webhook_secret: <from staging .env>
```

### Production
```
base_url: https://api.example.com
webhook_secret: <from production .env>
```

**⚠️ IMPORTANT:** Ne JAMAIS committer le `webhook_secret` de production!

## 📞 Support

Pour toute question ou problème:
1. Vérifier les logs: `docker compose logs -f app_engage`
2. Exécuter les tests: `php artisan test --group=cognito`
3. Monitoring: `php artisan cognito:monitor-fallback`

---

**Version**: 3.0.0
**Dernière mise à jour**: 2025-01-20
**Statut**: ✅ Production Ready
