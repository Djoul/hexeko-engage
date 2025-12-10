# Amilon API - Categories Endpoint Behavior

## Executive Summary

The `/retailers/categories` endpoint has a **different behavior** compared to other Amilon API endpoints. Unlike products and merchants endpoints, it does **not accept a culture parameter** in the URL path.

This document provides technical details and production testing results for this endpoint.

---

## Endpoint Details

### Base Information

| Property | Value |
|----------|-------|
| **Method** | GET |
| **URL** | `https://b2bsales-api.amilon.eu/b2bwebapi/v1/retailers/categories` |
| **Authentication** | Bearer Token (OAuth 2.0) |
| **Content-Type** | `application/json` |

### URL Structure

```
GET /b2bwebapi/v1/retailers/categories
```

**Note:** No `{culture}` parameter in the URL path.

---

## Key Difference from Other Endpoints

### Other Endpoints WITH Culture Parameter

Most Amilon API endpoints include a culture parameter in the URL:

```
✅ GET /contracts/{contract_id}/{culture}/products/complete
✅ GET /contracts/{contract_id}/{culture}/retailers
```

**Examples:**
- `/contracts/{contract_id}/pt-PT/products/complete`
- `/contracts/{contract_id}/fr-FR/retailers`
- `/contracts/{contract_id}/it-IT/products/complete`

### Categories Endpoint WITHOUT Culture Parameter

The categories endpoint does **not** include the culture parameter:

```
❌ GET /retailers/categories/{culture}  ← DOES NOT EXIST
✅ GET /retailers/categories             ← CORRECT FORMAT
```

**This means:**
- Categories are returned in a **single language** (Italian/English in production)
- No localization support for category names
- Same response regardless of user's locale preference

---

## Production Test Results

### Test Environment

| Parameter | Value |
|-----------|-------|
| **Environment** | Production |
| **API URL** | `https://b2bsales-api.amilon.eu` |
| **Test Date** | 2025-11-12 |
| **Credentials** | Production credentials |
| **Contract ID** | `def116ef-7949-487f-801d-2b15b254ab89` |

### Request Details

#### Headers

```http
GET /b2bwebapi/v1/retailers/categories HTTP/1.1
Host: b2bsales-api.amilon.eu
Accept: application/json
Content-Type: application/json
Authorization: Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6...
User-Agent: PostmanRuntime/7.50.0
```

#### Full Request

```bash
curl -X GET 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/retailers/categories' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer {access_token}'
```

### Response Details

#### Response Headers

```http
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
Content-Length: 1849
Date: Wed, 12 Nov 2025 10:24:23 GMT
Cache-Control: private
```

#### Response Body

```json
[
  {
    "CategoryId": "26c9eda2-32b8-4c57-921c-05c2baf4e93f",
    "CategoryName": "Beauty care"
  },
  {
    "CategoryId": "ace6e2c4-8aa1-40b1-8703-07e540dd253c",
    "CategoryName": "Beneficenza"
  },
  {
    "CategoryId": "26fc3c4a-0e5e-4400-a552-12370bcbaec8",
    "CategoryName": "Benzina e mobilità"
  },
  {
    "CategoryId": "3399d2bd-c11f-4281-bb7e-12e71d8bcd34",
    "CategoryName": "Casa e manutenzione"
  },
  {
    "CategoryId": "814cf294-73af-4e2b-b204-2b7086fbcbd1",
    "CategoryName": "Centri commerciali e Outlet"
  },
  {
    "CategoryId": "bb9dc999-3bd7-4129-a2a0-31dfa1c7c5ee",
    "CategoryName": "Coupon, offerte e sconti"
  },
  {
    "CategoryId": "98a7a818-3dd4-4a26-a894-483ac276c49e",
    "CategoryName": "Multibrand & marketplace"
  },
  {
    "CategoryId": "19e4c6ed-730f-4df3-bd60-49486ca4fbbd",
    "CategoryName": "Elettronica"
  },
  {
    "CategoryId": "da77759f-118d-4d45-aae8-5588284291c8",
    "CategoryName": "Intrattenimento e tempo libero"
  },
  {
    "CategoryId": "fc14d92b-e60b-4885-8d07-6088f429a278",
    "CategoryName": "Libri e riviste"
  },
  {
    "CategoryId": "b787bed2-b1df-41d8-8456-63af1a1ffb41",
    "CategoryName": "Moda e accessori"
  },
  {
    "CategoryId": "bda7b640-2031-4f8b-8241-64d2c0b4b9ef",
    "CategoryName": "Multibrand"
  },
  {
    "CategoryId": "52e84dca-13a0-4480-a40f-7ef8b77346ac",
    "CategoryName": "Ottica"
  },
  {
    "CategoryId": "9429e0d0-2a87-497a-87b3-88aef9b7d397",
    "CategoryName": "Prodotti per animali"
  },
  {
    "CategoryId": "9b539288-aa28-4cc9-8d1c-8c7aa95eee1f",
    "CategoryName": "Prodotti per l' infanzia"
  },
  {
    "CategoryId": "fd208fe2-a945-44e9-b894-cbec6fa58278",
    "CategoryName": "Prodotti per lo sport"
  },
  {
    "CategoryId": "54999601-91ae-4e54-9630-d0125baa873d",
    "CategoryName": "Ristorazione"
  },
  {
    "CategoryId": "e9bbdbfb-6a35-4388-b2a5-d8b1640cca5b",
    "CategoryName": "Salute"
  },
  {
    "CategoryId": "8fd66100-4585-43e5-90ac-fa7f9e241f08",
    "CategoryName": "Spesa"
  },
  {
    "CategoryId": "9b441615-556a-4751-8018-fd3f18626d83",
    "CategoryName": "Telefonia"
  },
  {
    "CategoryId": "a6815198-97fc-4363-9e81-fe3fac499973",
    "CategoryName": "Viaggi e turismo"
  }
]
```

