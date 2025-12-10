# E2E Test Suite - Invoicing System

## 🎯 Overview

This directory contains comprehensive End-to-End tests for the bi-level invoicing system.

**Status:** Phase 1 Complete ✅ (10 tests implemented)
**Coverage:** Critical workflows, prorata calculations, event sourcing, API, exports

---

## 📂 Test Files

### ✅ Implemented (Phase 1)

| File | Tests | Coverage |
|------|-------|----------|
| `MonthlyBatchGenerationTest.php` | 2 | Complete batch generation workflow |
| `ProrataCalculationTest.php` | 6 | Multi-level prorata + calendar edge cases |
| `EventSourcingTest.php` | 3 | Balance projection & event replay |
| `ApiWorkflowTest.php` | 3 | Full API lifecycle + pagination |
| `ExportTest.php` | 4 | PDF/Excel/Email exports |

**Total:** 18 test methods covering 10 E2E scenarios

### 🔲 Pending (Phases 2 & 3)

See [Complete Test Suite Documentation](../../docs/testing/e2e-test-suite-complete.md) for:
- E2E-011 to E2E-015 (Phase 2 - Important)
- E2E-016 to E2E-025 (Phase 3 - Complementary)

---

## 🚀 Running E2E Tests

### Run All E2E Tests
```bash
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml
```

### Run Specific Test File
```bash
# Monthly batch generation
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/MonthlyBatchGenerationTest.php

# Prorata calculations
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/ProrataCalculationTest.php

# Event sourcing
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/EventSourcingTest.php

# API workflow
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/ApiWorkflowTest.php

# Exports
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/ExportTest.php
```

### Run by Group
```bash
# All critical tests
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=e2e-critical

# Invoicing tests only
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=invoicing

# Specific category
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=prorata-calculation
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=event-sourcing
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=api-workflow
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=exports
```

### Run Specific Test Method
```bash
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --filter=e2e_001_it_generates_complete_monthly_batch
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --filter=e2e_002_it_calculates_three_level_prorata
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --filter=e2e_006_it_completes_full_invoice_lifecycle
```

### Run with Coverage
```bash
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --coverage --coverage-html=coverage/e2e
```

---

## 🏗️ Test Structure

### Base Class: `E2ETestCase`

All E2E tests extend `Tests\E2E\E2ETestCase` which provides:

**Automatic Setup:**
- Database refresh (`RefreshDatabase` trait)
- Cache clearing (Redis)
- Queue clearing
- E2E database connection

**Helper Methods:**
```php
// Create division + financer with relations
$data = $this->createTestFinancerWithDivision($divisionData, $financerData);
$division = $data['division'];
$financer = $data['financer'];

// Generate invoice
$invoice = $this->generateInvoice($financerId, '2025-10');

// Assertions
$this->assertJobDispatched(MyJob::class);
$this->assertEmailSent(MyMailable::class);
```

---

## ✨ Test Examples

### Example 1: Batch Generation
```php
#[Test]
#[Group('e2e')]
#[Group('e2e-critical')]
public function e2e_001_it_generates_complete_monthly_batch_with_prorata(): void
{
    // Setup: 2 financers, 80 beneficiaries, 1 module
    ['division' => $division] = $this->createTestFinancerWithDivision([
        'core_package_price' => 100000,
    ]);

    // Execute: Batch generation command
    $this->artisan(GenerateMonthlyInvoicesCommand::class, [
        '--month-year' => '2025-10',
    ])->assertSuccessful();

    // Verify: 3 invoices created (2 financer + 1 division)
    $this->assertEquals(3, Invoice::count());
}
```

### Example 2: Prorata Calculation
```php
#[Test]
#[Group('e2e')]
public function e2e_002_it_calculates_three_level_prorata_correctly(): void
{
    // Setup: Contract 20/10, Beneficiary 10/10, Module 15/10
    Carbon::setTestNow(Carbon::create(2025, 10, 25));

    ['financer' => $financer] = $this->createTestFinancerWithDivision([], [
        'contract_start_date' => Carbon::create(2025, 10, 20),
    ]);

    // Verify: Contract prorata = 12/31 = 0.3871
    $invoice = $this->generateInvoice($financer->id, '2025-10');
    $this->assertEquals(0.3871, $invoice->contract_prorata_ratio);

    Carbon::setTestNow();
}
```

### Example 3: API Workflow
```php
#[Test]
#[Group('e2e')]
public function e2e_006_it_completes_full_invoice_lifecycle_via_api(): void
{
    $invoice = Invoice::factory()->create(['status' => 'draft']);

    // Step 1: Confirm
    $this->actingAs($admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/confirm")
        ->assertOk();

    // Step 2: Download PDF
    $response = $this->actingAs($admin)
        ->get("/api/v1/invoices/{$invoice->id}/pdf");
    $response->assertHeader('Content-Type', 'application/pdf');

    // Step 3: Send Email
    $this->actingAs($admin)
        ->postJson("/api/v1/invoices/{$invoice->id}/send-email")
        ->assertOk();

    Queue::assertPushed(SendInvoiceEmailJob::class);
}
```

