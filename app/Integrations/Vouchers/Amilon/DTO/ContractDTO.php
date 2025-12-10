<?php

namespace App\Integrations\Vouchers\Amilon\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;

/**
 * @implements Arrayable<string, mixed>
 */
class ContractDTO implements Arrayable, Jsonable
{
    /**
     * Create a new ContractDTO instance.
     */
    public function __construct(
        public readonly string $contractName,
        public readonly string $contractId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly float $currentAmount,
        public readonly float $previousAmount,
        public readonly string $lastUpdate,
        public readonly string $currencyIsoCode,
    ) {}

    /**
     * Create a new ContractDTO from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            contractName: array_key_exists('contractName', $data) ? self::ensureString($data['contractName']) : '',
            contractId: array_key_exists('ContractId', $data) ? self::ensureString($data['ContractId']) : '',
            startDate: array_key_exists('StartDate', $data) ? self::ensureString($data['StartDate']) : '',
            endDate: array_key_exists('EndDate', $data) ? self::ensureString($data['EndDate']) : '',
            currentAmount: array_key_exists('CurrentAmount', $data) ? self::ensureFloat($data['CurrentAmount']) : 0.0,
            previousAmount: array_key_exists('PreviousAmount', $data) ? self::ensureFloat($data['PreviousAmount']) : 0.0,
            lastUpdate: array_key_exists('LastUpdate', $data) ? self::ensureString($data['LastUpdate']) : '',
            currencyIsoCode: array_key_exists('CurrencyIsoCode', $data) ? self::ensureString($data['CurrencyIsoCode']) : '',
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
     * Ensure a value is a float.
     */
    private static function ensureFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'contractName' => $this->contractName,
            'contractId' => $this->contractId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'currentAmount' => $this->currentAmount,
            'previousAmount' => $this->previousAmount,
            'lastUpdate' => $this->lastUpdate,
            'currencyIsoCode' => $this->currencyIsoCode,
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
