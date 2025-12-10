#!/bin/bash

# Script de configuration du monitoring WellWo
# Usage: ./postman/scripts/setup-wellwo-monitoring.sh [environment]

set -e

# Configuration
ENVIRONMENT=${1:-"production"}
MONITORING_DIR="./postman/monitoring"
REPORTS_DIR="./reports/monitoring"
SCRIPTS_DIR="./postman/scripts"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[MONITORING]${NC} $1"
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

# Vérifier les prérequis
check_prerequisites() {
    log "Vérification des prérequis..."

    # Newman
    if ! command -v newman &> /dev/null; then
        error "Newman n'est pas installé. Installez avec: npm install -g newman newman-reporter-htmlextra"
        exit 1
    fi

    # Collection WellWo
    if [ ! -f "./WellWo-API.postman_collection.json" ]; then
        error "Collection WellWo introuvable"
        exit 1
    fi

    # Configuration de monitoring
    if [ ! -f "${MONITORING_DIR}/config.json" ]; then
        error "Configuration de monitoring introuvable"
        exit 1
    fi

    success "Prérequis validés ✓"
}

# Créer les répertoires nécessaires
setup_directories() {
    log "Création des répertoires de monitoring..."

    mkdir -p "$REPORTS_DIR"
    mkdir -p "$MONITORING_DIR/logs"
    mkdir -p "$MONITORING_DIR/alerts"
    mkdir -p "$MONITORING_DIR/metrics"

    success "Répertoires créés ✓"
}

# Configurer les tâches cron pour le monitoring
setup_cron_jobs() {
    log "Configuration des tâches cron..."

    # Backup crontab actuel
    crontab -l > /tmp/crontab.backup 2>/dev/null || touch /tmp/crontab.backup

    # Monitoring WellWo toutes les 5 minutes (Health Check)
    CRON_HEALTH="*/5 * * * * cd $(pwd) && $SCRIPTS_DIR/monitor-wellwo-health.sh >> $MONITORING_DIR/logs/health.log 2>&1"

    # Monitoring complet toutes les heures
    CRON_FULL="0 * * * * cd $(pwd) && $SCRIPTS_DIR/run-wellwo-monitoring.sh $ENVIRONMENT >> $MONITORING_DIR/logs/full.log 2>&1"

    # Rapport quotidien à 8h00
    CRON_DAILY="0 8 * * * cd $(pwd) && $SCRIPTS_DIR/generate-wellwo-report.sh >> $MONITORING_DIR/logs/daily.log 2>&1"

    # Ajouter les tâches au crontab
    {
        cat /tmp/crontab.backup
        echo "# WellWo API Monitoring"
        echo "$CRON_HEALTH"
        echo "$CRON_FULL"
        echo "$CRON_DAILY"
    } | crontab -

    success "Tâches cron configurées ✓"
}

# Créer le script de monitoring de santé
create_health_monitor() {
    cat > "$SCRIPTS_DIR/monitor-wellwo-health.sh" << 'EOF'
#!/bin/bash

# Monitoring rapide de la santé WellWo
set -e

TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
REPORTS_DIR="./reports/monitoring"

newman run "./WellWo-API.postman_collection.json" \
    --environment "../../../../postman/environments/production.postman_environment.json" \
    --folder "Health Check" \
    --reporters cli,json \
    --reporter-json-export "$REPORTS_DIR/health-check-$TIMESTAMP.json" \
    --timeout 3000 \
    --delay-request 0 \
    --bail \
    --silent

# Vérifier le résultat
if [ $? -eq 0 ]; then
    echo "$(date): WellWo Health Check OK" >> ./postman/monitoring/logs/health-status.log
else
    echo "$(date): WellWo Health Check FAILED" >> ./postman/monitoring/logs/health-status.log
    # Envoyer une alerte (webhook Slack, email, etc.)
    curl -X POST "${SLACK_WEBHOOK_URL:-}" -H 'Content-type: application/json' \
        --data '{"text":"🚨 WellWo API Health Check Failed"}' 2>/dev/null || true
fi
EOF

    chmod +x "$SCRIPTS_DIR/monitor-wellwo-health.sh"
    success "Script de monitoring de santé créé ✓"
}

