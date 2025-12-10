<?php

declare(strict_types=1);

namespace App\Models\Traits\Division;

use App\Models\Division;
use App\Models\DivisionIntegration;
use App\Models\DivisionModule;
use App\Models\Financer;
use App\Models\Integration;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait DivisionRelations
{
    /**
     * @return HasMany<Financer, $this>
     */
    public function financers(): HasMany
    {
        return $this->hasMany(
            Financer::class,
            'division_id',
            'id'
        );
    }

    /**
     * @return BelongsToMany<User,DivisionModule>
     */
    public function modules(): BelongsToMany
    {
        // @phpstan-ignore-next-line
        return $this->belongsToMany(
            Module::class,
            'division_module',
            'division_id',
            'module_id',
            'id',
            'id'
        )->using(DivisionModule::class)
            ->withPivot(['active', 'price_per_beneficiary'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * @return BelongsToMany<Division,DivisionIntegration>
     */
    public function integrations(): BelongsToMany
    {
        // @phpstan-ignore-next-line
        return $this->belongsToMany(
            Integration::class,
            'division_integration',
            'division_id',
            'integration_id',
            'id',
            'id'
        )->using(DivisionIntegration::class)
            ->withPivot(['active'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }
}
