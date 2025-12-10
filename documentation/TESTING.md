# Testing Guide

Guide complet pour les tests dans l'API UpEngage.

## 🎯 Philosophie TDD (Test-Driven Development)

### Règles Fondamentales

1. **Écrire les tests AVANT l'implémentation** - Sans exception
2. **Minimum 80% de couverture** requis
3. **Les tests sont des spécifications** - Ne jamais modifier les tests existants pour les faire passer
4. **Si un test échoue**, corriger l'implémentation, PAS le test
5. **Tout développement commence** par un ou plusieurs tests

## 📁 Structure des Tests

```
tests/
├── Unit/              # Services, Actions, Utils (tests isolés avec mocks)
├── Feature/           # Endpoints API, routes, intégration complète
└── Integration/       # Tests spécifiques aux modules avec #[Group('name')]
```

## ⚡ Conventions de Test OBLIGATOIRES

### Utilisation des Attributs PHP 8

```php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

// ✅ CORRECT - Utiliser les attributs PHP 8
#[Group('orders')]
class OrderTest extends TestCase
{
    #[Test]
    public function it_creates_order_successfully(): void
    {
        // Implementation
    }
    
    #[Test]
    #[Group('payments')]  // Peut avoir plusieurs groupes
    public function it_processes_payment(): void
    {
        // Implementation
    }
}

// ❌ INCORRECT - Ne PAS utiliser les annotations
/** @test */  // JAMAIS utiliser ceci
public function test_something(): void
{
    // Ne pas faire ça
}
```

## 🗄️ Strategy de Base de Données

### OBLIGATOIRE : DatabaseTransactions

```php
// ✅ CORRECT - Rapide, isolé
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions; // Transaction rollback après chaque test
}

// ❌ INCORRECT - Lent, drop la DB
use RefreshDatabase; // NE JAMAIS utiliser
```

### Pattern de Comptage pour Assertions

```php
#[Test]
public function it_creates_multiple_records(): void
{
    // Obtenir le count initial
    $initialCount = User::count();
    
    // Effectuer les actions
    $this->postJson('/api/v1/users', [...]);
    $this->postJson('/api/v1/users', [...]);
    
    // Assert basé sur le changement
    $this->assertEquals($initialCount + 2, User::count());
}

#[Test]
public function it_deletes_specific_records(): void
{
    // Créer les données de test
    $users = User::factory()->count(3)->create();
    $initialCount = User::count();
    
    // Supprimer un élément
    $this->deleteJson("/api/v1/users/{$users[0]->id}");
    
    // Assert que le count a diminué
    $this->assertEquals($initialCount - 1, User::count());
    $this->assertDatabaseMissing('users', ['id' => $users[0]->id]);
}
```

## 🔐 ProtectedRouteTestCase

### Objectif

La classe `ProtectedRouteTestCase` est conçue pour tester la **logique métier** sans se soucier de l'authentification.

### Principe Fondamental

```php
// 🚨 IMPORTANT
// Cette classe bypass les middlewares d'auth/permissions
// Les tests d'auth ont leurs propres tests dédiés
// Ne PAS retester l'auth dans chaque feature test

abstract class ProtectedRouteTestCase extends TestCase
{
    use DatabaseTransactions;
    
    protected User $auth;
    protected bool $checkAuth = true;        // Peut être désactivé
    protected bool $checkPermissions = true; // Peut être désactivé
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser();
    }
}
```

### Utilisation Correcte

```php
// ✅ CORRECT - Focus sur la logique métier
class ArticleTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_creates_article_with_valid_data(): void
    {
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/articles', [
                'title' => 'Test Article',
                'content' => 'Article content'
            ]);
        
        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'title', 'content']]);
    }
}

// ❌ INCORRECT - Ne pas tester l'auth ici
public function it_checks_if_user_has_permission(): void
{
    // Les tests d'auth sont ailleurs
}
```

### Bypass des Middlewares

```php
class ProductTest extends ProtectedRouteTestCase
{
    // Désactiver les vérifications pour focus sur la feature
    protected bool $checkAuth = false;
    protected bool $checkPermissions = false;
    
    #[Test]
    public function it_calculates_complex_discount(): void
    {
        // Test purement métier sans auth
    }
}
```

