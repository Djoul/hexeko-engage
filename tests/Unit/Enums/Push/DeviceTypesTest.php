<?php

namespace Tests\Unit\Enums\Push;

use App\Enums\DeviceTypes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('push')]
#[Group('notification')]
class DeviceTypesTest extends TestCase
{
    #[Test]
    public function it_has_correct_device_type_values(): void
    {
        $this->assertEquals('ios', DeviceTypes::IOS);
        $this->assertEquals('android', DeviceTypes::ANDROID);
        $this->assertEquals('web', DeviceTypes::WEB);
        $this->assertEquals('desktop', DeviceTypes::DESKTOP);
    }

    #[Test]
    public function it_can_get_all_device_types(): void
    {
        $values = DeviceTypes::values();

        $this->assertIsArray($values);
        $this->assertCount(4, $values);
        $this->assertContains('ios', $values);
        $this->assertContains('android', $values);
        $this->assertContains('web', $values);
        $this->assertContains('desktop', $values);
    }

    #[Test]
    public function it_can_create_from_value(): void
    {
        $ios = DeviceTypes::fromValue('ios');
        $this->assertEquals('ios', $ios->value);

        $android = DeviceTypes::fromValue('android');
        $this->assertEquals('android', $android->value);
    }

    #[Test]
    public function it_can_try_from_value(): void
    {
        $ios = DeviceTypes::coerce('ios');
        $this->assertInstanceOf(DeviceTypes::class, $ios);
        $this->assertEquals('ios', $ios->value);

        $invalid = DeviceTypes::coerce('invalid');
        $this->assertNull($invalid);
    }

    #[Test]
    public function it_has_label_method(): void
    {
        $this->assertEquals('iOS', DeviceTypes::IOS()->label());
        $this->assertEquals('Android', DeviceTypes::ANDROID()->label());
        $this->assertEquals('Web Browser', DeviceTypes::WEB()->label());
        $this->assertEquals('Desktop App', DeviceTypes::DESKTOP()->label());
    }

    #[Test]
    public function it_can_check_if_mobile_device(): void
    {
        $this->assertTrue(DeviceTypes::IOS()->isMobile());
        $this->assertTrue(DeviceTypes::ANDROID()->isMobile());
        $this->assertFalse(DeviceTypes::WEB()->isMobile());
        $this->assertFalse(DeviceTypes::DESKTOP()->isMobile());
    }
}
