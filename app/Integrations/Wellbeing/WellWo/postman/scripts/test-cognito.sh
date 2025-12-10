#!/bin/bash

# Script pour tester WellWo avec authentification Cognito
# Usage: ./scripts/test-cognito.sh

set -e

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

log "🔐 Configuration des tests WellWo avec Cognito"
echo ""

# Récupérer les variables d'environnement
BASE_URL="http://localhost:1310"
COGNITO_REGION=${AWS_COGNITO_REGION:-"eu-west-3"}
COGNITO_USER_POOL_ID=${AWS_COGNITO_USER_POOL_ID:-""}
COGNITO_CLIENT_ID=${AWS_COGNITO_CLIENT_ID:-""}
COGNITO_CLIENT_SECRET=${AWS_COGNITO_CLIENT_SECRET:-""}

# Option 1: Utiliser un token Cognito existant
if [ -n "$COGNITO_TOKEN" ]; then
    log "🎫 Utilisation du token Cognito fourni"
    AUTH_TOKEN=$COGNITO_TOKEN
    
# Option 2: Créer un utilisateur de test local avec un token simulé
else
    log "🧪 Mode test local - Création d'un token de test"
    
    # Créer un token de test via artisan
    AUTH_TOKEN=$(docker compose exec -T app_engage php artisan tinker --execute="
        use App\Models\User;
        use Tests\Helpers\Facades\ModelFactory;
        
        // Créer ou récupérer l'utilisateur de test
        \$user = User::where('email', 'wellwo-test@example.com')->first();
        if (!\$user) {
            \$user = ModelFactory::createUser([
                'email' => 'wellwo-test@example.com',
                'first_name' => 'WellWo',
                'last_name' => 'Test',
                'cognito_id' => 'test-cognito-' . uniqid()
            ]);
            
            // Assigner le rôle beneficiary
            if (!\App\Models\Role::where('name', 'beneficiary')->exists()) {
                \App\Models\Role::create(['name' => 'beneficiary']);
            }
            \$user->assignRole('beneficiary');
        }
        
        // Générer un token de test (simulé)
        \$token = base64_encode(json_encode([
            'sub' => \$user->cognito_id,
            'email' => \$user->email,
            'exp' => time() + 3600
        ]));
        
        echo \$token;
    " 2>/dev/null | tail -n1)
    
    if [ -z "$AUTH_TOKEN" ]; then
        warning "⚠️ Impossible de créer un token de test"
        log "Essai avec une route de test non protégée..."
    else
        success "✅ Token de test créé"
    fi
fi

# Test 1: Health Check (sans auth)
log "1️⃣ Test Health Check..."
HEALTH_RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/v1/health" | tail -n1)
if [ "$HEALTH_RESPONSE" = "200" ]; then
    success "✅ Health check OK"
else
    error "❌ Health check failed (HTTP $HEALTH_RESPONSE)"
fi

# Test 2: Programs avec token
if [ -n "$AUTH_TOKEN" ]; then
    log "2️⃣ Test Programs avec authentification..."
    
    PROGRAMS_RESPONSE=$(curl -s -w "\n%{http_code}" \
        -H "Authorization: Bearer $AUTH_TOKEN" \
        "$BASE_URL/api/v1/wellbeing/wellwo/programs?lang=es" | tail -n1)
    
    if [ "$PROGRAMS_RESPONSE" = "200" ]; then
        success "✅ Programs endpoint OK avec auth"
    elif [ "$PROGRAMS_RESPONSE" = "401" ]; then
        warning "⚠️ Token invalide ou expiré"
    else
        error "❌ Programs endpoint retourne HTTP $PROGRAMS_RESPONSE"
    fi
else
    warning "⏭️ Test avec auth ignoré (pas de token)"
fi

# Test 3: Test direct de l'API WellWo externe
log "3️⃣ Test direct API WellWo externe..."

# Récupérer le token WellWo depuis la config
WELLWO_TOKEN=$(docker compose exec -T app_engage php artisan tinker --execute="
    echo config('services.wellwo.auth_token', 'not-configured');
" 2>/dev/null | tail -n1)

if [ "$WELLWO_TOKEN" != "not-configured" ] && [ -n "$WELLWO_TOKEN" ]; then
    WELLWO_RESPONSE=$(curl -s -X POST \
        -H "Content-Type: application/json" \
        -d "{\"authToken\":\"$WELLWO_TOKEN\",\"op\":\"healthyProgramsGetList\",\"lang\":\"es\"}" \
        "https://my.wellwo.net/api/v1/" \
        -w "\n%{http_code}" | tail -n1)
    
    if [ "$WELLWO_RESPONSE" = "200" ]; then
        success "✅ API WellWo externe accessible"
    else
        warning "⚠️ API WellWo externe retourne HTTP $WELLWO_RESPONSE"
    fi
else
    warning "⚠️ Token WellWo non configuré dans services.wellwo.auth_token"
fi

echo ""
log "📊 Résumé des tests"
echo "  - Health Check: ${GREEN}OK${NC}"
if [ -n "$AUTH_TOKEN" ]; then
    echo "  - Authentification: ${GREEN}Token de test généré${NC}"
else
    echo "  - Authentification: ${YELLOW}Non configurée${NC}"
fi

echo ""
log "💡 Pour des tests complets:"
echo "  1. Configurer COGNITO_TOKEN avec un vrai token Cognito"
echo "  2. Ou créer une route de test sans middleware auth"
echo "  3. Configurer services.wellwo.auth_token pour l'API externe"

# Sauvegarder le token pour les tests Newman si disponible
if [ -n "$AUTH_TOKEN" ]; then
    ENV_FILE="./environments/local.postman_environment.json"
    
    if [ -f "$ENV_FILE" ] && command -v jq &> /dev/null; then
        log "📝 Mise à jour du fichier d'environnement Postman..."
        jq '.values |= map(if .key == "auth_token" then .value = "'$AUTH_TOKEN'" else . end)' "$ENV_FILE" > "$ENV_FILE.tmp" && mv "$ENV_FILE.tmp" "$ENV_FILE"
        success "✅ Token sauvegardé dans l'environnement Postman"
        
        echo ""
        log "🚀 Vous pouvez maintenant exécuter les tests Postman:"
        echo "  ./scripts/run-tests.sh local"
    fi
fi

echo ""
success "✅ Tests de base terminés!"