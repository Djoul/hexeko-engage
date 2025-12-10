<?php

namespace App\DTOs\Push;

use App\Enums\DeviceTypes;

class DeviceRegistrationDTO
{
    public function __construct(
        public string $subscriptionId,
        public DeviceTypes $deviceType,
        public ?string $deviceModel = null,
        public ?string $deviceOs = null,
        public ?string $appVersion = null,
        public ?string $timezone = null,
        public ?string $language = null,
        public array $notificationPreferences = [],
        public ?string $userId = null,
        public bool $pushEnabled = true,
        public bool $soundEnabled = true,
        public bool $vibrationEnabled = true,
        public array $tags = [],
        public array $metadata = [],
    ) {}

    public static function from(array $data): self
    {
        return new self(
            subscriptionId: $data['subscription_id'],
            deviceType: DeviceTypes::fromValue($data['device_type']),
            deviceModel: $data['device_model'] ?? null,
            deviceOs: $data['device_os'] ?? null,
            appVersion: $data['app_version'] ?? null,
            timezone: $data['timezone'] ?? null,
            language: $data['language'] ?? null,
            notificationPreferences: $data['notification_preferences'] ?? [],
            userId: $data['user_id'] ?? null,
            pushEnabled: $data['push_enabled'] ?? true,
            soundEnabled: $data['sound_enabled'] ?? true,
            vibrationEnabled: $data['vibration_enabled'] ?? true,
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'device_type' => $this->deviceType->value,
            'device_model' => $this->deviceModel,
            'device_os' => $this->deviceOs,
            'app_version' => $this->appVersion,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'notification_preferences' => $this->notificationPreferences,
            'user_id' => $this->userId,
            'push_enabled' => $this->pushEnabled,
            'sound_enabled' => $this->soundEnabled,
            'vibration_enabled' => $this->vibrationEnabled,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
        ];
    }
}
