<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Financer;
use Staudenmeir\EloquentHasManyDeep\HasOneDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

trait HasDivisionThroughFinancer
{
    use HasRelationships;

    public function division(): HasOneDeep
    {
        return $this->hasOneDeepFromRelations(
            $this->financer(),
            (new Financer)->division()
        );
    }
}
