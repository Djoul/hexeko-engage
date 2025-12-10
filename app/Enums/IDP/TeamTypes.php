<?php

namespace App\Enums\IDP;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/*
 * @phpstan-ignore-next-line
 */
final class TeamTypes extends Enum implements LocalizedEnum
{
    const DIVISION = 'div';

    const FINANCER = 'fin';

    const OTHER = 'oth';

    const GLOBAL = 'glo';
}
