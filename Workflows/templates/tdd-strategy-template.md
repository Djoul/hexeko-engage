# TDD Strategy: [FEATURE-NAME]

## 🎯 Overview
- **Feature**: [Name]
- **Story/Epic**: [KEY]
- **Developer**: [Name]
- **Date**: [Date]

## 🧪 Test-Driven Development Plan

### TDD Principles Applied
1. **Write test first** - No code without failing test
2. **Minimal code** - Just enough to pass
3. **Refactor** - Improve after green
4. **Small cycles** - One behavior at a time

## 📋 Test Categories

### 1. Unit Tests
Location: `tests/Unit/[Module]/`

#### Service Layer Tests
```php
// tests/Unit/Services/[Feature]ServiceTest.php
class [Feature]ServiceTest extends TestCase
{
    #[Test]
    public function it_creates_resource_successfully(): void
    {
        // Arrange
        // Act
        // Assert
    }
    
    #[Test]
    public function it_handles_invalid_data(): void
    {
        // Test validation
    }
    
    #[Test]
    public function it_handles_not_found_error(): void
    {
        // Test error handling
    }
}
```

#### Action Layer Tests
```php
// tests/Unit/Actions/[Feature]/[Action]Test.php
class [Action]Test extends TestCase
{
    #[Test]
    public function it_executes_action_successfully(): void
    {
        // Test action orchestration
    }
    
    #[Test]
    public function it_handles_transaction_rollback(): void
    {
        // Test transaction handling
    }
}
```

### 2. Integration Tests
Location: `tests/Feature/[Module]/`

#### API Endpoint Tests
```php
// tests/Feature/[Module]/[Feature]ApiTest.php
class [Feature]ApiTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_returns_paginated_list(): void
    {
        // Test GET /api/v1/resource
    }
    
    #[Test]
    public function it_creates_resource_via_api(): void
    {
        // Test POST /api/v1/resource
    }
    
    #[Test]
    public function it_validates_request_data(): void
    {
        // Test validation rules
    }
    
    #[Test]
    public function it_handles_authentication(): void
    {
        // Test auth requirements
    }
}
```

#### Database Integration Tests
```php
#[Test]
public function it_persists_data_correctly(): void
{
    // Test database operations
}

#[Test]
public function it_handles_concurrent_updates(): void
{
    // Test race conditions
}
```

### 3. E2E Tests (if applicable)
Location: `tests/E2E/[Feature]/`

```php
#[Test]
public function it_completes_full_user_journey(): void
{
    // Complete workflow test
}
```

## 🔄 TDD Cycles

### Cycle 1: Basic Functionality
**RED Phase**
```php
#[Test]
public function it_performs_basic_operation(): void
{
    $service = new FeatureService();
    $result = $service->process($data);
    $this->assertEquals($expected, $result);
}
// ❌ Test fails - FeatureService doesn't exist
```

**GREEN Phase**
```php
class FeatureService
{
    public function process($data)
    {
        return $expected; // Minimal implementation
    }
}
// ✅ Test passes
```

**REFACTOR Phase**
```php
class FeatureService
{
    public function __construct(
        private readonly Repository $repository
    ) {}
    
    public function process(DTO $data): ResultDTO
    {
        // Proper implementation
    }
}
// ✅ Test still passes, code improved
```

### Cycle 2: Error Handling
**RED Phase**
```php
#[Test]
public function it_handles_invalid_input(): void
{
    $this->expectException(ValidationException::class);
    $service->process($invalidData);
}
// ❌ Test fails - No validation
```

**GREEN Phase**
```php
public function process($data)
{
    if (!$this->isValid($data)) {
        throw new ValidationException();
    }
    // ...
}
// ✅ Test passes
```

### Cycle 3: Edge Cases
[Continue pattern for each edge case]

## 📊 Test Data Setup

### Factories Required
```php
// Models needing factories
- User::factory()
- [Model]::factory()
```