## 🏭 ModelFactory - Création de Données de Test

### Règles OBLIGATOIRES

1. **TOUJOURS utiliser ModelFactory** pour créer les modèles de test
2. **MAINTENIR la cohérence** des relations
3. **JAMAIS créer manuellement** sans factories
4. **Ordre de création** : Division → Financer → User

### Utilisation de ModelFactory

```php
use Tests\Helpers\Facades\ModelFactory;

// ✅ CORRECT - Utiliser ModelFactory
$division = ModelFactory::createDivision([
    'name' => 'Test Division',
    'status' => 'active'
]);

$financer = ModelFactory::createFinancer([
    'division_id' => $division->id,
    'name' => 'Test Financer',
    'credit_limit' => 50000
]);

$user = ModelFactory::createUser([
    'email' => 'user@test.com',
    'financers' => [
        ['financer' => $financer, 'active' => true]
    ]
]);

// ❌ INCORRECT - Création manuelle
$user = new User(['email' => 'test@test.com']);
$user->save();

// ❌ INCORRECT - Factory Laravel directe
$user = User::factory()->create();
```

### Méthodes Disponibles

```php
// Création avec persistance
ModelFactory::createUser([...]);
ModelFactory::createFinancer([...]);
ModelFactory::createDivision([...]);
ModelFactory::createTeam([...]);
ModelFactory::createRole([...]);
ModelFactory::createPermission([...]);

// Création sans persistance (make)
$userData = ModelFactory::makeUser([...]); // Non sauvé en DB
$financerData = ModelFactory::makeFinancer([...]);
```

### Relations Cohérentes

```php
#[Test]
public function it_maintains_relationship_coherence(): void
{
    // 1. Créer la division d'abord
    $division = ModelFactory::createDivision(['name' => 'Main Division']);
    
    // 2. Créer le financer lié à la division
    $financer = ModelFactory::createFinancer([
        'division_id' => $division->id,
        'name' => 'Main Financer'
    ]);
    
    // 3. Créer les users avec relations cohérentes
    $admin = ModelFactory::createUser([
        'email' => 'admin@test.com',
        'financers' => [
            ['financer' => $financer, 'active' => true]
        ]
    ]);
    
    // 4. Assigner les rôles
    $this->ensureRoleExists(RoleDefaults::FINANCER_ADMIN);
    $admin->assignRole(RoleDefaults::FINANCER_ADMIN);
    
    // 5. Tester avec données cohérentes
    $response = $this->actingAs($admin)
        ->postJson('/api/v1/financer/orders', [
            'division_id' => $division->id,
            'financer_id' => $financer->id
        ]);
    
    // 6. Vérifier l'intégrité des relations
    $this->assertDatabaseHas('orders', [
        'user_id' => $admin->id,
        'division_id' => $division->id,
        'financer_id' => $financer->id
    ]);
}
```

## 🎯 Patterns de Test par Type

### Tests Unitaires (Unit/)

```php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\OrderService;
use Mockery\MockInterface;

class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private MockInterface $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = $this->mock(OrderRepository::class);
        $this->service = new OrderService($this->repository);
    }
    
    #[Test]
    public function it_calculates_total_with_tax(): void
    {
        // Arrange
        $items = [
            ['price' => 100, 'quantity' => 2],
            ['price' => 50, 'quantity' => 1]
        ];
        
        // Act
        $total = $this->service->calculateTotal($items);
        
        // Assert
        $this->assertEquals(250, $total);
    }
}
```

### Tests de Feature (Feature/)

```php
namespace Tests\Feature\Orders;

use Tests\ProtectedRouteTestCase;
use Tests\Helpers\Facades\ModelFactory;

#[Group('orders')]
class OrderApiTest extends ProtectedRouteTestCase
{
    private Financer $financer;
    private Division $division;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->division = ModelFactory::createDivision();
        $this->financer = ModelFactory::createFinancer([
            'division_id' => $this->division->id
        ]);
        
        $this->auth->financers()->attach($this->financer);
    }
    
    #[Test]
    public function it_creates_order_with_valid_data(): void
    {
        // Arrange
        $orderData = [
            'division_id' => $this->division->id,
            'financer_id' => $this->financer->id,
            'items' => [
                ['product_id' => 'uuid-1', 'quantity' => 2]
            ]
        ];
        
        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/orders', $orderData);
        
        // Assert
        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'total',
                    'items'
                ]
            ]);
        
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->auth->id,
            'division_id' => $this->division->id
        ]);
    }
}
```