# Créer le script de monitoring complet
create_full_monitor() {
    cat > "$SCRIPTS_DIR/run-wellwo-monitoring.sh" << 'EOF'
#!/bin/bash

# Monitoring complet WellWo
set -e

ENVIRONMENT=${1:-"production"}
TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
REPORTS_DIR="./reports/monitoring"

echo "Starting WellWo monitoring for $ENVIRONMENT at $(date)"

# Exécuter la collection complète
newman run "./WellWo-API.postman_collection.json" \
    --environment "../../../../postman/environments/$ENVIRONMENT.postman_environment.json" \
    --reporters cli,json,htmlextra \
    --reporter-json-export "$REPORTS_DIR/monitoring-$ENVIRONMENT-$TIMESTAMP.json" \
    --reporter-htmlextra-export "$REPORTS_DIR/monitoring-$ENVIRONMENT-$TIMESTAMP.html" \
    --timeout 10000 \
    --delay-request 100 \
    --color auto

# Analyser les résultats
RESULTS_FILE="$REPORTS_DIR/monitoring-$ENVIRONMENT-$TIMESTAMP.json"

if [ -f "$RESULTS_FILE" ]; then
    FAILED_TESTS=$(jq '.run.stats.tests.failed' "$RESULTS_FILE")
    TOTAL_TESTS=$(jq '.run.stats.tests.total' "$RESULTS_FILE")
    AVG_RESPONSE_TIME=$(jq '.run.timings.responseAverage' "$RESULTS_FILE")

    echo "WellWo Monitoring Results ($ENVIRONMENT):"
    echo "  Total Tests: $TOTAL_TESTS"
    echo "  Failed Tests: $FAILED_TESTS"
    echo "  Average Response Time: ${AVG_RESPONSE_TIME}ms"

    # Alertes basées sur les seuils
    if [ "$FAILED_TESTS" -gt 0 ] || [ "$AVG_RESPONSE_TIME" -gt 2000 ]; then
        echo "⚠️  Performance issues detected"

        # Notification Slack
        curl -X POST "${SLACK_WEBHOOK_URL:-}" -H 'Content-type: application/json' \
            --data "{\"text\":\"⚠️ WellWo API Monitoring Alert ($ENVIRONMENT):\\n- Failed Tests: $FAILED_TESTS/$TOTAL_TESTS\\n- Avg Response Time: ${AVG_RESPONSE_TIME}ms\"}" \
            2>/dev/null || true
    fi
fi

echo "WellWo monitoring completed at $(date)"
EOF

    chmod +x "$SCRIPTS_DIR/run-wellwo-monitoring.sh"
    success "Script de monitoring complet créé ✓"
}

