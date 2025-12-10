# Amilon API - Technical Reference

Technical details for environment variables, service mapping, and advanced configuration.

## 📦 Files Overview

### Core Postman Files

| File | Size | Purpose | Commit |
|------|------|---------|--------|
| `postman-collection.json` | 12K | Collection with 7 endpoints | ✅ Yes |
| `postman-environment.json` | 1.4K | Empty template | ✅ Yes |
| `postman-environment-dev.json` | 1.6K | Development with credentials | ❌ No |
| `postman-environment-staging.json` | 1.6K | Staging with credentials | ❌ No |
| `postman-environment-prod.json` | 1.6K | Production with credentials | ❌ No |
| `generate-postman-env.sh` | 11K | Generation script | ✅ Yes |

### Documentation Files

| File | Purpose |
|------|---------|
| `GETTING_STARTED.md` | Main user guide |
| `TECHNICAL_REFERENCE.md` | This file - technical details |
| `API_CALLS_REPORT.md` | Complete technical report for Amilon (25K) |
| `API_CATEGORIES_ENDPOINT_BEHAVIOR.md` | Categories endpoint behavior |

## 🔧 Environment Variables

### Required Variables (Base/Development)

```bash
AMILON_API_URL=https://b2bsales-api.amilon.eu/
AMILON_TOKEN_URL=https://b2bsales-sso.amilon.eu/connect/token
AMILON_CLIENT_ID=your_client_id
AMILON_CLIENT_SECRET=your_client_secret
AMILON_USERNAME=your_username
AMILON_PASSWORD=your_password
AMILON_CONTRACT_ID=your_contract_id
```

### Optional Variables (Staging)

Add `_STAGING` suffix:

```bash
AMILON_API_URL_STAGING=https://staging-api.amilon.eu/
AMILON_TOKEN_URL_STAGING=https://staging-sso.amilon.eu/connect/token
AMILON_CLIENT_ID_STAGING=staging_client_id
AMILON_CLIENT_SECRET_STAGING=staging_secret
AMILON_USERNAME_STAGING=staging.user
AMILON_PASSWORD_STAGING="staging_pass"
AMILON_CONTRACT_ID_STAGING=staging-uuid
```

### Optional Variables (Production)

Add `_PROD` suffix:

```bash
AMILON_API_URL_PROD=https://prod-api.amilon.eu/
AMILON_TOKEN_URL_PROD=https://prod-sso.amilon.eu/connect/token
AMILON_CLIENT_ID_PROD=prod_client_id
AMILON_CLIENT_SECRET_PROD=prod_secret
AMILON_USERNAME_PROD=prod.user
AMILON_PASSWORD_PROD="prod_pass"
AMILON_CONTRACT_ID_PROD=prod-uuid
```

### Variable Descriptions

| Variable | Description | Format | Example |
|----------|-------------|--------|---------|
| `AMILON_API_URL` | Base URL for Amilon B2B Web API | URL with trailing slash | `https://b2bsales-api.amilon.eu/` |
| `AMILON_TOKEN_URL` | OAuth2 token endpoint | URL | `https://b2bsales-sso.amilon.eu/connect/token` |
| `AMILON_CLIENT_ID` | OAuth2 client identifier | Alphanumeric | `b2bwsuserwebapi` |
| `AMILON_CLIENT_SECRET` | OAuth2 client secret | Alphanumeric | *(secret)* |
| `AMILON_USERNAME` | API username for password grant | String | `username.WS` |
| `AMILON_PASSWORD` | API password | String (quote if special chars) | `"password123"` |
| `AMILON_CONTRACT_ID` | Contract identifier | UUID | `def116ef-7949-487f-801d-2b15b254ab89` |

### Fallback Behavior

When generating environments:

1. **Development**: Always uses base variables (no suffix)
2. **Staging**: Tries `_STAGING` suffix first, falls back to base
3. **Production**: Tries `_PROD` suffix first, falls back to base

**Example:**
```bash
# If AMILON_API_URL_STAGING is not set
# It will use AMILON_API_URL instead
```

