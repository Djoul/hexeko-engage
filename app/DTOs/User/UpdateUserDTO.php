<?php

declare(strict_types=1);

namespace App\DTOs\User;

/**
 * DTO for updating user data
 *
 * Type-safe data transfer for user update operations.
 */
final readonly class UpdateUserDTO
{
    /**
     * @param  array<string, mixed>|null  $financers
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $locale = null,
        public ?string $timezone = null,
        public ?string $currency = null,
        public ?string $profileImage = null,
        public ?array $financers = null,
        public ?bool $enabled = null,
    ) {}

    /**
     * Create DTO from array (e.g., validated request data).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            locale: $data['locale'] ?? null,
            timezone: $data['timezone'] ?? null,
            currency: $data['currency'] ?? null,
            profileImage: $data['profile_image'] ?? null,
            financers: $data['financers'] ?? null,
            enabled: $data['enabled'] ?? null,
        );
    }

    /**
     * Convert DTO to array for database update.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->firstName !== null) {
            $data['first_name'] = $this->firstName;
        }
        if ($this->lastName !== null) {
            $data['last_name'] = $this->lastName;
        }
        if ($this->email !== null) {
            $data['email'] = $this->email;
        }
        if ($this->phone !== null) {
            $data['phone'] = $this->phone;
        }
        if ($this->locale !== null) {
            $data['locale'] = $this->locale;
        }
        if ($this->timezone !== null) {
            $data['timezone'] = $this->timezone;
        }
        if ($this->currency !== null) {
            $data['currency'] = $this->currency;
        }
        if ($this->profileImage !== null) {
            $data['profile_image'] = $this->profileImage;
        }
        if ($this->financers !== null) {
            $data['financers'] = $this->financers;
        }
        if ($this->enabled !== null) {
            $data['enabled'] = $this->enabled;
        }

        return $data;
    }
}
