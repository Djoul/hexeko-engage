<?php

namespace App\Models\Traits\Financer;

use App\Models\CreditBalance;
use App\Models\Division;
use App\Models\FinancerIntegration;
use App\Models\FinancerModule;
use App\Models\FinancerUser;
use App\Models\Integration;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\Module;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait FinancerRelations
{
    /**
     * @return BelongsTo<Division,$this> $division
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id', 'id');
    }

    /**
     * @return BelongsToMany<User, $this, FinancerUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'financer_user',
            'financer_id',
            'user_id',
            'id',
            'id'
        )->using(FinancerUser::class)
            ->withPivot(['active', 'sirh_id', 'started_at'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * @return MorphMany<CreditBalance, $this>
     */
    public function credits(): MorphMany
    {
        return $this->morphMany(CreditBalance::class, 'owner');
    }

    /**
     * @return BelongsToMany<Module, $this, FinancerModule>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(
            Module::class,
            'financer_module',
            'financer_id',
            'module_id',
            'id',
            'id'
        )->using(FinancerModule::class)
            ->withPivot(['active', 'promoted', 'price_per_beneficiary'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * @return BelongsToMany<Integration, $this, FinancerIntegration>
     */
    public function integrations(): BelongsToMany
    {
        return $this->belongsToMany(
            Integration::class,
            'financer_integration',
            'financer_id',
            'integration_id',
            'id',
            'id'
        )->using(FinancerIntegration::class)
            ->withPivot(['active'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * @return HasMany<WorkMode, $this>
     */
    public function workModes(): HasMany
    {
        return $this->hasMany(WorkMode::class, 'financer_id');
    }

    /**
     * @return HasMany<JobTitle, $this>
     */
    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class, 'financer_id');
    }

    /**
     * @return HasMany<JobLevel, $this>
     */
    public function jobLevels(): HasMany
    {
        return $this->hasMany(JobLevel::class, 'financer_id');
    }
}
