# Cursor Shortcuts & Commands for Engage Laravel API

> ⌨️ **Essential shortcuts and commands** for efficient development with Cursor IDE

## 🚀 Essential Cursor Shortcuts

### Navigation & File Management
| Shortcut | Action | Description |
|----------|--------|-------------|
| `Cmd+P` | Quick Open | Open files quickly |
| `Cmd+Shift+P` | Command Palette | Access all commands |
| `Cmd+Shift+E` | Explorer | Toggle file explorer |
| `Cmd+Shift+O` | Go to Symbol | Navigate to symbols in file |
| `Cmd+Shift+F` | Search in Files | Global search across project |
| `Cmd+G` | Go to Line | Jump to specific line number |
| `Cmd+Shift+G` | Go to Symbol in Workspace | Find symbols across project |

### Code Editing
| Shortcut | Action | Description |
|----------|--------|-------------|
| `Cmd+D` | Select Next Occurrence | Multi-cursor editing |
| `Cmd+Shift+L` | Select All Occurrences | Select all instances |
| `Cmd+/` | Toggle Line Comment | Comment/uncomment lines |
| `Cmd+Shift+A` | Toggle Block Comment | Comment/uncomment blocks |
| `Alt+Up/Down` | Move Line Up/Down | Move lines |
| `Shift+Alt+Up/Down` | Copy Line Up/Down | Duplicate lines |
| `Cmd+Shift+K` | Delete Line | Delete current line |

### AI Features
| Shortcut | Action | Description |
|----------|--------|-------------|
| `Cmd+K` | AI Chat | Open AI chat panel |
| `Cmd+L` | AI Composer | Open AI composer |
| `Cmd+I` | AI Edit | Edit with AI |
| `Cmd+Shift+I` | AI Explain | Explain selected code |
| `Tab` | Accept AI Suggestion | Accept inline AI suggestion |
| `Esc` | Reject AI Suggestion | Reject inline AI suggestion |

### Git Integration
| Shortcut | Action | Description |
|----------|--------|-------------|
| `Cmd+Shift+G` | Source Control | Open Git panel |
| `Cmd+Enter` | Commit | Commit staged changes |
| `Cmd+Shift+P` → "Git: Push" | Push | Push to remote |
| `Cmd+Shift+P` → "Git: Pull" | Pull | Pull from remote |
| `Cmd+Shift+P` → "Git: Fetch" | Fetch | Fetch from remote |

## 🧪 Laravel-Specific Commands

### Artisan Commands (via Cursor Terminal)
```bash
# Model creation
docker compose exec app_engage php artisan make:model User -m

# Controller creation
docker compose exec app_engage php artisan make:controller UserController --api

# Service creation
docker compose exec app_engage php artisan make:service UserService

# Test creation
docker compose exec app_engage php artisan make:test UserTest

# Migration creation
docker compose exec app_engage php artisan make:migration create_users_table

# Seeder creation
docker compose exec app_engage php artisan make:seeder UserSeeder
```

### Make Commands (via Cursor Terminal)
```bash
# Run tests
make test

# Quality check
make quality-check

# Code style
make pint

# Static analysis
make phpstan

# Coverage
make coverage

# Documentation
make docs
```

## 🎯 Cursor Command Palette Commands

### Laravel Extensions
- **Laravel: Generate Model** - Create model with migration
- **Laravel: Generate Controller** - Create controller
- **Laravel: Generate Service** - Create service class
- **Laravel: Generate Test** - Create test file
- **Laravel: Generate Migration** - Create migration
- **Laravel: Generate Seeder** - Create seeder

### PHP Extensions
- **PHP: Generate Constructor** - Generate constructor
- **PHP: Generate Getter/Setter** - Generate accessors
- **PHP: Generate PHPDoc** - Generate documentation
- **PHP: Format Document** - Format PHP code
- **PHP: Organize Imports** - Organize use statements

### Git Commands
- **Git: Add** - Stage files
- **Git: Commit** - Commit changes
- **Git: Push** - Push to remote
- **Git: Pull** - Pull from remote
- **Git: Fetch** - Fetch from remote
- **Git: Checkout** - Switch branches
- **Git: Merge** - Merge branches

## 🐳 Docker Commands (via Cursor Terminal)

### Container Management
```bash
# Start containers
docker compose up -d

# Stop containers
docker compose down

# View logs
docker compose logs -f app_engage

# Execute commands in container
docker compose exec app_engage php artisan migrate
docker compose exec app_engage composer install
docker compose exec app_engage php artisan test
```

