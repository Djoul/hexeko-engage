# Architecture & Patterns

## 🏗️ Vue d'ensemble

L'API UpEngage suit une architecture modulaire basée sur Laravel 12+ avec une séparation stricte des responsabilités et une isolation des intégrations externes.

## 📁 Structure du Projet

```
up-engage-api/
├── app/
│   ├── Actions/           # Orchestration de la logique métier
│   ├── Services/          # Logique métier pure
│   ├── Http/
│   │   ├── Controllers/   # Endpoints API (minimal)
│   │   ├── Requests/      # Validation des requêtes
│   │   └── Resources/     # Transformation des réponses
│   ├── Models/            # Eloquent ORM + traits de cache
│   ├── Events/            # Event Sourcing & Broadcasting
│   ├── DTOs/              # Data Transfer Objects
│   ├── Repositories/      # Abstraction de la persistance ⚠️ Sera supprimé à très court terme.
│   └── Integrations/      # 🔌 Modules externes isolés
├── database/
│   ├── migrations/        # Migrations du domaine principal
│   ├── seeders/          # Données de test
│   └── factories/        # Factories pour les tests
├── tests/
│   ├── Unit/             # Tests unitaires isolés
│   ├── Feature/          # Tests d'intégration
│   └── Integration/      # Tests des modules externes
└── documentation/        # Documentation technique
```

## 🎯 Service/Action Pattern

### Flux de données

```
Request → Controller → FormRequest → Action → Service → ~~Repository~~ → Model
          (minimal)    (validation)  (orchestration) (business)  (data)  (ORM)
```

### 1. Controllers (Minimal)

Les controllers sont **uniquement** des points d'entrée HTTP. Aucune logique métier.

```php
#[Route('POST', '/api/v1/orders')]
class OrderController extends Controller
{
    public function store(
        StoreOrderRequest $request,
        CreateOrderAction $action
    ): JsonResponse {
        $order = $action->execute($request->toDTO());
        
        return response()->json(
            new OrderResource($order),
            201
        );
    }
}
```

### 2. Actions (Orchestration)

Les Actions coordonnent plusieurs services et gèrent les transactions.

```php
namespace App\Actions\Order;

class CreateOrderAction
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly InventoryService $inventoryService,
        private readonly CreditService $creditService,
        private readonly NotificationService $notificationService
    ) {}

    public function execute(CreateOrderDTO $dto): OrderDTO
    {
        return DB::transaction(function () use ($dto) {
            // 1. Vérifications métier
            $this->inventoryService->ensureAvailability($dto->items);
            $this->creditService->ensureBalance($dto->userId, $dto->total);
            
            // 2. Opération principale
            $order = $this->orderService->create($dto);
            
            // 3. Effets de bord
            $this->inventoryService->reserve($order);
            $this->creditService->deduct($order);
            
            // 4. Événements
            event(new OrderCreated($order));
            
            // 5. Notifications
            $this->notificationService->notifyOrderCreated($order);
            
            return OrderDTO::fromModel($order);
        });
    }
}
```

### 3. Services (Logique métier)

Les Services contiennent la logique métier pure, sans dépendances HTTP.

```php
namespace App\Services;

class OrderService
{
    use Cachable;
    
    public function __construct(
        private readonly OrderRepository $repository
    ) {}
    
    public function create(CreateOrderDTO $dto): Order
    {
        // Logique métier pure
        $order = new Order();
        $order->user_id = $dto->userId;
        $order->total = $this->calculateTotal($dto->items);
        $order->status = OrderStatus::PENDING;
        
        // Appliquer les règles métier
        if ($this->isEligibleForDiscount($dto)) {
            $order->discount = $this->calculateDiscount($order);
        }
        
        return $this->repository->save($order);
    }
    
    private function calculateTotal(array $items): float
    {
        // Logique de calcul complexe
    }
}
```

### 4. DTOs (Data Transfer Objects)

Les DTOs garantissent le typage fort et la validation des données.

```php
namespace App\DTOs\Order;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\Min;

class CreateOrderDTO extends Data
{
    public function __construct(
        #[Required]
        public readonly string $userId,
        
        #[Required]
        public readonly array $items,
        
        #[Min(0)]
        public readonly float $total,
        
        public readonly ?string $couponCode = null
    ) {}
    
    public static function fromRequest(Request $request): self
    {
        return new self(
            userId: $request->user()->id,
            items: $request->input('items'),
            total: $request->input('total'),
            couponCode: $request->input('coupon_code')
        );
    }
}
```

## 🔌 Architecture des Intégrations

### Principe d'isolation

Chaque intégration est **isolée dans un mini-projet Laravel** pour faciliter la maintenance et la suppression sans impact sur le code principal.

