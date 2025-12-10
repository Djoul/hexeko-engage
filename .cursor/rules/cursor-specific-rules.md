# Cursor-Specific Rules for Engage Laravel API

> 🎯 **Cursor-optimized rules** adapted from the main engage-rules.mdc for better Cursor IDE integration

## 🚀 Cursor-Specific Workflow

### AI Assistant Integration
- **Always use Cursor's AI features** for code generation and refactoring
- **Leverage Cursor's context awareness** - it understands your codebase structure
- **Use Cursor's inline suggestions** for faster development
- **Prefer Cursor's refactoring tools** over manual edits when possible

### File Navigation & Context
- **Use Cursor's file tree** to understand project structure
- **Leverage Cursor's symbol search** (Cmd+Shift+O) for quick navigation
- **Use Cursor's "Go to Definition"** (Cmd+Click) for understanding relationships
- **Open related files in split view** for better context

## 🧪 Test-Driven Development (TDD) - Cursor Optimized

### Cursor Test Generation
```php
// Use Cursor's AI to generate test stubs
// Type: "Generate test for UserService create method"
// Cursor will create proper test structure with #[Test] attributes

#[Test]
public function it_creates_user_with_valid_data(): void
{
    // Cursor AI can fill in test implementation
    // Use Cursor's suggestions for assertions
}
```

### Cursor Test Running
- **Use Cursor's integrated terminal** for running tests
- **Leverage Cursor's test runner** when available
- **Use Cursor's error highlighting** to quickly identify test failures

### Test Data Creation with Cursor
```php
// Let Cursor AI suggest ModelFactory usage
$user = ModelFactory::createUser([
    'email' => 'test@example.com',
    // Cursor will suggest available fields
]);

// Use Cursor's autocomplete for factory methods
$financer = ModelFactory::createFinancer([
    // Cursor knows the available parameters
]);
```

## 🏗️ Architecture - Cursor-Friendly Patterns

### Service Pattern with Cursor
```php
// Use Cursor's AI to generate service methods
class UserService
{
    // Type method signature and let Cursor complete
    public function createUser(array $data): User
    {
        // Cursor AI can suggest implementation
        return User::create($data);
    }
}
```

### Action Pattern with Cursor
```php
// Use Cursor's template generation
class CreateUserAction
{
    public function execute(CreateUserDTO $dto): UserDTO
    {
        // Cursor can suggest transaction wrapper
        return DB::transaction(function () use ($dto) {
            // Cursor AI can complete the implementation
        });
    }
}
```

## 🐳 Docker Integration with Cursor

### Cursor Terminal Commands
```bash
# Use Cursor's integrated terminal
# Cursor understands the Docker context
docker compose exec app_engage php artisan make:model User
docker compose exec app_engage composer install
docker compose exec app_engage php artisan test
```

### Cursor Dockerfile Understanding
- **Cursor can analyze Docker configurations**
- **Use Cursor's Docker extension** for container management
- **Leverage Cursor's environment detection**

## 📦 Models & Database - Cursor Optimized

### Model Generation with Cursor
```php
// Use Cursor's AI to generate model structure
class User extends Model
{
    // Cursor can suggest traits based on project patterns
    use HasFactory, SoftDeletes, HasUuids;
    
    // Cursor AI can suggest fillable fields
    protected $fillable = [
        // Cursor will suggest based on migrations
    ];
}
```

### Migration Creation with Cursor
```php
// Use Cursor's AI to generate migration structure
Schema::create('users', function (Blueprint $table) {
    // Cursor can suggest common fields
    $table->id();
    $table->string('email')->unique();
    // Cursor AI can complete the migration
});
```

## 🚀 Development Commands - Cursor Integration

### Cursor Command Palette
- **Use Cmd+Shift+P** for Laravel-specific commands
- **Leverage Cursor's Laravel extension** for artisan commands
- **Use Cursor's integrated terminal** for make commands

### Quality Checks with Cursor
```bash
# Run in Cursor's terminal
make quality-check  # Cursor will highlight issues
make test          # Cursor shows test results
make coverage      # Cursor displays coverage reports
```

## 📋 API Development - Cursor Features

### Controller Generation
```php
// Use Cursor's AI to generate controller methods
class UserController extends Controller
{
    // Type method signature and let Cursor complete
    public function store(StoreUserRequest $request): JsonResponse
    {
        // Cursor AI can suggest implementation
    }
}
```

### FormRequest Creation
```php
// Use Cursor's AI to generate validation rules
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        // Cursor can suggest common validation rules
        return [
            'email' => 'required|email|unique:users',
            // Cursor AI can complete validation
        ];
    }
}
```

## 🎯 Cursor-Specific Best Practices

### Code Generation
- **Use Cursor's AI for boilerplate code**
- **Leverage Cursor's context for intelligent suggestions**
- **Use Cursor's refactoring tools** for code improvements

### Error Handling
- **Use Cursor's error highlighting** to quickly identify issues
- **Leverage Cursor's quick fixes** for common problems
- **Use Cursor's diagnostic tools** for debugging

### Performance
- **Use Cursor's code analysis** to identify performance issues
- **Leverage Cursor's optimization suggestions**
- **Use Cursor's profiling tools** when available

## 🔥 Cursor Quick Reference

### Essential Cursor Shortcuts
- **Cmd+Shift+P**: Command Palette
- **Cmd+P**: Quick Open
- **Cmd+Shift+O**: Go to Symbol
- **Cmd+Click**: Go to Definition
- **Cmd+Shift+F**: Search in Files
- **Cmd+Shift+E**: Explorer
- **Cmd+Shift+X**: Extensions

### Cursor AI Commands
- **Cmd+K**: AI Chat
- **Cmd+L**: AI Composer
- **Cmd+I**: AI Edit
- **Cmd+Shift+I**: AI Explain

### Laravel-Specific Cursor Features
- **Laravel Extension**: Enhanced Laravel support
- **PHP Intelephense**: Advanced PHP support
- **Docker Extension**: Container management
- **GitLens**: Enhanced Git integration

## 🚫 Cursor-Specific Forbidden Practices

### AI Usage
- **NEVER** use AI to generate code without understanding it
- **NEVER** accept AI suggestions without review
- **NEVER** use AI for security-critical code without validation

### Code Quality
- **NEVER** ignore Cursor's error warnings
- **NEVER** use AI to bypass quality checks
- **NEVER** commit code that Cursor flags as problematic

### Testing
- **NEVER** use AI to generate tests without understanding the logic
- **NEVER** accept AI test suggestions without validation
- **NEVER** use AI to modify existing tests

## ✅ Cursor Pre-Commit Checklist

**EVERY commit MUST pass:**

- [ ] Cursor's error highlighting shows no issues
- [ ] Cursor's AI suggestions have been reviewed
- [ ] Cursor's refactoring tools have been used appropriately
- [ ] Cursor's integrated terminal shows passing tests
- [ ] Cursor's quality checks pass
- [ ] Cursor's Git integration shows clean diff
- [ ] Cursor's AI has been used responsibly

## 🎯 Cursor Workflow Summary

1. **Start with Cursor's AI** for code generation
2. **Use Cursor's context awareness** for intelligent suggestions
3. **Leverage Cursor's refactoring tools** for improvements
4. **Use Cursor's integrated terminal** for commands
5. **Review Cursor's error highlighting** before commits
6. **Use Cursor's Git integration** for version control

**Remember: Cursor is a powerful tool, but human review is always required.**
