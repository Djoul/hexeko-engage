#!/bin/bash

# Script d'Audit Automatique - Méthodologie Backend API
# Usage: ./workflows/scripts/audit-feature.sh [FEATURE_ID] [TYPE] [OPTIONS]

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
AUDIT_REPORTS_DIR="${PROJECT_ROOT}/audit-reports"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
log() {
    echo -e "${BLUE}[AUDIT]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

show_help() {
    cat << EOF
🔍 Script d'Audit Automatique - Méthodologie Backend API

USAGE:
    ./workflows/scripts/audit-feature.sh [FEATURE_ID] [TYPE] [OPTIONS]

ARGUMENTS:
    FEATURE_ID    Identifiant de la fonctionnalité (UE-XXX, namespace, commit)
    TYPE          Type d'audit: full|architecture|tests|performance|documentation

OPTIONS:
    --jira        Récupérer depuis Jira (par défaut si UE-XXX)
    --namespace   Auditer un namespace complet (ex: app/Integrations/Vouchers/Amilon)
    --commit      Auditer un commit spécifique
    --pr          Auditer une Pull Request
    --output-dir  Dossier de sortie (défaut: ./audit-reports)
    --format      Format de sortie: html|markdown|json (défaut: markdown)
    --no-tests    Skip les tests automatisés
    --help, -h    Afficher cette aide

EXEMPLES:
    # Audit complet d'une story Jira
    ./workflows/scripts/audit-feature.sh UE-268 full

    # Audit architecture d'un module
    ./workflows/scripts/audit-feature.sh app/Integrations/Vouchers/Amilon architecture --namespace

    # Audit performance d'une API
    ./workflows/scripts/audit-feature.sh UE-301 performance

    # Audit d'un commit
    ./workflows/scripts/audit-feature.sh abc123 full --commit

    # Audit interactif
    ./workflows/scripts/audit-feature.sh --interactive

EOF
}

# Parse arguments
FEATURE_ID=""
AUDIT_TYPE="full"
SOURCE_TYPE=""
OUTPUT_DIR="${AUDIT_REPORTS_DIR}"
OUTPUT_FORMAT="markdown"
RUN_TESTS=true
INTERACTIVE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            show_help
            exit 0
            ;;
        --jira)
            SOURCE_TYPE="jira"
            shift
            ;;
        --namespace)
            SOURCE_TYPE="namespace"
            shift
            ;;
        --commit)
            SOURCE_TYPE="commit"
            shift
            ;;
        --pr)
            SOURCE_TYPE="pr"
            shift
            ;;
        --output-dir)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --format)
            OUTPUT_FORMAT="$2"
            shift 2
            ;;
        --no-tests)
            RUN_TESTS=false
            shift
            ;;
        --interactive)
            INTERACTIVE=true
            shift
            ;;
        -*)
            error "Option inconnue: $1"
            show_help
            exit 1
            ;;
        *)
            if [[ -z "$FEATURE_ID" ]]; then
                FEATURE_ID="$1"
            elif [[ -z "$AUDIT_TYPE" ]]; then
                AUDIT_TYPE="$1"
            fi
            shift
            ;;
    esac
done

# Mode interactif
if [[ "$INTERACTIVE" == "true" ]]; then
    log "🎯 Mode interactif activé"

    echo "Quel type d'audit souhaitez-vous effectuer ?"
    echo "1) Story/Epic Jira (UE-XXX)"
    echo "2) Module/Namespace"
    echo "3) Commit spécifique"
    echo "4) Pull Request"
    read -p "Choix (1-4): " choice

    case $choice in
        1)
            SOURCE_TYPE="jira"
            read -p "ID Jira (ex: UE-268): " FEATURE_ID
            ;;
        2)
            SOURCE_TYPE="namespace"
            read -p "Namespace (ex: app/Integrations/Vouchers/Amilon): " FEATURE_ID
            ;;
        3)
            SOURCE_TYPE="commit"
            read -p "Commit hash: " FEATURE_ID
            ;;
        4)
            SOURCE_TYPE="pr"
            read -p "PR number: " FEATURE_ID
            ;;
        *)
            error "Choix invalide"
            exit 1
            ;;
    esac

    echo "Type d'audit :"
    echo "1) Complet (full)"
    echo "2) Architecture uniquement"
    echo "3) Tests et coverage"
    echo "4) Performance"
    echo "5) Documentation"
    read -p "Choix (1-5): " audit_choice

    case $audit_choice in
        1) AUDIT_TYPE="full" ;;
        2) AUDIT_TYPE="architecture" ;;
        3) AUDIT_TYPE="tests" ;;
        4) AUDIT_TYPE="performance" ;;
        5) AUDIT_TYPE="documentation" ;;
        *) AUDIT_TYPE="full" ;;
    esac