```
app/Integrations/
├── Amilon/               # 🔌 Module Amilon (vouchers)
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Requests/
│   ├── Models/
│   ├── Services/
│   ├── Database/
│   │   ├── Migrations/   # Préfixées avec int_amilon_
│   │   └── Seeders/
│   ├── Config/
│   ├── Routes/
│   │   └── api.php       # Routes isolées
│   └── AmilonServiceProvider.php
│
├── Apideck/              # 🔌 Module Apideck ⚠️doit être refactoré
│   ├── Http/
│   ├── Models/
│   ├── Services/
│   ├── Database/
│   │   └── Migrations/   # Préfixées avec int_apideck_
│   ├── Config/
│   └── ApideckServiceProvider.php
│
├── WellBeing/            # 🔌 Module WellBeing
│   └── [structure similaire]
│
└── Stripe/               # 🔌 Module Stripe
    └── [structure similaire]
```


### Conventions des intégrations

1. **Préfixes de tables** : `int_<integration>_` (ex: `int_amilon_orders`)
2. **Namespace** : `App\Integrations\<module>\<Integration>\`
3. **Routes** : `/api/v1/<integration>/`
4. **Configuration** : `config/integrations/<integration>.php`
5. **Tests** : `tests/Integration/<Integration>/`

## 📊 Event Sourcing pour les Crédits

### Architecture Event Sourcing

```php
namespace App\Events\Credit;

abstract class CreditEvent
{
    public function __construct(
        public readonly string $userId,
        public readonly string $financerId,
        public readonly float $amount,
        public readonly string $reason,
        public readonly Carbon $occurredAt
    ) {}
}

class CreditAllocated extends CreditEvent {}
class CreditConsumed extends CreditEvent {}
class CreditRefunded extends CreditEvent {}
class CreditExpired extends CreditEvent {}
```

### Projection des événements

```php
class CreditProjection
{
    public function handle(CreditEvent $event): void
    {
        match (get_class($event)) {
            CreditAllocated::class => $this->handleAllocation($event),
            CreditConsumed::class => $this->handleConsumption($event),
            CreditRefunded::class => $this->handleRefund($event),
            CreditExpired::class => $this->handleExpiration($event),
        };
    }
    
    private function handleAllocation(CreditAllocated $event): void
    {
        DB::table('credit_balances')
            ->where('user_id', $event->userId)
            ->increment('balance', $event->amount);
            
        DB::table('credit_events')->insert([
            'type' => 'allocated',
            'user_id' => $event->userId,
            'amount' => $event->amount,
            'metadata' => json_encode($event),
            'occurred_at' => $event->occurredAt
        ]);
    }
}
```

## 🗄️ Repository Pattern 
⚠️ Initialement prévu, mais vu qu'il n'y a aucun intérêt et aucun souhait de quitter Eloquent, le pattern est superflu et sera supprimé lors des prochains refacteurs.

### Interface Repository

```php
namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    public function all(array $columns = ['*']): Collection;
    public function find(string $id, array $columns = ['*']): ?Model;
    public function create(array $data): Model;
    public function update(string $id, array $data): bool;
    public function delete(string $id): bool;
    public function paginate(int $perPage = 15): LengthAwarePaginator;
}
```

### Implementation Repository

```php
namespace App\Repositories;

class UserRepository implements UserRepositoryInterface
{
    use Cachable;
    
    public function __construct(
        private readonly User $model
    ) {}
    
    public function findByEmail(string $email): ?User
    {
        return Cache::tags(['users'])->remember(
            "user:email:{$email}",
            300,
            fn() => $this->model->where('email', $email)->first()
        );
    }
    
    public function findActiveByFinancer(string $financerId): Collection
    {
        return $this->model
            ->whereHas('financers', function ($query) use ($financerId) {
                $query->where('financer_id', $financerId)
                      ->where('active', true);
            })
            ->where('enabled', true)
            ->get();
    }
}
```

## 🔒 Permissions & Roles (Spatie)

### Structure des rôles

```php
namespace App\Constants;

class RoleDefaults
{
    // Rôles système
    public const SUPER_ADMIN = 'super-admin';
    
    // Rôles financer
    public const FINANCER_SUPER_ADMIN = 'financer-super-admin';
    public const FINANCER_ADMIN = 'financer-admin';
    public const FINANCER_MANAGER = 'financer-manager';
    
    // Rôles utilisateur
    public const BENEFICIARY = 'beneficiary';
    public const EMPLOYEE = 'employee';
}
```

### Permissions modulaires

```php
class PermissionsDefaults
{
    // Permissions par module
    public const VOUCHERS = [
        'vouchers.view',
        'vouchers.create',
        'vouchers.use',
        'vouchers.cancel'
    ];
    
    public const CREDITS = [
        'credits.view',
        'credits.allocate',
        'credits.transfer'
    ];
    
