# CLAUDE.md 


# Instructions for Claude

## Critical behavior

- Be direct and critical in your assessments
- If you see a problem, state it clearly without beating around the bush
- Challenge my assumptions and technical decisions
- Suggest alternatives even if I don't explicitly ask for them
- Don't automatically agree with my ideas—evaluate them objectively
- Point out risks, edge cases, and potential problems
- If my approach is suboptimal, explain why and suggest a better one

## Code review

- Point out bugs, even minor ones
- Identify performance issues
- Suggest refactorings when the code can be improved
- Mention violations of best practices


## 🎯 Project Stack


| Technology   | Version/Port | Purpose                    |
| ------------ | ------------ | -------------------------- |
| PHP          | 8.4+         | Backend avec strict typing |
| Laravel      | 12+          | Headless API               |
| PostgreSQL   | port 5433    | Base de données           |
| Redis        | port 6379    | Cache & Queues             |
| Docker/Nginx | port 1310    | Environnement              |
| AWS Cognito  | -            | Authentification JWT       |

**Architecture**: Event Sourcing (credits) • Spatie Permissions (RBAC) • OneSignal (push)

---

## 🚨 WORKFLOW OBLIGATOIRE

### 1. Démarrage de chaque tâche

```bash
# TOUJOURS demander si une branche est nécessaire
git checkout -b [feature/task-name]
```

### 2. Cycle TDD (NON NÉGOCIABLE)

1. ✅ Écrire le(s) test(s) AVANT le code
2. ❌ Test échoue (RED)
3. ✅ Implémenter (GREEN)
4. 🔄 Refactorer si nécessaire

### 3. Avant CHAQUE commit

```bash
make quality-check  # MUST PASS (PHPStan niveau 9)
make test          # MUST PASS (100%)
```

---

## 🧪 Tests - Règles Essentielles

### Structure obligatoire

```php
use Illuminate\Foundation\Testing\DatabaseTransactions; // ✅ TOUJOURS
#[Test] // ✅ PAS @test
public function it_describes_behavior(): void
{
    // Arrange
    $initialCount = Model::count();
  
    // Act
    $this->actingAs($user)->postJson('/api/v1/resource', $data);
  
    // Assert
    $this->assertEquals($initialCount + 1, Model::count());
}
```

### Base Classes


| Type de Test   | Extends                  | Quand l'utiliser     |
| -------------- | ------------------------ | -------------------- |
| Feature API    | `ProtectedRouteTestCase` | Endpoints protégés |
| Unit           | `TestCase`               | Logique isolée      |
| Feature Public | `TestCase`               | Endpoints publics    |

### Database Testing

**✅ CORRECT** : `DatabaseTransactions` (inclus dans `ProtectedRouteTestCase`)
**❌ INTERDIT** : `RefreshDatabase` (trop lent)

```php
// Pattern count-based (PREFERRED)
$before = User::count();
// ... action ...
$this->assertEquals($before + 1, User::count());
```

### Factories - TOUJOURS utiliser ModelFactory

```php
use Tests\Helpers\Facades\ModelFactory;

// ✅ Avec relations cohérentes
$division = ModelFactory::createDivision();
$financer = ModelFactory::createFinancer(['division_id' => $division->id]);
$user = ModelFactory::createUser(['financer_id' => $financer->id]);

// ❌ JAMAIS de création manuelle sans factory
```

---

## 🏗️ Architecture & Patterns

### Service/Action Pattern (OBLIGATOIRE)


| Pattern     | Responsabilité             | Exemple                               |
| ----------- | --------------------------- | ------------------------------------- |
| **Action**  | 1 tâche atomique           | `CreateUserAction`, `SendEmailAction` |
| **Service** | Orchestration multi-actions | `UserService->register()`             |
| **DTO**     | Transfert de données       | `CreateUserDTO`                       |

```php
// Action (logique métier pure)
class CreateResourceAction
{
    public function execute(CreateResourceDTO $dto): Resource
    {
        return Resource::create($dto->toArray());
    }
}

// Service (orchestration)
class ResourceService
{
    public function __construct(
        private CreateResourceAction $createAction,
        private NotifyAction $notifyAction
    ) {}
  
    public function register(array $data): Resource
    {
        $resource = $this->createAction->execute(
            CreateResourceDTO::fromArray($data)
        );
        $this->notifyAction->execute($resource);
        return $resource;
    }
}
```

### Controller - Léger et Standardisé