fi

# Validation des arguments
if [[ -z "$FEATURE_ID" ]]; then
    error "Feature ID requis"
    show_help
    exit 1
fi

# Auto-détection du type de source
if [[ -z "$SOURCE_TYPE" ]]; then
    if [[ "$FEATURE_ID" =~ ^UE-[0-9]+$ ]]; then
        SOURCE_TYPE="jira"
        log "Auto-détection: Source Jira"
    elif [[ "$FEATURE_ID" =~ ^[a-f0-9]{6,40}$ ]]; then
        SOURCE_TYPE="commit"
        log "Auto-détection: Commit hash"
    elif [[ "$FEATURE_ID" =~ ^[0-9]+$ ]]; then
        SOURCE_TYPE="pr"
        log "Auto-détection: Pull Request"
    elif [[ "$FEATURE_ID" == *"/"* ]]; then
        SOURCE_TYPE="namespace"
        log "Auto-détection: Namespace"
    else
        warning "Type de source non détecté, utilisation de 'jira' par défaut"
        SOURCE_TYPE="jira"
    fi
fi

# Création du dossier de rapport
mkdir -p "$OUTPUT_DIR"
REPORT_FILE="${OUTPUT_DIR}/audit-${FEATURE_ID//\//-}-${AUDIT_TYPE}-${TIMESTAMP}.${OUTPUT_FORMAT}"

log "🔍 Démarrage audit de ${FEATURE_ID}"
log "📁 Type: ${AUDIT_TYPE} | Source: ${SOURCE_TYPE}"
log "📄 Rapport: ${REPORT_FILE}"

# Initialisation du rapport
case $OUTPUT_FORMAT in
    "markdown")
        cat > "$REPORT_FILE" << EOF
# 📋 Rapport d'Audit : ${FEATURE_ID}

**Date**: $(date)
**Type**: ${AUDIT_TYPE}
**Source**: ${SOURCE_TYPE}
**Auditeur**: Méthodologie Backend API (Automatisé)

---

## 📊 Résumé Exécutif

**Status**: 🔄 En cours...

