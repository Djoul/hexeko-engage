#!/bin/bash

# Script pour exécuter les tests WellWo via Newman
# Usage: ./postman/scripts/run-wellwo-tests.sh [environment] [folder]

set -e

# Configuration par défaut
DEFAULT_ENV="local"
DEFAULT_CONFIG="./newman-config.json"
REPORTS_DIR="./reports/newman"
ENV_DIR="./environments"

# Arguments
ENVIRONMENT=${1:-$DEFAULT_ENV}
FOLDER=${2:-""}

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

# Vérifier que Newman est installé
if ! command -v newman &> /dev/null; then
    error "Newman n'est pas installé. Installez-le avec: npm install -g newman newman-reporter-htmlextra"
    exit 1
fi

# Créer le répertoire de rapports s'il n'existe pas
mkdir -p "$REPORTS_DIR"

# Construire la commande Newman
NEWMAN_CMD="newman run"
CONFIG_FILE="$DEFAULT_CONFIG"

# Vérifier que le fichier de configuration existe
if [ ! -f "$CONFIG_FILE" ]; then
    error "Fichier de configuration Newman introuvable: $CONFIG_FILE"
    exit 1
fi

# Vérifier que l'environnement existe
ENV_FILE="${ENV_DIR}/${ENVIRONMENT}.postman_environment.json"
if [ ! -f "$ENV_FILE" ]; then
    warning "Environnement $ENVIRONMENT introuvable dans $ENV_DIR"
    # Essayer l'environnement local par défaut
    ENV_FILE="${ENV_DIR}/local.postman_environment.json"
    if [ ! -f "$ENV_FILE" ]; then
        error "Aucun fichier d'environnement trouvé. Créez ${ENV_DIR}/local.postman_environment.json"
        exit 1
    fi
    warning "Utilisation de l'environnement local par défaut"
fi

# Construire les options Newman à partir du fichier de configuration
COLLECTION=$(jq -r '.collection' "$CONFIG_FILE")
REPORTERS=$(jq -r '.reporters | join(",")' "$CONFIG_FILE")
TIMEOUT=$(jq -r '.timeout' "$CONFIG_FILE")
DELAY=$(jq -r '.delayRequest' "$CONFIG_FILE")

# Options de base
NEWMAN_OPTIONS="--environment $ENV_FILE"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --reporters $REPORTERS"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --timeout $TIMEOUT"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --delay-request $DELAY"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --reporter-htmlextra-export $REPORTS_DIR/newman-wellwo-report.html"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --reporter-json-export $REPORTS_DIR/newman-wellwo-report.json"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --reporter-junit-export $REPORTS_DIR/junit-wellwo.xml"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --export-globals $REPORTS_DIR/newman-wellwo-globals.json"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --export-environment $REPORTS_DIR/newman-wellwo-environment.json"
NEWMAN_OPTIONS="$NEWMAN_OPTIONS --color auto"

# Ajouter le folder si spécifié
if [ -n "$FOLDER" ]; then
    NEWMAN_OPTIONS="$NEWMAN_OPTIONS --folder '$FOLDER'"
fi

# Affichage des informations de lancement
log "🚀 Lancement des tests WellWo API"
log "📁 Collection: $COLLECTION"
log "🌍 Environnement: $ENV_FILE"
log "📊 Rapports: $REPORTS_DIR"
if [ -n "$FOLDER" ]; then
    log "📂 Dossier: $FOLDER"
fi

# Exécution des tests
log "▶️  Exécution en cours..."

if eval "$NEWMAN_CMD $COLLECTION $NEWMAN_OPTIONS"; then
    success "✅ Tests WellWo exécutés avec succès!"

    # Afficher les liens vers les rapports
    echo ""
    log "📋 Rapports disponibles:"
    echo "  - HTML: file://$(pwd)/$REPORTS_DIR/newman-wellwo-report.html"
    echo "  - JSON: $(pwd)/$REPORTS_DIR/newman-wellwo-report.json"
    echo "  - JUnit: $(pwd)/$REPORTS_DIR/junit-wellwo.xml"

    exit 0
else
    error "❌ Échec des tests WellWo"

    # Afficher les rapports même en cas d'échec pour debug
    if [ -f "$REPORTS_DIR/newman-wellwo-report.html" ]; then
        log "📋 Rapport d'erreur disponible: file://$(pwd)/$REPORTS_DIR/newman-wellwo-report.html"
    fi

    exit 1
fi