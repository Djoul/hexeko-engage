# Amilon API - Postman Testing Guide

Quick guide to test the Amilon third-party API directly using Postman.

## 🚀 Quick Start (3 Steps)

### Step 1: Generate Environments

```bash
cd app/Integrations/Vouchers/Amilon
./generate-postman-env.sh all
```

**Output:**
```
✅ Generated: ./postman-environment-dev.json
✅ Generated: ./postman-environment-staging.json
✅ Generated: ./postman-environment-prod.json
```

### Step 2: Import to Postman

1. Open Postman
2. Click **Import**
3. Select these files:
   - `postman-collection.json`
   - `postman-environment-dev.json`
   - `postman-environment-staging.json`
   - `postman-environment-prod.json`
4. Click **Import**

### Step 3: Test

1. Select environment (dropdown top-right): **Amilon API - Development**
2. Run: **Authentication** > **Get Access Token** → Send
3. Test any endpoint!

## 📋 Available Endpoints

### 1. Authentication (OAuth2)
```
POST /connect/token
→ Get access token (5 min TTL)
```

### 2. Contracts
```
GET /b2bwebapi/v1/contracts/{contract_id}
→ Contract details
```

### 3. Products
```
GET /b2bwebapi/v1/contracts/{contract_id}/{culture}/products/complete
→ Complete product list (10 cultures supported)
```

### 4. Merchants
```
GET /b2bwebapi/v1/contracts/{contract_id}/{culture}/retailers
→ Available merchants
```

### 5. Categories
```
GET /b2bwebapi/v1/retailers/categories
→ Merchant categories (Italian only, no culture parameter)
```

### 6. Orders - Create
```
POST /b2bwebapi/v1/Orders/create/{contract_id}
→ Create voucher order
```

### 7. Orders - Get Info
```
GET /b2bwebapi/v1/Orders/{external_order_id}/complete
→ Retrieve order status and voucher codes
```

## 🌍 Supported Cultures

| Code | Country | Language |
|------|---------|----------|
| `pt-PT` | Portugal 🇵🇹 | Portuguese |
| `fr-FR` | France 🇫🇷 | French |
| `es-ES` | Spain 🇪🇸 | Spanish |
| `it-IT` | Italy 🇮🇹 | Italian |
| `de-DE` | Germany 🇩🇪 | German |
| `en-GB` | United Kingdom 🇬🇧 | English |
| `nl-NL` | Netherlands 🇳🇱 | Dutch |
| `da-DK` | Denmark 🇩🇰 | Danish |
| `nn-NO` | Norway 🇳🇴 | Norwegian |
| `pl-PL` | Poland 🇵🇱 | Polish |

**Change culture:** Edit the `culture` variable in your Postman environment.

## 🧪 Testing Workflows

### Scenario 1: Quick Test (2 min)
```
1. Select "Development" environment
2. Run: Get Access Token
3. Run: Get Products Complete
→ Verify it works
```

### Scenario 2: Complete Flow (10 min)
```
1. Run: Get Access Token
2. Run: Get Categories
3. Run: Get Retailers
4. Run: Get Products Complete (note a product ID)
5. Run: Create Order (use the product ID)
6. Run: Get Order Info (check status)
7. Wait 30 seconds
8. Run: Get Order Info (retrieve voucher codes)
```

### Scenario 3: Multi-Culture Test (15 min)
```
1. Run: Get Access Token
2. Set culture = "pt-PT" → Get Products
3. Set culture = "fr-FR" → Get Products
4. Set culture = "es-ES" → Get Products
5. Compare results
```

### Scenario 4: Multi-Environment Test
```
1. Select "Staging" environment
2. Run: Get Access Token
3. Test endpoints
4. Select "Production" environment (CAREFUL!)
5. Test read-only endpoints only
```

## 🔧 Multi-Environment Setup

### Option 1: Same Credentials Everywhere (Default)

Nothing to do! The script uses base variables for all environments.

### Option 2: Separate Credentials per Environment

Add to `.env`:

```bash
# Staging
AMILON_API_URL_STAGING=https://staging-api.amilon.eu/
AMILON_CLIENT_ID_STAGING=staging_client_id
AMILON_CLIENT_SECRET_STAGING=staging_secret
AMILON_USERNAME_STAGING=staging.user
AMILON_PASSWORD_STAGING="staging_pass"
AMILON_CONTRACT_ID_STAGING=staging-uuid

# Production
AMILON_API_URL_PROD=https://prod-api.amilon.eu/
AMILON_CLIENT_ID_PROD=prod_client_id
AMILON_CLIENT_SECRET_PROD=prod_secret
AMILON_USERNAME_PROD=prod.user
AMILON_PASSWORD_PROD="prod_pass"
AMILON_CONTRACT_ID_PROD=prod-uuid
```

Then regenerate:
```bash
./generate-postman-env.sh all
```

## 🆘 Troubleshooting

### "No access token found"
**Solution:** Run "Get Access Token" first

### "401 Unauthorized"
**Cause:** Token expired (5 min TTL)
**Solution:** Re-run "Get Access Token"

### "Script generation doesn't work"
```bash
# Check permissions
chmod +x generate-postman-env.sh

# Check .env exists
ls -la ../../../../.env
```

### "Products not found"
1. Verify `contract_id` in variables
2. Check culture is supported
3. Try culture = "pt-PT"

### "Authentication fails"
1. Check credentials in `.env`
2. Remove whitespace from username/password
3. Quote passwords with special characters
4. Verify URLs are correct

### "Token not being sent in requests"
1. Check environment is selected (dropdown top-right)
2. Verify `access_token` variable has a value
3. Check Collection Auth is set to Bearer Token
4. Re-run "Get Access Token"

## 🔐 Security

### ⚠️ NEVER Commit
- `postman-environment-dev.json`
- `postman-environment-staging.json`
- `postman-environment-prod.json`
- Any `*-configured.json`

These files are automatically excluded by `.gitignore`.

### ✅ Safe to Commit
- `postman-collection.json` - No credentials
- `postman-environment.json` - Empty template
- `generate-postman-env.sh` - Script only
- All `.md` files - Documentation

### Best Practices
1. Always use HTTPS
2. Refresh token before expiration (4 min)
3. Quote passwords with special characters
4. Separate variables per environment
5. No credentials in logs

## 🔄 Automatic Features

### Pre-Request Scripts
- Validates token before each request
- Warns if token is missing

### Test Scripts
- Auto-saves access token after authentication
- Auto-saves order IDs after creation
- Detects 401 errors and suggests refresh
- Detailed logs in Postman console

### Auto-Generated Variables
- `access_token` - After authentication
- `last_external_order_id` - After order creation
- `last_order_id` - After order creation

## 📖 Additional Documentation

### Technical Documentation
- **API_CALLS_REPORT.md** - Complete technical report for Amilon developers (25K)
- **API_CATEGORIES_ENDPOINT_BEHAVIOR.md** - Categories endpoint behavior details
- **TECHNICAL_REFERENCE.md** - Environment variables and advanced configuration

### Code
- Services: `app/Integrations/Vouchers/Amilon/Services/`
- DTOs: `app/Integrations/Vouchers/Amilon/DTO/`
- Models: `app/Integrations/Vouchers/Amilon/Models/`

### Logs
- Laravel: `storage/logs/laravel.log`
- Postman: Console (bottom-left icon)

---

**Created:** 2025-11-12
**Version:** 1.0
**Maintainer:** Development Team