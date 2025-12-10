<?php

namespace App\Models\Traits\User;

use App\Integrations\HRTools\Models\Link;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\Survey\Models\Survey;
use App\Models\ContractType;
use App\Models\CreditBalance;
use App\Models\Department;
use App\Models\Division;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\Integration;
use App\Models\JobLevel;
use App\Models\JobTitle;
use App\Models\Module;
use App\Models\PushSubscription;
use App\Models\Segment;
use App\Models\Site;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkMode;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

trait UserRelations
{
    use HasRelationships;

    // @phpstan-ignore-next-line
    public function financers(): BelongsToMany
    {
        return $this->belongsToMany(
            Financer::class,
            'financer_user',
            'user_id',
            'financer_id',
            'id',
            'id'
        )->using(FinancerUser::class)
            ->withPivot(['active', 'sirh_id', 'from', 'to', 'role', 'language', 'started_at', 'work_mode_id', 'job_title_id', 'job_level_id'])
            ->withTimestamps()
            ->orderByPivot('active', 'desc');
    }

    /**
     * Get the pivot record for the current financer.
     *
     * @return HasOne<FinancerUser, $this>
     */
    public function currentFinancerPivot(): HasOne
    {
        return $this->hasOne(FinancerUser::class, 'user_id', 'id')
            ->where('financer_id', activeFinancerID());
    }

    /**
     * @return HasManyDeep<Division, self>
     */
    public function divisions(): HasManyDeep
    {
        return $this->hasManyDeepFromRelations(
            $this->financers(),
            (new Financer)->division()
        );
    }

    /**
     * Get all integrations associated with the user through the financers.
     *
     * @return HasManyThrough<Integration, Financer, $this>
     */
    public function integrations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Integration::class,
            Financer::class,
            'user_id', // Foreign key on the financers table...
            'financer_id', // Foreign key on the integrations table...
            'id', // Local key on the users table...
            'id' // Local key on the financers table...
        );
    }

    /*
     * @return BelongsTo<Team, User>
     * */
    // @phpstan-ignore-next-line
    public function team(): BelongsTo
    {
        return $this->belongsTo(
            Team::class,
            'team_id',
            'id');
    }

    /**
     * @return MorphMany<CreditBalance, $this>
     */
    public function credits(): MorphMany
    {
        return $this->morphMany(CreditBalance::class, 'owner');
    }

    /**
     * Modules épinglés par l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Module, \App\Models\User>
     */
    /** @phpstan-ignore-next-line */
    public function pinnedModules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'user_pinned_modules')->withTimestamps();
    }

    /**
     * Liens HRTools épinglés par l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Integrations\HRTools\Models\Link, \App\Models\User>
     */
    /** @phpstan-ignore-next-line */
    public function pinnedHRToolsLinks(): BelongsToMany
    {
        return $this->belongsToMany(Link::class, 'int_outils_rh_link_user')
            ->withPivot('pinned')
            ->withTimestamps();
    }

    /**
     * Engagement logs for the user
     *
     * @return HasMany<EngagementLog, $this>
     */
    public function engagementLogs(): HasMany
    {
        return $this->hasMany(EngagementLog::class, 'user_id');
    }

    /**
     * Push notification subscriptions for the user
     *
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'user_id');
    }

    /**
     * Departments for the user
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user', 'user_id', 'department_id')
            ->withTimestamps();
    }

    /**
     * Sites for the user
     *
     * @return BelongsToMany<Site, $this>
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_user', 'user_id', 'site_id')
            ->withTimestamps();
    }

    /**
     * The managers that belong to the user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'manager_user', 'user_id', 'manager_id')
            ->withTimestamps();
    }

    /**
     * The contract types that belong to the user.
     *
     * @return BelongsToMany<ContractType, $this>
     */
    public function contractTypes(): BelongsToMany
    {
        return $this->belongsToMany(ContractType::class, 'contract_type_user', 'user_id', 'contract_type_id')
            ->withTimestamps();
    }

    /**
     * The tags that belong to the user.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_user', 'user_id', 'tag_id')
            ->withTimestamps();
    }

    /**
     * The work mode of the user through the active financer.
     *
     * @return HasOneThrough<WorkMode, FinancerUser, $this>
     */
    public function workMode(): HasOneThrough
    {
        return $this->hasOneThrough(
            WorkMode::class,
            FinancerUser::class,
            'user_id',
            'id',
            'id',
            'work_mode_id'
        )->where('financer_user.financer_id', activeFinancerID());
    }

    /**
     * The job title of the user through the active financer.
     *
     * @return HasOneThrough<JobTitle, FinancerUser, $this>
     */
    public function jobTitle(): HasOneThrough
    {
        return $this->hasOneThrough(
            JobTitle::class,
            FinancerUser::class,
            'user_id',
            'id',
            'id',
            'job_title_id'
        )->where('financer_user.financer_id', activeFinancerID());
    }

    /**
     * The job level of the user through the active financer.
     *
     * @return HasOneThrough<JobLevel, FinancerUser, $this>
     */
    public function jobLevel(): HasOneThrough
    {
        return $this->hasOneThrough(
            JobLevel::class,
            FinancerUser::class,
            'user_id',
            'id',
            'id',
            'job_level_id'
        )->where('financer_user.financer_id', activeFinancerID());
    }

    /**
     * Surveys for the user
     *
     * @return BelongsToMany<Survey, $this>
     */
    public function surveys(): BelongsToMany
    {
        return $this->belongsToMany(Survey::class, 'int_survey_survey_user', 'user_id', 'survey_id')
            ->withTimestamps();
    }

    /**
     * Segments for the user
     *
     * @return BelongsToMany<Segment, $this>
     */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'segment_user', 'user_id', 'segment_id')
            ->withTimestamps();
    }

    /**
     * Get the user who invited this user.
     * Relationship for the 'invited_by' field.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'invited_by', 'id');
    }

    /**
     * Get all users invited by this user.
     * Inverse relationship of inviter().
     *
     * @return HasMany<User, $this>
     */
    public function invitedUsers(): HasMany
    {
        return $this->hasMany(__CLASS__, 'invited_by', 'id');
    }

    /**
     * Get articles authored by this user.
     *
     * @return HasMany<Article, $this>
     */
    public function authoredArticles(): HasMany
    {
        return $this->hasMany(Article::class, 'author_id');
    }

    /**
     * Get articles marked as favorite by this user.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function favoriteArticles(): BelongsToMany
    {
        return $this->belongsToMany(
            Article::class,
            'int_communication_rh_article_interactions',
            'user_id',
            'article_id'
        )
            ->wherePivot('is_favorite', true)
            ->withTimestamps();
    }

    /**
     * Get articles with any interaction from this user.
     *
     * @return BelongsToMany<Article, $this>
     */
    public function interactedArticles(): BelongsToMany
    {
        return $this->belongsToMany(
            Article::class,
            'int_communication_rh_article_interactions',
            'user_id',
            'article_id'
        )
            ->withTimestamps();
    }
}
