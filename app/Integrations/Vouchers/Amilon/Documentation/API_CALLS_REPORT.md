# Rapport Technique - Appels API Amilon

**Date:** 2025-11-12
**Version:** 1.0
**Client:** Up Engage API
**Contact:** Development Team
**Objectif:** Documentation complète des appels API pour contrôle et validation

---

## Table des Matières

1. [Informations Générales](#informations-générales)
2. [Authentication](#1-authentication)
3. [Contracts](#2-contracts)
4. [Products](#3-products)
5. [Merchants (Retailers)](#4-merchants-retailers)
6. [Categories](#5-categories)
7. [Orders - Creation](#6-orders---creation)
8. [Orders - Information](#7-orders---information)
9. [Codes d'Erreur](#codes-derreur)
10. [Résumé des Intégrations](#résumé-des-intégrations)

---

## Informations Générales

### URLs de Base

| Environnement | URL API | URL Token |
|---------------|---------|-----------|
| Production | `https://b2bsales-api.amilon.eu/` | `https://b2bsales-sso.amilon.eu/connect/token` |
| Staging | TBD | TBD |

### Version API
- **Version actuelle:** v1
- **Base path:** `/b2bwebapi/v1`

### Authentication
- **Type:** OAuth 2.0
- **Grant Type:** Password
- **Token TTL:** 5 minutes (300 secondes)
- **Format:** Bearer Token

### Contract ID
- **Production:** `def116ef-7949-487f-801d-2b15b254ab89`

---

## 1. Authentication

### Endpoint
```
POST https://b2bsales-sso.amilon.eu/connect/token
```

### Request Headers
```http
Content-Type: application/x-www-form-urlencoded
```

### Request Parameters (Body - Form URL Encoded)

| Paramètre | Type | Requis | Description | Exemple |
|-----------|------|--------|-------------|---------|
| `grant_type` | string | ✅ | Type de grant OAuth2 | `password` |
| `client_id` | string | ✅ | Identifiant client OAuth2 | `b2bwsuserwebapi` |
| `client_secret` | string | ✅ | Secret client OAuth2 | `2c1629da82` |
| `username` | string | ✅ | Nom d'utilisateur API | `charles.crespinet.WS` |
| `password` | string | ✅ | Mot de passe API | `s A8ziM@k$#bJ&K#p` |

### Request Example (cURL)
```bash
curl -X POST 'https://b2bsales-sso.amilon.eu/connect/token' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'grant_type=password' \
  -d 'client_id=b2bwsuserwebapi' \
  -d 'client_secret=2c1629da82' \
  -d 'username=charles.crespinet.WS' \
  -d 'password=s A8ziM@k$#bJ&K#p'
```

### Request Example (Raw Body)
```
grant_type=password&client_id=b2bwsuserwebapi&client_secret=2c1629da82&username=charles.crespinet.WS&password=s A8ziM@k$#bJ&K#p
```

### Response Success (200 OK)
```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjFGNEU...",
  "expires_in": 300,
  "token_type": "Bearer",
  "scope": "api"
}
```

### Response Fields

| Champ | Type | Description |
|-------|------|-------------|
| `access_token` | string | Token JWT à utiliser pour les appels API |
| `expires_in` | integer | Durée de validité en secondes (300 = 5 min) |
| `token_type` | string | Type de token (toujours "Bearer") |
| `scope` | string | Scope d'accès |

### Response Error (401 Unauthorized)
```json
{
  "error": "invalid_grant",
  "error_description": "Invalid username or password"
}
```

### Notes d'Implémentation
- Le token doit être rafraîchi toutes les 4 minutes (250 secondes) pour éviter expiration
- Actuellement implémenté dans `AmilonAuthService.php:33`
- Cache non utilisé actuellement (token récupéré à chaque fois)

---

## 2. Contracts

### Endpoint
```
GET https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/{contract_id}
```

### Request Headers
```http
Authorization: Bearer {access_token}
Accept: application/json
```

### Path Parameters

| Paramètre | Type | Requis | Description | Exemple |
|-----------|------|--------|-------------|---------|
| `contract_id` | UUID | ✅ | Identifiant du contrat | `def116ef-7949-487f-801d-2b15b254ab89` |

### Request Example (cURL)
```bash
curl -X GET 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/def116ef-7949-487f-801d-2b15b254ab89' \
  -H 'Authorization: Bearer eyJhbGciOiJSUzI1NiIs...' \
  -H 'Accept: application/json'
```

### Response Success (200 OK)
```json
{
  "id": "def116ef-7949-487f-801d-2b15b254ab89",
  "name": "Up Engage Contract",
  "status": "Active",
  "startDate": "2024-01-01T00:00:00Z",
  "endDate": "2025-12-31T23:59:59Z",
  "terms": "Standard terms and conditions",
  "allowedCountries": ["PT", "FR", "ES", "IT"],
  "maxOrderAmount": 5000.00,
  "currency": "EUR"
}
```

### Response Fields

| Champ | Type | Description |
|-------|------|-------------|
| `id` | UUID | Identifiant unique du contrat |
| `name` | string | Nom du contrat |
| `status` | string | Statut (Active/Inactive) |
| `startDate` | datetime | Date de début |
| `endDate` | datetime | Date de fin |
| `allowedCountries` | array | Pays autorisés (codes ISO) |
| `maxOrderAmount` | decimal | Montant maximum par commande |
| `currency` | string | Devise (EUR) |

### Notes d'Implémentation
- Implémenté dans `AmilonContractService.php:41`
- Retry automatique en cas de 401 (refresh token)
- Utilisé pour validation des limites

---

## 3. Products

### Endpoint
```
GET https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/{contract_id}/{culture}/products/complete
```

### Request Headers
```http
Authorization: Bearer {access_token}
Accept: application/json
```

### Path Parameters

| Paramètre | Type | Requis | Description | Exemple |
|-----------|------|--------|-------------|---------|
| `contract_id` | UUID | ✅ | Identifiant du contrat | `def116ef-7949-487f-801d-2b15b254ab89` |
| `culture` | string | ✅ | Code culture (locale) | `pt-PT` |

### Cultures Supportées

| Code | Pays | Langue |
|------|------|--------|
| `pt-PT` | Portugal | Portugais |
| `it-IT` | Italie | Italien |
| `da-DK` | Danemark | Danois |
| `en-GB` | Royaume-Uni | Anglais |
| `fr-FR` | France | Français |
| `es-ES` | Espagne | Espagnol |
| `de-DE` | Allemagne | Allemand |
| `nl-NL` | Pays-Bas | Néerlandais |
| `nn-NO` | Norvège | Norvégien |
| `pl-PL` | Pologne | Polonais |

### Request Example (cURL)
```bash
curl -X GET 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/def116ef-7949-487f-801d-2b15b254ab89/pt-PT/products/complete' \
  -H 'Authorization: Bearer eyJhbGciOiJSUzI1NiIs...' \
  -H 'Accept: application/json'
```

### Response Success (200 OK)
```json
[
  {
    "ProductCode": "AMAZON_50_EUR",
    "MerchantCode": "AMAZON",
    "MerchantId": "a1234567-89ab-cdef-0123-456789abcdef",
    "Name": "Amazon Gift Card €50",
    "Description": "Amazon voucher for online shopping",
    "Price": 50.00,
    "NetPrice": 47.50,
    "Currency": "EUR",
    "Discount": 5.0,
    "Category": "e-commerce",
    "CategoryId": "cat-001",
    "ImageUrl": "https://cdn.amilon.com/images/amazon-50.png",
    "Active": true,
    "Stock": "Available",
    "MinQuantity": 1,
    "MaxQuantity": 10,
    "Terms": "Valid for 12 months",
    "DeliveryMethod": "Digital",
    "DeliveryTime": "Instant"
  },
  {
    "ProductCode": "FNAC_25_EUR",
    "MerchantCode": "FNAC",
    "MerchantId": "b2345678-90ab-cdef-1234-56789abcdef0",
    "Name": "Fnac Gift Card €25",
    "Description": "Fnac voucher for books, electronics, and more",
    "Price": 25.00,
    "NetPrice": 23.75,
    "Currency": "EUR",
    "Discount": 5.0,
    "Category": "retail",
    "CategoryId": "cat-002",
    "ImageUrl": "https://cdn.amilon.com/images/fnac-25.png",
    "Active": true,
    "Stock": "Available",
    "MinQuantity": 1,
    "MaxQuantity": 20,
    "Terms": "Valid for 24 months",
    "DeliveryMethod": "Digital",
    "DeliveryTime": "Instant"
  }
]
```

### Response Fields

| Champ | Type | Description |
|-------|------|-------------|
| `ProductCode` | string | Code unique du produit |
| `MerchantCode` | string | Code du marchand |
| `MerchantId` | UUID | ID unique du marchand |
| `Name` | string | Nom du produit |
| `Description` | string | Description détaillée |
| `Price` | decimal | Prix de vente (EUR) |
| `NetPrice` | decimal | Prix net après remise |
| `Currency` | string | Devise (EUR) |
| `Discount` | decimal | Pourcentage de remise |
| `Category` | string | Nom de la catégorie |
| `CategoryId` | string | ID de la catégorie |
| `ImageUrl` | string | URL de l'image du produit |
| `Active` | boolean | Produit actif/disponible |
| `Stock` | string | Statut du stock |
| `MinQuantity` | integer | Quantité minimale |
| `MaxQuantity` | integer | Quantité maximale |
| `Terms` | string | Conditions d'utilisation |
| `DeliveryMethod` | string | Méthode de livraison |
| `DeliveryTime` | string | Délai de livraison |

### Filtrage Client
**Note:** Notre implémentation filtre les produits côté client:
- Filtre par `MerchantCode` pour récupérer uniquement les produits d'un marchand spécifique
- Filtre par montants disponibles: `[10, 25, 50, 100, 250, 500]` EUR
- Stockage en base de données avec upsert

### Notes d'Implémentation
- Implémenté dans `AmilonProductService.php:59`
- Retry automatique en cas de 401
- Cache en base de données (prioritaire sur API)
- Filtrage par merchant et montants disponibles

---

## 4. Merchants (Retailers)

### Endpoint
```
GET https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/{contract_id}/{culture}/retailers
```

### Request Headers
```http
Authorization: Bearer {access_token}
Accept: application/json
```

### Path Parameters

| Paramètre | Type | Requis | Description | Exemple |
|-----------|------|--------|-------------|---------|
| `contract_id` | UUID | ✅ | Identifiant du contrat | `def116ef-7949-487f-801d-2b15b254ab89` |
| `culture` | string | ✅ | Code culture (locale) | `pt-PT` |

### Request Example (cURL)
```bash
curl -X GET 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/contracts/def116ef-7949-487f-801d-2b15b254ab89/pt-PT/retailers' \
  -H 'Authorization: Bearer eyJhbGciOiJSUzI1NiIs...' \
  -H 'Accept: application/json'
```

### Response Success (200 OK)
```json
[
  {
    "id": "a1234567-89ab-cdef-0123-456789abcdef",
    "RetailerId": "a1234567-89ab-cdef-0123-456789abcdef",
    "Name": "Amazon",
    "Description": "World's largest online retailer",
    "LogoUrl": "https://cdn.amilon.com/logos/amazon.png",
    "WebsiteUrl": "https://www.amazon.com",
    "Category": "e-commerce",
    "Country": "PRT",
    "Active": true,
    "ProductCount": 15,
    "MinAmount": 10.00,
    "MaxAmount": 500.00,
    "Currency": "EUR",
    "AverageDiscount": 5.0,
    "Features": [
      "Instant delivery",
      "No expiry",
      "Reloadable"
    ]
  },
  {
    "id": "b2345678-90ab-cdef-1234-56789abcdef0",
    "RetailerId": "b2345678-90ab-cdef-1234-56789abcdef0",
    "Name": "Fnac",
    "Description": "French retail chain for cultural and electronic products",
    "LogoUrl": "https://cdn.amilon.com/logos/fnac.png",
    "WebsiteUrl": "https://www.fnac.pt",
    "Category": "retail",
    "Country": "PRT",
    "Active": true,
    "ProductCount": 8,
    "MinAmount": 10.00,
    "MaxAmount": 250.00,
    "Currency": "EUR",
    "AverageDiscount": 5.0,
    "Features": [
      "Digital delivery",
      "24 months validity"
    ]
  }
]
```

### Response Fields

| Champ | Type | Description |
|-------|------|-------------|
| `id` | UUID | Identifiant unique |
| `RetailerId` | UUID | ID du retailer (même que id) |
| `Name` | string | Nom du marchand |
| `Description` | string | Description |
| `LogoUrl` | string | URL du logo |
| `WebsiteUrl` | string | Site web |
| `Category` | string | Catégorie |
| `Country` | string | Code pays (ISO 3) |
| `Active` | boolean | Actif/Inactif |
| `ProductCount` | integer | Nombre de produits |
| `MinAmount` | decimal | Montant minimum |
| `MaxAmount` | decimal | Montant maximum |
| `Currency` | string | Devise |
| `AverageDiscount` | decimal | Remise moyenne (%) |
| `Features` | array | Fonctionnalités |

### Filtrage Client
**Note:** Notre implémentation exclut certains marchands:
- **Twitch** est explicitement exclu (App Store policy)
  - ID: `0199bdd3-32b1-72be-905b-591833b488cf`
  - Merchant ID: `a4322514-36f1-401e-af3d-6a1784a3da7a`

### Notes d'Implémentation
- Implémenté dans `AmilonMerchantService.php:59`
- Filtrage par country: `PRT` (Portugal)
- Exclusion Twitch pour conformité App Store
- Cache en base de données

---

## 5. Categories

### Endpoint
```
GET https://b2bsales-api.amilon.eu/b2bwebapi/v1/retailers/categories
```

### Request Headers
```http
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

### Request Example (cURL)
```bash
curl -X GET 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/retailers/categories' \
  -H 'Authorization: Bearer eyJhbGciOiJSUzI1NiIs...' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json'
```

### Response Success (200 OK)
```json
[
  {
    "CategoryId": "cat-001",
    "CategoryName": "e-commerce"
  },
  {
    "CategoryId": "cat-002",
    "CategoryName": "retail"
  },
  {
    "CategoryId": "cat-003",
    "CategoryName": "entertainment"
  },
  {
    "CategoryId": "cat-004",
    "CategoryName": "food-delivery"
  },
  {
    "CategoryId": "cat-005",
    "CategoryName": "travel"
  },
  {
    "CategoryId": "cat-006",
    "CategoryName": "gaming"
  }
]
```

### Response Fields

| Champ | Type | Description |
|-------|------|-------------|
| `CategoryId` | string | Identifiant unique de la catégorie |
| `CategoryName` | string | Nom de la catégorie |

### Notes d'Implémentation
- Implémenté dans `AmilonCategoryService.php:58`
- Stockage en base de données avec UUID comme ID
- Retry automatique en cas de 401

---

## 6. Orders - Creation

### Endpoint
```
POST https://b2bsales-api.amilon.eu/b2bwebapi/v1/Orders/create/{contract_id}
```

### Request Headers
```http
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json
```

### Path Parameters

| Paramètre | Type | Requis | Description | Exemple |
|-----------|------|--------|-------------|---------|
| `contract_id` | UUID | ✅ | Identifiant du contrat | `def116ef-7949-487f-801d-2b15b254ab89` |

### Request Body

| Champ | Type | Requis | Description | Exemple |
|-------|------|--------|-------------|---------|
| `externalOrderId` | string | ✅ | ID unique externe (notre système) | `ENGAGE-2025-12345678-1234-1234-1234-123456789abc` |
| `orderRows` | array | ✅ | Liste des produits à commander | Voir exemple ci-dessous |

#### orderRows Item

| Champ | Type | Requis | Description | Exemple |
|-------|------|--------|-------------|---------|
| `productId` | string | ✅ | Code produit (ProductCode) | `AMAZON_50_EUR` |
| `quantity` | integer | ✅ | Quantité (généralement 1) | `1` |

### Request Example (JSON)
```json
{
  "externalOrderId": "ENGAGE-2025-12345678-1234-1234-1234-123456789abc",
  "orderRows": [
    {
      "productId": "AMAZON_50_EUR",
      "quantity": 1
    }
  ]
}
```

### Request Example (cURL)
```bash
curl -X POST 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/Orders/create/def116ef-7949-487f-801d-2b15b254ab89' \
  -H 'Authorization: Bearer eyJhbGciOiJSUzI1NiIs...' \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "externalOrderId": "ENGAGE-2025-12345678-1234-1234-1234-123456789abc",
    "orderRows": [
      {
        "productId": "AMAZON_50_EUR",
        "quantity": 1
      }
    ]
  }'
```

### Response Success (200 OK)
```json
{
  "id": "amilon-order-12345",
  "externalOrderId": "ENGAGE-2025-12345678-1234-1234-1234-123456789abc",
  "status": "Pending",
  "orderStatus": "OrderCreated",
  "createdAt": "2025-11-12T10:30:00Z",
  "totalAmount": 50.00,
  "currency": "EUR",
  "orderRows": [
    {
      "productId": "AMAZON_50_EUR",
      "productName": "Amazon Gift Card €50",
      "quantity": 1,
      "unitPrice": 50.00,
      "totalPrice": 50.00,
      "vouchers": []
    }
  ]
}
```

### Response Fields

| Champ | Type | Description |
|-------|------|-------------|
| `id` | string | ID Amilon de la commande |
| `externalOrderId` | string | Notre ID externe |
| `status` | string | Statut simplifié (Pending/Completed/Failed) |
| `orderStatus` | string | Statut détaillé Amilon |
| `createdAt` | datetime | Date de création |
| `totalAmount` | decimal | Montant total |
| `currency` | string | Devise |
| `orderRows` | array | Lignes de commande |
| `orderRows[].vouchers` | array | Codes voucher (vide initialement) |

### Format externalOrderId
**Pattern:** `ENGAGE-{YEAR}-{UUID}`
- `ENGAGE` - Préfixe fixe
- `YEAR` - Année courante (2025)
- `UUID` - UUID v4 généré

**Exemple:** `ENGAGE-2025-a1b2c3d4-e5f6-7890-abcd-ef1234567890`

### Statuts Possibles

| Statut | Description |
|--------|-------------|
| `Pending` | Commande créée, en attente |
| `Processing` | En cours de traitement |
| `Completed` | Commande complétée, vouchers disponibles |
| `Failed` | Échec de la commande |
| `Cancelled` | Commande annulée |

### Notes d'Implémentation
- Implémenté dans `AmilonOrderService.php:68`
- Génération automatique de `externalOrderId` si non fourni
- Stockage en base de données
- Event dispatched: `OrderCreated`
- WebSocket notification envoyée à l'utilisateur
- Retry automatique en cas de 401

---

## 7. Orders - Information

### Endpoint
```
GET https://b2bsales-api.amilon.eu/b2bwebapi/v1/Orders/{external_order_id}/complete
```

### Request Headers
```http
Authorization: Bearer {access_token}
Accept: application/json
```

### Path Parameters

| Paramètre | Type | Requis | Description | Exemple |
|-----------|------|--------|-------------|---------|
| `external_order_id` | string | ✅ | Notre ID externe de commande | `ENGAGE-2025-12345678-1234-1234-1234-123456789abc` |

### Request Example (cURL)
```bash
curl -X GET 'https://b2bsales-api.amilon.eu/b2bwebapi/v1/Orders/ENGAGE-2025-12345678-1234-1234-1234-123456789abc/complete' \
  -H 'Authorization: Bearer eyJhbGciOiJSUzI1NiIs...' \
  -H 'Accept: application/json'
```

### Response Success (200 OK) - Vouchers Disponibles
```json
{
  "id": "amilon-order-12345",
  "externalOrderId": "ENGAGE-2025-12345678-1234-1234-1234-123456789abc",
  "status": "Completed",
  "orderStatus": "VouchersDelivered",
  "createdAt": "2025-11-12T10:30:00Z",
  "completedAt": "2025-11-12T10:31:30Z",
  "totalAmount": 50.00,
  "currency": "EUR",
  "orderRows": [
    {
      "productId": "AMAZON_50_EUR",
      "productName": "Amazon Gift Card €50",
      "quantity": 1,
      "unitPrice": 50.00,
      "totalPrice": 50.00,
      "vouchers": [
        {
          "code": "AMZN-1234-5678-90AB-CDEF",
          "pin": null,
          "url": "https://www.amazon.com/gp/css/gc/payment/view-gc-balance?ie=UTF8&code=AMZN-1234-5678-90AB-CDEF",
          "qrCode": "https://cdn.amilon.com/qr/voucher-12345.png",
          "expiryDate": "2026-11-12T23:59:59Z",
          "value": 50.00,
          "currency": "EUR",
          "status": "Active",
          "instructions": "Redeemable on Amazon.com or Amazon mobile app"
        }
      ]
    }
  ]
}
```

### Response Success (200 OK) - En Attente
```json
{
  "id": "amilon-order-12345",
  "externalOrderId": "ENGAGE-2025-12345678-1234-1234-1234-123456789abc",
  "status": "Pending",
  "orderStatus": "ProcessingVouchers",
  "createdAt": "2025-11-12T10:30:00Z",
  "totalAmount": 50.00,
  "currency": "EUR",
  "orderRows": [
    {
      "productId": "AMAZON_50_EUR",
      "productName": "Amazon Gift Card €50",
      "quantity": 1,
      "unitPrice": 50.00,
      "totalPrice": 50.00,
      "vouchers": []
    }
  ]
}
```

### Response Fields

| Champ | Type | Description |
|-------|------|-------------|
| `id` | string | ID Amilon |
| `externalOrderId` | string | Notre ID externe |
| `status` | string | Statut simplifié |
| `orderStatus` | string | Statut détaillé |
| `createdAt` | datetime | Date de création |
| `completedAt` | datetime | Date de complétion (si completed) |
| `totalAmount` | decimal | Montant total |
| `currency` | string | Devise |
| `orderRows[].vouchers` | array | Liste des vouchers |

#### Voucher Object

| Champ | Type | Description |
|-------|------|-------------|
| `code` | string | Code du voucher (à utiliser) |
| `pin` | string | PIN (si requis) |
| `url` | string | URL de redemption |
| `qrCode` | string | URL du QR code |
| `expiryDate` | datetime | Date d'expiration |
| `value` | decimal | Valeur |
| `currency` | string | Devise |
| `status` | string | Statut (Active/Used/Expired) |
| `instructions` | string | Instructions d'utilisation |

### Délai de Disponibilité
- **Généralement:** Instantané (< 30 secondes)
- **Maximum:** 5 minutes
- **Recommendation:** Polling toutes les 10 secondes si vouchers non disponibles

### Notes d'Implémentation
- Implémenté dans `AmilonOrderService.php:273`
- Mise à jour automatique du statut en base
- Mise à jour des vouchers dans `order_items`
- WebSocket notification envoyée quand vouchers reçus
- Retry automatique en cas de 401

---

## Codes d'Erreur

### Authentication Errors

| Code | Message | Cause | Solution |
|------|---------|-------|----------|
| 401 | `invalid_grant` | Credentials invalides | Vérifier username/password |
| 401 | `invalid_client` | Client ID/Secret invalide | Vérifier client credentials |
| 400 | `unsupported_grant_type` | Grant type incorrect | Utiliser "password" |

### API Errors

| Code | Message | Cause | Solution |
|------|---------|-------|----------|
| 401 | `Unauthorized` | Token expiré/invalide | Refresh token |
| 400 | `Bad Request` | Payload invalide | Vérifier format JSON |
| 404 | `Not Found` | Ressource introuvable | Vérifier IDs |
| 422 | `Unprocessable Entity` | Données invalides | Vérifier business rules |
| 500 | `Internal Server Error` | Erreur serveur Amilon | Retry ou contact support |

### Order Specific Errors

| Code | Message | Cause | Solution |
|------|---------|-------|----------|
| 400 | `Invalid product` | Product code invalide | Vérifier ProductCode |
| 400 | `Invalid quantity` | Quantité hors limites | Respecter min/max |
| 400 | `Duplicate order` | externalOrderId existant | Utiliser UUID unique |
| 422 | `Insufficient funds` | Budget dépassé | Vérifier montant |
| 422 | `Product unavailable` | Stock épuisé | Choisir autre produit |

---

## Résumé des Intégrations

### Services Laravel → Endpoints API

| Service | Endpoint | Méthode | Usage |
|---------|----------|---------|-------|
| `AmilonAuthService` | `/connect/token` | POST | Authentication OAuth2 |
| `AmilonContractService` | `/contracts/{id}` | GET | Récupération contrat |
| `AmilonProductService` | `/contracts/{id}/{culture}/products/complete` | GET | Liste produits |
| `AmilonMerchantService` | `/contracts/{id}/{culture}/retailers` | GET | Liste marchands |
| `AmilonCategoryService` | `/retailers/categories` | GET | Liste catégories |
| `AmilonOrderService` | `/Orders/create/{contract_id}` | POST | Création commande |
| `AmilonOrderService` | `/Orders/{external_id}/complete` | GET | Info commande |

### Fréquence d'Appels (Production)

| Endpoint | Fréquence | Cache | Notes |
|----------|-----------|-------|-------|
| Authentication | À chaque 4 min | Non | Token TTL 5 min |
| Contracts | Rare (validation) | Non | Une fois au démarrage |
| Products | 1x/jour | DB | Sync quotidien |
| Merchants | 1x/jour | DB | Sync quotidien |
| Categories | 1x/jour | DB | Sync quotidien |
| Create Order | Variable | Non | À la demande utilisateur |
| Get Order Info | Variable | Non | Polling jusqu'à vouchers |

### Volumes Estimés

| Type | Volume/Jour | Peak | Notes |
|------|-------------|------|-------|
| Authentication | ~300 | 50/h | Refresh fréquent |
| Products | ~1 | - | Sync quotidien |
| Merchants | ~1 | - | Sync quotidien |
| Categories | ~1 | - | Sync quotidien |
| Orders Create | ~50-100 | 20/h | Basé sur utilisateurs |
| Orders Info | ~200-500 | 50/h | Polling + checks |

### Gestion d'Erreurs

| Erreur | Action | Retry | Notification |
|--------|--------|-------|--------------|
| 401 | Refresh token + retry | 1x | Log warning |
| 4xx | Log + retour erreur | Non | User notification |
| 5xx | Log + retry | 3x | Alert développeur |
| Timeout | Retry | 2x | Log error |

### Sécurité

#### Credentials Storage
- ✅ Stockés dans `.env`
- ✅ Jamais en code dur
- ✅ Différents par environnement
- ❌ Pas de cache des passwords

#### Token Management
- ✅ Bearer token dans Authorization header
- ✅ Refresh avant expiration (4 min sur TTL 5 min)
- ❌ Pas de stockage persistant du token
- ✅ HTTPS uniquement

#### Data Protection
- ✅ Credentials masqués dans logs
- ✅ Passwords quotés si caractères spéciaux
- ✅ Exclusions spécifiques (Twitch)
- ✅ Validation des montants

---

## Recommandations

### Pour l'API Amilon

1. **Documentation**
   - Fournir OpenAPI/Swagger spec
   - Documenter tous les codes d'erreur
   - Exemples de réponses pour chaque statut

2. **Performance**
   - Considérer un TTL token plus long (15-30 min)
   - Endpoint de health check
   - Rate limiting information dans headers

3. **Fonctionnalités**
   - Webhook pour ordre complété (éviter polling)
   - Batch endpoint pour products sync
   - Pagination pour grandes listes

4. **Monitoring**
   - Status page publique
   - Alertes maintenance planifiée
   - Logs détaillés côté Amilon

### Pour Notre Implémentation

1. **Optimisations**
   - Implémenter cache Redis pour token
   - Webhook listener pour ordres
   - Batch sync produits/marchands
   - Retry avec exponential backoff

2. **Monitoring**
   - Métriques par endpoint
   - Alertes sur erreurs 5xx
   - Dashboard temps réel
   - Tracking temps de réponse

3. **Qualité**
   - Tests d'intégration complets
   - Mock API pour tests
   - Contract testing
   - Load testing

---

## Contact

**Pour questions techniques:**
- Email: development@up-engage.com
- Slack: #amilon-integration

**Pour support API Amilon:**
- Contact: [support@amilon.com]
- Documentation: [docs URL]

---

**Rapport généré le:** 2025-11-12
**Version:** 1.0
**Auteur:** Development Team
**Status:** ✅ Prêt pour revue