### Response Analysis

#### Total Categories
**21 categories** returned in the response.

#### Language
**Italian** - All category names are in Italian (e.g., "Beneficenza", "Benzina e mobilità", "Casa e manutenzione")

#### Category Structure

Each category object contains:

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `CategoryId` | UUID | Unique identifier | `26c9eda2-32b8-4c57-921c-05c2baf4e93f` |
| `CategoryName` | string | Category name (Italian) | `Beauty care` |

---

## Complete Categories List

| # | CategoryId | CategoryName (IT) | English Translation |
|---|------------|-------------------|---------------------|
| 1 | `26c9eda2-32b8-4c57-921c-05c2baf4e93f` | Beauty care | Beauty care |
| 2 | `ace6e2c4-8aa1-40b1-8703-07e540dd253c` | Beneficenza | Charity |
| 3 | `26fc3c4a-0e5e-4400-a552-12370bcbaec8` | Benzina e mobilità | Gas & Mobility |
| 4 | `3399d2bd-c11f-4281-bb7e-12e71d8bcd34` | Casa e manutenzione | Home & Maintenance |
| 5 | `814cf294-73af-4e2b-b204-2b7086fbcbd1` | Centri commerciali e Outlet | Shopping Malls & Outlets |
| 6 | `bb9dc999-3bd7-4129-a2a0-31dfa1c7c5ee` | Coupon, offerte e sconti | Coupons, Offers & Discounts |
| 7 | `98a7a818-3dd4-4a26-a894-483ac276c49e` | Multibrand & marketplace | Multibrand & Marketplace |
| 8 | `19e4c6ed-730f-4df3-bd60-49486ca4fbbd` | Elettronica | Electronics |
| 9 | `da77759f-118d-4d45-aae8-5588284291c8` | Intrattenimento e tempo libero | Entertainment & Leisure |
| 10 | `fc14d92b-e60b-4885-8d07-6088f429a278` | Libri e riviste | Books & Magazines |
| 11 | `b787bed2-b1df-41d8-8456-63af1a1ffb41` | Moda e accessori | Fashion & Accessories |
| 12 | `bda7b640-2031-4f8b-8241-64d2c0b4b9ef` | Multibrand | Multibrand |
| 13 | `52e84dca-13a0-4480-a40f-7ef8b77346ac` | Ottica | Optical |
| 14 | `9429e0d0-2a87-497a-87b3-88aef9b7d397` | Prodotti per animali | Pet Products |
| 15 | `9b539288-aa28-4cc9-8d1c-8c7aa95eee1f` | Prodotti per l' infanzia | Children Products |
| 16 | `fd208fe2-a945-44e9-b894-cbec6fa58278` | Prodotti per lo sport | Sports Products |
| 17 | `54999601-91ae-4e54-9630-d0125baa873d` | Ristorazione | Restaurants |
| 18 | `e9bbdbfb-6a35-4388-b2a5-d8b1640cca5b` | Salute | Health |
| 19 | `8fd66100-4585-43e5-90ac-fa7f9e241f08` | Spesa | Groceries |
| 20 | `9b441615-556a-4751-8018-fd3f18626d83` | Telefonia | Telecom |
| 21 | `a6815198-97fc-4363-9e81-fe3fac499973` | Viaggi e turismo | Travel & Tourism |

---

## Implementation Implications

### 1. No Localization Support

Since the endpoint does not accept a culture parameter, category names are **always returned in Italian**.

**Impact:**
- Frontend applications must handle translation locally
- Cannot request categories in different languages from the API
- Category names should be stored with translations in application database

### 2. Consistent Category IDs

Category IDs (UUIDs) are **stable and language-independent**.

**Benefit:**
- Safe to use CategoryId as reference across different languages
- Can map Italian category names to local translations using CategoryId

### 3. Caching Strategy

Since categories don't vary by culture:

**Recommendation:**
- Cache categories globally (not per culture)
- Longer cache TTL acceptable (24 hours)
- Single API call sufficient for all users

---

## Comparison with Other Endpoints

### Summary Table