    public const METRICS = [
        'metrics.view',
        'metrics.export',
        'view_financer_metrics'
    ];
}
```

## 🚀 Cache Strategy 
⚠️ Initialement prévu, mais en cours de refactore, car problématique avec l'utilisation de Redis Cluster sur les environnements déployés.

### Traits de cache

```php
// GlobalCachable pour cache statique
trait GlobalCachable
{
    protected static int $cacheTtl = 300;
    
    public static function findCached(string $id): ?static
    {
        $cacheKey = static::getCacheKey($id);
        
        return Cache::tags([static::getCacheTag()])
            ->remember($cacheKey, static::$cacheTtl, function () use ($id) {
                return static::find($id);
            });
    }
    
    public static function flushCache(): void
    {
        Cache::tags([static::getCacheTag()])->flush();
    }
}

// Cachable pour cache d'instance
trait Cachable
{
    protected int $cacheTtl = 300;
    
    public function cacheKey(string $suffix = ''): string
    {
        return sprintf('%s:%s%s', 
            $this->getTable(),
            $this->getKey(),
            $suffix ? ":{$suffix}" : ''
        );
    }
}
```

## 🧪 Testing Strategy

### Structure des tests

```
tests/
├── Unit/              # Tests unitaires (mocks)
│   ├── Services/
│   ├── Actions/
│   └── Models/
├── Feature/           # Tests API endpoints
│   ├── Auth/
│   ├── Orders/
│   └── Credits/

```

### Conventions de Test

**IMPORTANT**: Utiliser les attributs PHP 8 pour les tests :
- **`#[Test]`** : Pour déclarer une méthode de test (PAS `/** @test */`)
- **`#[Group('name')]`** : Pour grouper les tests par module/fonctionnalité

```php
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

#[Group('orders')]
class OrderTest extends TestCase
{
    #[Test]
    public function it_creates_order_successfully(): void
    {
        // Test implementation
    }
    
    #[Test]
    #[Group('payments')]
    public function it_processes_payment_for_order(): void
    {
        // Test avec multiple groupes
    }
}
```

### Base Test Classes

```php
// Pour les routes protégées
🚨 C'est la classe de base utilisée pour tous les tests qui testent des endpoints protégés. 
Ils bypassent les différents middlewares. Les rôles, permissions et autres middleware d'authentification ont des tests qui leur sont dédiés. 
Il n'est donc pas nécessaire de retester cette partie dans chaque intégration ou fonctionnalité.

abstract class ProtectedRouteTestCase extends TestCase
{
    use DatabaseTransactions;
    
    protected User $auth;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser();
    }
}

```

## 🔄 Queue & Jobs

### Job Pattern

```php
class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        private readonly Order $order
    ) {}
    
    public function handle(
        OrderService $orderService,
        NotificationService $notificationService
    ): void {
        try {
            $orderService->process($this->order);
            $notificationService->notifyProcessed($this->order);
        } catch (\Exception $e) {
            $this->fail($e);
        }
    }
    
    public function failed(\Throwable $exception): void
    {
        Log::error('Order processing failed', [
            'order_id' => $this->order->id,
            'error' => $exception->getMessage()
        ]);
    }
}
```

## 📈 Performance Optimizations

### Query Optimization 

```php
// Eager loading pour éviter N+1 
$users = User::with(['financers', 'roles', 'permissions'])
    ->where('active', true)
    ->get();

// Chunking pour grandes données
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // Process user
    }
});

// Cursor pour memory efficiency
foreach (User::cursor() as $user) {
    // Process user
}
```



## 🔐 Security Patterns

### Request Validation

```php
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }
    
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', new ValidCoupon()]
        ];
    }
    
    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user()->id,
            'financer_id' => $this->header('X-Financer-Context')
        ]);
    }
}
```

### API Rate Limiting

```php
// Dans RouteServiceProvider
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('metrics', function (Request $request) {
    return Limit::perMinute(30)->by($request->header('X-Financer-Context'));
});
```

## 📚 Best Practices

### 1. SOLID Principles
- **S**ingle Responsibility: Une classe = une responsabilité
- **O**pen/Closed: Ouvert à l'extension, fermé à la modification
- **L**iskov Substitution: Les sous-classes doivent être substituables
- **I**nterface Segregation: Interfaces spécifiques plutôt que générales
- **D**ependency Inversion: Dépendre des abstractions

### 2. Clean Code
- Noms explicites et meaningfuls
- Fonctions courtes (< 20 lignes)
- Pas de magic numbers
- DRY (Don't Repeat Yourself)
- KISS (Keep It Simple, Stupid)

### 3. Laravel Conventions
- Utiliser les Resources pour les API responses
- FormRequests pour validation
- Uses `#[RequiresPermission()]` attribute on controller methods for authorization
- Observers pour model events
- Scopes pour requêtes réutilisables

### 4. Testing
- TDD obligatoire
- Coverage minimum 80%
- Tests isolés et rapides
- Fixtures réutilisables
- Mocks pour services externes

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko  
**Architecture Version**: 2.0
