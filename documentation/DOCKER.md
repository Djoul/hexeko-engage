# Docker Setup Documentation

## 🐳 Architecture Docker

Le projet utilise Docker Compose pour orchestrer plusieurs services :

```
up-engage-api/
├── docker-compose.yml          # Configuration principale
├── docker-compose.worktree.yml # Configuration pour worktrees Git
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   └── local.ini
│   └── nginx/
│       └── default.local.conf
```

## 📦 Services Docker

### Services Principaux

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| **app_engage** | app_engage | - | Application PHP-FPM |
| **webserver_engage** | webserver_engage | 1310 | Serveur Nginx |
| **db_engage** | db_engage | 5433 | PostgreSQL 15 |
| **redis-cluster** | redis-cluster | 6379 | Redis Cluster (6 nodes) |
| **reverb_engage** | reverb_engage | 8080 | WebSocket Server |

### Configuration des Services

#### PHP Application (app_engage)

```yaml
app_engage:
  build:
    context: .
    dockerfile: ./docker/php/Dockerfile
  container_name: app_engage
  volumes:
    - .:/var/www
    - ./docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini
  networks:
    - engage_network
  depends_on:
    - db_engage
    - redis-cluster
```

#### Nginx Web Server

```yaml
webserver_engage:
  image: nginx:alpine
  container_name: webserver_engage
  ports:
    - "1310:80"
  volumes:
    - .:/var/www
    - ./docker/nginx/default.local.conf:/etc/nginx/conf.d/default.conf
  networks:
    - engage_network
```

#### PostgreSQL Database

```yaml
db_engage:
  image: postgres:15
  container_name: db_engage
  ports:
    - "5433:5432"
  environment:
    POSTGRES_DB: db_engage
    POSTGRES_USER: root
    POSTGRES_PASSWORD: password
  volumes:
    - postgres_data:/var/lib/postgresql/data
  networks:
    - engage_network
```

#### Redis Cluster

```yaml
redis-cluster:
  image: redis:7-alpine
  container_name: redis-cluster
  ports:
    - "6379:6379"
  command: redis-server --appendonly yes
  volumes:
    - redis_data:/data
  networks:
    - engage_network
```

## 🚀 Commandes Essentielles

### Démarrage et Arrêt

```bash
# Démarrer tous les services
docker-compose up -d

# Démarrer avec rebuild
docker-compose up -d --build

# Arrêter tous les services
docker-compose down

# Arrêter et supprimer les volumes
docker-compose down -v
```

### Logs et Monitoring

```bash
# Voir tous les logs
docker-compose logs -f

# Logs d'un service spécifique
docker-compose logs -f app_engage
docker-compose logs -f db_engage

# Statut des containers
docker-compose ps

# Ressources utilisées
docker stats
```

### Commandes dans les Containers

```bash
# Exécuter des commandes PHP/Artisan
docker-compose exec app_engage php artisan migrate
docker-compose exec app_engage php artisan tinker
docker-compose exec app_engage composer install

# Accéder au shell du container
docker-compose exec app_engage bash

# Commandes PostgreSQL
docker-compose exec db_engage psql -U root -d db_engage

# Commandes Redis
docker-compose exec redis-cluster redis-cli
```

## 🛠️ Commandes Make pour Docker

Le Makefile fournit des raccourcis pratiques :

```bash
# Redémarrage complet
make docker-restart

# Nettoyage safe (préserve les données)
make docker-clean

# Nettoyage des worktrees
make docker-clean-worktree

# Nettoyage profond (ATTENTION: supprime tout)
make docker-deep-clean
```

### Détail des Commandes Make

#### `make docker-restart`
- Arrête tous les containers
- Supprime les containers orphelins
- Supprime les réseaux non utilisés
- Redémarre tous les services

#### `make docker-clean`
- Nettoie les containers arrêtés
- Supprime les images non utilisées
- Préserve les bases de données
- Garde les containers actifs

#### `make docker-clean-worktree`
- Nettoie spécifiquement les containers worktree
- Supprime les containers redis_cluster_*
- Utile pour le développement multi-branches

#### `make docker-deep-clean`
**⚠️ DANGER**: Supprime TOUT
- Tous les containers
- Toutes les images
- Tous les volumes
- Tous les réseaux
- Réinitialisation complète

## 📁 Volumes et Persistance

### Volumes Nommés

```yaml
volumes:
  postgres_data:    # Données PostgreSQL
  redis_data:       # Données Redis
  reverb_data:      # Logs Reverb
```

### Montages de Volumes

```yaml
# Code source (bidirectionnel)
- .:/var/www

# Configuration PHP
- ./docker/php/local.ini:/usr/local/etc/php/conf.d/local.ini

# Configuration Nginx
- ./docker/nginx/default.local.conf:/etc/nginx/conf.d/default.conf
```

