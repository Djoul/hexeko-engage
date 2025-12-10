<?php

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @extends Enum<string>
 */
final class CreditTypes extends Enum implements LocalizedEnum
{
    const AI_TOKEN = 'ai_token';

    const SMS = 'sms';

    const EMAIL = 'email';

    const CASH = 'cash';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return [
            self::AI_TOKEN,
            self::SMS,
            self::EMAIL,
            self::CASH,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::AI_TOKEN => 'AI Token',
            self::SMS => 'SMS',
            self::EMAIL => 'Email',
            self::CASH => 'Cash',
        ];
    }
}
