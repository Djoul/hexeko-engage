# Template de Scénarios de Tests Postman

## 📋 Scénarios de Base (Obligatoires)

### 1. Authentication Flow
```javascript
// Scénario: Cycle complet d'authentification
describe('Authentication Flow', () => {
    it('should login successfully', () => {
        // POST /auth/login
        // Assert: 200, token received
        // Store: auth_token, refresh_token
    });
    
    it('should access protected resource', () => {
        // GET /api/protected
        // Headers: Authorization: Bearer {{auth_token}}
        // Assert: 200, data received
    });
    
    it('should refresh token', () => {
        // POST /auth/refresh
        // Body: { refresh_token: {{refresh_token}} }
        // Assert: 200, new tokens received
    });
    
    it('should logout successfully', () => {
        // POST /auth/logout
        // Assert: 204
        // Clear: auth_token, refresh_token
    });
});
```

### 2. CRUD Operations
```javascript
// Scénario: Opérations CRUD complètes
describe('CRUD Operations', () => {
    let createdId;
    
    it('should list all items', () => {
        // GET /items
        // Assert: 200, array of items
        // Store: first item_id if exists
    });
    
    it('should create new item', () => {
        // POST /items
        // Body: { name, description, ... }
        // Assert: 201, item created
        // Store: createdId = response.data.id
    });
    
    it('should retrieve created item', () => {
        // GET /items/{{createdId}}
        // Assert: 200, item details match
    });
    
    it('should update item', () => {
        // PUT /items/{{createdId}}
        // Body: { name: "Updated", ... }
        // Assert: 200, item updated
    });
    
    it('should delete item', () => {
        // DELETE /items/{{createdId}}
        // Assert: 204, no content
    });
    
    it('should verify deletion', () => {
        // GET /items/{{createdId}}
        // Assert: 404, not found
    });
});
```

### 3. Error Handling
```javascript
// Scénario: Gestion des erreurs
describe('Error Handling', () => {
    it('should handle 400 bad request', () => {
        // POST /items
        // Body: malformed JSON
        // Assert: 400, error message
    });
    
    it('should handle 401 unauthorized', () => {
        // GET /protected
        // Headers: no auth token
        // Assert: 401
    });
    
    it('should handle 403 forbidden', () => {
        // DELETE /admin/users/1
        // Headers: user token (not admin)
        // Assert: 403
    });
    
    it('should handle 404 not found', () => {
        // GET /items/non-existent
        // Assert: 404
    });
    
    it('should handle 422 validation error', () => {
        // POST /items
        // Body: { price: -100 }
        // Assert: 422, validation errors
    });
    
    it('should handle 429 rate limit', () => {
        // Loop 100+ requests quickly
        // Assert: 429, rate limit message
    });
    
    it('should handle 500 server error gracefully', () => {
        // Trigger server error scenario
        // Assert: proper error response
    });
});
```

## 🎯 Scénarios Métier Spécifiques

### Vouchers - Amilon Integration
```javascript
// Scénario: Achat de voucher complet
describe('Amilon Voucher Purchase', () => {
    let merchantId;
    let productId;
    let orderId;
    let initialBalance;
    
    it('should get user credit balance', () => {
        // GET /credits/balance
        // Store: initialBalance
    });
    
    it('should list available merchants', () => {
        // GET /vouchers/amilon/merchants
        // Assert: merchants array
        // Store: merchantId = first merchant
    });
    
    it('should get merchant products', () => {
        // GET /vouchers/amilon/products?merchant_id={{merchantId}}
        // Assert: products array
        // Store: productId = first affordable product
    });
    
    it('should validate product availability', () => {
        // GET /vouchers/amilon/products/{{productId}}/availability
        // Assert: available = true
    });
    
    it('should create order', () => {
        // POST /vouchers/amilon/orders
        // Body: { product_id, amount, quantity }
        // Assert: 201, order created
        // Store: orderId
    });
    
    it('should verify credit deduction', () => {
        // GET /credits/balance
        // Assert: balance < initialBalance
    });
    
    it('should get order details', () => {
        // GET /vouchers/amilon/orders/{{orderId}}
        // Assert: order status, voucher codes
    });
    
    it('should be in order history', () => {
        // GET /vouchers/amilon/orders
        // Assert: orderId in list
    });
});
```

### Credits Management
```javascript
// Scénario: Gestion des crédits
describe('Credits Management', () => {
    let currentBalance;
    let transactionId;
    
    it('should get current balance', () => {
        // GET /credits/balance
        // Store: currentBalance
    });
    
    it('should check credit limit', () => {
        // GET /credits/limit
        // Assert: limit > 0
    });
    
    it('should simulate debit', () => {
        // POST /credits/debit
        // Body: { amount: 10, reason: "test" }
        // Assert: 200, new balance
        // Store: transactionId
    });
    
    it('should verify transaction in history', () => {
        // GET /credits/history
        // Assert: transactionId in list
    });
    
    it('should handle insufficient credits', () => {
        // POST /credits/debit
        // Body: { amount: 999999 }
        // Assert: 402, insufficient credits
    });
});
```

## 🔄 Scénarios de Performance

### Load Testing
```javascript
// Scénario: Test de charge
describe('Load Testing', () => {
    it('should handle concurrent requests', () => {
        // Run 50 parallel requests
        // Assert: all succeed
        // Assert: avg response time < 500ms
    });
    
    it('should maintain performance under load', () => {
        // Run 100 requests over 10 seconds
        // Assert: 95th percentile < 1000ms
    });
});
```

### Data Volume Testing
```javascript
// Scénario: Test avec volumes importants
describe('Data Volume Testing', () => {
    it('should paginate large datasets', () => {
        // GET /items?per_page=100
        // Assert: pagination works
        // Assert: response time acceptable
    });
    
    it('should handle bulk operations', () => {
        // POST /items/bulk
        // Body: array of 100 items
        // Assert: all created
        // Assert: response time < 5s
    });
});
```

