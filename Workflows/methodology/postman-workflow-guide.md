# Guide Postman pour API RESTful

## 📮 Vue d'Ensemble

Postman est un élément **CRITIQUE** de notre workflow de développement API. Il sert à la fois de documentation vivante, d'outil de test et de validation pour toutes nos APIs.

## 🎯 Objectifs

### Documentation
- **Collections organisées** par module/intégration
- **Exemples concrets** pour chaque endpoint
- **Variables d'environnement** documentées
- **Pre-request scripts** pour l'authentification

### Validation
- **Tests automatisés** pour chaque endpoint
- **Scénarios end-to-end** complets
- **Tests de régression** via Newman
- **Intégration CI/CD** obligatoire

### Collaboration
- **Partage avec le frontend** pour intégration
- **Documentation client** exportable
- **Mock servers** pour développement parallèle
- **Monitoring** des APIs en production

## 🏗️ Structure des Collections

### Organisation Hiérarchique
```
📁 UP Engage API
├── 📁 Authentication
│   ├── POST Login
│   ├── POST Refresh Token
│   └── POST Logout
├── 📁 Vouchers
│   ├── 📁 Amilon
│   │   ├── GET Merchants
│   │   ├── GET Products
│   │   ├── POST Create Order
│   │   └── GET Balance
│   ├── 📁 WellWo
│   │   ├── GET Activities
│   │   └── POST Book Activity
│   └── 📁 Common
│       └── GET Available Integrations
├── 📁 Credits
│   ├── GET Balance
│   ├── POST Debit
│   └── GET History
└── 📁 Admin
    ├── 📁 Users
    └── 📁 Settings
```

### Conventions de Nommage
| Élément | Convention | Exemple |
|---------|-----------|---------|
| Collection | `[Project] Module` | `UP Engage Vouchers` |
| Dossier | `PascalCase` | `AmilonIntegration` |
| Request | `[METHOD] Action` | `POST Create Order` |
| Variable | `snake_case` | `base_url`, `auth_token` |
| Test | `should_` prefix | `should_return_200` |

## 📝 Workflow de Développement

### Phase 1: Création de la Collection

#### 1.1 Initialisation
```javascript
// Collection Variables
{
  "base_url": "{{protocol}}://{{host}}:{{port}}/api/v1",
  "auth_token": "",
  "user_id": "",
  "financer_id": ""
}
```

#### 1.2 Pre-request Script (Collection Level)
```javascript
// Authentification automatique si nécessaire
if (!pm.collectionVariables.get("auth_token")) {
    const loginRequest = {
        url: pm.collectionVariables.get("base_url") + "/auth/login",
        method: 'POST',
        header: {
            'Content-Type': 'application/json'
        },
        body: {
            mode: 'raw',
            raw: JSON.stringify({
                email: pm.environment.get("test_email"),
                password: pm.environment.get("test_password")
            })
        }
    };
    
    pm.sendRequest(loginRequest, (err, res) => {
        if (!err && res.code === 200) {
            const jsonData = res.json();
            pm.collectionVariables.set("auth_token", jsonData.data.token);
        }
    });
}
```

### Phase 2: Documentation des Endpoints

#### 2.1 Description Complète
Pour chaque endpoint, documenter :
- **Purpose** : Objectif de l'endpoint
- **Authentication** : Type requis (JWT, API Key, etc.)
- **Rate Limiting** : Limites appliquées
- **Permissions** : Rôles nécessaires
- **Cache** : TTL et invalidation

#### 2.2 Exemples Multiples
```javascript
// Exemple 1: Cas nominal
{
    "name": "Success Case",
    "request": {
        "body": {
            "product_id": "FNAC-100",
            "amount": 100,
            "quantity": 1
        }
    },
    "response": {
        "status": 201,
        "body": {
            "data": {
                "order_id": "ORD-123",
                "status": "pending"
            }
        }
    }
}

// Exemple 2: Cas d'erreur
{
    "name": "Insufficient Credits",
    "request": {
        "body": {
            "product_id": "FNAC-500",
            "amount": 50000
        }
    },
    "response": {
        "status": 402,
        "body": {
            "error": {
                "code": "INSUFFICIENT_CREDITS",
                "message": "Not enough credits"
            }
        }
    }
}
```

### Phase 3: Tests Automatisés

#### 3.1 Tests de Base (Obligatoires)
```javascript
// Tests minimaux pour CHAQUE endpoint
pm.test("Status code is 200", () => {
    pm.response.to.have.status(200);
});

pm.test("Response time is less than 500ms", () => {
    pm.expect(pm.response.responseTime).to.be.below(500);
});

pm.test("Response has correct structure", () => {
    const jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('data');
    pm.expect(jsonData).to.have.property('meta');
});
```

