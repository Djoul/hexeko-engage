<?php

namespace Tests\Unit\DTOs\Push;

use App\DTOs\Push\DeviceRegistrationDTO;
use App\Enums\DeviceTypes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('push')]
#[Group('notification')]
class DeviceRegistrationDTOTest extends TestCase
{
    #[Test]
    public function it_can_create_dto_from_array(): void
    {
        $data = [
            'subscription_id' => 'onesignal-123-456',
            'device_type' => 'ios',
            'device_model' => 'iPhone 14 Pro',
            'device_os' => 'iOS 17.0',
            'app_version' => '1.0.0',
            'push_enabled' => true,
            'sound_enabled' => true,
            'vibration_enabled' => false,
            'tags' => ['premium' => true, 'locale' => 'fr-FR'],
        ];

        $dto = DeviceRegistrationDTO::from($data);

        $this->assertInstanceOf(DeviceRegistrationDTO::class, $dto);
        $this->assertEquals('onesignal-123-456', $dto->subscriptionId);
        $this->assertEquals(DeviceTypes::IOS, $dto->deviceType);
        $this->assertEquals('iPhone 14 Pro', $dto->deviceModel);
        $this->assertEquals('iOS 17.0', $dto->deviceOs);
        $this->assertEquals('1.0.0', $dto->appVersion);
        $this->assertTrue($dto->pushEnabled);
        $this->assertTrue($dto->soundEnabled);
        $this->assertFalse($dto->vibrationEnabled);
        $this->assertEquals(['premium' => true, 'locale' => 'fr-FR'], $dto->tags);
    }

    #[Test]
    public function it_uses_default_values_for_optional_fields(): void
    {
        $data = [
            'subscription_id' => 'onesignal-123-456',
            'device_type' => 'android',
        ];

        $dto = DeviceRegistrationDTO::from($data);

        $this->assertEquals('onesignal-123-456', $dto->subscriptionId);
        $this->assertEquals(DeviceTypes::ANDROID, $dto->deviceType);
        $this->assertNull($dto->deviceModel);
        $this->assertNull($dto->deviceOs);
        $this->assertNull($dto->appVersion);
        $this->assertTrue($dto->pushEnabled);
        $this->assertTrue($dto->soundEnabled);
        $this->assertTrue($dto->vibrationEnabled);
        $this->assertEquals([], $dto->tags);
    }

    #[Test]
    public function it_can_convert_to_array(): void
    {
        $data = [
            'subscription_id' => 'onesignal-123-456',
            'device_type' => 'web',
            'device_model' => 'Chrome',
            'device_os' => 'Windows 11',
            'app_version' => '2.0.0',
            'push_enabled' => false,
            'sound_enabled' => true,
            'vibration_enabled' => true,
            'tags' => ['beta' => true],
        ];

        $dto = DeviceRegistrationDTO::from($data);
        $array = $dto->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('onesignal-123-456', $array['subscription_id']);
        $this->assertEquals('web', $array['device_type']);
        $this->assertEquals('Chrome', $array['device_model']);
        $this->assertEquals('Windows 11', $array['device_os']);
        $this->assertEquals('2.0.0', $array['app_version']);
        $this->assertFalse($array['push_enabled']);
        $this->assertTrue($array['sound_enabled']);
        $this->assertTrue($array['vibration_enabled']);
        $this->assertEquals(['beta' => true], $array['tags']);
    }

    #[Test]
    public function it_can_create_for_guest_device(): void
    {
        $data = [
            'subscription_id' => 'guest-device-123',
            'device_type' => 'ios',
            'user_id' => null,
        ];

        $dto = DeviceRegistrationDTO::from($data);

        $this->assertNull($dto->userId);
        $this->assertEquals('guest-device-123', $dto->subscriptionId);
        $this->assertEquals(DeviceTypes::IOS, $dto->deviceType);
    }

    #[Test]
    public function it_can_create_with_user_id(): void
    {
        $userId = 'uuid-123-456';
        $data = [
            'subscription_id' => 'user-device-123',
            'device_type' => 'android',
            'user_id' => $userId,
        ];

        $dto = DeviceRegistrationDTO::from($data);

        $this->assertEquals($userId, $dto->userId);
        $this->assertEquals('user-device-123', $dto->subscriptionId);
        $this->assertEquals(DeviceTypes::ANDROID, $dto->deviceType);
    }
}
