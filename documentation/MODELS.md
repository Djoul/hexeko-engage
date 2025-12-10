# Models & Database Strategy

Guide complet pour l'organisation des modèles, la stratégie VIBE et les conventions de base de données.

## 🎯 Stratégie VIBE

### Vue d'ensemble
VIBE (Visibility, Intention, Behavior, Encapsulation) est notre stratégie d'organisation des modèles pour maintenir la clarté et la maintenabilité.

- **V**isibility : Contrôle de la visibilité des propriétés et méthodes
- **I**ntention : Code expressif qui révèle l'intention métier
- **B**ehavior : Encapsulation du comportement dans des traits spécialisés
- **E**ncapsulation : Protection des invariants métier

## 🔓 Unguarded Models Strategy

### Principe Fondamental
Le projet utilise la stratégie **Unguarded Models** pour simplifier la gestion de l'assignation en masse. Cette approche élimine le besoin de maintenir des listes `$fillable` qui deviennent rapidement obsolètes et sources d'erreurs.

```php
// Dans AppServiceProvider ou un provider dédié
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    // Désactiver globalement la protection contre l'assignation en masse
    Model::unguard();
}
```

### Pourquoi Unguarded Models ?

1. **Simplicité** : Pas besoin de maintenir des listes `$fillable` dans chaque modèle
2. **Flexibilité** : Les DTOs et FormRequests gèrent déjà la validation des données
3. **Performance** : Évite les vérifications inutiles d'Eloquent sur chaque assignation
4. **Sécurité** : La validation se fait en amont (FormRequest, DTOs, Services)

### Implémentation dans les Modèles

```php
class User extends Model
{
    // ❌ NE PAS UTILISER - Inutile avec unguarded
    // protected $fillable = ['name', 'email', ...];
    
    // ❌ NE PAS UTILISER - Redondant avec unguarded
    // protected $guarded = [];
    
    // ✅ CORRECT - Aucune déclaration nécessaire
    // Le modèle accepte toute assignation, la validation est faite en amont
}
```

### Sécurité avec Unguarded Models

Bien que les modèles soient "unguarded", la sécurité est assurée par plusieurs couches :

1. **FormRequests** : Validation et autorisation des données entrantes
   ```php
   class StoreUserRequest extends FormRequest
   {
       public function rules(): array
       {
           return [
               'email' => 'required|email|unique:users',
               'name' => 'required|string|max:255',
               // Seuls ces champs seront passés au modèle
           ];
       }
   }
   ```

2. **DTOs** : Typage fort et validation métier
   ```php
   class CreateUserDTO extends Data
   {
       public function __construct(
           #[Required, Email]
           public readonly string $email,
           
           #[Required, Max(255)]
           public readonly string $name,
           
           // Propriétés strictement typées
       ) {}
   }
   ```

3. **Services** : Contrôle explicite des données
   ```php
   class UserService
   {
       public function create(CreateUserDTO $dto): User
       {
           // Contrôle total sur ce qui est assigné
           return User::create([
               'email' => $dto->email,
               'name' => $dto->name,
               'team_id' => $this->determineTeam($dto),
               // Assignation explicite et contrôlée
           ]);
       }
   }
   ```

### Avantages de cette Approche

- **Pas de désynchronisation** : Plus de problème de champs oubliés dans `$fillable`
- **Tests simplifiés** : Les factories peuvent créer des modèles sans restrictions
- **Développement rapide** : Pas besoin de modifier les modèles à chaque nouveau champ
- **Validation centralisée** : Un seul endroit pour les règles (FormRequest/DTO)

## 📁 Organisation des Modèles

### Structure des Fichiers
```
app/
├── Models/
│   ├── User.php
│   ├── Order.php
│   ├── Traits/
│   │   ├── UserFiltersAndScopes.php
│   │   ├── UserRelations.php
│   │   ├── UserAccessorsAndHelpers.php
│   │   ├── Cachable.php
│   │   └── GlobalCachable.php
│   └── Concerns/
│       ├── HasUuid.php
│       ├── HasFinancerContext.php
│       └── HasAuditLog.php
│
└── Integrations/
    └── Amilon/
        ├── Models/
        │   └── AmilonVoucher.php
        └── Traits/
            └── AmilonVoucherRelations.php
```

## 🔧 Traits et leur Utilisation

### Règles d'Organisation des Traits

Les traits sont utilisés **uniquement si plusieurs méthodes sont nécessaires**. Un trait avec une seule méthode doit être intégré directement dans le modèle.

