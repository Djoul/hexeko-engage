# Troubleshooting Guide

Guide de résolution des problèmes courants pour l'API UpEngage.

## 🚨 Problèmes Fréquents

### 1. L'application ne démarre pas

#### Symptômes
- Page blanche sur http://localhost:1310
- Erreur 502 Bad Gateway
- Connection refused

#### Solutions

```bash
# 1. Vérifier que Docker est lancé
docker ps

# 2. Vérifier les containers
docker-compose ps

# 3. Redémarrer tous les services
make docker-restart

# 4. Vérifier les logs
docker-compose logs -f app_engage
docker-compose logs -f webserver_engage

# 5. Reconstruire si nécessaire
docker-compose up -d --build
```

### 2. Erreur de connexion à la base de données

#### Symptômes
- `SQLSTATE[08006] could not connect to server`
- `Connection refused`
- `No such host is known`

#### Solutions

```bash
# 1. Vérifier que PostgreSQL est lancé
docker-compose ps db_engage

# 2. Vérifier les variables d'environnement
grep DB_ .env

# Configuration correcte :
DB_CONNECTION=pgsql
DB_HOST=db_engage
DB_PORT=5432
DB_DATABASE=db_engage
DB_USERNAME=root
DB_PASSWORD=password

# 3. Tester la connexion
docker-compose exec db_engage psql -U root -d db_engage -c "SELECT 1"

# 4. Recréer la base si nécessaire
docker-compose exec app_engage php artisan migrate:fresh --seed
```

### 3. Erreur de cache Redis

#### Symptômes
- `Connection refused [tcp://redis-cluster:6379]`
- `No connection could be made`
- Cache not working

#### Solutions

```bash
# 1. Vérifier Redis
docker-compose ps redis-cluster

# 2. Tester la connexion
docker-compose exec redis-cluster redis-cli ping
# Doit retourner: PONG

# 3. Vider le cache
docker-compose exec app_engage php artisan cache:clear

# 4. Vérifier la configuration
grep -E "REDIS_|CACHE_" .env

# Configuration correcte :
CACHE_DRIVER=redis
REDIS_HOST=redis-cluster
REDIS_PORT=6379
```

### 4. Problèmes de permissions

#### Symptômes
- `Permission denied` sur les fichiers
- Cannot write to `storage/logs`
- Failed to open stream

#### Solutions

```bash
# 1. Fixer les permissions storage
docker-compose exec app_engage chmod -R 775 storage bootstrap/cache
docker-compose exec app_engage chown -R www-data:www-data storage bootstrap/cache

# 2. Clear caches
docker-compose exec app_engage php artisan cache:clear
docker-compose exec app_engage php artisan config:clear
docker-compose exec app_engage php artisan view:clear

# 3. Si problème persiste, depuis l'hôte
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R $(whoami):$(whoami) .
```

### 5. Port déjà utilisé

#### Symptômes
- `bind: address already in use`
- Cannot start container

#### Solutions

```bash
# 1. Identifier le processus
lsof -i :1310  # Nginx
lsof -i :5433  # PostgreSQL
lsof -i :6379  # Redis
lsof -i :8080  # Reverb

# 2. Tuer le processus
kill -9 <PID>

# 3. Ou changer le port dans docker-compose.yml
ports:
  - "1311:80"  # Utiliser 1311 au lieu de 1310
```

## 🔍 Debugging

### Activer le mode debug

```bash
# Dans .env
APP_DEBUG=true
APP_ENV=local
LOG_LEVEL=debug

# Redémarrer l'application
docker-compose restart app_engage
```

### Consulter les logs

```bash
# Logs Laravel
docker-compose exec app_engage tail -f storage/logs/laravel.log

# Logs Docker
docker-compose logs -f --tail=100 app_engage

# Logs Nginx
docker-compose logs -f webserver_engage

# Logs PostgreSQL
docker-compose logs -f db_engage

# Tous les logs
docker-compose logs -f
```

### Laravel Telescope (si installé)

```bash
# Activer Telescope
TELESCOPE_ENABLED=true

# Accéder à Telescope
http://localhost:1310/telescope
```

### Log Viewer

```bash
# Accéder au log viewer
http://localhost:1310/log-viewer
```

## 🧪 Tests qui échouent

### PHPUnit errors

```bash
# 1. Préparer la base de test
docker-compose exec app_engage php artisan migrate --env=testing

# 2. Clear caches
docker-compose exec app_engage php artisan config:clear --env=testing

# 3. Run tests avec plus de détails
docker-compose exec app_engage php artisan test --verbose

# 4. Run un test spécifique
docker-compose exec app_engage php artisan test --filter=TestName

# 5. Avec coverage
docker-compose exec app_engage php artisan test --coverage
```

### Database transactions issues

```bash
# Vérifier que vous utilisez DatabaseTransactions
grep -r "RefreshDatabase" tests/
# Remplacer par DatabaseTransactions

# Clear test database
docker-compose exec app_engage php artisan migrate:fresh --env=testing
```

## 🔐 Problèmes d'authentification

### JWT Token invalide

```bash
# 1. Vérifier la configuration Cognito
grep COGNITO .env

# 2. Regenerer les clés si nécessaire
docker-compose exec app_engage php artisan jwt:secret

# 3. Clear cache
docker-compose exec app_engage php artisan config:cache
```

### Permissions refusées

```bash
# 1. Vérifier les rôles et permissions
docker-compose exec app_engage php artisan tinker
>>> $user = User::find(1);
>>> $user->getRoleNames();
>>> $user->getAllPermissions()->pluck('name');

# 2. Réinitialiser les permissions
docker-compose exec app_engage php artisan permission:cache-reset
docker-compose exec app_engage php artisan cache:forget spatie.permission.cache
```

