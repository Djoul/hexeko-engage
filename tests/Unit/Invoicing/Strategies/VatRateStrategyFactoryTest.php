<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\Strategies;

use App\Services\Invoicing\Strategies\BelgiumVatRateStrategy;
use App\Services\Invoicing\Strategies\FallbackVatRateStrategy;
use App\Services\Invoicing\Strategies\FrenchVatRateStrategy;
use App\Services\Invoicing\Strategies\PortugueseVatRateStrategy;
use App\Services\Invoicing\Strategies\VatRateStrategyFactory;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class VatRateStrategyFactoryTest extends TestCase
{
    private VatRateStrategyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = app(VatRateStrategyFactory::class);
    }

    #[Test]
    public function it_returns_strategy_for_supported_country(): void
    {
        $strategy = $this->factory->getStrategy('FR');
        $this->assertInstanceOf(FrenchVatRateStrategy::class, $strategy);
        $this->assertSame(0.20, $strategy->getRate());

        $strategy = $this->factory->getStrategy('BE');
        $this->assertInstanceOf(BelgiumVatRateStrategy::class, $strategy);
        $this->assertSame(0.21, $strategy->getRate());

        $strategy = $this->factory->getStrategy('PT');
        $this->assertInstanceOf(PortugueseVatRateStrategy::class, $strategy);
        $this->assertSame(0.23, $strategy->getRate());
    }

    #[Test]
    public function it_returns_fallback_strategy_for_unsupported_country(): void
    {
        $strategy = $this->factory->getStrategy('XX');

        $this->assertInstanceOf(FallbackVatRateStrategy::class, $strategy);
        $this->assertSame(0.20, $strategy->getRate());
    }

    #[Test]
    public function it_logs_warning_when_using_fallback_strategy(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Using fallback VAT rate for unsupported country',
                [
                    'country' => 'XX',
                    'fallback_rate' => 0.20,
                ]
            );

        $this->factory->getStrategy('XX');
    }
}