| Type de Trait | Rôle | Localisation | Quand l'utiliser |
|---------------|------|--------------|------------------|
| `*FiltersAndScopes` | Scopes de requête, pipelines | `app/Models/Traits/` | ≥ 3 scopes |
| `*Relations` | Relations Eloquent | `app/Models/Traits/` ou par module | ≥ 5 relations |
| `*AccessorsAndHelpers` | Getters, setters, helpers | `app/Models/Traits/` | ≥ 3 helpers |
| `Cachable` | Cache d'instance | `app/Models/Traits/` | Cache par modèle |
| `GlobalCachable` | Cache statique | `app/Models/Traits/` | Cache global |

### Exemple d'Implémentation Complète

```php
namespace App\Models;

use App\Models\Traits\UserFiltersAndScopes;
use App\Models\Traits\UserRelations;
use App\Models\Traits\UserAccessorsAndHelpers;
use App\Models\Traits\Cachable;
use App\Models\Concerns\HasUuid;
use App\Models\Concerns\HasFinancerContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use HasFactory;
    use HasUuid;
    use HasFinancerContext;
    use UserFiltersAndScopes;
    use UserRelations;
    use UserAccessorsAndHelpers;
    use Cachable;

    /**
     * Cache TTL en secondes
     */
    protected int $cacheTtl = 300; // 5 minutes

    /**
     * The attributes that are mass assignable.
     * NEVER use $fillable - use $guarded instead
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'cognito_id',
        'mfa_secret'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birthdate' => 'date',
        'enabled' => 'boolean',
        'metadata' => 'array',
        'settings' => 'json',
        'last_login_at' => 'datetime'
    ];

    /**
     * The attributes that should be appended to arrays.
     */
    protected $appends = [
        'full_name',
        'is_active',
        'has_valid_subscription'
    ];
}
```

## 📚 Trait FiltersAndScopes

### Structure et Conventions

```php
namespace App\Models\Traits;

trait UserFiltersAndScopes
{
    /**
     * Scope pour les utilisateurs actifs
     */
    public function scopeActive($query)
    {
        return $query->where('enabled', true)
                     ->whereNotNull('email_verified_at');
    }

    /**
     * Scope pour filtrer par financer
     */
    public function scopeByFinancer($query, string $financerId)
    {
        return $query->whereHas('financers', function ($q) use ($financerId) {
            $q->where('financer_id', $financerId)
              ->where('active', true);
        });
    }

    /**
     * Scope pour recherche textuelle
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('email', 'like', "%{$search}%")
              ->orWhere('first_name', 'like', "%{$search}%")
              ->orWhere('last_name', 'like', "%{$search}%");
        });
    }

    /**
     * Pipeline de filtres
     */
    public function scopeFilter($query, array $filters)
    {
        return $query->when($filters['search'] ?? null, fn($q, $search) => 
                    $q->search($search)
                )
                ->when($filters['financer_id'] ?? null, fn($q, $id) => 
                    $q->byFinancer($id)
                )
                ->when($filters['active'] ?? null, fn($q) => 
                    $q->active()
                )
                ->when($filters['role'] ?? null, fn($q, $role) => 
                    $q->role($role)
                );
    }
}
```

## 🔗 Trait Relations

### Organisation des Relations

```php
namespace App\Models\Traits;

use App\Models\Financer;
use App\Models\Team;
use App\Models\Order;
use App\Models\Credit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait UserRelations
{
    /**
     * Relations BelongsTo
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Relations BelongsToMany
     */
    public function financers(): BelongsToMany
    {
        return $this->belongsToMany(Financer::class, 'user_financer')
                    ->withPivot(['active', 'sirh_id', 'from', 'to'])
                    ->withTimestamps()
                    ->using(UserFinancerPivot::class);
    }

    /**
     * Relations HasMany
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Relations avec scopes
     */
    public function activeFinancers(): BelongsToMany
    {
        return $this->financers()->wherePivot('active', true);
    }

    public function recentOrders(): HasMany
    {
        return $this->orders()
                    ->where('created_at', '>=', now()->subDays(30))
                    ->orderBy('created_at', 'desc');
    }
}
```

## 🛠️ Trait AccessorsAndHelpers

### Getters, Setters et Méthodes Utilitaires

