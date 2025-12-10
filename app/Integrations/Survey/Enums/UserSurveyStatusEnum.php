<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static DRAFT()
 * @method static static PUBLISHED()
 * @method static static ARCHIVED()
 */
/** @extends Enum<string> */
final class UserSurveyStatusEnum extends Enum implements LocalizedEnum
{
    const OPEN = 'open';

    const ONGOING = 'ongoing';

    const COMPLETED = 'completed';
}
