<?php

namespace Tests\Unit\Enums\IDP;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('role')]
#[Group('permission')]
#[Group('idp')]
class RolePermissionTest extends TestCase
{
    #[Test]
    public function gods_can_always_have_all_permissions(): void
    {
        $this->assertGreaterThanOrEqual(
            count(PermissionDefaults::asArray()),
            count(RoleDefaults::getPermissionsByRole(RoleDefaults::GOD))
        );
    }
}