```php
namespace App\Models\Traits;

use Illuminate\Support\Str;

trait UserAccessorsAndHelpers
{
    /**
     * Accesseurs (Getters)
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->enabled 
            && $this->email_verified_at !== null
            && $this->hasActiveFinancer();
    }

    public function getHasValidSubscriptionAttribute(): bool
    {
        return $this->subscriptions()
                    ->where('status', 'active')
                    ->where('expires_at', '>', now())
                    ->exists();
    }

    /**
     * Mutateurs (Setters)
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = Str::lower($value);
    }

    public function setFirstNameAttribute($value): void
    {
        $this->attributes['first_name'] = Str::title($value);
    }

    /**
     * Méthodes Helper
     */
    public function hasActiveFinancer(): bool
    {
        return $this->financers()
                    ->wherePivot('active', true)
                    ->exists();
    }

    public function getCurrentFinancer(): ?Financer
    {
        return $this->activeFinancers()->first();
    }

    public function getTotalCredits(): float
    {
        return $this->credits()
                    ->where('status', 'active')
                    ->sum('balance');
    }

    public function canAccessModule(string $module): bool
    {
        return $this->hasPermissionTo("access.{$module}")
            || $this->hasRole('super-admin');
    }

    /**
     * Méthodes de formatage
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'email' => $this->email,
            'financers' => $this->financers->pluck('name')->toArray(),
        ];
    }
}
```

## 💾 Stratégie de Cache

### GlobalCachable vs Cachable

| Trait | Usage | Méthodes | TTL par défaut |
|-------|-------|----------|----------------|
| `GlobalCachable` | Cache statique, méthodes de classe | `findCached()`, `flushCache()` | 300s |
| `Cachable` | Cache d'instance, méthodes d'objet | `cacheKey()`, `remember()` | 300s |

### Implementation GlobalCachable

```php
namespace App\Models\Traits;

use Illuminate\Support\Facades\Cache;

trait GlobalCachable
{
    protected static int $cacheTtl = 300;

    /**
     * Find avec cache
     */
    public static function findCached(string $id): ?static
    {
        $cacheKey = static::getCacheKey($id);
        
        return Cache::tags([static::getCacheTag()])
            ->remember($cacheKey, static::$cacheTtl, function () use ($id) {
                return static::find($id);
            });
    }

    /**
     * All avec cache
     */
    public static function allCached(): Collection
    {
        return Cache::tags([static::getCacheTag()])
            ->remember(static::getCacheKey('all'), static::$cacheTtl, function () {
                return static::all();
            });
    }

    /**
     * Invalider le cache
     */
    public static function flushCache(): void
    {
        Cache::tags([static::getCacheTag()])->flush();
    }

    /**
     * Générer la clé de cache
     */
    protected static function getCacheKey(string $suffix = ''): string
    {
        $table = (new static)->getTable();
        return $suffix ? "{$table}:{$suffix}" : $table;
    }

    /**
     * Tag de cache pour le modèle
     */
    protected static function getCacheTag(): string
    {
        return (new static)->getTable();
    }

    /**
     * Boot trait - invalider cache sur événements
     */
    protected static function bootGlobalCachable(): void
    {
        static::saved(fn() => static::flushCache());
        static::deleted(fn() => static::flushCache());
    }
}
```

### Implementation Cachable

```php
namespace App\Models\Traits;

use Illuminate\Support\Facades\Cache;

trait Cachable
{
    protected int $cacheTtl = 300;

    /**
     * Générer une clé de cache unique pour l'instance
     */
    public function cacheKey(string $suffix = ''): string
    {
        return sprintf('%s:%s%s', 
            $this->getTable(),
            $this->getKey(),
            $suffix ? ":{$suffix}" : ''
        );
    }

    /**
     * Remember cache pour méthodes d'instance
     */
    public function remember(string $key, \Closure $callback, ?int $ttl = null)
    {
        $cacheKey = $this->cacheKey($key);
        $ttl = $ttl ?? $this->cacheTtl;

        return Cache::tags([$this->getCacheTag()])
            ->remember($cacheKey, $ttl, $callback);
    }

    /**
     * Invalider le cache de l'instance
     */
    public function forgetCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget($this->cacheKey($key));
        } else {
            Cache::tags([$this->getCacheTag()])->flush();
        }
    }

    /**
     * Tag de cache pour l'instance
     */
    protected function getCacheTag(): string
    {
        return $this->getTable();
    }
}
```

## 🗄️ Conventions de Base de Données

### Règles de Nommage