## 📊 Service Mapping

Maps Postman requests to Laravel services:

| Postman Request | Laravel Service | File | Line |
|----------------|-----------------|------|------|
| Get Access Token | `AmilonAuthService` | `AmilonAuthService.php` | 33 |
| Get Contract | `AmilonContractService` | `AmilonContractService.php` | 41 |
| Get Products Complete | `AmilonProductService` | `AmilonProductService.php` | 59 |
| Get Retailers | `AmilonMerchantService` | `AmilonMerchantService.php` | 59 |
| Get Categories | `AmilonCategoryService` | `AmilonCategoryService.php` | 58 |
| Create Order | `AmilonOrderService` | `AmilonOrderService.php` | 68 |
| Get Order Info | `AmilonOrderService` | `AmilonOrderService.php` | 273 |

## 🔄 Script Usage

### Generate All Environments

```bash
./generate-postman-env.sh all
```

Generates dev, staging, and prod environment files.

### Generate Specific Environment

```bash
./generate-postman-env.sh dev       # Development only
./generate-postman-env.sh staging   # Staging only
./generate-postman-env.sh prod      # Production only
```

### Script Features

- Reads from `.env` file (4 levels up)
- Supports environment-specific variables with suffix
- Fallback to base variables if suffix not found
- Generates JSON files ready for Postman import
- Shows warnings for missing variables

## 🔐 Security Details

### Password Best Practices

**Problem:** Password with special characters `$`, `#`, `&`, etc.
**Solution:** Quote the password

```bash
# ❌ Bad
AMILON_PASSWORD=password$with#special

# ✅ Good
AMILON_PASSWORD="password$with#special"
```

### Credential Management

1. **Never commit** environment files with credentials
2. **Use different credentials** per environment
3. **Rotate credentials** regularly (every 90 days)
4. **Limit access** to production credentials
5. **Use secret management** (AWS Secrets Manager, Vault)

### Token Security

- Tokens expire after **5 minutes**
- Refresh before expiration (recommendation: 4 min)
- Tokens are saved in Postman variables (not persistent across sessions)
- Never log full tokens in application logs

## 🌐 API Details

### Base URLs

| Environment | API URL | Token URL |
|-------------|---------|-----------|
| Production | `https://b2bsales-api.amilon.eu/` | `https://b2bsales-sso.amilon.eu/connect/token` |
| Staging | *(if available)* | *(if available)* |

### Authentication

- **Type:** OAuth2 Password Grant
- **Token TTL:** 5 minutes (300 seconds)
- **Token Type:** Bearer
- **Token Format:** JWT

### Request Headers

All API requests require:

```http
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

### Culture/Locale Support

10 cultures supported for Products and Merchants endpoints:

- `pt-PT` - Portuguese (Portugal)
- `fr-FR` - French (France)
- `es-ES` - Spanish (Spain)
- `it-IT` - Italian (Italy)
- `de-DE` - German (Germany)
- `en-GB` - English (United Kingdom)
- `nl-NL` - Dutch (Netherlands)
- `da-DK` - Danish (Denmark)
- `nn-NO` - Norwegian (Norway)
- `pl-PL` - Polish (Poland)

**Important:** Categories endpoint does NOT support culture parameter (see `API_CATEGORIES_ENDPOINT_BEHAVIOR.md`).

## 📝 Complete .env Example

```bash
# ==============================================
# Amilon API Configuration
# ==============================================

# Development (Default)
AMILON_API_URL=https://b2bsales-api.amilon.eu/
AMILON_TOKEN_URL=https://b2bsales-sso.amilon.eu/connect/token
AMILON_CLIENT_ID=dev_client_id
AMILON_CLIENT_SECRET=dev_client_secret
AMILON_USERNAME=dev.user.WS
AMILON_PASSWORD="dev_password_123"
AMILON_CONTRACT_ID=11111111-1111-1111-1111-111111111111

