<?php

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @extends Enum<string>
 */
final class OrigineInterfaces extends Enum implements LocalizedEnum
{
    const MOBILE = 'mobile';

    const WEB_FINANCER = 'web_financer';

    const WEB_BENEFICIARY = 'web_beneficiary';
}