| Élément | Convention | Exemple |
|---------|-----------|---------|
| Tables | snake_case pluriel | `users`, `order_items` |
| Colonnes | snake_case singulier | `first_name`, `created_at` |
| Clés primaires | `id` | `id` (UUID ou auto-increment) |
| Clés étrangères | `{table}_id` | `user_id`, `financer_id` |
| Tables pivot | alphabétique singulier | `financer_user`, `permission_role` |
| Tables d'intégration | `int_{integration}_` | `int_amilon_vouchers` |
| Index | `idx_{table}_{columns}` | `idx_users_email` |

### Structure de Migration

```php
// TOUJOURS séparer création et foreign keys
// 1. 2024_01_01_000001_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('email')->unique();
    $table->string('first_name');
    $table->string('last_name');
    $table->uuid('team_id')->nullable();
    $table->uuid('division_id')->nullable();
    $table->boolean('enabled')->default(true);
    $table->timestamp('email_verified_at')->nullable();
    $table->timestamps();
    
    // Index pour performances
    $table->index('email', 'idx_users_email');
    $table->index(['first_name', 'last_name'], 'idx_users_name');
});

// 2. 2024_01_01_000002_add_foreign_keys_to_users_table.php
Schema::table('users', function (Blueprint $table) {
    $table->foreign('team_id')
          ->references('id')
          ->on('teams')
          ->nullOnDelete(); // JAMAIS onDelete('cascade')
          
    $table->foreign('division_id')
          ->references('id')
          ->on('divisions')
          ->nullOnDelete();
});
```

### Tables d'Intégration

```php
// app/Integrations/Amilon/Database/Migrations/
// 2024_01_01_000001_create_int_amilon_vouchers_table.php

Schema::create('int_amilon_vouchers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('external_id')->unique(); // ID Amilon
    $table->uuid('user_id');
    $table->string('code')->unique();
    $table->decimal('amount', 10, 2);
    $table->string('status');
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->foreign('user_id')
          ->references('id')
          ->on('users')
          ->nullOnDelete();
          
    $table->index('external_id', 'idx_int_amilon_vouchers_external');
    $table->index('status', 'idx_int_amilon_vouchers_status');
});
```

## 🚫 Règles Strictes

### JAMAIS dans les Modèles

```php
// ❌ JAMAIS utiliser $fillable
protected $fillable = ['name', 'email']; // INTERDIT

// ✅ Utiliser $guarded à la place
protected $guarded = ['id', 'created_at', 'updated_at'];

// ❌ JAMAIS de logique métier dans les modèles
public function calculateDiscount() // INTERDIT
{
    // La logique métier va dans les Services
}

// ❌ JAMAIS d'appels API dans les modèles
public function syncWithExternalApi() // INTERDIT
{
    // Les intégrations vont dans les Services
}
```

### JAMAIS dans les Migrations

```php
// ❌ JAMAIS onDelete('cascade')
$table->foreign('user_id')
      ->references('id')
      ->on('users')
      ->onDelete('cascade'); // INTERDIT

// ✅ Utiliser nullOnDelete() ou rien
$table->foreign('user_id')
      ->references('id')
      ->on('users')
      ->nullOnDelete(); // CORRECT

// ❌ JAMAIS de données dans les migrations
DB::table('users')->insert([...]); // INTERDIT

// ✅ Utiliser les Seeders pour les données
// database/seeders/UserSeeder.php
```

## 🎯 Patterns Avancés

### Pivot Models Personnalisés

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserFinancerPivot extends Pivot
{
    protected $table = 'user_financer';
    
    protected $casts = [
        'active' => 'boolean',
        'from' => 'datetime',
        'to' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * Scope pour les relations actives
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)
                     ->where(function ($q) {
                         $q->whereNull('to')
                           ->orWhere('to', '>', now());
                     });
    }

    /**
     * Vérifier si la relation est active
     */
    public function isActive(): bool
    {
        return $this->active 
            && ($this->to === null || $this->to->isFuture());
    }
}
```

### Query Builders Personnalisés

```php
namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class UserQueryBuilder extends Builder
{
    /**
     * Utilisateurs avec crédits
     */
    public function withCredits(): self
    {
        return $this->whereHas('credits', function ($query) {
            $query->where('balance', '>', 0)
                  ->where('expires_at', '>', now());
        });
    }

    /**
     * Utilisateurs actifs récemment
     */
    public function recentlyActive(int $days = 30): self
    {
        return $this->where('last_login_at', '>=', now()->subDays($days));
    }

    /**
     * Pipeline de filtres complexes
     */
    public function applyFilters(array $filters): self
    {
        return $this->when($filters['has_credits'] ?? false, 
                        fn($q) => $q->withCredits()
                    )
                    ->when($filters['recently_active'] ?? false,
                        fn($q) => $q->recentlyActive($filters['days'] ?? 30)
                    )
                    ->when($filters['financer_ids'] ?? null,
                        fn($q, $ids) => $q->whereHas('financers', fn($q) => 
                            $q->whereIn('financer_id', $ids)
                        )
                    );
    }
}

