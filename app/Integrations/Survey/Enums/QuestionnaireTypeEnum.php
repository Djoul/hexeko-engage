<?php

namespace App\Integrations\Survey\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static NPS()
 * @method static static SATISFACTION()
 * @method static static CUSTOM()
 */
/** @extends Enum<string> */
final class QuestionnaireTypeEnum extends Enum implements LocalizedEnum
{
    const NPS = 'nps';

    const SATISFACTION = 'satisfaction';

    const CUSTOM = 'custom';
}
