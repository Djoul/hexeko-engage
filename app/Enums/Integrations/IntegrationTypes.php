<?php

namespace App\Enums\Integrations;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/*
 * @phpstan-ignore-next-line
 */
final class IntegrationTypes extends Enum implements LocalizedEnum
{
    const THIRD_PARTY_API = 'third_party_api'; // External API developed by another company

    const INTERNAL_API = 'internal_api'; // API developed internally

    const EMBEDDED_SERVICE = 'embedded'; // Integration using a feature from the monolithic application
}