| Endpoint | Culture Parameter | Localized Response | Notes |
|----------|-------------------|-------------------|-------|
| **Products** | ✅ Required | ✅ Yes | `/contracts/{id}/{culture}/products/complete` |
| **Merchants** | ✅ Required | ✅ Yes | `/contracts/{id}/{culture}/retailers` |
| **Categories** | ❌ Not Supported | ❌ No (Italian only) | `/retailers/categories` |
| **Orders** | ❌ Not Applicable | N/A | `/Orders/create/{contract_id}` |
| **Contracts** | ❌ Not Applicable | N/A | `/contracts/{id}` |

### Supported Cultures (for other endpoints)

| Code | Language | Country |
|------|----------|---------|
| `pt-PT` | Portuguese | Portugal |
| `fr-FR` | French | France |
| `es-ES` | Spanish | Spain |
| `it-IT` | Italian | Italy |
| `de-DE` | German | Germany |
| `en-GB` | English | United Kingdom |
| `nl-NL` | Dutch | Netherlands |
| `da-DK` | Danish | Denmark |
| `nn-NO` | Norwegian | Norway |
| `pl-PL` | Polish | Poland |

**Note:** Categories endpoint ignores culture, always returns Italian names.

---

## Recommendations for Amilon API Team

### Enhancement Request

To improve consistency across the API, we recommend:

1. **Add culture support** to categories endpoint:
   ```
   GET /retailers/categories/{culture}
   ```

2. **Return localized category names** based on culture:
   ```json
   // pt-PT example
   {
     "CategoryId": "26c9eda2-32b8-4c57-921c-05c2baf4e93f",
     "CategoryName": "Cuidados de beleza"  // Portuguese
   }
   ```

3. **Backward compatibility:**
   - Keep current endpoint for Italian: `/retailers/categories`
   - Add new endpoint with culture: `/retailers/categories/{culture}`

### Benefits

- **Consistency:** Aligns with products and merchants endpoints
- **Better UX:** Users see categories in their language
- **Reduced complexity:** Applications don't need local translation tables

---

## Application Implementation

### Current Workaround

Since categories are only available in Italian, our application implements client-side translation:

#### Laravel Service

```php
// app/Integrations/Vouchers/Amilon/Services/AmilonCategoryService.php:58

public function getCategories(): Collection
{
    // Returns Italian categories from API
    $categories = $this->fetchCategoriesFromApi();

    // Store in database with translations
    return $this->upsertCategoriesDatabase($categories);
}
```

#### Translation Mapping

We maintain a translation table in our database:

```sql
CREATE TABLE category_translations (
    id UUID PRIMARY KEY,
    category_id UUID REFERENCES categories(id),
    locale VARCHAR(10),
    name VARCHAR(255),
    UNIQUE(category_id, locale)
);
```

**Example:**
```sql
INSERT INTO category_translations VALUES
  ('...', '26c9eda2-32b8-4c57-921c-05c2baf4e93f', 'en', 'Beauty care'),
  ('...', '26c9eda2-32b8-4c57-921c-05c2baf4e93f', 'pt', 'Cuidados de beleza'),
  ('...', '26c9eda2-32b8-4c57-921c-05c2baf4e93f', 'fr', 'Soins de beauté');
```

---

## Testing Notes

### Successful Test

✅ **Status:** 200 OK
✅ **Authentication:** Bearer Token working correctly
✅ **Response:** Valid JSON with 21 categories
✅ **Performance:** ~400ms response time
✅ **SSL/TLS:** Valid certificate from Sectigo

### Failed Attempts (Documentation)

The following URLs do **NOT work**:

```
❌ /retailers/categories/pt-PT  → 404 Not Found
❌ /retailers/categories/en-GB  → 404 Not Found
❌ /retailers/pt-PT/categories  → 404 Not Found
```

**Only this works:**
```
✅ /retailers/categories  → 200 OK (Italian categories)
```

---

## Technical Specifications

### Network Details

| Parameter | Value |
|-----------|-------|
| **Protocol** | HTTPS (TLS 1.3) |
| **Cipher** | TLS_AES_256_GCM_SHA384 |
| **Server IP** | 45.87.64.115 |
| **SSL Certificate** | Valid (Sectigo) |
| **Certificate Expiry** | Jan 24, 2026 |
| **Response Time** | ~400ms |

### SSL Certificate

```
Subject: CN=*.amilon.eu, O=Amilon Srl, L=Milano, C=IT
Issuer: CN=Sectigo RSA Organization Validation Secure Server CA
Valid From: Dec 26, 2024
Valid To: Jan 24, 2026
```

---

## Conclusion

The Amilon API `/retailers/categories` endpoint:

1. ✅ **Works correctly** with Bearer Token authentication
2. ❌ **Does not support** culture/locale parameter
3. 🇮🇹 **Returns Italian** category names only
4. ✅ **Provides stable** UUIDs for category references
5. ⚠️ **Requires client-side** translation for multi-language support

### Action Items

**For Amilon:**
- Consider adding culture support to maintain consistency
- Document this behavior in official API documentation

**For Our Application:**
- Continue using client-side translation table
- Cache categories globally (not per culture)
- Use CategoryId for stable references

---

**Document Version:** 1.0
**Last Updated:** 2025-11-12
**Tested By:** Development Team
**Environment:** Production
**API Version:** v1