```php
class ResourceController extends Controller
{
    public function store(StoreResourceRequest $request): JsonResponse
    {
        $resource = $this->service->create($request->validated());
        return response()->json(
            new ResourceResource($resource),
            Response::HTTP_CREATED
        );
    }
}
```

---

## 🔐 Authentification & Permissions

### AWS Cognito + Spatie

- **Auth**: JWT Bearer tokens via `CognitoAuthMiddleware`
- **RBAC**: Spatie Permissions (roles + permissions)
- **Admin Panel**: GOD role requis + même auth Cognito

### Roles disponibles

```php
RoleDefaults::GOD              // Super admin
RoleDefaults::BENEFICIARY      // Utilisateur standard
RoleDefaults::FINANCER_ADMIN   // Admin financeur
// ... voir RoleDefaults.php pour la liste complète
```

### Tests avec auth

```php
class MyTest extends ProtectedRouteTestCase
{
    // Bypass auth/permissions pour focus sur logique métier
    protected bool $checkAuth = false;
    protected bool $checkPermissions = false;
  
    #[Test]
    public function it_tests_business_logic(): void
    {
        $user = $this->createAuthUser(RoleDefaults::BENEFICIARY);
        // ... test ...
    }
}
```

---

## 🗂️ Structure des Fichiers

### Organisation par domaine

```
app/
├── Actions/           # 1 tâche = 1 action
├── Services/          # Orchestration
├── DTOs/             # Data Transfer Objects
├── Models/           # Eloquent (pas de $fillable!)
├── Http/
│   ├── Controllers/  # Légers, délèguent aux services
│   ├── Requests/     # Validation uniquement
│   └── Resources/    # Transformation réponse API
└── Integrations/     # Feature-based modules

tests/
├── Unit/             # Logique isolée
├── Feature/          # API endpoints
└── Integration/      # Tests groupés par module
```

---

## 📊 Event Sourcing (Crédits)

**Pattern**: Store → Event → Projection → Query

```php
// Store event
CreditEvent::create([
    'user_id' => $user->id,
    'type' => CreditEventType::PURCHASE,
    'amount' => -100,
    'metadata' => ['order_id' => $order->id]
]);

// Query projection
$balance = CreditBalance::where('user_id', $user->id)->value('balance');
```

**Règle**: Jamais de modification directe des projections

---

## 🐳 Docker & Commandes

### Développement

```bash
docker-compose up -d                          # Start
docker compose exec app_engage php artisan    # Artisan
docker compose exec app_engage composer       # Composer
docker compose logs -f app_engage            # Logs
```

### Multi-Agent (Worktrees)

```bash
# Environnement isolé pour agents parallèles
docker-compose -f docker-compose.worktree.yml up -d
```

---

## 🔔 OneSignal Push Notifications

### Endpoints

```bash
POST   /api/v1/devices/register          # Enregistrer device
DELETE /api/v1/devices/{id}             # Désenregistrer
PATCH  /api/v1/devices/{id}/preferences # Préférences
POST   /api/v1/webhooks/onesignal       # Webhook (signature validée)
```

### Actions clés

- `RegisterDeviceAction`, `SendPushNotificationAction`
- Queue: `push-notifications` (Redis)
- Tests: `php artisan test --group=push`

---

## ✅ Checklist Pré-Commit

- [ ]  Tests écrits AVANT implémentation
- [ ]  `make test` → 100% pass
- [ ]  `make quality-check` → 0 erreur PHPStan
- [ ]  `make coverage` → >80%
- [ ]  Pas de `@phpstan-ignore` ajouté
- [ ]  Service/Action pattern respecté
- [ ]  API docs mises à jour (`make docs`)
- [ ]  ModelFactory utilisé pour toute création de modèle
- [ ]  DatabaseTransactions (pas RefreshDatabase)

---

## 📚 Documentation Complémentaire


| Sujet             | Fichier                                    |
| ----------------- | ------------------------------------------ |
| Tests détaillés | `tests/README.md`                          |
| MCP Server        | `/Users/fred/.../MCP_SERVER_USER_GUIDE.md` |
| OneSignal         | `/docs/push-notifications.md`              |
| Translations      | Feature 006 (auto-sync post-migration)     |
| Exemples étendus | `CLAUDE_REFERENCE.md`                      |

---

## 🔥 Anti-Patterns à Éviter


