<?php

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * @extends Enum<string|int>
 */
class BaseEnum extends Enum implements LocalizedEnum
{
    /**
     * @return array<int, array{label: string, value: string|int}>
     */
    public static function asSelectObject(): array
    {
        /** @var array<int, array{label: string, value: string|int}> */
        $result = collect(static::asSelectArray())
            ->map(fn ($value, $key): array => [
                'label' => static::getDescription($key),
                'value' => $key,
            ])
            ->toArray();

        return $result;
    }
}
