<?php

declare(strict_types=1);

namespace App\Enums\Security;

use App\Enums\BaseEnum;

/**
 * Authorization mode for multi-financer access control
 *
 * @extends BaseEnum<string>
 */
final class AuthorizationMode extends BaseEnum
{
    /**
     * Standard user mode - access limited to their own financers/divisions
     */
    const SELF = 'self';

    /**
     * Take Control mode - Admin scoping to specific financer(s)
     * Activated via financer_id query parameter
     * - GOD/HEXEKO admins: can target any financer
     * - Division admins: can target financers within their division
     *
     * @todo Refactor activation mechanism (alternative approach TBD)
     */
    const TAKE_CONTROL = 'take_control';

    /**
     * Global mode - GOD/HEXEKO admin with access to all financers/divisions
     * Without Take Control activation
     */
    const GLOBAL = 'global';
}
