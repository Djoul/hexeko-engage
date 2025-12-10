<?php

namespace Tests\Unit\Modules\InternalCommunication;

use App\Integrations\InternalCommunication\Services\TokenQuotaService;
use App\Models\CreditBalance;
use App\Models\Financer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('credit')]
#[Group('internal-communication')]
#[Group('token-quota')]
class TokenQuotaServiceTest extends TestCase
{
    protected TokenQuotaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TokenQuotaService;
    }

    #[Test]
    public function it_calculates_quota_information_from_credit_balance(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $financer->load('division');

        CreditBalance::create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 150000,
            'context' => [
                'initial_quota' => 200000,
                'consumed' => 50000,
            ],
        ]);

        // Act
        $quota = $this->service->getQuotaForFinancer($financer->id);

        // Assert
        $this->assertEquals($financer->division_id, $quota['division_id']);
        $this->assertEquals($financer->division->name, $quota['division_name']);
        $this->assertEquals(200000, $quota['total']);
        $this->assertEquals(50000, $quota['consumed']);
        $this->assertEquals(150000, $quota['remaining']);
        $this->assertEquals(25.0, $quota['percentage_used']);
    }

    #[Test]
    public function it_returns_zero_quota_when_no_balance_exists(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $financer->load('division');

        // Act
        $quota = $this->service->getQuotaForFinancer($financer->id);

        // Assert
        $this->assertNull($quota['division_id']);
        $this->assertNull($quota['division_name']);
        $this->assertEquals(0, $quota['total']);
        $this->assertEquals(0, $quota['consumed']);
        $this->assertEquals(0, $quota['remaining']);
        $this->assertEquals(0.0, $quota['percentage_used']);
    }

    #[Test]
    public function it_calculates_percentage_correctly(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $financer->load('division');

        CreditBalance::create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 25000,
            'context' => [
                'initial_quota' => 100000,
                'consumed' => 75000,
            ],
        ]);

        // Act
        $quota = $this->service->getQuotaForFinancer($financer->id);

        // Assert
        $this->assertEquals($financer->division_id, $quota['division_id']);
        $this->assertEquals($financer->division->name, $quota['division_name']);
        $this->assertEquals(75.0, $quota['percentage_used']);
    }

    #[Test]
    public function it_handles_zero_total_quota_without_division_by_zero(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $financer->load('division');

        CreditBalance::create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 0,
            'context' => [
                'initial_quota' => 0,
                'consumed' => 0,
            ],
        ]);

        // Act
        $quota = $this->service->getQuotaForFinancer($financer->id);

        // Assert
        $this->assertEquals($financer->division_id, $quota['division_id']);
        $this->assertEquals($financer->division->name, $quota['division_name']);
        $this->assertEquals(0.0, $quota['percentage_used']);
    }

    #[Test]
    public function it_handles_missing_context_data(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $financer->load('division');

        CreditBalance::create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 50000,
            'context' => null, // Missing context
        ]);

        // Act
        $quota = $this->service->getQuotaForFinancer($financer->id);

        // Assert - When no context, uses config default for total
        $expectedTotal = config('ai.initial_token_amount'); // 1000000 par défaut
        $this->assertEquals($expectedTotal, $quota['total']);
        $this->assertEquals($expectedTotal - 50000, $quota['consumed']);
        $this->assertEquals(50000, $quota['remaining']); // Uses balance as remaining
        $this->assertEquals($financer->division_id, $quota['division_id']);
        $this->assertEquals($financer->division->name, $quota['division_name']);
        $this->assertEquals(95.0, $quota['percentage_used']);
    }

    #[Test]
    public function it_handles_malformed_context_data(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $financer->load('division');

        CreditBalance::create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 75000,
            'context' => [
                'some_other_field' => 'value',
                // Missing initial_quota and consumed
            ],
        ]);

        // Act
        $quota = $this->service->getQuotaForFinancer($financer->id);

        // Assert - When malformed context, uses config default for total
        $expectedTotal = config('ai.initial_token_amount'); // 1000000 par défaut
        $this->assertEquals($expectedTotal, $quota['total']);
        $this->assertEquals($expectedTotal - 75000, $quota['consumed']);
        $this->assertEquals(75000, $quota['remaining']);
        $this->assertEquals($financer->division_id, $quota['division_id']);
        $this->assertEquals($financer->division->name, $quota['division_name']);
        $this->assertEquals(92.5, $quota['percentage_used']);
    }
}
