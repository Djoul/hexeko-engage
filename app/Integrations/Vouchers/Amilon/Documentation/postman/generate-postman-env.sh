#!/bin/bash

# Script to generate Postman environment files for different environments
# Usage: ./generate-postman-env.sh [dev|staging|prod|all]

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${GREEN}🔧 Amilon Postman Environment Generator${NC}"
echo ""

# Default to all if no argument provided
ENVIRONMENT=${1:-all}

# Find the project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
ENV_FILE="${PROJECT_ROOT}/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}❌ Error: .env file not found at ${ENV_FILE}${NC}"
    exit 1
fi

echo -e "${YELLOW}📁 Found .env file at: ${ENV_FILE}${NC}"
echo ""

# Function to generate environment file
generate_env_file() {
    local ENV_NAME=$1
    local ENV_DISPLAY_NAME=$2
    local BASE_URL=$3
    local TOKEN_URL=$4
    local CLIENT_ID=$5
    local CLIENT_SECRET=$6
    local USERNAME=$7
    local PASSWORD=$8
    local CONTRACT_ID=$9

    local OUTPUT_FILE="$(dirname "${BASH_SOURCE[0]}")/postman-environment-${ENV_NAME}.json"

    cat > "$OUTPUT_FILE" << EOF
{
  "id": "amilon-environment-${ENV_NAME}",
  "name": "Amilon API - ${ENV_DISPLAY_NAME}",
  "values": [
    {
      "key": "base_url",
      "value": "${BASE_URL}",
      "type": "default",
      "enabled": true
    },
    {
      "key": "token_url",
      "value": "${TOKEN_URL}",
      "type": "default",
      "enabled": true
    },
    {
      "key": "api_version",
      "value": "v1",
      "type": "default",
      "enabled": true
    },
    {
      "key": "client_id",
      "value": "${CLIENT_ID}",
      "type": "secret",
      "enabled": true
    },
    {
      "key": "client_secret",
      "value": "${CLIENT_SECRET}",
      "type": "secret",
      "enabled": true
    },
    {
      "key": "username",
      "value": "${USERNAME}",
      "type": "secret",
      "enabled": true
    },
    {
      "key": "password",
      "value": "${PASSWORD}",
      "type": "secret",
      "enabled": true
    },
    {
      "key": "contract_id",
      "value": "${CONTRACT_ID}",
      "type": "default",
      "enabled": true
    },
    {
      "key": "access_token",
      "value": "",
      "type": "secret",
      "enabled": true
    },
    {
      "key": "culture",
      "value": "pt-PT",
      "type": "default",
      "enabled": true
    },
    {
      "key": "last_external_order_id",
      "value": "",
      "type": "default",
      "enabled": true
    },
    {
      "key": "last_order_id",
      "value": "",
      "type": "default",
      "enabled": true
    },
    {
      "key": "environment",
      "value": "${ENV_NAME}",
      "type": "default",
      "enabled": true
    }
  ],
  "_postman_variable_scope": "environment"
}
EOF

    echo -e "${GREEN}✅ Generated: ${OUTPUT_FILE}${NC}"
}

