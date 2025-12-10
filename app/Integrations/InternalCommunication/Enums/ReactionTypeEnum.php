<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @extends Enum<string>
 */
final class ReactionTypeEnum extends Enum implements LocalizedEnum
{
    const LIKE = 'like';

    const LOVE = 'love';

    const LAUGH = 'laugh';

    const SURPRISED = 'surprised';

    const SAD = 'sad';

    const DISLIKE = 'dislike';

    /**
     * @return array<string, string>
     */
    public static function emojiMap(): array
    {
        return [
            self::LIKE => '👍',
            self::LOVE => '❤️',
            self::LAUGH => '😂',
            self::SURPRISED => '😮',
            self::SAD => '😢',
            self::DISLIKE => '👎',
        ];
    }

    public static function emoji(string $value): string
    {
        return self::emojiMap()[$value] ?? '';
    }
}
