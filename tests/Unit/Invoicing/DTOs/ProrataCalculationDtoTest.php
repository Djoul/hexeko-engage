<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\DTOs;

use App\DTOs\Invoicing\ProrataCalculationDTO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class ProrataCalculationDtoTest extends TestCase
{
    #[Test]
    public function it_serializes_prorata_information(): void
    {
        $dto = new ProrataCalculationDTO(
            percentage: 0.5,
            days: 15,
            totalDays: 30,
            periodStart: '2025-03-01',
            periodEnd: '2025-03-31',
            activationDate: '2025-03-10',
            deactivationDate: null,
        );

        $payload = $dto->toArray();

        $this->assertSame(0.5, $payload['percentage']);
        $this->assertSame(15, $payload['days']);
        $this->assertSame('2025-03-10', $payload['activation_date']);
        $this->assertArrayHasKey('deactivation_date', $payload);
    }
}
