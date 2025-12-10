<?php

declare(strict_types=1);

namespace App\Actions\ContractType;

use App\Models\ContractType;

class UpdateContractTypeAction
{
    /** @param array<string, mixed> $data */
    public function execute(ContractType $contractType, array $data): ContractType
    {
        $contractType->fill($data);
        $contractType->save();

        return $contractType->refresh();
    }
}