## 🚀 Performance Issues

### Application lente

```bash
# 1. Activer OPcache
docker-compose exec app_engage php -i | grep opcache

# 2. Optimiser l'autoloader
docker-compose exec app_engage composer install --optimize-autoloader --no-dev

# 3. Cache de configuration
docker-compose exec app_engage php artisan config:cache
docker-compose exec app_engage php artisan route:cache
docker-compose exec app_engage php artisan view:cache

# 4. Vérifier les queries N+1
# Utiliser Laravel Telescope ou Debugbar
```

### Mémoire insuffisante

```bash
# 1. Augmenter la limite PHP
# Dans docker/php/local.ini
memory_limit = 512M

# 2. Redémarrer
docker-compose restart app_engage

# 3. Monitorer l'usage
docker stats app_engage
```

## 🔄 Queue/Jobs Issues

### Jobs non traités

```bash
# 1. Vérifier que le worker tourne
docker-compose exec app_engage php artisan queue:work --tries=1

# 2. Ou utiliser make
make queue

# 3. Voir les jobs échoués
docker-compose exec app_engage php artisan queue:failed

# 4. Retry failed jobs
docker-compose exec app_engage php artisan queue:retry all

# 5. Clear failed jobs
docker-compose exec app_engage php artisan queue:flush
```

### Queue connection error

```bash
# Vérifier la configuration
grep QUEUE .env

# Configuration correcte:
QUEUE_CONNECTION=redis
```

## 🌐 Reverb WebSocket Issues

### WebSocket ne se connecte pas

```bash
# 1. Vérifier que Reverb est lancé
make reverb-status

# 2. Start Reverb
make reverb-start

# 3. Vérifier les logs
make reverb-logs

# 4. Tester la connexion
curl http://localhost:8080
```

### Events non reçus

```bash
# 1. Vérifier la configuration
grep REVERB .env
grep BROADCAST .env

# 2. Tester manuellement
make reverb-test

# 3. Vérifier la queue
make queue
```

## 🗄️ Problèmes de Migration

### Migration échoue

```bash
# 1. Rollback
docker-compose exec app_engage php artisan migrate:rollback

# 2. Fix the migration file

# 3. Re-run
docker-compose exec app_engage php artisan migrate

# 4. Si bloqué, fresh install (ATTENTION: perte de données)
docker-compose exec app_engage php artisan migrate:fresh --seed
```

### Foreign key constraint

```bash
# Désactiver temporairement les contraintes
docker-compose exec app_engage php artisan tinker
>>> DB::statement('SET FOREIGN_KEY_CHECKS=0');
>>> // Run your operations
>>> DB::statement('SET FOREIGN_KEY_CHECKS=1');
```

## 🆘 Reset Complet

Si rien ne fonctionne, réinitialisation complète :

```bash
# 1. Sauvegarder .env
cp .env .env.backup

# 2. Arrêter tout
docker-compose down -v

# 3. Nettoyer Docker
make docker-deep-clean

# 4. Récupérer le code
git reset --hard
git clean -fd

# 5. Restaurer .env
cp .env.backup .env

# 6. Rebuild complet
docker-compose up -d --build

# 7. Setup database
make migrate-fresh

# 8. Vérifier
curl http://localhost:1310/health
```

## 📊 Monitoring et Logs

### Sentry (Production)

```bash
# Configuration dans .env
SENTRY_LARAVEL_DSN=your-sentry-dsn

# Tester Sentry
docker-compose exec app_engage php artisan sentry:test
```

### Health Check

```bash
# Endpoint de santé
curl http://localhost:1310/health

# Devrait retourner:
{
  "status": "healthy",
  "services": {
    "database": "connected",
    "redis": "connected",
    "queue": "running"
  }
}
```

### Métriques système

```bash
# CPU et mémoire
docker stats

# Espace disque
df -h

# Processus
docker-compose exec app_engage top
```

## 📝 Commandes Utiles

### Artisan Commands

```bash
# Clear tout
docker-compose exec app_engage php artisan optimize:clear

# Lister les routes
docker-compose exec app_engage php artisan route:list

# Tinker (REPL PHP)
docker-compose exec app_engage php artisan tinker

# Créer un utilisateur admin
docker-compose exec app_engage php artisan make:admin
```

### Database Commands

```bash
# Backup database
docker-compose exec db_engage pg_dump -U root db_engage > backup.sql

# Restore database
docker-compose exec -T db_engage psql -U root db_engage < backup.sql

# Access PostgreSQL
docker-compose exec db_engage psql -U root -d db_engage
```

### Redis Commands

```bash
# Monitor Redis
docker-compose exec redis-cluster redis-cli monitor

# Flush all
docker-compose exec redis-cluster redis-cli FLUSHALL

# Get all keys
docker-compose exec redis-cluster redis-cli KEYS "*"
```

## 🐛 Rapporter un Bug

Si le problème persiste :

1. **Collecter les informations**
   ```bash
   # Version PHP
   docker-compose exec app_engage php -v
   
   # Version Laravel
   docker-compose exec app_engage php artisan --version
   
   # Environnement
   docker-compose exec app_engage php artisan env
   ```

2. **Logs complets**
   ```bash
   docker-compose logs > docker-logs.txt
   tail -n 1000 storage/logs/laravel.log > laravel-logs.txt
   ```

3. **Créer une issue sur GitLab** avec :
   - Description du problème
   - Étapes pour reproduire
   - Logs collectés
   - Configuration environnement

## 📚 Ressources

- [Laravel Documentation](https://laravel.com/docs)
- [Docker Documentation](https://docs.docker.com/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Redis Documentation](https://redis.io/documentation)
- Confluence interne : "UpEngage Troubleshooting"
- Contact équipe : #up-engage-support sur Slack

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko