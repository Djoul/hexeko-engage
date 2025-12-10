<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoicing;

use App\Services\Invoicing\CalculateProrataService;
use App\Services\Invoicing\GetActiveBeneficiariesService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('user')]
class GetActiveBeneficiariesServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CalculateProrataService $calculateProrataService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculateProrataService = app(CalculateProrataService::class);
    }

    #[Test]
    public function it_counts_active_beneficiaries_in_period(): void
    {
        $financer = ModelFactory::createFinancer();

        ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2024-12-01'),
            ]],
        ]);

        ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-01-10'),
            ]],
        ]);

        // User inactive for the period should not be counted
        ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-03-01'),
            ]],
        ]);

        $service = new GetActiveBeneficiariesService($this->calculateProrataService);

        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-28');

        $count = $service->getActiveBeneficiariesCount($financer->id, $periodStart, $periodEnd);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_returns_prorata_map_for_active_beneficiaries(): void
    {
        $financer = ModelFactory::createFinancer();
        $userOne = ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2024-12-01'),
            ]],
        ]);
        $userTwo = ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-02-10'),
            ]],
        ]);

        $mock = $this->mock(CalculateProrataService::class);
        $mock->shouldReceive('calculateBeneficiaryProrata')
            ->once()
            ->andReturn([
                $userOne->id => 1.0,
                $userTwo->id => 0.5,
            ]);

        $service = new GetActiveBeneficiariesService($mock);

        $result = $service->getActiveBeneficiariesWithProrata(
            $financer->id,
            Carbon::parse('2025-02-01'),
            Carbon::parse('2025-02-28')
        );

        $this->assertSame([
            $userOne->id => 1.0,
            $userTwo->id => 0.5,
        ], $result);
    }
}