| ❌ Ne JAMAIS faire                 | ✅ Faire à la place                        |
| ---------------------------------- | ------------------------------------------- |
| `protected $fillable` dans models  | `$guarded` ou DTOs                          |
| `RefreshDatabase` dans tests       | `DatabaseTransactions`                      |
| Tests sans`#[Test]` attribute      | Toujours`#[Test]`                           |
| Création manuelle de modèles     | `ModelFactory::create*()`                   |
| Logique dans Controller            | Déléguer au Service                       |
| Modifier test pour le faire passer | Fixer l'implémentation                     |
| Commit sans quality-check          | Toujours`make quality-check`                |
| Methode down dans les migrations   | jamais de methode`down` dans les migrations |

---

**Philosophie**: Quality > Speed • TDD is mandatory • Tests are specifications

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.10
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/pulse (PULSE) - v1
- laravel/reverb (REVERB) - v1
- laravel/telescope (TELESCOPE) - v5
- livewire/livewire (LIVEWIRE) - v3
- larastan/larastan (LARASTAN) - v3
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- rector/rector (RECTOR) - v2
- tailwindcss (TAILWINDCSS) - v3

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs

- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
  - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
...
}
</code-snippet>

## Comments

- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks

- Add useful array shape type definitions for arrays when appropriate.

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure

- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

## Livewire Core

- Use the `search-docs` tool to find exact version specific documentation for how to write Livewire & Livewire tests.
- Use the `php artisan make:livewire [Posts\CreatePost]` artisan command to create new components
- State should live on the server, with the UI reflecting it.
- All Livewire requests hit the Laravel backend, they're like regular HTTP requests. Always validate form data, and run authorization checks in Livewire actions.

## Livewire Best Practices

- Livewire components require a single root element.
- Use `wire:loading` and `wire:dirty` for delightful loading states.
- Add `wire:key` in loops:

  ```blade
  @foreach ($items as $item)
      <div wire:key="item-{{ $item->id }}">
          {{ $item->name }}
      </div>
  @endforeach
  ```
- Prefer lifecycle hooks like `mount()`, `updatedFoo()` for initialization and reactive side effects:

<code-snippet name="Lifecycle hook examples" lang="php">
public function mount(User $user) { $this->user = $user; }
public function updatedSearch() { $this->resetPage(); }
</code-snippet>

## Testing Livewire

<code-snippet name="Example Livewire component test" lang="php">
Livewire::test(Counter::class)
->assertSet('count', 0)
->call('increment')
->assertSet('count', 1)
->assertSee(1)
->assertStatus(200);
</code-snippet>

<code-snippet name="Testing a Livewire component exists within a page" lang="php">
    $this->get('/posts/create')
    ->assertSeeLivewire(CreatePost::class);
</code-snippet>
=== livewire/v3 rules ===

## Livewire 3

### Key Changes From Livewire 2

- These things changed in Livewire 2, but may not have been updated in this application. Verify this application's setup to ensure you conform with application conventions.
  - Use `wire:model.live` for real-time updates, `wire:model` is now deferred by default.
  - Components now use the `App\Livewire` namespace (not `App\Http\Livewire`).
  - Use `$this->dispatch()` to dispatch events (not `emit` or `dispatchBrowserEvent`).
  - Use the `components.layouts.app` view as the typical layout path (not `layouts.app`).

### New Directives

- `wire:show`, `wire:transition`, `wire:cloak`, `wire:offline`, `wire:target` are available for use. Use the documentation to find usage examples.

### Alpine

- Alpine is now included with Livewire, don't manually include Alpine.js.
- Plugins included with Alpine: persist, intersect, collapse, and focus.

### Lifecycle Hooks

- You can listen for `livewire:init` to hook into Livewire initialization, and `fail.status === 419` for the page expiring:

<code-snippet name="livewire:load example" lang="js">
document.addEventListener('livewire:init', function () {
Livewire.hook('request', ({ fail }) => {
if (fail && fail.status === 419) {
alert('Your session expired');
}
});

Livewire.hook('message.failed', (message, component) => {
    console.error(message);
});
});
</code-snippet>

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit <name>` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing

- When listing items, use gap utilities for spacing, don't use margins.

  <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
  <div class="flex gap-8">
  <div>Superior</div>
  <div>Michigan</div>
  <div>Erie</div>
  </div>
  </code-snippet>

### Dark Mode

- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v3 rules ===

## Tailwind 3

- Always use Tailwind CSS v3 - verify you're using only classes supported by this version.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
  </laravel-boost-guidelines>
- on va eviter d'utiliser ArticleResourceCollection ou TagResourceCollection mais utiliser plutot ArticleResource::Collection de cette maniere le package scramble a plus facide de génèrer la documentation