#### 3.2 Tests Métier
```javascript
// Tests spécifiques au domaine
pm.test("Merchant has required fields", () => {
    const merchant = pm.response.json().data;
    pm.expect(merchant).to.have.property('id');
    pm.expect(merchant).to.have.property('name');
    pm.expect(merchant).to.have.property('category');
    pm.expect(merchant.products).to.be.an('array');
});

pm.test("Credit balance is updated", () => {
    const previousBalance = pm.collectionVariables.get("previous_balance");
    const currentBalance = pm.response.json().data.balance;
    pm.expect(currentBalance).to.be.below(previousBalance);
});
```

#### 3.3 Tests de Sécurité
```javascript
// Validation de sécurité
pm.test("No sensitive data exposed", () => {
    const jsonData = pm.response.json();
    pm.expect(JSON.stringify(jsonData)).to.not.include('password');
    pm.expect(JSON.stringify(jsonData)).to.not.include('secret');
    pm.expect(JSON.stringify(jsonData)).to.not.include('token');
});

pm.test("CORS headers are present", () => {
    pm.response.to.have.header("Access-Control-Allow-Origin");
});
```

### Phase 4: Scénarios End-to-End

#### 4.1 Collection Runner Scripts
```javascript
// Scénario: Commande complète Amilon
const scenarios = [
    {
        name: "1. Get Merchants",
        request: "GET /vouchers/amilon/merchants"
    },
    {
        name: "2. Get Products",
        request: "GET /vouchers/amilon/products?merchant_id={{merchant_id}}"
    },
    {
        name: "3. Check Balance",
        request: "GET /credits/balance"
    },
    {
        name: "4. Create Order",
        request: "POST /vouchers/amilon/orders"
    },
    {
        name: "5. Verify Order",
        request: "GET /vouchers/amilon/orders/{{order_id}}"
    }
];
```

#### 4.2 Data-Driven Testing
```csv
product_id,amount,expected_status,expected_error
FNAC-100,100,201,
FNAC-500,50000,402,INSUFFICIENT_CREDITS
INVALID,100,404,PRODUCT_NOT_FOUND
FNAC-100,-10,422,INVALID_AMOUNT
```

### Phase 5: Environnements

#### 5.1 Configuration Multi-Environnements
```json
// Local Development
{
    "name": "Local",
    "values": [
        {"key": "protocol", "value": "http"},
        {"key": "host", "value": "localhost"},
        {"key": "port", "value": "1310"},
        {"key": "test_email", "value": "test@local.com"},
        {"key": "test_password", "value": "password123"}
    ]
}

// Staging
{
    "name": "Staging",
    "values": [
        {"key": "protocol", "value": "https"},
        {"key": "host", "value": "staging-api.up-engage.com"},
        {"key": "port", "value": "443"},
        {"key": "test_email", "value": "test@staging.com"},
        {"key": "test_password", "value": "{{STAGING_PASSWORD}}"}
    ]
}

// Production
{
    "name": "Production",
    "values": [
        {"key": "protocol", "value": "https"},
        {"key": "host", "value": "api.up-engage.com"},
        {"key": "port", "value": "443"},
        {"key": "test_email", "value": "monitor@prod.com"},
        {"key": "test_password", "value": "{{PROD_PASSWORD}}"}
    ]
}
```

### Phase 6: CI/CD Integration

#### 6.1 Newman Configuration
```json
// newman-config.json
{
    "collection": "./postman/UP-Engage-API.postman_collection.json",
    "environment": "./postman/environments/staging.postman_environment.json",
    "reporters": ["cli", "json", "html", "junit"],
    "reporter": {
        "json": {
            "export": "./reports/postman-results.json"
        },
        "html": {
            "export": "./reports/postman-results.html"
        },
        "junit": {
            "export": "./reports/postman-junit.xml"
        }
    },
    "bail": false,
    "insecure": false,
    "timeout": 180000,
    "timeoutRequest": 5000,
    "timeoutScript": 5000,
    "delayRequest": 0,
    "iterationCount": 1,
    "color": true,
    "verbose": false
}
```

#### 6.2 GitHub Actions
```yaml
# .github/workflows/postman-tests.yml
name: Postman API Tests

on:
  push:
    branches: [develop, main]
  pull_request:
    branches: [develop]

jobs:
  api-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Install Newman
      run: npm install -g newman newman-reporter-html
    
    - name: Run Postman Collection
      run: |
        newman run postman/UP-Engage-API.postman_collection.json \
          -e postman/environments/ci.postman_environment.json \
          --reporters cli,json,html \
          --reporter-json-export reports/results.json \
          --reporter-html-export reports/results.html
    
    - name: Upload Test Results
      if: always()
      uses: actions/upload-artifact@v2
      with:
        name: postman-reports
        path: reports/
```

