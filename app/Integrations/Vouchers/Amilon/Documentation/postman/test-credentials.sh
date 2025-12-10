#!/bin/bash

# Script to test Amilon API credentials
# This will help diagnose authentication issues

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔐 Amilon Credentials Tester${NC}"
echo ""

# Get project root
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../.." && pwd)"
cd "$PROJECT_ROOT"

# Load credentials from .env
if [ ! -f ".env" ]; then
    echo -e "${RED}❌ .env file not found${NC}"
    exit 1
fi

source <(grep -E "^AMILON_" .env | sed 's/^/export /')

echo -e "${YELLOW}📋 Current Configuration:${NC}"
echo "Token URL: ${AMILON_TOKEN_URL}"
echo "Client ID: ${AMILON_CLIENT_ID}"
echo "Username: ${AMILON_USERNAME}"
echo "Password length: ${#AMILON_PASSWORD} characters"
echo ""

# Remove quotes from password if present
AMILON_PASSWORD="${AMILON_PASSWORD%\"}"
AMILON_PASSWORD="${AMILON_PASSWORD#\"}"

echo -e "${BLUE}🧪 Testing authentication...${NC}"
echo ""

# Test 1: Direct cURL call
echo -e "${YELLOW}Test 1: Direct API Call${NC}"

RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${AMILON_TOKEN_URL}" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password" \
  -d "client_id=${AMILON_CLIENT_ID}" \
  -d "client_secret=${AMILON_CLIENT_SECRET}" \
  -d "username=${AMILON_USERNAME}" \
  -d "password=${AMILON_PASSWORD}")

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$RESPONSE" | sed '$d')

echo "HTTP Status: ${HTTP_CODE}"

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✅ Authentication SUCCESSFUL${NC}"
    echo ""
    echo "Access Token (first 50 chars):"
    echo "$RESPONSE_BODY" | jq -r '.access_token' | cut -c1-50
    echo ""
    echo "Token expires in:"
    echo "$RESPONSE_BODY" | jq -r '.expires_in'
    echo " seconds"
    exit 0
else
    echo -e "${RED}❌ Authentication FAILED${NC}"
    echo ""
    echo "Error Response:"
    echo "$RESPONSE_BODY" | jq . 2>/dev/null || echo "$RESPONSE_BODY"
    echo ""
fi

# Diagnose common issues
echo -e "${YELLOW}🔍 Diagnostic Information:${NC}"
echo ""

# Check for whitespace
if [[ "$AMILON_USERNAME" != "${AMILON_USERNAME// /}" ]]; then
    echo -e "${YELLOW}⚠️  Username contains spaces${NC}"
fi

if [[ "$AMILON_PASSWORD" != "${AMILON_PASSWORD// /}" ]]; then
    echo -e "${YELLOW}⚠️  Password contains spaces${NC}"
    # Check if space is at the beginning
    if [[ "$AMILON_PASSWORD" =~ ^[[:space:]] ]]; then
        echo -e "${RED}❌ Password starts with a space - this may be incorrect${NC}"
    fi
fi

# Check for special characters
if [[ "$AMILON_PASSWORD" =~ [\$#@\&] ]]; then
    echo -e "${YELLOW}⚠️  Password contains special characters: \$ # @ &${NC}"
    echo "   Make sure they are not escaped in .env"
fi

echo ""
echo -e "${BLUE}💡 Possible Issues:${NC}"
echo ""
echo "1. Credentials may be incorrect or expired"
echo "   → Contact Amilon support to verify credentials"
echo ""
echo "2. Password has leading/trailing spaces"
echo "   → Check .env file for extra spaces"
echo ""
echo "3. Special characters not properly handled"
echo "   → Ensure password is in quotes in .env: AMILON_PASSWORD=\"your_pass\""
echo ""
echo "4. Account may be locked or suspended"
echo "   → Contact Amilon support"
echo ""
echo "5. Wrong environment (dev/staging/prod)"
echo "   → Verify you're using the correct credentials"
echo ""

echo -e "${YELLOW}📝 To test with different credentials:${NC}"
echo ""
echo "1. Edit .env file"
echo "2. Run this script again: ./test-credentials.sh"
echo ""

exit 1