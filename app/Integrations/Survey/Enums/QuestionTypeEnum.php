<?php

namespace App\Integrations\Survey\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static SCALE()
 * @method static static TEXT()
 * @method static static SINGLE_CHOICE()
 * @method static static MULTIPLE_CHOICE()
 */
/** @extends Enum<string> */
final class QuestionTypeEnum extends Enum implements LocalizedEnum
{
    const SCALE = 'scale';

    const TEXT = 'text';

    const SINGLE_CHOICE = 'single_choice';

    const MULTIPLE_CHOICE = 'multiple_choice';

    public static function requiresOptions(mixed $value): bool
    {
        return in_array($value, [
            self::SINGLE_CHOICE,
            self::MULTIPLE_CHOICE,
            self::SCALE,
        ]);
    }

    public static function type(mixed $value): string
    {
        return $value == self::MULTIPLE_CHOICE ? 'array' : 'string';
    }
}