# Créer le générateur de rapport
create_report_generator() {
    cat > "$SCRIPTS_DIR/generate-wellwo-report.sh" << 'EOF'
#!/bin/bash

# Générateur de rapport quotidien WellWo
set -e

REPORTS_DIR="./reports/monitoring"
TODAY=$(date '+%Y-%m-%d')
YESTERDAY=$(date -d "yesterday" '+%Y-%m-%d')

echo "Generating daily WellWo report for $TODAY"

# Collecter les métriques des dernières 24h
RECENT_REPORTS=$(find "$REPORTS_DIR" -name "*monitoring-production*" -newermt "$YESTERDAY" | sort)

if [ -z "$RECENT_REPORTS" ]; then
    echo "No monitoring reports found for the last 24 hours"
    exit 0
fi

# Analyser les rapports
TOTAL_REQUESTS=0
TOTAL_FAILURES=0
RESPONSE_TIMES=()

for report in $RECENT_REPORTS; do
    if [ -f "$report" ]; then
        REQUESTS=$(jq '.run.stats.requests.total' "$report" 2>/dev/null || echo 0)
        FAILURES=$(jq '.run.stats.requests.failed' "$report" 2>/dev/null || echo 0)
        AVG_TIME=$(jq '.run.timings.responseAverage' "$report" 2>/dev/null || echo 0)

        TOTAL_REQUESTS=$((TOTAL_REQUESTS + REQUESTS))
        TOTAL_FAILURES=$((TOTAL_FAILURES + FAILURES))
        RESPONSE_TIMES+=($AVG_TIME)
    fi
done

# Calculer la disponibilité
if [ $TOTAL_REQUESTS -gt 0 ]; then
    AVAILABILITY=$(echo "scale=2; 100 - ($TOTAL_FAILURES * 100 / $TOTAL_REQUESTS)" | bc)
else
    AVAILABILITY=0
fi

# Générer le rapport
REPORT_FILE="$REPORTS_DIR/daily-report-$TODAY.txt"
cat > "$REPORT_FILE" << EOL
WellWo API Daily Report - $TODAY
=====================================

Summary:
- Total Requests: $TOTAL_REQUESTS
- Failed Requests: $TOTAL_FAILURES
- Availability: ${AVAILABILITY}%
- Reports Analyzed: $(echo "$RECENT_REPORTS" | wc -l)

Performance:
$(printf '%s\n' "${RESPONSE_TIMES[@]}" | awk '{sum+=$1; count++} END {printf "- Average Response Time: %.0fms\n", sum/count}')

Status: $([ "$TOTAL_FAILURES" -eq 0 ] && echo "✅ Healthy" || echo "⚠️  Issues Detected")

Generated at: $(date)
EOL

echo "Daily report generated: $REPORT_FILE"

# Envoyer le rapport par Slack si configuré
if [ -n "${SLACK_WEBHOOK_URL:-}" ]; then
    REPORT_CONTENT=$(cat "$REPORT_FILE")
    curl -X POST "$SLACK_WEBHOOK_URL" -H 'Content-type: application/json' \
        --data "{\"text\":\"📊 WellWo API Daily Report\\n\`\`\`\\n$REPORT_CONTENT\\n\`\`\`\"}" \
        2>/dev/null || true
fi
EOF

    chmod +x "$SCRIPTS_DIR/generate-wellwo-report.sh"
    success "Générateur de rapport créé ✓"
}

# Configuration des webhooks et alertes
setup_alerting() {
    log "Configuration des alertes..."

    # Créer le fichier de configuration des alertes
    cat > "$MONITORING_DIR/alerts-config.json" << EOF
{
  "webhooks": {
    "slack": {
      "url": "\${SLACK_WEBHOOK_URL}",
      "channel": "#api-monitoring",
      "enabled": true
    },
    "discord": {
      "url": "\${DISCORD_WEBHOOK_URL}",
      "enabled": false
    }
  },
  "email": {
    "smtp_server": "\${SMTP_SERVER}",
    "smtp_port": 587,
    "username": "\${SMTP_USERNAME}",
    "password": "\${SMTP_PASSWORD}",
    "recipients": ["devops@up-engage.com"],
    "enabled": false
  },
  "thresholds": {
    "response_time_ms": 2000,
    "error_rate_percent": 5,
    "availability_percent": 99.5
  }
}
EOF

    success "Configuration des alertes créée ✓"
}

# Fonction principale
main() {
    log "🚀 Configuration du monitoring WellWo pour l'environnement: $ENVIRONMENT"

    check_prerequisites
    setup_directories
    create_health_monitor
    create_full_monitor
    create_report_generator
    setup_alerting
    setup_cron_jobs

    success "✅ Configuration du monitoring WellWo terminée!"

    echo ""
    log "📋 Étapes suivantes:"
    echo "1. Configurez les variables d'environnement (SLACK_WEBHOOK_URL, etc.)"
    echo "2. Testez le monitoring: $SCRIPTS_DIR/monitor-wellwo-health.sh"
    echo "3. Vérifiez les logs: tail -f $MONITORING_DIR/logs/health.log"
    echo "4. Consultez les rapports: ls -la $REPORTS_DIR/"

    warning "⚠️  N'oubliez pas de configurer les webhooks dans votre environnement!"
}

# Exécution
main "$@"