### Phase 7: Monitoring

#### 7.1 Postman Monitors
```javascript
// Configuration du monitor
{
    "name": "Production Health Check",
    "schedule": "*/15 * * * *", // Every 15 minutes
    "environment": "Production",
    "collection": "Health Checks",
    "notifications": {
        "onFailure": ["team@company.com"],
        "onSuccess": false
    }
}
```

#### 7.2 Tests de Monitoring
```javascript
// Tests spécifiques au monitoring
pm.test("API is responding", () => {
    pm.response.to.have.status(200);
});

pm.test("Database connection is healthy", () => {
    const health = pm.response.json().data;
    pm.expect(health.database).to.equal('connected');
});

pm.test("Redis cache is operational", () => {
    const health = pm.response.json().data;
    pm.expect(health.cache).to.equal('operational');
});

pm.test("External services are reachable", () => {
    const health = pm.response.json().data;
    pm.expect(health.services.amilon).to.equal('reachable');
    pm.expect(health.services.wellwo).to.equal('reachable');
});
```

## 🔄 Versioning et Export

### Export Automatique
```bash
#!/bin/bash
# export-postman.sh

# Variables
COLLECTION_ID="your-collection-id"
API_KEY="your-postman-api-key"
OUTPUT_DIR="./postman/collections"

# Export collection
curl -X GET \
  "https://api.getpostman.com/collections/${COLLECTION_ID}" \
  -H "X-Api-Key: ${API_KEY}" \
  > "${OUTPUT_DIR}/collection-$(date +%Y%m%d).json"

# Commit to Git
git add ${OUTPUT_DIR}
git commit -m "chore: update Postman collection $(date +%Y-%m-%d)"
```

### Import dans le Projet
```json
// package.json
{
  "scripts": {
    "postman:export": "./scripts/export-postman.sh",
    "postman:test": "newman run postman/collection.json",
    "postman:test:staging": "newman run postman/collection.json -e postman/staging.env.json",
    "postman:monitor": "newman run postman/monitors/health.json"
  }
}
```

## 📋 Checklist de Validation

### Pour chaque nouvel endpoint
- [ ] Request créée dans la bonne collection
- [ ] Description complète ajoutée
- [ ] Exemples success et error ajoutés
- [ ] Variables d'environnement utilisées
- [ ] Headers corrects configurés
- [ ] Tests de base implémentés
- [ ] Tests métier ajoutés
- [ ] Tests de sécurité inclus
- [ ] Documentation des paramètres
- [ ] Pre-request script si nécessaire
- [ ] Collection exportée et versionnée

### Pour chaque release
- [ ] Tous les nouveaux endpoints documentés
- [ ] Tests end-to-end mis à jour
- [ ] Environnements synchronisés
- [ ] Newman tests passants
- [ ] Monitors configurés
- [ ] Documentation exportée
- [ ] Collection partagée avec l'équipe

## 🚀 Commandes Rapides

```bash
# Tester localement
newman run postman/collection.json -e postman/local.env.json

# Tester avec rapport HTML
newman run postman/collection.json -r html --reporter-html-export report.html

# Tester un dossier spécifique
newman run postman/collection.json --folder "Vouchers/Amilon"

# Tester avec données CSV
newman run postman/collection.json -d data.csv

# Exporter les résultats en JSON
newman run postman/collection.json -r json --reporter-json-export results.json
```

## 🔗 Intégration avec la Méthodologie

### Dans le workflow TDD
1. **Écrire les tests Postman** avant l'implémentation
2. **RED** : Les tests échouent (endpoint n'existe pas)
3. **GREEN** : Implémenter jusqu'à ce que les tests passent
4. **REFACTOR** : Optimiser en gardant les tests verts

### Dans la phase de validation
- Exécuter la collection complète via Newman
- Vérifier la couverture des scénarios
- Valider les performances
- Confirmer la documentation

### Pour la documentation
- Exporter en OpenAPI depuis Postman
- Générer la documentation client
- Publier sur Confluence
- Partager avec le frontend

## 📚 Ressources

- [Postman Learning Center](https://learning.postman.com/)
- [Newman Documentation](https://learning.postman.com/docs/running-collections/using-newman-cli/command-line-integration-with-newman/)
- [API Testing Best Practices](https://www.postman.com/api-platform/api-testing/)
- [Collection Format v2.1](https://schema.postman.com/)

---

*Ce guide fait partie de la méthodologie de développement backend API RESTful*
*Version : 1.0.0*