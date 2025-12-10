#!/bin/bash

# Script pour configurer l'environnement de test WellWo
# Usage: ./scripts/setup-test-env.sh

set -e

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction de logging
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" >&2
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log "🔧 Configuration de l'environnement de test WellWo"

# Vérifier si Docker est en cours d'exécution
if ! docker compose ps | grep -q "app_engage.*running"; then
    warning "Le conteneur Docker n'est pas en cours d'exécution"
    log "Démarrage du conteneur..."
    docker compose up -d
    sleep 5
fi

# Générer un token de test avec Laravel
log "🔑 Génération d'un token JWT de test..."

# Créer un utilisateur de test et obtenir un token
TOKEN=$(docker compose exec -T app_engage php artisan tinker --execute="
    use App\Models\User;
    use Tymon\JWTAuth\Facades\JWTAuth;
    
    \$user = User::first();
    if (!\$user) {
        \$user = User::factory()->create([
            'email' => 'wellwo-test@example.com',
            'first_name' => 'WellWo',
            'last_name' => 'Test'
        ]);
    }
    
    \$token = JWTAuth::fromUser(\$user);
    echo \$token;
" 2>/dev/null | tail -n1)

if [ -z "$TOKEN" ]; then
    error "Impossible de générer un token JWT"
    exit 1
fi

# Mettre à jour le fichier d'environnement avec le token
ENV_FILE="./environments/local.postman_environment.json"

if [ -f "$ENV_FILE" ]; then
    log "📝 Mise à jour du fichier d'environnement avec le token..."
    
    # Utiliser jq pour mettre à jour le token
    if command -v jq &> /dev/null; then
        jq '.values |= map(if .key == "auth_token" then .value = "'$TOKEN'" else . end)' "$ENV_FILE" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
        success "Token JWT mis à jour dans l'environnement"
    else
        warning "jq n'est pas installé. Le token doit être copié manuellement:"
        echo ""
        echo "Token JWT: $TOKEN"
        echo ""
        echo "Copiez ce token dans $ENV_FILE pour la clé 'auth_token'"
    fi
else
    error "Fichier d'environnement non trouvé: $ENV_FILE"
    exit 1
fi

# Vérifier la connexion à l'API
log "🔍 Vérification de la connexion à l'API..."
HEALTH_CHECK=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:1310/api/v1/health)

if [ "$HEALTH_CHECK" = "200" ]; then
    success "✅ API accessible sur http://localhost:1310"
else
    warning "⚠️  L'API a retourné le code HTTP: $HEALTH_CHECK"
fi

# Afficher les informations de configuration
echo ""
log "📋 Configuration actuelle:"
echo "  - API URL: http://localhost:1310"
echo "  - Environment: local"
echo "  - Token JWT: [Configuré]"
echo ""

# Instructions pour exécuter les tests
log "🚀 Pour exécuter les tests WellWo:"
echo "  ./scripts/run-tests.sh local"
echo ""
log "📊 Pour exécuter uniquement certains dossiers:"
echo "  ./scripts/run-tests.sh local 'Programs'"
echo "  ./scripts/run-tests.sh local 'Videos'"
echo ""

success "✅ Environnement de test configuré avec succès!"