EOF
        ;;
    "html")
        cat > "$REPORT_FILE" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>Audit Report: ${FEATURE_ID}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .score { font-size: 2em; font-weight: bold; }
        .excellent { color: #28a745; }
        .satisfactory { color: #ffc107; }
        .needs-improvement { color: #fd7e14; }
        .non-compliant { color: #dc3545; }
    </style>
</head>
<body>
    <h1>📋 Rapport d'Audit : ${FEATURE_ID}</h1>
    <p><strong>Date:</strong> $(date)</p>
    <p><strong>Type:</strong> ${AUDIT_TYPE}</p>
    <p><strong>Source:</strong> ${SOURCE_TYPE}</p>
    <div id="content">
        <p>🔄 Génération en cours...</p>
    </div>
</body>
</html>
EOF
        ;;
    "json")
        cat > "$REPORT_FILE" << EOF
{
    "audit": {
        "feature_id": "${FEATURE_ID}",
        "type": "${AUDIT_TYPE}",
        "source": "${SOURCE_TYPE}",
        "date": "$(date -Iseconds)",
        "status": "in_progress",
        "scores": {},
        "findings": [],
        "recommendations": []
    }
}
EOF
        ;;
esac

# Phase 1: Tests automatisés (si activés)
if [[ "$RUN_TESTS" == "true" ]]; then
    log "🧪 Phase 1: Exécution des tests automatisés"

    cd "$PROJECT_ROOT"

    # Tests unitaires et fonctionnels
    if make test > /dev/null 2>&1; then
        success "Tests passants"
        TESTS_STATUS="✅ PASS"
    else
        error "Tests échouent"
        TESTS_STATUS="❌ FAIL"
    fi

    # Quality check
    if make quality-check > /dev/null 2>&1; then
        success "Quality check passant"
        QUALITY_STATUS="✅ PASS"
    else
        error "Quality check échoue"
        QUALITY_STATUS="❌ FAIL"
    fi

    # Coverage
    if make coverage > /dev/null 2>&1; then
        COVERAGE_OUTPUT=$(make coverage 2>/dev/null | grep -oE '[0-9]+\.[0-9]+%' | tail -1)
        if [[ -n "$COVERAGE_OUTPUT" ]]; then
            COVERAGE_VALUE=${COVERAGE_OUTPUT%\%}
            if (( $(echo "$COVERAGE_VALUE > 80" | bc -l) )); then
                success "Coverage: $COVERAGE_OUTPUT"
                COVERAGE_STATUS="✅ $COVERAGE_OUTPUT"
            else
                warning "Coverage insuffisant: $COVERAGE_OUTPUT"
                COVERAGE_STATUS="⚠️ $COVERAGE_OUTPUT"
            fi
        else
            warning "Coverage non déterminé"
            COVERAGE_STATUS="❓ N/A"
        fi
    else
        warning "Coverage non disponible"
        COVERAGE_STATUS="❓ N/A"
    fi

    # Ajout au rapport
    cat >> "$REPORT_FILE" << EOF

## 🧪 Tests Automatisés

| Aspect | Status |
|--------|--------|
| Tests | ${TESTS_STATUS} |
| Quality Check | ${QUALITY_STATUS} |
| Coverage | ${COVERAGE_STATUS} |

EOF
fi

# Phase 2: Analyse spécifique selon le type d'audit
log "🔍 Phase 2: Analyse ${AUDIT_TYPE}"

case $AUDIT_TYPE in
    "full")
        log "Audit complet en cours..."
        # Ici on appellerait Claude Code via MCP ou API
        cat >> "$REPORT_FILE" << EOF

## 📊 Audit Complet

### Architecture (/10)
- 🔄 Analyse en cours...

### Qualité Code (/10)
- 🔄 Analyse en cours...

### Tests (/10)
- 🔄 Analyse en cours...

### Documentation (/10)
- 🔄 Analyse en cours...

### Performance (/10)
- 🔄 Analyse en cours...

### Production (/10)
- 🔄 Analyse en cours...

EOF
        ;;
    "architecture")
        log "Audit architecture en cours..."
        cat >> "$REPORT_FILE" << EOF

## 🏗️ Audit Architecture

### Service/Action Pattern
- 🔄 Vérification en cours...

### DTOs vs Arrays
- 🔄 Vérification en cours...

### Dependency Injection
- 🔄 Vérification en cours...

EOF
        ;;
    "tests")
        log "Audit tests en cours..."
        cat >> "$REPORT_FILE" << EOF

## 🧪 Audit Tests Détaillé

### Coverage par Fichier
- 🔄 Analyse en cours...

### Qualité des Assertions
- 🔄 Analyse en cours...

### Tests Manqués
- 🔄 Identification en cours...

EOF
        ;;
    "performance")
        log "Audit performance en cours..."
        cat >> "$REPORT_FILE" << EOF

## ⚡ Audit Performance

### Response Times
- 🔄 Mesure en cours...

### Database Queries
- 🔄 Analyse N+1 en cours...

### Cache Strategy
- 🔄 Évaluation en cours...

EOF
        ;;
    "documentation")
        log "Audit documentation en cours..."
        cat >> "$REPORT_FILE" << EOF

## 📚 Audit Documentation

### API Documentation
- 🔄 Vérification OpenAPI...

### Guides Utilisateur
- 🔄 Vérification présence...

### Code Documentation
- 🔄 Analyse PHPDoc...

EOF
        ;;
esac

# Phase 3: Génération du prompt Claude Code
log "🤖 Phase 3: Génération prompt Claude Code"

CLAUDE_PROMPT_FILE="${OUTPUT_DIR}/claude-audit-prompt-${FEATURE_ID//\//-}-${TIMESTAMP}.md"