### Development Commands
```bash
# Install dependencies
docker compose exec app_engage composer install

# Run migrations
docker compose exec app_engage php artisan migrate

# Clear cache
docker compose exec app_engage php artisan cache:clear
docker compose exec app_engage php artisan config:clear
docker compose exec app_engage php artisan route:clear

# Generate optimized autoloader
docker compose exec app_engage composer dump-autoload -o
```

## 🎯 AI Prompt Templates

### Model Generation
```
Generate a [ModelName] model with:
- UUID primary key
- Soft deletes
- Translatable [field1, field2] fields
- BelongsTo relationship with [RelatedModel]
- HasMany relationship with [RelatedModel]
- Proper casts for [field: type]
- Follow the project's model patterns
```

### Service Generation
```
Generate a [ServiceName]Service with methods for:
- create[Model](array $data): [Model]
- find[Model](string $id): [Model]
- update[Model]([Model] $model, array $data): [Model]
- delete[Model]([Model] $model): bool
- Follow the project's service pattern with proper error handling
```

### Controller Generation
```
Generate a [ModelName]Controller with RESTful methods:
- index() - list [models] with pagination
- store() - create [model] with validation
- show() - get single [model]
- update() - update [model]
- destroy() - delete [model]
- Use FormRequest for validation
- Return JSON responses with proper status codes
- Follow the project's controller pattern
```

### Test Generation
```
Generate a test for [ClassName]::[methodName] method that:
- Tests successful [operation] with valid data
- Tests validation errors for invalid [field]
- Tests [edge case] handling
- Uses ModelFactory for test data
- Follows the #[Test] attribute pattern
```

## 🚀 Workflow Shortcuts

### Development Workflow
1. **Cmd+Shift+P** → "Git: Checkout" → Create new branch
2. **Cmd+P** → Open relevant files
3. **Cmd+K** → Use AI for code generation
4. **Cmd+Shift+E** → Navigate project structure
5. **Cmd+Shift+P** → "Terminal: Create New Terminal"
6. **make test** → Run tests
7. **make quality-check** → Quality check
8. **Cmd+Shift+G** → Commit changes

### Debugging Workflow
1. **F9** → Toggle breakpoint
2. **F5** → Start debugging
3. **F10** → Step over
4. **F11** → Step into
5. **Shift+F11** → Step out
6. **Cmd+Shift+F5** → Restart debugging

### Testing Workflow
1. **Cmd+Shift+P** → "Terminal: Create New Terminal"
2. **make test** → Run all tests
3. **docker compose exec app_engage php artisan test --filter=TestName** → Run specific test
4. **make coverage** → Check coverage
5. **Cmd+Shift+G** → Commit if tests pass

## 🎯 Custom Keybindings (Optional)

### Add to Cursor Settings
```json
{
    "key": "cmd+shift+t",
    "command": "workbench.action.terminal.sendSequence",
    "args": {
        "text": "make test\n"
    }
},
{
    "key": "cmd+shift+q",
    "command": "workbench.action.terminal.sendSequence",
    "args": {
        "text": "make quality-check\n"
    }
},
{
    "key": "cmd+shift+c",
    "command": "workbench.action.terminal.sendSequence",
    "args": {
        "text": "make coverage\n"
    }
}
```

## 🚫 Common Mistakes to Avoid

### Shortcut Mistakes
- ❌ **Don't use** `Cmd+S` for saving (Cursor auto-saves)
- ❌ **Don't use** `Cmd+Z` excessively (use Git instead)
- ❌ **Don't use** `Cmd+F` for global search (use `Cmd+Shift+F`)

### Command Mistakes
- ❌ **Don't run** `php artisan` directly (use Docker)
- ❌ **Don't use** `composer` directly (use Docker)
- ❌ **Don't skip** quality checks before commits

## ✅ Quick Reference Card

### Daily Commands
- **Cmd+P** → Open files
- **Cmd+K** → AI chat
- **Cmd+Shift+P** → Command palette
- **Cmd+Shift+E** → File explorer
- **Cmd+Shift+G** → Git panel

### Laravel Commands
- **make test** → Run tests
- **make quality-check** → Quality check
- **make coverage** → Check coverage
- **make docs** → Generate docs

### Docker Commands
- **docker compose up -d** → Start containers
- **docker compose exec app_engage php artisan migrate** → Run migrations
- **docker compose logs -f app_engage** → View logs

**Remember: Practice these shortcuts daily for maximum efficiency!**
