<?php

declare(strict_types=1);

namespace App\DTOs\User;

use Carbon\Carbon;

/**
 * Unified DTO for creating invited users.
 * Combines functionality from previous CreateInvitedUserDTO and CreateUserInvitationDTO.
 *
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $financer_id
 * @property string|null $team_id
 * @property string|null $intended_role
 * @property string|null $phone
 * @property string|null $external_id
 * @property string|null $sirh_id
 * @property string|null $invited_by User ID who created the invitation
 * @property string|null $language Language code from Languages enum (e.g., 'fr-FR', 'en-GB')
 * @property array<string, mixed>|null $metadata Additional metadata
 * @property array<string, mixed>|null $extra_data Extra data fields
 * @property Carbon|null $expires_at Custom expiration date
 * @property int $expiration_days Days until invitation expires (default: 7)
 */
final readonly class CreateInvitedUserDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $extra_data
     */
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public ?string $financer_id = null,
        public ?string $team_id = null,
        public ?string $intended_role = null,
        public ?string $phone = null,
        public ?string $external_id = null,
        public ?string $sirh_id = null,
        public ?string $invited_by = null,
        public ?string $language = null,
        public ?array $metadata = null,
        public ?array $extra_data = null,
        public ?Carbon $expires_at = null,
        public int $expiration_days = 7,
    ) {}

    /**
     * Create DTO from array with snake_case keys (simple version for backward compatibility).
     * This is the primary method used by controllers.
     *
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            first_name: is_scalar($data['first_name']) ? (string) $data['first_name'] : '',
            last_name: is_scalar($data['last_name']) ? (string) $data['last_name'] : '',
            email: is_scalar($data['email']) ? (string) $data['email'] : '',
            financer_id: self::extractNullableString($data, 'financer_id'),
            team_id: self::extractNullableString($data, 'team_id'),
            intended_role: self::extractNullableString($data, 'intended_role'),
            phone: self::extractNullableString($data, 'phone'),
            external_id: self::extractNullableString($data, 'external_id'),
            sirh_id: self::extractNullableString($data, 'sirh_id'),
            invited_by: self::extractNullableString($data, 'invited_by'),
            language: self::extractNullableString($data, 'language'),
            metadata: self::extractNullableArray($data, 'metadata'),
            extra_data: self::extractNullableArray($data, 'extra_data'),
            expires_at: self::extractNullableCarbon($data, 'expires_at'),
            expiration_days: array_key_exists('expiration_days', $data) && is_int($data['expiration_days']) ? $data['expiration_days'] : 7,
        );
    }

    /**
     * Create DTO from array with full validation and normalization.
     * Supports both snake_case and camelCase for legacy compatibility.
     *
     * @param  array{
     *     email: string,
     *     first_name?: string,
     *     last_name?: string,
     *     firstName?: string,
     *     lastName?: string,
     *     financer_id?: string|null,
     *     financerId?: string|null,
     *     team_id?: string|null,
     *     teamId?: string|null,
     *     phone?: string|null,
     *     sirh_id?: string|null,
     *     sirhId?: string|null,
     *     external_id?: string|null,
     *     externalId?: string|null,
     *     intended_role?: string|null,
     *     intendedRole?: string|null,
     *     invited_by?: string|null,
     *     invitedBy?: string|null,
     *     metadata?: array<string, mixed>|null,
     *     extra_data?: array<string, mixed>|null,
     *     extraData?: array<string, mixed>|null,
     *     expires_at?: string|null,
     *     expiresAt?: string|null,
     *     expiration_days?: int
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            first_name: self::extractString($data, 'first_name', 'firstName'),
            last_name: self::extractString($data, 'last_name', 'lastName'),
            email: is_scalar($data['email']) ? (string) $data['email'] : '',
            financer_id: self::extractNullableString($data, 'financer_id') ?? self::extractNullableString($data, 'financerId'),
            team_id: self::extractNullableString($data, 'team_id') ?? self::extractNullableString($data, 'teamId'),
            intended_role: self::extractNullableString($data, 'intended_role') ?? self::extractNullableString($data, 'intendedRole'),
            phone: self::extractNullableString($data, 'phone'),
            external_id: self::extractNullableString($data, 'external_id') ?? self::extractNullableString($data, 'externalId'),
            sirh_id: self::extractNullableString($data, 'sirh_id') ?? self::extractNullableString($data, 'sirhId'),
            invited_by: self::extractNullableString($data, 'invited_by') ?? self::extractNullableString($data, 'invitedBy'),
            language: self::extractNullableString($data, 'language'),
            metadata: self::extractNullableArray($data, 'metadata'),
            extra_data: self::extractNullableArray($data, 'extra_data') ?? self::extractNullableArray($data, 'extraData'),
            expires_at: self::extractNullableCarbon($data, 'expires_at') ?? self::extractNullableCarbon($data, 'expiresAt'),
            expiration_days: array_key_exists('expiration_days', $data) && is_int($data['expiration_days']) ? $data['expiration_days'] : 7,
        );
    }

    /**
     * Convert DTO to array for database insertion (User model creation).
     * Includes invitation-specific fields with generated token.
     *
     * @return array{
     *     email: string,
     *     first_name: string,
     *     last_name: string,
     *     invited_by: string|null,
     *     team_id: string|null,
     *     phone: string|null,
     *     invitation_status: string,
     *     invitation_token: string,
     *     invitation_expires_at: Carbon,
     *     invited_at: Carbon,
     *     invitation_metadata: array<string, mixed>,
     *     enabled: bool,
     *     cognito_id: null
     * }
     */
    public function toArray(): array
    {
        // Merge metadata, extra_data, and invitation-specific fields into invitation_metadata
        $invitationMetadata = array_merge(
            $this->metadata ?? [],
            $this->extra_data ?? [],
            array_filter([
                'financer_id' => $this->financer_id,
                'sirh_id' => $this->sirh_id,
                'external_id' => $this->external_id,
                'intended_role' => $this->intended_role,
                'language' => $this->language,
            ], fn (?string $value): bool => $value !== null)
        );

        return [
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'invited_by' => $this->invited_by,
            'team_id' => $this->team_id,
            'phone' => $this->phone,
            'locale' => $this->language, // Set user.locale from language column
            'invitation_status' => 'pending',
            'invitation_token' => $this->generateToken(),
            'invitation_expires_at' => $this->expires_at ?? now()->addDays($this->expiration_days),
            'invited_at' => now(),
            'invitation_metadata' => $invitationMetadata,
            'enabled' => false,
            'cognito_id' => null,
        ];
    }

    /**
     * Generate a secure invitation token.
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 characters hex string
    }

    /**
     * Extract nullable string from array data.
     *
     * @param  array<string, mixed>  $data
     */
    private static function extractNullableString(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '' || $data[$key] === '0') {
            return null;
        }

        return is_scalar($data[$key]) ? (string) $data[$key] : null;
    }

    /**
     * Extract required string from array data with fallback key.
     *
     * @param  array<string, mixed>  $data
     */
    private static function extractString(array $data, string $key, ?string $fallbackKey = null): string
    {
        if (array_key_exists($key, $data) && is_scalar($data[$key])) {
            return (string) $data[$key];
        }

        if ($fallbackKey !== null && array_key_exists($fallbackKey, $data) && is_scalar($data[$fallbackKey])) {
            return (string) $data[$fallbackKey];
        }

        return '';
    }

    /**
     * Extract nullable array from array data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function extractNullableArray(array $data, string $key): ?array
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key])) {
            return null;
        }

        return $data[$key];
    }

    /**
     * Extract nullable Carbon from array data.
     *
     * @param  array<string, mixed>  $data
     */
    private static function extractNullableCarbon(array $data, string $key): ?Carbon
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = $data[$key];
        if (is_string($value) || $value instanceof Carbon) {
            return Carbon::parse($value);
        }

        return null;
    }
}
