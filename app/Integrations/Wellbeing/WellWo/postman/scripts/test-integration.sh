#!/bin/bash

# Script de test d'intégration complet pour WellWo
# Ce script teste à la fois les tests unitaires PHP et l'API externe WellWo
# Usage: ./scripts/test-integration.sh

set -e

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
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

info() {
    echo -e "${CYAN}[INFO]${NC} $1"
}

# Bannière
echo ""
echo -e "${CYAN}╔════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║     🧪 Tests d'Intégration WellWo API 🧪      ║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════╝${NC}"
echo ""

# Étape 1: Tests unitaires et feature PHP
log "📦 Étape 1/4: Tests PHPUnit"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

cd /Users/fred/PhpstormProjects/up-engage-api

# Exécuter les tests WellWo
if make test-group GROUPS="wellwo" 2>&1 | grep -q "OK"; then
    success "✅ Tests PHPUnit: PASSÉS"
    PHPUNIT_STATUS="${GREEN}✅ Passés${NC}"
else
    error "❌ Tests PHPUnit: ÉCHEC"
    PHPUNIT_STATUS="${RED}❌ Échec${NC}"
fi

echo ""

# Étape 2: Vérifier la configuration
log "⚙️ Étape 2/4: Vérification de la configuration"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Vérifier le token WellWo
WELLWO_TOKEN=$(docker compose exec -T app_engage php artisan tinker --execute="
    echo config('services.wellwo.auth_token', 'not-configured');
" 2>/dev/null | tail -n1)

if [ "$WELLWO_TOKEN" != "not-configured" ] && [ -n "$WELLWO_TOKEN" ]; then
    success "✅ Token WellWo configuré"
    CONFIG_STATUS="${GREEN}✅ OK${NC}"
else
    warning "⚠️ Token WellWo non configuré (services.wellwo.auth_token)"
    CONFIG_STATUS="${YELLOW}⚠️ Partiel${NC}"
fi

# Vérifier la configuration Cognito
COGNITO_POOL=$(docker compose exec -T app_engage php artisan tinker --execute="
    echo config('services.cognito.user_pool_id', 'not-configured');
" 2>/dev/null | tail -n1)

if [ "$COGNITO_POOL" != "not-configured" ] && [ -n "$COGNITO_POOL" ]; then
    info "ℹ️ Cognito configuré: $COGNITO_POOL"
else
    info "ℹ️ Cognito non configuré (utilisation du mode test)"
fi

echo ""

# Étape 3: Test de l'API externe WellWo
log "🌐 Étape 3/4: Test API WellWo externe"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ "$WELLWO_TOKEN" != "not-configured" ] && [ -n "$WELLWO_TOKEN" ]; then
    # Test de récupération des programmes
    WELLWO_RESPONSE=$(curl -s -X POST \
        -H "Content-Type: application/json" \
        -d "{\"authToken\":\"$WELLWO_TOKEN\",\"op\":\"healthyProgramsGetList\",\"lang\":\"es\"}" \
        "https://my.wellwo.net/api/v1/" \
        -o /tmp/wellwo-response.json \
        -w "%{http_code}")
    
    if [ "$WELLWO_RESPONSE" = "200" ]; then
        success "✅ API WellWo externe: ACCESSIBLE"
        
        # Compter le nombre de programmes
        PROGRAM_COUNT=$(grep -o '"id"' /tmp/wellwo-response.json 2>/dev/null | wc -l)
        info "📦 Nombre de programmes disponibles: $PROGRAM_COUNT"
        
        EXTERNAL_API_STATUS="${GREEN}✅ OK${NC}"
    else
        warning "⚠️ API WellWo externe: HTTP $WELLWO_RESPONSE"
        EXTERNAL_API_STATUS="${YELLOW}⚠️ HTTP $WELLWO_RESPONSE${NC}"
    fi
else
    warning "⏭️ Test API externe ignoré (token non configuré)"
    EXTERNAL_API_STATUS="${YELLOW}⏭️ Ignoré${NC}"
fi

echo ""

# Étape 4: Test du cache Redis
log "💾 Étape 4/4: Test du cache Redis"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Vérifier la connexion Redis
REDIS_TEST=$(docker compose exec -T app_engage php artisan tinker --execute="
    use Illuminate\Support\Facades\Cache;
    
    try {
        Cache::tags(['{wellwo}:programs'])->put('{wellwo}:test', 'test-value', 60);
        \$value = Cache::tags(['{wellwo}:programs'])->get('{wellwo}:test');
        Cache::tags(['{wellwo}:programs'])->forget('{wellwo}:test');
        echo \$value === 'test-value' ? 'OK' : 'FAIL';
    } catch (Exception \$e) {
        echo 'ERROR: ' . \$e->getMessage();
    }
" 2>/dev/null | tail -n1)

if [ "$REDIS_TEST" = "OK" ]; then
    success "✅ Cache Redis: FONCTIONNEL"
    info "ℹ️ Utilisation du format Redis Cluster ({wellwo}:*)"
    CACHE_STATUS="${GREEN}✅ OK${NC}"
else
    error "❌ Cache Redis: $REDIS_TEST"
    CACHE_STATUS="${RED}❌ Échec${NC}"
fi

echo ""

# Résumé final
echo -e "${CYAN}╔════════════════════════════════════════════════╗${NC}"
echo -e "${CYAN}║              📊 RÉSUMÉ DES TESTS              ║${NC}"
echo -e "${CYAN}╠════════════════════════════════════════════════╣${NC}"
echo -e "${CYAN}║${NC} Tests PHPUnit      : $PHPUNIT_STATUS                  ${CYAN}║${NC}"
echo -e "${CYAN}║${NC} Configuration      : $CONFIG_STATUS                     ${CYAN}║${NC}"
echo -e "${CYAN}║${NC} API WellWo externe : $EXTERNAL_API_STATUS            ${CYAN}║${NC}"
echo -e "${CYAN}║${NC} Cache Redis        : $CACHE_STATUS                     ${CYAN}║${NC}"
echo -e "${CYAN}╚════════════════════════════════════════════════╝${NC}"
echo ""

# Recommandations
if [ "$CONFIG_STATUS" = "${YELLOW}⚠️ Partiel${NC}" ]; then
    log "💡 Recommandations:"
    echo "  1. Configurez le token WellWo dans .env:"
    echo "     WELLWO_AUTH_TOKEN=votre-token-ici"
    echo ""
fi

if [ "$EXTERNAL_API_STATUS" != "${GREEN}✅ OK${NC}" ]; then
    echo "  2. Vérifiez la connexion à l'API WellWo externe"
    echo "     URL: https://my.wellwo.net/api/v1/"
    echo ""
fi

# Code de sortie basé sur les tests critiques
if [ "$PHPUNIT_STATUS" = "${GREEN}✅ Passés${NC}" ] && [ "$CACHE_STATUS" = "${GREEN}✅ OK${NC}" ]; then
    success "🎉 Tests d'intégration WellWo terminés avec succès!"
    exit 0
else
    error "❌ Certains tests ont échoué. Veuillez vérifier les erreurs ci-dessus."
    exit 1
fi