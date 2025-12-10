<?php

declare(strict_types=1);

namespace App\Actions\ContractType;

use App\Models\ContractType;

class CreateContractTypeAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): ContractType
    {
        $contractType = new ContractType;
        $contractType->fill($data);
        $contractType->save();

        return $contractType->refresh();
    }
}
