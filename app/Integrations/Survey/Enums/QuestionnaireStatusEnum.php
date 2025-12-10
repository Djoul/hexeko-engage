<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static DRAFT()
 * @method static static published()
 */
/** @extends Enum<string> */
final class QuestionnaireStatusEnum extends Enum implements LocalizedEnum
{
    const DRAFT = 'draft';

    const PUBLISHED = 'published';
}