---

## 🎯 Phase 1 Test Coverage

### E2E-001: Monthly Batch Generation ✅
- [x] Full workflow: Command → Jobs → DB → Events
- [x] Multiple divisions support
- [x] Prorata mid-month contracts
- [x] Division invoice aggregation
- [x] Sequential invoice numbering

### E2E-002/003: Prorata Calculations ✅
- [x] Three-level prorata (contract + beneficiary + module)
- [x] February non-leap year (28 days)
- [x] February leap year (29 days)
- [x] Month length variations (30 vs 31 days)
- [x] End-of-month contract starts
- [x] Eurocent precision validation

### E2E-004: Event Sourcing ✅
- [x] Balance projection from events
- [x] Event replay reconstruction
- [x] Concurrent event recording
- [x] Balance evolution tracking

### E2E-006/007: API Workflow ✅
- [x] Complete lifecycle (List → Confirm → PDF → Email → Paid)
- [x] Pagination with 150+ invoices
- [x] Combined filters (status + period + type)
- [x] HTTP status codes validation

### E2E-008/009/010: Exports ✅
- [x] PDF generation with S3 cache
- [x] Excel streaming (500+ invoices)
- [x] Email with PDF attachment
- [x] Error handling scenarios

---

## 📊 Performance Benchmarks (Phase 1)

| Operation | Target | Actual | Status |
|-----------|--------|--------|--------|
| Batch 100 invoices | < 5min | TBD | ⏳ |
| API pagination | < 500ms | TBD | ⏳ |
| PDF generation (first) | < 3s | TBD | ⏳ |
| PDF cache hit | < 100ms | TBD | ⏳ |
| Excel export (500) | < 10s | TBD | ⏳ |

---

## 🔧 Troubleshooting

### Database Issues
```bash
# Reset E2E database
docker compose exec app_engage php artisan migrate:fresh --database=e2e --env=e2e --force

# Verify database connection
docker exec db_engage_testing psql -U root -d hexeko_e2e -c "SELECT COUNT(*) FROM invoices;"
```

### Test Failures
```bash
# Run single test with verbose output
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/MonthlyBatchGenerationTest.php -vvv

# Check logs
docker compose logs -f app_engage
```

### Cache Issues
```bash
# Clear Redis cache
docker compose exec app_engage php artisan cache:clear --env=e2e

# Verify Redis connection
docker compose exec redis-cluster redis-cli PING
```

---

## 📝 Writing New E2E Tests

### 1. Create Test File
```php
<?php

declare(strict_types=1);

namespace Tests\E2E\Invoicing;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\E2E\E2ETestCase;

#[Group('e2e')]
#[Group('invoicing')]
class MyNewTest extends E2ETestCase
{
    #[Test]
    public function e2e_XXX_it_does_something_specific(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

### 2. Add Test Groups
- `#[Group('e2e')]` - Required for all E2E tests
- `#[Group('e2e-critical')]` - For critical path tests
- `#[Group('invoicing')]` - For invoicing-related tests
- `#[Group('{category}')]` - For specific category

### 3. Follow Naming Convention
- Method: `e2e_{number}_it_{action}_{expected_result}()`
- Use descriptive names
- Start with `e2e_` prefix

### 4. Use Proper Setup
```php
protected function setUp(): void
{
    parent::setUp();

    // Fake services as needed
    Queue::fake();
    Mail::fake();
    Storage::fake('s3-local');
}
```

---

## 🚀 Next Steps

1. **Run Phase 1 Tests**
   ```bash
   docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=e2e-critical
   ```

2. **Review Results**
   - Check all tests pass
   - Review performance metrics
   - Identify bottlenecks

3. **Implement Phase 2**
   - See [Complete Test Suite](../../docs/testing/e2e-test-suite-complete.md)
   - Priority: Performance, VAT, Concurrency

4. **CI/CD Integration**
   - Add E2E stage to pipeline
   - Set up performance baselines
   - Configure notifications

---

## 📚 Documentation

- **[Complete Test Suite](../../docs/testing/e2e-test-suite-complete.md)** - Full roadmap
- **[E2E Setup Guide](../../docs/testing/e2e-setup.md)** - Configuration details
- **[Testing Best Practices](../README.md)** - General testing guidelines

---

**Maintainer:** Development Team
**Last Updated:** 2025-10-09
**Status:** Phase 1 Complete ✅