cat > "$CLAUDE_PROMPT_FILE" << EOF
# 🔍 Prompt d'Audit Automatique

Effectue un audit ${AUDIT_TYPE} de **${FEATURE_ID}** :

## Context
- **Source**: ${SOURCE_TYPE}
- **Type d'audit**: ${AUDIT_TYPE}
- **Rapport automatique**: ${REPORT_FILE}

## Instructions

$(case $SOURCE_TYPE in
    "jira")
        echo "1. Récupère les détails de ${FEATURE_ID} depuis Jira"
        echo "2. Liste tous les composants développés pour cette story"
        ;;
    "namespace")
        echo "1. Analyse tous les fichiers dans ${FEATURE_ID}/"
        echo "2. Mappe l'architecture du module"
        ;;
    "commit")
        echo "1. Analyse les changements du commit ${FEATURE_ID}"
        echo "2. Évalue l'impact des modifications"
        ;;
    "pr")
        echo "1. Analyse la Pull Request #${FEATURE_ID}"
        echo "2. Évalue les changements proposés"
        ;;
esac)

3. Évalue selon la méthodologie backend-api-methodology.md
4. Utilise la checklist universelle comme référence
5. Génère un scoring détaillé pour chaque catégorie
6. Identifie les gaps avec priorité (critique/important/mineur)
7. Propose un plan d'amélioration concret

## Scoring Attendu

$(case $AUDIT_TYPE in
    "full")
        cat << SCORING
- Architecture (/10)
- Qualité Code (/10)
- Tests (/10)
- Documentation (/10)
- Performance (/10)
- Production (/10)

**Score global pondéré** : Architecture(25%) + Qualité(20%) + Tests(25%) + Documentation(15%) + Performance(10%) + Production(5%)
SCORING
        ;;
    "architecture")
        echo "- Score Architecture détaillé (/10) avec justifications"
        ;;
    "tests")
        echo "- Score Tests détaillé (/10) avec recommandations"
        ;;
    "performance")
        echo "- Score Performance détaillé (/10) avec optimisations"
        ;;
    "documentation")
        echo "- Score Documentation détaillé (/10) avec gaps"
        ;;
esac)

## Format de Sortie
Remplace le contenu de \`${REPORT_FILE}\` par ton analyse complète.

EOF

success "Prompt Claude Code généré: ${CLAUDE_PROMPT_FILE}"

# Phase 4: Instructions finales
log "📋 Phase 4: Instructions finales"

cat >> "$REPORT_FILE" << EOF

---

## 🚀 Prochaines Étapes

1. **Exécuter l'audit complet** :
   \`\`\`
   Copie le contenu de ${CLAUDE_PROMPT_FILE} dans Claude Code
   \`\`\`

2. **Analyser les résultats** :
   - Score global obtenu
   - Gaps critiques identifiés
   - Plan d'amélioration proposé

3. **Actions correctives** :
   - Prioriser selon criticité
   - Planifier les améliorations
   - Re-auditer après corrections

---

**Audit généré automatiquement le $(date)**
**Script**: workflows/scripts/audit-feature.sh
EOF

# Affichage final
echo
success "🎯 Audit préparé avec succès !"
echo
echo "📄 Rapport: ${REPORT_FILE}"
echo "🤖 Prompt Claude: ${CLAUDE_PROMPT_FILE}"
echo
echo "➡️  Prochaines étapes :"
echo "   1. Copie le prompt dans Claude Code"
echo "   2. Claude analysera ${FEATURE_ID} selon la méthodologie"
echo "   3. Le rapport sera mis à jour avec le scoring détaillé"
echo
echo "🔗 Commandes utiles :"
echo "   cat \"${CLAUDE_PROMPT_FILE}\" | pbcopy  # Copier le prompt"
echo "   open \"${REPORT_FILE}\"                # Ouvrir le rapport"
echo

# Optionnel: ouvrir automatiquement les fichiers
if command -v code &> /dev/null; then
    warning "Ouvrir les fichiers dans VS Code ? (y/n)"
    read -r -n 1 response
    if [[ $response =~ ^[Yy]$ ]]; then
        code "$CLAUDE_PROMPT_FILE" "$REPORT_FILE"
    fi
fi

exit 0