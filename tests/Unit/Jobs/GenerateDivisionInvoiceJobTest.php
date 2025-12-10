<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Actions\Invoicing\GenerateDivisionInvoiceAction;
use App\Aggregates\InvoiceGenerationAggregate;
use App\Jobs\Invoicing\GenerateDivisionInvoiceJob;
use App\Models\InvoiceGenerationBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('jobs')]
class GenerateDivisionInvoiceJobTest extends TestCase
{
    use DatabaseTransactions;

    private string $divisionId;

    private string $batchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->divisionId = (string) fake()->uuid();
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

        GenerateDivisionInvoiceJob::dispatch($this->divisionId, '2025-05', $this->batchId);

        Queue::assertPushed(GenerateDivisionInvoiceJob::class, function (GenerateDivisionInvoiceJob $job): bool {
            return $job->queue === 'invoicing-generation'
                && $job->divisionId === $this->divisionId
                && $job->monthYear === '2025-05';
        });
    }

    #[Test]
    public function it_has_unique_and_retry_configuration(): void
    {
        $job = new GenerateDivisionInvoiceJob($this->divisionId, '2025-05', $this->batchId);

        $this->assertSame('invoicing-generation', $job->queue);
        $this->assertSame(3, $job->tries);
        $this->assertSame(60, $job->backoff);
        $this->assertSame($this->divisionId.'::2025-05', $job->uniqueId());
    }

    #[Test]
    public function it_records_failure_when_action_throws_exception(): void
    {
        Date::setTestNow('2025-05-01 09:00:00');

        InvoiceGenerationAggregate::retrieve($this->batchId)
            ->batchStarted($this->batchId, '2025-05', 1, Date::now())
            ->persist();

        /** @var MockInterface $action */
        $action = Mockery::mock(GenerateDivisionInvoiceAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->with($this->divisionId, '2025-05', $this->batchId)
            ->andThrow(new RuntimeException('Generation failed'));

        $job = new GenerateDivisionInvoiceJob($this->divisionId, '2025-05', $this->batchId);

        try {
            $job->handle($action);
            $this->fail('Expected RuntimeException to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Generation failed', $exception->getMessage());
        }

        $batch = InvoiceGenerationBatch::where('batch_id', $this->batchId)->firstOrFail();
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame('Generation failed', $batch->last_error);

        Date::setTestNow();
    }
}