## 🔐 Scénarios de Sécurité

### Security Validation
```javascript
// Scénario: Validation de sécurité
describe('Security Validation', () => {
    it('should not expose sensitive data', () => {
        // GET /users/profile
        // Assert: no password field
        // Assert: no internal IDs
    });
    
    it('should validate JWT properly', () => {
        // GET /protected
        // Headers: malformed JWT
        // Assert: 401
    });
    
    it('should prevent SQL injection', () => {
        // GET /items?id=1' OR '1'='1
        // Assert: 400 or proper escaping
    });
    
    it('should prevent XSS', () => {
        // POST /items
        // Body: { name: "<script>alert('xss')</script>" }
        // Assert: sanitized in response
    });
    
    it('should enforce CORS', () => {
        // Check CORS headers
        // Assert: proper origin restrictions
    });
});
```

## 📊 Scénarios de Reporting

### Metrics Collection
```javascript
// Scénario: Collecte de métriques
describe('Metrics Collection', () => {
    const metrics = {
        responseTimes: [],
        errorRates: {},
        throughput: 0
    };
    
    afterEach(() => {
        // Collect response time
        metrics.responseTimes.push(pm.response.responseTime);
        
        // Track errors
        const status = pm.response.code;
        metrics.errorRates[status] = (metrics.errorRates[status] || 0) + 1;
    });
    
    after(() => {
        // Calculate statistics
        const avg = metrics.responseTimes.reduce((a, b) => a + b) / metrics.responseTimes.length;
        const p95 = percentile(metrics.responseTimes, 0.95);
        
        // Generate report
        console.log('Performance Report:');
        console.log(`Average Response Time: ${avg}ms`);
        console.log(`95th Percentile: ${p95}ms`);
        console.log(`Error Rate: ${calculateErrorRate(metrics.errorRates)}%`);
    });
});
```

## 🧪 Template de Tests Unitaires

### Test Structure
```javascript
// Structure standard pour chaque endpoint
pm.test("Status code validation", () => {
    pm.response.to.have.status(200);
});

pm.test("Response time validation", () => {
    pm.expect(pm.response.responseTime).to.be.below(500);
});

pm.test("Content-Type validation", () => {
    pm.response.to.have.header("Content-Type");
    pm.expect(pm.response.headers.get("Content-Type")).to.include("application/json");
});

pm.test("Response structure validation", () => {
    const jsonData = pm.response.json();
    
    // Check main structure
    pm.expect(jsonData).to.be.an('object');
    pm.expect(jsonData).to.have.property('data');
    pm.expect(jsonData).to.have.property('meta');
    
    // Validate data types
    pm.expect(jsonData.data).to.be.an('array');
    pm.expect(jsonData.meta).to.be.an('object');
});

pm.test("Business logic validation", () => {
    const jsonData = pm.response.json();
    
    // Custom business rules
    jsonData.data.forEach(item => {
        pm.expect(item.price).to.be.above(0);
        pm.expect(item.status).to.be.oneOf(['active', 'inactive', 'pending']);
    });
});

pm.test("Security validation", () => {
    // No sensitive data exposed
    const responseText = pm.response.text();
    pm.expect(responseText).to.not.include('password');
    pm.expect(responseText).to.not.include('secret');
    
    // Security headers present
    pm.response.to.have.header("X-Content-Type-Options");
    pm.response.to.have.header("X-Frame-Options");
});
```

## 🚀 Scénarios d'Intégration Continue

### Newman CI Pipeline
```yaml
# .github/workflows/postman-tests.yml
name: API Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
  schedule:
    - cron: '0 */6 * * *'  # Every 6 hours

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        environment: [staging, production]
    
    steps:
      - uses: actions/checkout@v2
      
      - name: Install Newman
        run: |
          npm install -g newman
          npm install -g newman-reporter-htmlextra
      
      - name: Run Collection
        run: |
          newman run postman/collection.json \
            -e postman/env.${{ matrix.environment }}.json \
            -r cli,htmlextra,json \
            --reporter-htmlextra-export reports/report.html \
            --reporter-json-export reports/results.json
      
      - name: Upload Reports
        if: always()
        uses: actions/upload-artifact@v2
        with:
          name: test-reports-${{ matrix.environment }}
          path: reports/
      
      - name: Notify on Failure
        if: failure()
        uses: 8398a7/action-slack@v3
        with:
          status: failure
          text: 'API Tests Failed on ${{ matrix.environment }}'
```

## 📈 Monitoring Scenarios

### Health Check Monitor
```javascript
// Monitor: Health check every 5 minutes
pm.test("API is responsive", () => {
    pm.response.to.have.status(200);
});

pm.test("Critical services operational", () => {
    const health = pm.response.json();
    pm.expect(health.database).to.eql('healthy');
    pm.expect(health.redis).to.eql('healthy');
});

// Alert if down
if (pm.response.code !== 200) {
    // Trigger alert
    console.error('ALERT: API is down!');
}
```

### Performance Monitor
```javascript
// Monitor: Performance degradation
const baseline = 200; // ms

pm.test("Performance within baseline", () => {
    pm.expect(pm.response.responseTime).to.be.below(baseline * 1.5);
});

// Track trend
pm.globals.set('perf_history', [
    ...(pm.globals.get('perf_history') || []),
    {
        timestamp: new Date().toISOString(),
        responseTime: pm.response.responseTime
    }
].slice(-100)); // Keep last 100
```

---

*Ce template fait partie de la méthodologie de développement backend API RESTful*
*À adapter selon les besoins spécifiques de chaque module*