### Tests d'Intégration (Integration/)

```php
namespace Tests\Integration\Amilon;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;

#[Group('amilon')]
class AmilonSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock API externe
        Http::fake([
            'api.amilon.com/*' => Http::response([
                'success' => true,
                'data' => []
            ], 200)
        ]);
    }
    
    #[Test]
    public function it_syncs_products_from_amilon(): void
    {
        // Act
        $this->artisan('amilon:sync-products')
            ->assertSuccessful();
        
        // Assert
        $this->assertDatabaseHas('int_amilon_products', [
            'sync_status' => 'completed'
        ]);
    }
}
```

## 🚀 Commandes de Test

### Commandes Make

```bash
# Suite complète
make test

# Tests avec rapport
make test-with-report

# Tests optimisés
make test-optimized

# Tests par groupe
make test-group GROUPS="orders,payments"

# Tests échoués uniquement
make test-failed

# Coverage
make coverage
```

### Commandes Docker

```bash
# Tests simples
docker compose exec app_engage php artisan test

# Test spécifique
docker compose exec app_engage php artisan test --filter=OrderTest

# Tests par groupe
docker compose exec app_engage php artisan test --group=orders

# Avec coverage
docker compose exec app_engage php artisan test --coverage --min=80
```

## 📊 Métriques de Qualité

### Coverage Minimum

- **Global** : 80% minimum
- **Services** : 90% recommandé
- **Actions** : 85% recommandé
- **Controllers** : 70% (car minimalistes)

### Vérification

```bash
# Générer rapport de coverage
make coverage

# Vérifier le minimum
docker compose exec app_engage php artisan test --coverage --min=80
```

## ⚠️ Erreurs Communes à Éviter

### 1. RefreshDatabase

```php
// ❌ JAMAIS
use RefreshDatabase;

// ✅ TOUJOURS
use DatabaseTransactions;
```

### 2. Tests d'Auth dans Feature Tests

```php
// ❌ INCORRECT
#[Test]
public function it_requires_authentication(): void
{
    $this->getJson('/api/v1/orders')
        ->assertUnauthorized();
}

// ✅ CORRECT - Tester la feature
#[Test]
public function it_lists_user_orders(): void
{
    $orders = Order::factory()->count(3)->create(['user_id' => $this->auth->id]);
    
    $this->actingAs($this->auth)
        ->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(3, 'data');
}
```

### 3. Création Manuelle de Données

```php
// ❌ INCORRECT
$user = new User();
$user->email = 'test@test.com';
$user->save();

// ✅ CORRECT
$user = ModelFactory::createUser(['email' => 'test@test.com']);
```

### 4. Tests Sans Assertions

```php
// ❌ INCORRECT
#[Test]
public function it_does_something(): void
{
    $this->postJson('/api/v1/orders', [...]);
    // Pas d'assertion !
}

// ✅ CORRECT
#[Test]
public function it_creates_order(): void
{
    $response = $this->postJson('/api/v1/orders', [...]);
    
    $response->assertCreated();
    $this->assertDatabaseHas('orders', [...]);
}
```

## 🎓 Best Practices

1. **Nom des tests** : Utilisez `it_` ou `test_` avec description claire
2. **AAA Pattern** : Arrange, Act, Assert
3. **Un concept par test** : Testez une seule chose
4. **Tests indépendants** : Aucune dépendance entre tests
5. **Données minimales** : Créez seulement ce qui est nécessaire
6. **Mocks judicieux** : Mocker les services externes, pas la logique métier
7. **Tests rapides** : < 100ms pour unit, < 500ms pour feature

## 📚 Ressources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [TDD by Example - Kent Beck](https://www.amazon.com/Test-Driven-Development-Kent-Beck/dp/0321146530)
- Guide interne : Confluence "TDD Best Practices"

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko