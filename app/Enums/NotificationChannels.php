<?php

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static EMAIL()
 * @method static static PUSH()
 * @method static static SMS()
 * @method static static IN_APP()
 */
final class NotificationChannels extends Enum implements LocalizedEnum
{
    const EMAIL = 'email';

    const PUSH = 'push';

    const SMS = 'sms';

    const IN_APP = 'in_app';

    /**
     * Get the description for a notification channel
     */
    public function description(): string
    {
        return match ($this->value) {
            self::EMAIL => 'Email notification',
            self::PUSH => 'Push notification',
            self::SMS => 'SMS notification',
            self::IN_APP => 'In-app notification',
            default => 'Unknown channel',
        };
    }

    /**
     * Check if the channel is enabled
     */
    public function isEnabled(): bool
    {
        return match ($this->value) {
            self::EMAIL, self::PUSH, self::IN_APP => true,
            self::SMS => false, // SMS disabled for now
            default => false,
        };
    }
}
