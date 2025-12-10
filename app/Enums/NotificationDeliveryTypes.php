<?php

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static BROADCAST()
 * @method static static SEGMENT()
 * @method static static USER()
 * @method static static TOPIC()
 */
final class NotificationDeliveryTypes extends Enum implements LocalizedEnum
{
    const BROADCAST = 'broadcast';

    const SEGMENT = 'segment';

    const USER = 'user';

    const TOPIC = 'topic';

    /**
     * Get the description for a delivery type
     */
    public function description(): string
    {
        return match ($this->value) {
            self::BROADCAST => 'Broadcast to all users',
            self::SEGMENT => 'Send to specific segments',
            self::USER => 'Send to specific users',
            self::TOPIC => 'Send to topic subscribers',
            default => 'Unknown delivery type',
        };
    }

    /**
     * Check if the delivery type requires recipients
     */
    public function requiresRecipients(): bool
    {
        return match ($this->value) {
            self::USER => true,
            self::SEGMENT, self::TOPIC => false,
            self::BROADCAST => false,
            default => false,
        };
    }

    /**
     * Check if the delivery type requires segments
     */
    public function requiresSegments(): bool
    {
        return match ($this->value) {
            self::SEGMENT => true,
            default => false,
        };
    }

    /**
     * Check if the delivery type requires topics
     */
    public function requiresTopics(): bool
    {
        return match ($this->value) {
            self::TOPIC => true,
            default => false,
        };
    }
}
