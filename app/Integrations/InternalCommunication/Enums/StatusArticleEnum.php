<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static DRAFT()
 * @method static static PUBLISHED()
 * @method static static PENDING()
 * @method static static DELETED()
 *
 * @extends Enum<string>
 */
final class StatusArticleEnum extends Enum implements LocalizedEnum
{
    /**
     * Article is in draft mode, not visible to users.
     */
    public const DRAFT = 'draft';

    /**
     * Article is published and visible to users.
     */
    public const PUBLISHED = 'published';

    /**
     * Article is pending review before publication.
     */
    public const PENDING = 'pending';

    /**
     * Article is marked as deleted (soft delete).
     */
    public const DELETED = 'deleted';
}
