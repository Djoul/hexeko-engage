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
final class SurveyStatusEnum extends Enum implements LocalizedEnum
{
    const DRAFT = 'draft';

    const NEW = 'new';

    const PUBLISHED = 'published';

    const SCHEDULED = 'scheduled';

    const ACTIVE = 'active';

    const CLOSED = 'closed';

    const ARCHIVED = 'archived';

    /**
     * @return array<string>
     */
    public static function getStaticValues(): array
    {
        return [
            self::DRAFT,
            self::PUBLISHED,
            self::ARCHIVED,
        ];
    }

    public static function getDynamicValues(): array
    {
        return [
            self::NEW,
            self::SCHEDULED,
            self::ACTIVE,
            self::CLOSED,
        ];
    }

    public static function getAllValues(): array
    {
        return array_merge(self::getStaticValues(), self::getDynamicValues());
    }
}
