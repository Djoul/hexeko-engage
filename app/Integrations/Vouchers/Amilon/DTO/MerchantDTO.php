<?php

namespace App\Integrations\Vouchers\Amilon\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * @implements Arrayable<string, mixed>
 */
class MerchantDTO implements Arrayable, Jsonable
{
    /**
     * Create a new MerchantDTO instance.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $country,
        public readonly string $merchant_id,
        public readonly ?string $description = null,
        public readonly ?string $image_url = null,
    ) {}

    /**
     * Create a new MerchantDTO from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $countryValue = array_key_exists('CountryISOAlpha3', $data) ? self::ensureStringOrNull($data['CountryISOAlpha3']) : null;

        return new self(
            name: array_key_exists('Name', $data) ? self::ensureString($data['Name']) : '',
            country: self::normalizeCountryValue($countryValue),
            merchant_id: array_key_exists('RetailerId', $data) ? self::ensureString($data['RetailerId']) : '',
            description: array_key_exists('LongDescription', $data) ? self::ensureStringOrNull($data['LongDescription']) : null,
            image_url: array_key_exists('ImageUrl', $data) ? self::ensureStringOrNull($data['ImageUrl']) : null,
        );
    }

    /**
     * Ensure a value is a string.
     */
    private static function ensureString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
        }

        if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Ensure a value is a string or null.
     */
    private static function ensureStringOrNull(mixed $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        return self::ensureString($value);
    }

    /**
     * Normalize country value to handle special cases.
     * The Amilon API sometimes returns "EUR" (currency code) instead of country codes.
     * This method normalizes such values to valid country codes or special markers.
     */
    private static function normalizeCountryValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Normalize to uppercase for consistent comparison
        $normalized = strtoupper(trim($value));

        // If the value is "EUR" (Eurozone currency), treat it as Eurozone merchants
        // Store as "EUR" to indicate merchant is available in Eurozone
        if ($normalized === 'EUR') {
            return 'EUR';
        }

        // Return the original value for valid country codes
        return $value;
    }

    /**
     * Create a collection of MerchantDTO from an array of merchants.
     *
     * @param  array<array<string, mixed>>  $merchants
     * @return array<MerchantDTO>
     */
    public static function collection(array $merchants): array
    {
        return array_map(fn (array $merchant): MerchantDTO => self::fromArray($merchant), $merchants);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'country' => $this->country,
            'merchant_id' => $this->merchant_id,
            'description' => $this->description,
            'image_url' => $this->image_url,
        ];
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson($options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }
}