### Fixtures
```php
// Common test data
protected function getValidData(): array
{
    return [
        'field1' => 'value1',
        'field2' => 'value2',
    ];
}

protected function getInvalidData(): array
{
    return [
        'field1' => null, // Required field
    ];
}
```

### Mocks & Stubs
```php
// External services to mock
- StripeService::class
- EmailService::class
- [ExternalAPI]::class

// Example mock setup
$this->mock(StripeService::class)
    ->shouldReceive('charge')
    ->once()
    ->andReturn($paymentResult);
```

## ✅ Test Checklist

### Behavior Coverage
- [ ] Happy path
- [ ] Validation failures
- [ ] Authentication required
- [ ] Authorization checks
- [ ] Not found scenarios
- [ ] Duplicate prevention
- [ ] Concurrent access
- [ ] Transaction rollback
- [ ] Cache invalidation
- [ ] Event dispatching

### Data Scenarios
- [ ] Empty data
- [ ] Minimal valid data
- [ ] Maximum valid data
- [ ] Boundary values
- [ ] Special characters
- [ ] Unicode/emoji
- [ ] SQL injection attempts
- [ ] XSS attempts

### Performance Tests
- [ ] Response time < 500ms
- [ ] Handles N+1 queries
- [ ] Pagination works
- [ ] Cache effectiveness

## 🎯 Coverage Goals

### Minimum Requirements
- **Line Coverage**: 80%
- **Branch Coverage**: 75%
- **Method Coverage**: 90%

### Target by Component
| Component | Target Coverage | Priority |
|-----------|----------------|----------|
| Services | 95% | High |
| Actions | 90% | High |
| Controllers | 85% | Medium |
| DTOs | 80% | Low |

## 🔨 Test Commands

### Running Tests
```bash
# All tests for feature
docker compose exec app_engage php artisan test --group=[feature]

# Specific test file
docker compose exec app_engage php artisan test tests/Unit/Services/[Feature]ServiceTest.php

# With coverage
docker compose exec app_engage php artisan test --coverage --group=[feature]

# Watch mode
docker compose exec app_engage php artisan test --watch
```

### Coverage Report
```bash
# Generate HTML coverage
docker compose exec app_engage php artisan test --coverage-html=coverage

# Check coverage threshold
docker compose exec app_engage php artisan test --coverage --min=80
```

## 📝 Test Documentation

### Test Naming Convention
```php
// Good test names
it_creates_user_with_valid_data()
it_throws_exception_when_email_is_duplicate()
it_sends_notification_after_user_creation()

// Avoid
test1()
testUserCreation()
itWorks()
```

### Assertion Messages
```php
// Provide context in assertions
$this->assertEquals(
    $expected,
    $actual,
    "User balance should be updated after payment"
);

$this->assertDatabaseHas('users', [
    'email' => $email
], 'User should be persisted in database');
```

## 🚨 Common Pitfalls to Avoid

1. **Testing implementation instead of behavior**
   - ❌ Test private methods
   - ✅ Test public interface

2. **Brittle tests**
   - ❌ Hardcoded IDs
   - ✅ Use factories

3. **Test pollution**
   - ❌ Tests depend on order
   - ✅ Each test independent

4. **Incomplete cleanup**
   - ❌ Leave test data
   - ✅ Use DatabaseTransactions

5. **Over-mocking**
   - ❌ Mock everything
   - ✅ Mock external dependencies only

## 🔄 Test Maintenance

### When to Update Tests
- Requirements change
- Bug discovered (add regression test)
- Refactoring (tests should still pass)
- Performance improvements

### Test Review Checklist
- [ ] Tests are readable
- [ ] Tests are independent
- [ ] Tests are fast
- [ ] Tests are deterministic
- [ ] Tests document behavior

---
*TDD Strategy prepared on: [Date]*
*Last updated: [Date]*