## 🔧 Configuration PHP

### Dockerfile PHP

```dockerfile
FROM php:8.4-fpm

# Extensions PHP requises
RUN docker-php-ext-install pdo pdo_pgsql opcache bcmath

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
```

### Configuration PHP (local.ini)

```ini
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 600
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
```

## 🌐 Configuration Nginx

### Virtual Host Configuration

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/public;
    index index.php;

    # Logs
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass app_engage:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Laravel routing
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

## 🔄 Redis Cluster Configuration

### Setup Redis Cluster

```bash
# Créer le cluster (6 nodes: 3 masters, 3 replicas)
docker-compose exec redis-cluster redis-cli --cluster create \
    127.0.0.1:7000 127.0.0.1:7001 127.0.0.1:7002 \
    127.0.0.1:7003 127.0.0.1:7004 127.0.0.1:7005 \
    --cluster-replicas 1

# Vérifier le cluster
docker-compose exec redis-cluster redis-cli cluster info
docker-compose exec redis-cluster redis-cli cluster nodes
```

### Monitoring Redis

```bash
# Statistiques en temps réel
docker-compose exec redis-cluster redis-cli monitor

# Info mémoire
docker-compose exec redis-cluster redis-cli info memory

# Statistiques générales
docker-compose exec redis-cluster redis-cli info stats
```

## 🔍 Troubleshooting Docker

### Problèmes Courants

#### Port déjà utilisé

```bash
# Vérifier quel processus utilise le port
lsof -i :1310  # Pour Nginx
lsof -i :5433  # Pour PostgreSQL
lsof -i :6379  # Pour Redis
lsof -i :8080  # Pour Reverb

# Tuer le processus
kill -9 <PID>
```

#### Container ne démarre pas

```bash
# Voir les logs détaillés
docker-compose logs <service_name>

# Reconstruire l'image
docker-compose build --no-cache <service_name>

# Inspecter le container
docker inspect <container_name>
```

#### Problèmes de permissions

```bash
# Fixer les permissions dans le container
docker-compose exec app_engage chown -R www-data:www-data /var/www/storage
docker-compose exec app_engage chmod -R 775 /var/www/storage
```

#### Espace disque insuffisant

```bash
# Nettoyer Docker
docker system prune -a --volumes

# Voir l'espace utilisé
docker system df

# Supprimer les images non utilisées
docker image prune -a
```

### Réinitialisation Complète

```bash
# 1. Arrêter tous les containers
docker-compose down -v

# 2. Supprimer tous les containers Docker
docker rm -f $(docker ps -aq)

# 3. Supprimer toutes les images
docker rmi -f $(docker images -q)

# 4. Supprimer tous les volumes
docker volume rm $(docker volume ls -q)

# 5. Supprimer tous les réseaux
docker network prune -f

# 6. Reconstruire
docker-compose up -d --build
```

## 🚄 Performance et Optimisation

### Optimisations Docker

```yaml
# Dans docker-compose.yml
services:
  app_engage:
    build:
      context: .
      cache_from:
        - php:8.4-fpm
    deploy:
      resources:
        limits:
          cpus: '2'
          memory: 2G
        reservations:
          cpus: '1'
          memory: 1G
```

### Cache des Builds

```bash
# Utiliser BuildKit pour des builds plus rapides
DOCKER_BUILDKIT=1 docker-compose build

# Build avec cache
docker-compose build --build-arg BUILDKIT_INLINE_CACHE=1
```

### Monitoring des Resources

```bash
# Voir l'utilisation en temps réel
docker stats

# Limiter les logs
docker-compose logs --tail=100 -f

# Nettoyer les logs
truncate -s 0 $(docker inspect --format='{{.LogPath}}' <container_name>)
```

## 🔐 Sécurité Docker

### Best Practices

1. **Ne jamais utiliser root dans les containers**
   ```dockerfile
   USER www-data
   ```

2. **Utiliser des images officielles**
   ```yaml
   image: postgres:15-alpine  # Version spécifique + alpine
   ```

3. **Limiter les ports exposés**
   ```yaml
   ports:
     - "127.0.0.1:5433:5432"  # Bind uniquement sur localhost
   ```

4. **Secrets dans .env**
   ```bash
   # Jamais de mots de passe dans docker-compose.yml
   POSTGRES_PASSWORD=${DB_PASSWORD}
   ```

5. **Réseaux isolés**
   ```yaml
   networks:
     engage_network:
       driver: bridge
       internal: true  # Pas d'accès internet direct
   ```

## 📚 Ressources Supplémentaires

- [Documentation Docker](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/compose-file/)
- [Best Practices PHP Docker](https://www.docker.com/blog/php-development-environment/)
- [PostgreSQL Docker](https://hub.docker.com/_/postgres)
- [Redis Docker](https://hub.docker.com/_/redis)

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko