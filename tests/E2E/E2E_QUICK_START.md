# E2E Tests - Quick Start Guide 🚀

## ✅ Phase 1 Implementation - COMPLETE

**Status:** 10 E2E tests implemented (18 test methods)
**Coverage:** Critical workflows, prorata, event sourcing, API, exports
**Documentation:** Complete with templates for Phases 2 & 3

---

## 🎯 What's Included

### Phase 1 - Critical Tests ✅

| Test ID | Description | File | Status |
|---------|-------------|------|--------|
| **E2E-001** | Monthly Batch Generation | `MonthlyBatchGenerationTest.php` | ✅ |
| **E2E-002/003** | Prorata Calculations + Edge Cases | `ProrataCalculationTest.php` | ✅ |
| **E2E-004** | Event Sourcing Balance | `EventSourcingTest.php` | ✅ |
| **E2E-006/007** | API Workflow + Pagination | `ApiWorkflowTest.php` | ✅ |
| **E2E-008/009/010** | PDF/Excel/Email Exports | `ExportTest.php` | ✅ |

**Total:** 5 test files, 18 test methods covering 10 critical E2E scenarios

---

## 🚀 Running E2E Tests

### Prerequisites

1. **E2E Database Setup** (one-time):
```bash
# Create E2E database
docker exec db_engage_testing psql -U root -d postgres -c "CREATE DATABASE hexeko_e2e;"

# Run migrations
docker compose exec app_engage php artisan migrate --database=e2e --env=e2e --force
```

2. **Verify Configuration**:
```bash
# Check .env.e2e exists with:
DB_CONNECTION=e2e
DB_E2E_HOST=db_engage_testing
DB_E2E_DATABASE=hexeko_e2e
```

### Run Commands

#### All E2E Tests
```bash
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml
```

#### By Priority
```bash
# Critical tests only (Phase 1)
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=e2e-critical

# All invoicing tests
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=invoicing
```

#### By Category
```bash
# Batch generation
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/MonthlyBatchGenerationTest.php

# Prorata calculations
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/ProrataCalculationTest.php

# Event sourcing
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/EventSourcingTest.php

# API workflow
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/ApiWorkflowTest.php

# Exports (PDF/Excel/Email)
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/ExportTest.php
```

#### Specific Test
```bash
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --filter=e2e_001_it_generates_complete_monthly_batch

docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --filter=e2e_002_it_calculates_three_level_prorata
```

---

## 📁 Project Structure

```
tests/E2E/
├── E2ETestCase.php                          # Base class with helpers
└── Invoicing/
    ├── MonthlyBatchGenerationTest.php       # E2E-001 ✅
    ├── ProrataCalculationTest.php           # E2E-002/003 ✅
    ├── EventSourcingTest.php                # E2E-004 ✅
    ├── ApiWorkflowTest.php                  # E2E-006/007 ✅
    └── ExportTest.php                       # E2E-008/009/010 ✅

docs/testing/
├── e2e-setup.md                             # Complete setup guide
└── e2e-test-suite-complete.md               # Full roadmap (25 tests)

.env.e2e                                     # E2E environment config
phpunit-e2e.xml                              # E2E PHPUnit config
```

---

## 🎓 Key Concepts

### E2E Test Base Class

All tests extend `E2ETestCase` which provides:

```php
// Automatic setup
protected function setUp(): void
{
    parent::setUp();
    $this->app['config']->set('database.default', 'e2e');
    $this->artisan('cache:clear');
    $this->artisan('queue:clear');
    $this->seedBaseData();
}

// Helper: Create division + financer with relations
$data = $this->createTestFinancerWithDivision(
    ['core_package_price' => 100000],
    ['contract_start_date' => now()]
);

// Helper: Generate invoice
$invoice = $this->generateInvoice($financerId, '2025-10');

// Helpers: Assertions
$this->assertJobDispatched(MyJob::class);
$this->assertEmailSent(MyMailable::class);
```

### Test Naming Convention

```php
#[Test]
#[Group('e2e')]
#[Group('e2e-critical')]
public function e2e_001_it_generates_complete_monthly_batch(): void
{
    // Arrange: Setup test data
    // Act: Execute operation
    // Assert: Verify results
}
```

### Groups Usage

- `#[Group('e2e')]` - All E2E tests
- `#[Group('e2e-critical')]` - Phase 1 critical tests
- `#[Group('invoicing')]` - Invoicing domain
- `#[Group('batch-generation')]` - Specific category

---

## 📊 Test Coverage Summary

### E2E-001: Monthly Batch Generation ✅
- [x] Command → Jobs → DB → Event Sourcing flow
- [x] Multiple financers with different contract dates
- [x] Prorata calculation for mid-month contracts
- [x] Division invoice aggregation
- [x] Sequential invoice numbering (FAC-YYYY-NNNNN)
- [x] Event sourcing validation

### E2E-002/003: Prorata Calculations ✅
- [x] Three-level prorata (contract × beneficiary × module)
- [x] February non-leap year (28 days)
- [x] February leap year (29 days)
- [x] Month length variations (30 vs 31 days)
- [x] End-of-month contract starts
- [x] Eurocent precision validation

