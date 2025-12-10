<?php

namespace App\Integrations\HRTools\Traits;

use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait LinkRelations
{
    /**
     * Get the financer that owns the link.
     */
    public function financer(): BelongsTo
    {
        return $this->belongsTo(Financer::class, 'financer_id', 'id');
    }

    /**
     * Users who have pinned this link.
     */
    public function pinnedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'int_outils_rh_link_user')
            ->withPivot('pinned')
            ->withTimestamps();
    }
}
