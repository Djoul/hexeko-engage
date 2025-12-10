# Cursor AI Guidelines for Engage Laravel API

> 🤖 **AI-specific guidelines** for using Cursor's AI features effectively with this Laravel project

## 🎯 AI Usage Principles

### When to Use AI
- ✅ **Code generation** for boilerplate (models, controllers, tests)
- ✅ **Refactoring** existing code for better structure
- ✅ **Documentation** generation and updates
- ✅ **Test case** generation based on specifications
- ✅ **Error fixing** with context understanding

### When NOT to Use AI
- ❌ **Security-critical code** without human review
- ❌ **Business logic** without understanding the requirements
- ❌ **Database migrations** without understanding the schema
- ❌ **API endpoints** without understanding the business rules
- ❌ **Authentication/authorization** logic without security review

## 🧪 AI for Test Generation

### Effective AI Prompts for Tests
```php
// Good AI prompt:
"Generate a test for UserService::createUser method that:
- Tests successful user creation with valid data
- Tests validation errors for invalid email
- Tests duplicate email handling
- Uses ModelFactory for test data
- Follows the #[Test] attribute pattern"

// AI will generate:
#[Test]
public function it_creates_user_with_valid_data(): void
{
    // Arrange
    $userData = ModelFactory::makeUser([
        'email' => 'test@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe'
    ])->toArray();

    // Act
    $user = $this->userService->createUser($userData);

    // Assert
    $this->assertInstanceOf(User::class, $user);
    $this->assertEquals('test@example.com', $user->email);
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
}
```

### AI Test Patterns to Avoid
```php
// Bad AI prompt:
"Write a test for user creation"

// This might generate:
/** @test */  // Wrong attribute
public function testUserCreation()  // Wrong naming
{
    $user = new User();  // Wrong - should use ModelFactory
    $user->save();
    $this->assertTrue(true);  // Useless assertion
}
```

## 🏗️ AI for Architecture Patterns

### Service Pattern Generation
```php
// Good AI prompt:
"Generate a UserService with methods for:
- createUser(array $data): User
- findUser(string $id): User
- updateUser(User $user, array $data): User
- deleteUser(User $user): bool
- Follow the project's service pattern with proper error handling"

// AI will generate:
class UserService
{
    public function createUser(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);
            
            // Dispatch event
            event(new UserCreated($user));
            
            return $user;
        });
    }

    public function findUser(string $id): User
    {
        $user = User::findCached($id);
        
        if (!$user) {
            throw new ModelNotFoundException('User not found');
        }
        
        return $user;
    }
    
    // ... other methods
}
```

### Action Pattern Generation
```php
// Good AI prompt:
"Generate a CreateUserAction that:
- Takes CreateUserDTO as input
- Returns UserDTO as output
- Uses UserService for business logic
- Handles database transactions
- Dispatches UserCreated event
- Follows the project's action pattern"

// AI will generate:
class CreateUserAction
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function execute(CreateUserDTO $dto): UserDTO
    {
        return DB::transaction(function () use ($dto) {
            $user = $this->userService->createUser($dto->toArray());
            
            event(new UserCreated($user));
            
            return UserDTO::fromModel($user);
        });
    }
}
```

## 📦 AI for Model Generation

### Model Generation with AI
```php
// Good AI prompt:
"Generate a Product model with:
- UUID primary key
- Soft deletes
- Translatable name and description fields
- BelongsTo relationship with Category
- HasMany relationship with OrderItems
- Proper casts for price (decimal) and is_active (boolean)
- Follow the project's model patterns"

// AI will generate:
class Product extends Model
{
    use HasFactory, SoftDeletes, HasUuids, HasTranslations;

    protected $table = 'products';

    public array $translatable = ['name', 'description'];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
```

## 🚀 AI for API Development

### Controller Generation
```php
// Good AI prompt:
"Generate a ProductController with RESTful methods:
- index() - list products with pagination
- store() - create product with validation
- show() - get single product
- update() - update product
- destroy() - delete product
- Use FormRequest for validation
- Return JSON responses with proper status codes
- Follow the project's controller pattern"

// AI will generate:
class ProductController extends Controller
{
    public function index(IndexProductRequest $request): JsonResponse
    {
        $products = $this->productService->getPaginated($request->validated());
        
        return response()->json(ProductResource::collection($products));
    }

    public function store(StoreProductRequest $request, CreateProductAction $action): JsonResponse
    {
        $product = $action->execute($request->validated());
        
        return response()->json(new ProductResource($product), 201);
    }
    
    // ... other methods
}
```

## 🐳 AI for Docker Commands

### Docker Command Generation
```bash
# Good AI prompt:
"Generate Docker commands for:
- Running migrations
- Installing composer dependencies
- Running tests
- Clearing cache
- Following the project's Docker setup"

# AI will generate:
docker compose exec app_engage php artisan migrate
docker compose exec app_engage composer install
docker compose exec app_engage php artisan test
docker compose exec app_engage php artisan cache:clear
```

## 🎯 AI Prompt Engineering Best Practices

### Effective Prompts
- ✅ **Be specific** about requirements
- ✅ **Include context** about the project
- ✅ **Specify patterns** to follow
- ✅ **Mention constraints** and limitations
- ✅ **Request examples** when needed

### Ineffective Prompts
- ❌ **Too vague**: "Write some code"
- ❌ **No context**: "Create a user model"
- ❌ **Missing constraints**: "Generate an API endpoint"
- ❌ **No patterns**: "Make a service class"

## 🚫 AI Limitations to Be Aware Of

### Security Concerns
- **AI may not understand** security implications
- **AI may suggest** insecure patterns
- **AI may not consider** authentication/authorization
- **Always review** AI-generated security code

### Business Logic
- **AI may not understand** complex business rules
- **AI may suggest** incorrect logic
- **AI may not consider** edge cases
- **Always validate** AI-generated business logic

### Performance
- **AI may not optimize** for performance
- **AI may suggest** inefficient queries
- **AI may not consider** caching strategies
- **Always review** AI-generated performance code

## ✅ AI Code Review Checklist

**Before accepting AI-generated code:**

- [ ] **Security review** - No security vulnerabilities
- [ ] **Business logic validation** - Logic is correct
- [ ] **Performance check** - No performance issues
- [ ] **Pattern compliance** - Follows project patterns
- [ ] **Test coverage** - Adequate test coverage
- [ ] **Documentation** - Code is well-documented
- [ ] **Error handling** - Proper error handling
- [ ] **Validation** - Input validation is present

## 🎯 AI Workflow Summary

1. **Define requirements** clearly
2. **Use specific prompts** with context
3. **Review generated code** thoroughly
4. **Test AI-generated code** extensively
5. **Refactor if needed** using AI suggestions
6. **Document changes** appropriately
7. **Commit with confidence** after review

**Remember: AI is a powerful assistant, but human judgment is irreplaceable.**
