<?php

declare(strict_types=1);

namespace Tests\E2E;

use App\Actions\Invoicing\GenerateFinancerInvoiceAction;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

/**
 * Base class for End-to-End tests
 *
 * E2E tests validate complete business flows across multiple layers:
 * - Database operations
 * - Queue jobs
 * - External API calls
 * - File generation
 * - Email sending
 *
 * Like unit/feature tests, E2E tests use DatabaseTransactions for isolation.
 */
abstract class E2ETestCase extends TestCase
{
    use DatabaseTransactions;

    /**
     * Setup that runs before EACH E2E test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear Redis cache to avoid cross-test pollution (direct call to avoid artisan overhead)
        Cache::flush();

        // Seed common base data needed across all E2E tests
        $this->seedBaseData();
    }

    /**
     * Common data needed for all E2E tests
     *
     * Override this method in specific test classes to add
     * test-specific base data.
     */
    protected function seedBaseData(): void
    {
        // Base data can be seeded here
        // Example: default countries, currencies, VAT rates, etc.
    }

    /**
     * Helper: Generate an invoice for a financer
     *
     * Simplifies invoice generation in E2E scenarios
     *
     * @param  string  $financerId  UUID of the financer
     * @param  string  $monthYear  Format: YYYY-MM (e.g., "2025-10")
     */
    protected function generateInvoice(string $financerId, string $monthYear): Invoice
    {
        $action = app(GenerateFinancerInvoiceAction::class);

        return $action->execute(
            GenerateFinancerInvoiceDTO::from([
                'financerId' => $financerId,
                'monthYear' => $monthYear,
            ])
        );
    }

    /**
     * Helper: Create a complete test financer with division
     *
     * Returns an array with ['division' => Division, 'financer' => Financer]
     */
    protected function createTestFinancerWithDivision(array $divisionData = [], array $financerData = []): array
    {
        $division = ModelFactory::createDivision(array_merge([
            'name' => 'Test Division',
            'country' => 'BE',
            'currency' => 'EUR',
            'status' => 'active',
        ], $divisionData));

        $financer = ModelFactory::createFinancer(array_merge([
            'division_id' => $division->id,
            'name' => 'Test Financer',
            'status' => 'active',
            'contract_start_date' => now()->subMonths(3),
        ], $financerData));

        return [
            'division' => $division,
            'financer' => $financer,
        ];
    }

    /**
     * Helper: Assert that a job was dispatched to the queue
     */
    protected function assertJobDispatched(string $jobClass): void
    {
        Queue::assertPushed($jobClass);
    }

    /**
     * Helper: Assert that an email was sent
     */
    protected function assertEmailSent(string $mailable): void
    {
        Mail::assertSent($mailable);
    }

    /**
     * Teardown after each test
     */
    protected function tearDown(): void
    {
        // Clear any remaining cache (direct call to avoid artisan overhead)
        Cache::flush();

        parent::tearDown();
    }
}
