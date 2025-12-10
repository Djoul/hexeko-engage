<?php

declare(strict_types=1);

namespace App\Enums\User;

use App\Enums\BaseEnum;

/**
 * Enum representing the different status states for Financer entities.
 *
 * @method static static ACTIVE()
 * @method static static PENDING()
 * @method static static ARCHIVED()
 */
class UserStatus extends BaseEnum
{
    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    public const INVITED = 'invited';
}