# Extract common values from .env (fallback to these if specific env vars not found)
AMILON_API_URL=$(grep "^AMILON_API_URL=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
AMILON_TOKEN_URL=$(grep "^AMILON_TOKEN_URL=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
AMILON_CLIENT_ID=$(grep "^AMILON_CLIENT_ID=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
AMILON_CLIENT_SECRET=$(grep "^AMILON_CLIENT_SECRET=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
AMILON_USERNAME=$(grep "^AMILON_USERNAME=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
AMILON_PASSWORD=$(grep "^AMILON_PASSWORD=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")
AMILON_CONTRACT_ID=$(grep "^AMILON_CONTRACT_ID=" "$ENV_FILE" | cut -d '=' -f2- | tr -d '"' | tr -d "'")

# Try to find environment-specific variables
AMILON_API_URL_STAGING=$(grep "^AMILON_API_URL_STAGING=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_TOKEN_URL_STAGING=$(grep "^AMILON_TOKEN_URL_STAGING=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_CLIENT_ID_STAGING=$(grep "^AMILON_CLIENT_ID_STAGING=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_CLIENT_SECRET_STAGING=$(grep "^AMILON_CLIENT_SECRET_STAGING=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_USERNAME_STAGING=$(grep "^AMILON_USERNAME_STAGING=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_PASSWORD_STAGING=$(grep "^AMILON_PASSWORD_STAGING=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_CONTRACT_ID_STAGING=$(grep "^AMILON_CONTRACT_ID_STAGING=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")

AMILON_API_URL_PROD=$(grep "^AMILON_API_URL_PROD=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_TOKEN_URL_PROD=$(grep "^AMILON_TOKEN_URL_PROD=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_CLIENT_ID_PROD=$(grep "^AMILON_CLIENT_ID_PROD=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_CLIENT_SECRET_PROD=$(grep "^AMILON_CLIENT_SECRET_PROD=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_USERNAME_PROD=$(grep "^AMILON_USERNAME_PROD=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_PASSWORD_PROD=$(grep "^AMILON_PASSWORD_PROD=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")
AMILON_CONTRACT_ID_PROD=$(grep "^AMILON_CONTRACT_ID_PROD=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" || echo "")

# Generate files based on argument
case $ENVIRONMENT in
    dev|development)
        echo -e "${BLUE}📝 Generating DEVELOPMENT environment${NC}"
        generate_env_file "dev" "Development" \
            "${AMILON_API_URL}" \
            "${AMILON_TOKEN_URL}" \
            "${AMILON_CLIENT_ID}" \
            "${AMILON_CLIENT_SECRET}" \
            "${AMILON_USERNAME}" \
            "${AMILON_PASSWORD}" \
            "${AMILON_CONTRACT_ID}"
        ;;
    staging)
        echo -e "${BLUE}📝 Generating STAGING environment${NC}"
        # Use staging-specific vars if available, otherwise fallback to dev
        generate_env_file "staging" "Staging" \
            "${AMILON_API_URL_STAGING:-$AMILON_API_URL}" \
            "${AMILON_TOKEN_URL_STAGING:-$AMILON_TOKEN_URL}" \
            "${AMILON_CLIENT_ID_STAGING:-$AMILON_CLIENT_ID}" \
            "${AMILON_CLIENT_SECRET_STAGING:-$AMILON_CLIENT_SECRET}" \
            "${AMILON_USERNAME_STAGING:-$AMILON_USERNAME}" \
            "${AMILON_PASSWORD_STAGING:-$AMILON_PASSWORD}" \
            "${AMILON_CONTRACT_ID_STAGING:-$AMILON_CONTRACT_ID}"
        ;;
    prod|production)
        echo -e "${BLUE}📝 Generating PRODUCTION environment${NC}"
        # Use prod-specific vars if available, otherwise fallback to dev
        generate_env_file "prod" "Production" \
            "${AMILON_API_URL_PROD:-$AMILON_API_URL}" \
            "${AMILON_TOKEN_URL_PROD:-$AMILON_TOKEN_URL}" \
            "${AMILON_CLIENT_ID_PROD:-$AMILON_CLIENT_ID}" \
            "${AMILON_CLIENT_SECRET_PROD:-$AMILON_CLIENT_SECRET}" \
            "${AMILON_USERNAME_PROD:-$AMILON_USERNAME}" \
            "${AMILON_PASSWORD_PROD:-$AMILON_PASSWORD}" \
            "${AMILON_CONTRACT_ID_PROD:-$AMILON_CONTRACT_ID}"
        ;;
    all)
        echo -e "${BLUE}📝 Generating ALL environments${NC}"
        echo ""

        # Development
        echo -e "${YELLOW}Creating Development environment...${NC}"
        generate_env_file "dev" "Development" \
            "${AMILON_API_URL}" \
            "${AMILON_TOKEN_URL}" \
            "${AMILON_CLIENT_ID}" \
            "${AMILON_CLIENT_SECRET}" \
            "${AMILON_USERNAME}" \
            "${AMILON_PASSWORD}" \
            "${AMILON_CONTRACT_ID}"

        echo ""

        # Staging
        echo -e "${YELLOW}Creating Staging environment...${NC}"
        generate_env_file "staging" "Staging" \
            "${AMILON_API_URL_STAGING:-$AMILON_API_URL}" \
            "${AMILON_TOKEN_URL_STAGING:-$AMILON_TOKEN_URL}" \
            "${AMILON_CLIENT_ID_STAGING:-$AMILON_CLIENT_ID}" \
            "${AMILON_CLIENT_SECRET_STAGING:-$AMILON_CLIENT_SECRET}" \
            "${AMILON_USERNAME_STAGING:-$AMILON_USERNAME}" \
            "${AMILON_PASSWORD_STAGING:-$AMILON_PASSWORD}" \
            "${AMILON_CONTRACT_ID_STAGING:-$AMILON_CONTRACT_ID}"

        if [ -z "$AMILON_API_URL_STAGING" ]; then
            echo -e "${YELLOW}⚠️  No staging-specific variables found, using dev credentials${NC}"
        fi

        echo ""

        # Production
        echo -e "${YELLOW}Creating Production environment...${NC}"
        generate_env_file "prod" "Production" \
            "${AMILON_API_URL_PROD:-$AMILON_API_URL}" \
            "${AMILON_TOKEN_URL_PROD:-$AMILON_TOKEN_URL}" \
            "${AMILON_CLIENT_ID_PROD:-$AMILON_CLIENT_ID}" \
            "${AMILON_CLIENT_SECRET_PROD:-$AMILON_CLIENT_SECRET}" \
            "${AMILON_USERNAME_PROD:-$AMILON_USERNAME}" \
            "${AMILON_PASSWORD_PROD:-$AMILON_PASSWORD}" \
            "${AMILON_CONTRACT_ID_PROD:-$AMILON_CONTRACT_ID}"

        if [ -z "$AMILON_API_URL_PROD" ]; then
            echo -e "${YELLOW}⚠️  No production-specific variables found, using dev credentials${NC}"
        fi
        ;;
    *)
        echo -e "${RED}❌ Invalid environment: $ENVIRONMENT${NC}"
        echo ""
        echo "Usage: $0 [dev|staging|prod|all]"
        echo ""
        echo "Examples:"
        echo "  $0           # Generate all environments"
        echo "  $0 dev       # Generate only development"
        echo "  $0 staging   # Generate only staging"
        echo "  $0 prod      # Generate only production"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}🎉 Environment files generated successfully!${NC}"
echo ""
echo -e "${YELLOW}📝 Next steps:${NC}"
echo "   1. Open Postman"
echo "   2. Click 'Import' button"
echo "   3. Import all generated postman-environment-*.json files"
echo "   4. Import postman-collection.json"
echo "   5. Select the desired environment from the dropdown (top-right)"
echo "   6. Run 'Get Access Token' request"
echo "   7. Start testing!"
echo ""
echo -e "${BLUE}💡 Tip: Switch between environments easily using Postman's environment selector${NC}"
echo ""
echo -e "${YELLOW}⚠️  Note: To use separate credentials for staging/prod, add these to your .env:${NC}"
echo "   AMILON_API_URL_STAGING=https://staging-api.amilon.com"
echo "   AMILON_CLIENT_ID_STAGING=your_staging_client_id"
echo "   # ... (other staging variables)"
echo ""
echo "   AMILON_API_URL_PROD=https://api.amilon.com"
echo "   AMILON_CLIENT_ID_PROD=your_prod_client_id"
echo "   # ... (other production variables)"