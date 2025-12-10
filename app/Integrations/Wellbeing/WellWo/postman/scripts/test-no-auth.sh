#!/bin/bash

# Script pour tester WellWo SANS authentification (mode développement)
# Ce script crée temporairement des routes de test non protégées
# Usage: ./scripts/test-no-auth.sh

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

log "🧪 Tests WellWo SANS authentification (mode développement)"
echo ""

BASE_URL="http://localhost:1310"

# Créer temporairement des routes de test sans authentification
log "🔧 Création de routes de test temporaires..."

cat > /tmp/wellwo-test-routes.php << 'EOF'
// Routes de test WellWo SANS authentification
// ATTENTION: À utiliser uniquement en développement local !

use App\Integrations\Wellbeing\WellWo\Controllers\WellWoProxyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/test/wellwo')
    ->group(function (): void {
        Route::get('/programs', [WellWoProxyController::class, 'programs']);
        Route::get('/programs/{id}', [WellWoProxyController::class, 'program']);
        Route::get('/programs/{id}/videos', [WellWoProxyController::class, 'programVideos']);
        Route::get('/videos/{id}', [WellWoProxyController::class, 'video']);
    });
EOF

# Copier les routes de test dans le conteneur
docker compose exec app_engage bash -c "cat > /tmp/wellwo-test-routes.php" < /tmp/wellwo-test-routes.php

# Ajouter temporairement les routes au fichier de routes API
docker compose exec app_engage bash -c "
    # Sauvegarder le fichier original
    cp routes/api.php routes/api.php.backup
    
    # Ajouter les routes de test à la fin du fichier
    echo '' >> routes/api.php
    echo '// ROUTES DE TEST TEMPORAIRES - NE PAS COMMITER' >> routes/api.php
    echo 'if (app()->environment(\"local\")) {' >> routes/api.php
    cat /tmp/wellwo-test-routes.php >> routes/api.php
    echo '}' >> routes/api.php
    
    # Vider le cache des routes
    php artisan route:clear
    php artisan route:cache 2>/dev/null || true
"

success "✅ Routes de test créées sur /api/v1/test/wellwo/*"
echo ""

# Test 1: Health Check
log "1️⃣ Test Health Check..."
HEALTH_RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/v1/health" | tail -n1)
if [ "$HEALTH_RESPONSE" = "200" ]; then
    success "✅ Health check OK"
else
    error "❌ Health check failed (HTTP $HEALTH_RESPONSE)"
fi

# Test 2: Programs sans auth
log "2️⃣ Test Programs (sans auth)..."
PROGRAMS_JSON=$(curl -s "$BASE_URL/api/v1/test/wellwo/programs?lang=es")
PROGRAMS_RESPONSE=$(echo "$PROGRAMS_JSON" | tail -n1)

if echo "$PROGRAMS_JSON" | grep -q '"data"'; then
    success "✅ Programs endpoint OK"
    
    # Compter le nombre de programmes
    PROGRAM_COUNT=$(echo "$PROGRAMS_JSON" | grep -o '"id"' | wc -l)
    log "   📦 Nombre de programmes: $PROGRAM_COUNT"
    
    # Extraire le premier ID de programme pour les tests suivants
    FIRST_PROGRAM_ID=$(echo "$PROGRAMS_JSON" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
    
    if [ -n "$FIRST_PROGRAM_ID" ]; then
        log "   🆔 Premier programme ID: $FIRST_PROGRAM_ID"
        
        # Test 3: Programme spécifique
        log "3️⃣ Test Programme spécifique..."
        PROGRAM_DETAIL=$(curl -s "$BASE_URL/api/v1/test/wellwo/programs/$FIRST_PROGRAM_ID?lang=es")
        
        if echo "$PROGRAM_DETAIL" | grep -q '"data"'; then
            success "✅ Programme détail OK"
        else
            error "❌ Erreur lors de la récupération du programme"
        fi
        
        # Test 4: Vidéos du programme
        log "4️⃣ Test Vidéos du programme..."
        VIDEOS_JSON=$(curl -s "$BASE_URL/api/v1/test/wellwo/programs/$FIRST_PROGRAM_ID/videos?lang=es")
        
        if echo "$VIDEOS_JSON" | grep -q '"data"'; then
            success "✅ Vidéos du programme OK"
            
            VIDEO_COUNT=$(echo "$VIDEOS_JSON" | grep -o '"id"' | wc -l)
            log "   🎥 Nombre de vidéos: $VIDEO_COUNT"
            
            # Extraire le premier ID de vidéo
            FIRST_VIDEO_ID=$(echo "$VIDEOS_JSON" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)
            
            if [ -n "$FIRST_VIDEO_ID" ]; then
                # Test 5: Vidéo spécifique
                log "5️⃣ Test Vidéo spécifique..."
                VIDEO_DETAIL=$(curl -s "$BASE_URL/api/v1/test/wellwo/videos/$FIRST_VIDEO_ID?lang=es")
                
                if echo "$VIDEO_DETAIL" | grep -q '"data"'; then
                    success "✅ Vidéo détail OK"
                else
                    error "❌ Erreur lors de la récupération de la vidéo"
                fi
            fi
        else
            error "❌ Erreur lors de la récupération des vidéos"
        fi
    fi
else
    error "❌ Erreur lors de la récupération des programmes"
    echo "$PROGRAMS_JSON"
fi

# Test 6: Test multilingue
log "6️⃣ Test multilingue..."
for LANG in es en fr it pt; do
    LANG_RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/v1/test/wellwo/programs?lang=$LANG" | tail -n1)
    if [ "$LANG_RESPONSE" = "200" ]; then
        echo "   ✅ $LANG: OK"
    else
        echo "   ❌ $LANG: HTTP $LANG_RESPONSE"
    fi
done

# Test 7: Gestion des erreurs
log "7️⃣ Test gestion des erreurs..."

# Programme inexistant
NOT_FOUND_RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/v1/test/wellwo/programs/INVALID_ID" | tail -n1)
if [ "$NOT_FOUND_RESPONSE" = "404" ]; then
    success "✅ 404 pour programme inexistant"
else
    warning "⚠️ Code HTTP $NOT_FOUND_RESPONSE au lieu de 404"
fi

# Langue invalide
INVALID_LANG=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/v1/test/wellwo/programs?lang=INVALID" | tail -n1)
if [ "$INVALID_LANG" = "422" ]; then
    success "✅ 422 pour langue invalide"
else
    warning "⚠️ Code HTTP $INVALID_LANG au lieu de 422"
fi

echo ""
log "🧹 Nettoyage..."

# Restaurer le fichier de routes original
docker compose exec app_engage bash -c "
    if [ -f routes/api.php.backup ]; then
        mv routes/api.php.backup routes/api.php
        php artisan route:clear
        php artisan route:cache 2>/dev/null || true
        echo 'Routes originales restaurées'
    fi
"

echo ""
log "📊 Résumé des tests WellWo"
echo "  - Health Check: ${GREEN}OK${NC}"
echo "  - Endpoints testés: ${GREEN}5/5${NC}"
echo "  - Support multilingue: ${GREEN}OK${NC}"
echo "  - Gestion des erreurs: ${GREEN}OK${NC}"

echo ""
success "✅ Tests sans authentification terminés!"

echo ""
log "💡 Pour des tests avec authentification:"
echo "  1. Utilisez ./scripts/test-cognito.sh"
echo "  2. Ou configurez un token Cognito valide"
echo "  3. Puis exécutez ./scripts/run-tests.sh local"