### E2E-004: Event Sourcing ✅
- [x] Balance projection from stored events
- [x] Event replay reconstruction
- [x] Concurrent event recording
- [x] Balance evolution tracking

### E2E-006/007: API Workflow ✅
- [x] Complete lifecycle (List → Confirm → PDF → Email → Paid)
- [x] Pagination with 150+ invoices
- [x] Combined filters (status + period + type)
- [x] HTTP status codes validation

### E2E-008/009/010: Exports ✅
- [x] PDF generation with S3 caching
- [x] Excel streaming (500+ invoices)
- [x] Email with PDF attachment
- [x] Error handling scenarios

---

## 🔧 Troubleshooting

### Database Connection Issues

```bash
# Reset E2E database
docker compose exec app_engage php artisan migrate:fresh --database=e2e --env=e2e --force

# Verify connection
docker exec db_engage_testing psql -U root -d hexeko_e2e -c "SELECT COUNT(*) FROM invoices;"

# Check tables
docker exec db_engage_testing psql -U root -d hexeko_e2e -c "\dt"
```

### Test Failures

```bash
# Run with verbose output
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/MonthlyBatchGenerationTest.php -vvv

# Check application logs
docker compose logs -f app_engage

# Clear caches
docker compose exec app_engage php artisan cache:clear --env=e2e
docker compose exec app_engage php artisan config:clear
```

### Cache Issues

```bash
# Clear Redis cache
docker compose exec app_engage php artisan cache:clear --env=e2e

# Verify Redis connection
docker compose exec redis-cluster redis-cli PING
```

### Queue Issues

```bash
# Clear queue
docker compose exec app_engage php artisan queue:clear --env=e2e

# Monitor queue
docker compose exec app_engage php artisan queue:listen --queue=default
```

---

## 📈 Performance Benchmarks (Phase 1)

| Operation | Target | Status |
|-----------|--------|--------|
| Batch 100 invoices | < 5min | ⏳ TBD |
| API pagination (150 invoices) | < 500ms | ⏳ TBD |
| PDF generation (first) | < 3s | ⏳ TBD |
| PDF cache hit | < 100ms | ⏳ TBD |
| Excel export (500 invoices) | < 30s | ⏳ TBD |

**Note:** Run tests to get actual metrics and update this table.

---

## 🚧 Next Steps (Phases 2 & 3)

### Phase 2 - Important Tests (Pending)

| Test ID | Description | Priority |
|---------|-------------|----------|
| **E2E-011** | Performance - 1000 invoices batch | HIGH |
| **E2E-012** | Concurrency - Invoice number uniqueness | HIGH |
| **E2E-013** | Multi-module prorata variations | MEDIUM |
| **E2E-014** | International VAT (BE, FR, US) | CRITICAL |
| **E2E-015** | Bulk operations (100 invoices) | MEDIUM |

### Phase 3 - Complementary Tests (Pending)

- E2E-016 to E2E-025: Edge cases, security, permissions, audit trails

**Implementation Templates:** See `docs/testing/e2e-test-suite-complete.md`

---

## 📚 Documentation

- **[Complete E2E Setup Guide](../../docs/testing/e2e-setup.md)** - Configuration, infrastructure, best practices
- **[Full Test Suite Roadmap](../../docs/testing/e2e-test-suite-complete.md)** - All 25 tests with templates
- **[E2E README](README.md)** - Quick reference and examples

---

## ✅ Pre-Commit Checklist for E2E Tests

Before committing E2E test changes:

- [ ] All Phase 1 tests pass locally
- [ ] New tests extend `E2ETestCase`
- [ ] Tests use `#[Test]` attribute (not `/** @test */`)
- [ ] Proper groups applied (`#[Group('e2e')]`, etc.)
- [ ] Test naming follows convention: `e2e_{number}_it_{action}_{result}`
- [ ] Database uses `RefreshDatabase` trait
- [ ] Helper methods used from `E2ETestCase`
- [ ] Documentation updated if adding new tests
- [ ] Performance benchmarks noted (if applicable)

---

## 🎯 Example: Running Your First E2E Test

```bash
# 1. Setup database (one-time)
docker exec db_engage_testing psql -U root -d postgres -c "CREATE DATABASE hexeko_e2e;"
docker compose exec app_engage php artisan migrate --database=e2e --env=e2e --force

# 2. Run single test to verify setup
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml tests/E2E/Invoicing/MonthlyBatchGenerationTest.php

# 3. If successful, run all Phase 1 tests
docker compose exec app_engage php artisan test --configuration=phpunit-e2e.xml --group=e2e-critical

# 4. View results and celebrate 🎉
```

---

## 💡 Pro Tips

1. **Fast Feedback Loop**: Run specific test files during development, full suite before commit
2. **Parallel Testing**: E2E tests use separate database, can run alongside unit tests
3. **Debug Mode**: Add `-vvv` flag for verbose output when troubleshooting
4. **Cache Strategy**: E2E tests clear cache between runs, no manual cleanup needed
5. **Job Processing**: Tests use `Queue::fake()`, jobs are processed synchronously in tests

---

**Maintainer:** Development Team
**Last Updated:** 2025-10-09
**Status:** Phase 1 Complete ✅ (10 tests implemented)

**Ready to start?** Run your first E2E test with the command above! 🚀
