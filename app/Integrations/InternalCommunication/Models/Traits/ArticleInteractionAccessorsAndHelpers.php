<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Models\Traits;

use App\Integrations\InternalCommunication\Enums\ReactionTypeEnum;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;

/**
 * @mixin ArticleInteraction
 */
trait ArticleInteractionAccessorsAndHelpers
{
    /**
     * Get the emoji representation of the reaction.
     */
    public function getEmojiAttribute(): string
    {
        $reaction = $this->getAttributeValue('reaction');
        if ($reaction === null) {
            return '';
        }

        return ReactionTypeEnum::emoji(safeString($reaction));
    }
}