// Dans le modèle User
public function newEloquentBuilder($query): UserQueryBuilder
{
    return new UserQueryBuilder($query);
}
```

### Observers pour Logique Transversale

```php
namespace App\Observers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\CacheService;

class UserObserver
{
    public function __construct(
        private readonly AuditService $auditService,
        private readonly CacheService $cacheService
    ) {}

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        $this->auditService->log('user.created', $user);
        $this->cacheService->invalidate('users.count');
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        $this->auditService->log('user.updated', $user, $user->getChanges());
        
        if ($user->wasChanged('email')) {
            $this->cacheService->invalidate("user.email.{$user->getOriginal('email')}");
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->auditService->log('user.deleted', $user);
        $this->cacheService->invalidate("user.{$user->id}");
    }
}

// Enregistrement dans AppServiceProvider
User::observe(UserObserver::class);
```

## 📊 Optimisation des Requêtes

### Eager Loading Obligatoire

```php
// ❌ INCORRECT - N+1 queries
$users = User::all();
foreach ($users as $user) {
    echo $user->financers->count(); // N+1 !
}

// ✅ CORRECT - Eager loading
$users = User::with('financers')->get();
foreach ($users as $user) {
    echo $user->financers->count(); // Déjà chargé
}

// ✅ OPTIMAL - Avec comptage
$users = User::withCount('financers')->get();
foreach ($users as $user) {
    echo $user->financers_count; // Attribut généré
}
```

### Chunking pour Grandes Données

```php
// Pour traiter de grandes quantités de données
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        // Traitement par batch de 1000
    }
});

// Avec cursor pour économie mémoire
foreach (User::cursor() as $user) {
    // Traitement un par un, mémoire optimale
}

// Lazy loading pour collections
User::lazy(1000)->each(function ($user) {
    // Traitement lazy par batch
});
```

## 🧪 Tests des Modèles

### Structure des Tests

```php
namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UserModelTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_has_fillable_attributes(): void
    {
        $user = new User();
        
        // Vérifier que guarded est utilisé, pas fillable
        $this->assertEmpty($user->getFillable());
        $this->assertContains('id', $user->getGuarded());
    }

    #[Test]
    public function it_casts_attributes_correctly(): void
    {
        $user = User::factory()->create([
            'metadata' => ['key' => 'value'],
            'enabled' => '1', // String qui doit être casté
        ]);

        $this->assertIsArray($user->metadata);
        $this->assertIsBool($user->enabled);
        $this->assertTrue($user->enabled);
    }

    #[Test]
    public function it_generates_cache_key_correctly(): void
    {
        $user = User::factory()->create();
        
        $expectedKey = "users:{$user->id}";
        $this->assertEquals($expectedKey, $user->cacheKey());
        
        $suffixKey = "users:{$user->id}:orders";
        $this->assertEquals($suffixKey, $user->cacheKey('orders'));
    }

    #[Test]
    #[Group('relations')]
    public function it_has_correct_relationships(): void
    {
        $user = User::factory()->create();
        
        // Vérifier les relations
        $this->assertInstanceOf(BelongsToMany::class, $user->financers());
        $this->assertInstanceOf(HasMany::class, $user->orders());
        $this->assertInstanceOf(BelongsTo::class, $user->team());
    }
}
```

## 📚 Best Practices

### 1. Séparation des Responsabilités
- Modèles : Structure et relations uniquement
- Services : Logique métier
- Actions : Orchestration
- Repositories : Accès aux données (si nécessaire)

### 2. Performance
- Toujours utiliser eager loading
- Indexer les colonnes de recherche
- Utiliser le cache pour les lectures fréquentes
- Chunking pour les grandes collections

### 3. Maintenabilité
- Un trait par responsabilité
- Noms explicites et en anglais
- Tests unitaires pour chaque scope/accessor
- Documentation des méthodes complexes

### 4. Sécurité
- Jamais de données sensibles dans $appends
- Toujours utiliser $hidden pour les secrets
- Validation dans les Services, pas les modèles
- Audit trail pour les opérations sensibles

---

**Last Updated**: 2025-09-06  
**Maintainer**: Équipe Hexeko  
**Version**: 1.0