# Staging
AMILON_API_URL_STAGING=https://staging-api.amilon.eu/
AMILON_TOKEN_URL_STAGING=https://staging-sso.amilon.eu/connect/token
AMILON_CLIENT_ID_STAGING=staging_client_id
AMILON_CLIENT_SECRET_STAGING=staging_client_secret
AMILON_USERNAME_STAGING=staging.user.WS
AMILON_PASSWORD_STAGING="staging_password_456"
AMILON_CONTRACT_ID_STAGING=22222222-2222-2222-2222-222222222222

# Production
AMILON_API_URL_PROD=https://prod-api.amilon.eu/
AMILON_TOKEN_URL_PROD=https://prod-sso.amilon.eu/connect/token
AMILON_CLIENT_ID_PROD=prod_client_id
AMILON_CLIENT_SECRET_PROD=prod_client_secret
AMILON_USERNAME_PROD=prod.user.WS
AMILON_PASSWORD_PROD="prod_password_789"
AMILON_CONTRACT_ID_PROD=33333333-3333-3333-3333-333333333333
```

## 🧪 Response Structures

### Authentication Response

```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6...",
  "token_type": "Bearer",
  "expires_in": 300
}
```

### Product Response Example

```json
{
  "ProductCode": "AMAZON_50_EUR",
  "MerchantCode": "AMAZON",
  "Price": 50.00,
  "Currency": "EUR",
  "Name": "Amazon €50",
  "Description": "Amazon voucher 50 EUR",
  "Category": "Shopping",
  "AvailableAmounts": [10, 25, 50, 100]
}
```

### Order Response Example

```json
{
  "id": "amilon-internal-order-id",
  "externalOrderId": "ENGAGE-2025-12345678-1234-1234-1234-123456789abc",
  "status": "Pending",
  "createdAt": "2025-11-12T10:30:00Z",
  "orderRows": [
    {
      "productId": "AMAZON_50_EUR",
      "quantity": 1,
      "vouchers": []
    }
  ]
}
```

### Error Response Example

```json
{
  "error": "unauthorized",
  "error_description": "The access token expired"
}
```

## ⚙️ Collection Features

### Global Pre-Request Script

Validates token before each request:

```javascript
const token = pm.collectionVariables.get('access_token') || pm.environment.get('access_token');
const url = pm.request.url ? pm.request.url.toString() : '';

if (!token && !url.includes('oauth') && !url.includes('token')) {
    console.warn('⚠️ No access token found. Run "Get Access Token" first.');
}
```

### Authentication Test Script

Auto-saves token after successful authentication:

```javascript
if (pm.response.code === 200) {
    const response = pm.response.json();
    pm.environment.set('access_token', response.access_token);
    pm.collectionVariables.set('access_token', response.access_token);
    console.log('✅ Access token saved:', response.access_token.substring(0, 50) + '...');
    console.log('Token expires in:', response.expires_in, 'seconds');
}
```

### Order Creation Test Script

Auto-saves order IDs:

```javascript
if (pm.response.code === 200 || pm.response.code === 201) {
    const response = pm.response.json();
    pm.environment.set('last_external_order_id', response.externalOrderId);
    pm.environment.set('last_order_id', response.id);
    console.log('✅ Order created:', response.externalOrderId);
}
```

## 🔗 Related Documentation

### Official Amilon Documentation
- Contact Amilon support for API documentation
- Request access to API changelog
- Check for API versioning updates

### Internal Documentation
- `GETTING_STARTED.md` - Quick start guide
- `API_CALLS_REPORT.md` - Complete technical report (25K)
- `API_CATEGORIES_ENDPOINT_BEHAVIOR.md` - Categories endpoint details

### Laravel Integration
- Config: `config/services.php` (amilon section)
- Services: `app/Integrations/Vouchers/Amilon/Services/`
- DTOs: `app/Integrations/Vouchers/Amilon/DTO/`
- Models: `app/Integrations/Vouchers/Amilon/Models/`
- Tests: `tests/Feature/Integrations/Vouchers/Amilon/`

---

**Created:** 2025-11-12
**Version:** 1.0
**Maintainer:** Development Team