<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Actions\Invoicing\GenerateFinancerInvoiceAction;
use App\DTOs\Invoicing\InvoiceDTO;
use App\Jobs\Invoicing\GenerateFinancerInvoiceJob;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('jobs')]
class GenerateFinancerInvoiceJobTest extends TestCase
{
    use DatabaseTransactions;

    private string $financerId;

    private string $batchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financerId = (string) fake()->uuid();
        $this->batchId = (string) fake()->uuid();

        Config::set('invoicing.generation', [
            'queue' => 'invoicing-generation',
            'retry_attempts' => 3,
            'retry_backoff' => 60,
        ]);
    }

    #[Test]
    public function it_dispatches_to_configured_queue(): void
    {
        Queue::fake();

        GenerateFinancerInvoiceJob::dispatch($this->financerId, '2025-05', $this->batchId);

        Queue::assertPushed(GenerateFinancerInvoiceJob::class, function (GenerateFinancerInvoiceJob $job): bool {
            return $job->queue === 'invoicing-generation'
                && $job->financerId === $this->financerId
                && $job->monthYear === '2025-05';
        });
    }

    #[Test]
    public function it_defines_unique_identifier_and_retry_settings(): void
    {
        $job = new GenerateFinancerInvoiceJob($this->financerId, '2025-05', $this->batchId);

        $this->assertSame('invoicing-generation', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
        $this->assertSame($this->financerId.'::2025-05', $job->uniqueId());
    }

    #[Test]
    public function it_invokes_generate_financer_invoice_action(): void
    {
        $invoice = Invoice::factory()->create();
        $dto = InvoiceDTO::fromModel($invoice);

        $action = Mockery::mock(GenerateFinancerInvoiceAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->with($this->financerId, '2025-05', $this->batchId)
            ->andReturn($dto);

        $job = new GenerateFinancerInvoiceJob($this->financerId, '2025-05', $this->batchId);
        $job->handle($action);
    }
}
