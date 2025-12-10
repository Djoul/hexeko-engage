<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Contracts\Searchable;
use App\Enums\Languages;
use App\Models\Concerns\MarksAsDemo;
use App\Models\Traits\HasDivisionScopes;
use App\Models\Traits\User\UserAccessorsAndHelpers;
use App\Models\Traits\User\UserFiltersAndScopes;
use App\Models\Traits\User\UserRelations;
use App\Traits\AuditableModel;
use Context;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $cognito_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property bool $force_change_email
 * @property string|null $birthdate
 * @property bool $terms_confirmed
 * @property bool $enabled
 * @property string $locale
 * @property string $currency
 * @property string|null $timezone
 * @property string|null $stripe_id
 * @property string|null $sirh_id
 * @property string|null $last_login
 * @property bool $opt_in
 * @property string|null $phone
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $invitation_status
 * @property string|null $invitation_token
 * @property Carbon|null $invitation_expires_at
 * @property string|null $invited_by
 * @property Carbon|null $invited_at
 * @property Carbon|null $invitation_accepted_at
 * @property array|null $invitation_metadata
 * @property string|null $original_invited_user_id
 * @property-read \App\Models\User|null $use_factory
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User pipeFiltered()
 * @method Builder<static>|User pipeFiltered()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereBirthdate($value)
 * @method static Builder<static>|User whereCognitoId($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereCurrency($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereEnabled($value)
 * @method static Builder<static>|User whereExternalId($value)
 * @method static Builder<static>|User whereFirstName($value)
 * @method static Builder<static>|User whereForceChangeEmail($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereLastLogin($value)
 * @method static Builder<static>|User whereLastName($value)
 * @method static Builder<static>|User whereLocale($value)
 * @method static Builder<static>|User whereOptIn($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePhone($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereStripeId($value)
 * @method static Builder<static>|User whereTermsConfirmed($value)
 * @method static Builder<static>|User whereTimezone($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 *
 * @property string|null $team_id
 * @property-read FinancerUser|null $pivot
 * @property-read Collection<int, Financer> $financers
 * @property-read int|null $financers_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Team|null $team
 *
 * @method static Builder<static>|User permission($permissions, $without = false)
 * @method static Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static Builder<static>|User whereTeamId($value)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, $guard = null)
 *
 * @property string|null $temp_password
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read string $full_name
 *
 * @method static Builder<static>|User whereTempPassword($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable implements Auditable, HasMedia, Searchable
{
    use AuditableModel;
    use HasDivisionScopes;

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    use HasRoles;
    use HasUuids;
    use InteractsWithMedia;
    use LogsActivity;
    use MarksAsDemo;
    use SoftDeletes;
    use UserAccessorsAndHelpers;
    use UserFiltersAndScopes;
    use UserRelations;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
        'email_verified_at' => 'datetime',
        'birthdate' => 'date',
        'description' => 'string',
        'terms_confirmed' => 'boolean',
        'enabled' => 'boolean',
        'opt_in' => 'boolean',
        'force_change_email' => 'boolean',
        'locale' => 'string',
        'currency' => 'string',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'gender' => 'string',
        'invitation_expires_at' => 'datetime',
        'invited_at' => 'datetime',
        'invitation_accepted_at' => 'datetime',
        'invitation_metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Register media collections for the model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile_image')
            ->singleFile();
    }

    /**
     * Get the password for the user.
     * Override to handle Cognito authentication without password field.
     */
    public function getAuthPassword(): string
    {
        return ''; // Return empty string as we use Cognito for authentication
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    public function getSearchableFields(): array
    {
        return ['first_name', 'last_name', 'email', 'full_name'];
    }

    public function getSearchableRelations(): array
    {
        return ['team' => ['name']];
    }

    /**
     * Get the SQL expression for sorting a virtual field.
     * Returns null if the field should use standard sorting.
     */
    public static function getSortableExpression(string $field): ?string
    {
        return match ($field) {
            'created_at' => self::getCreatedAtSortExpression(),
            'full_name' => "CONCAT(first_name, ' ', last_name)",
            default => null,
        };
    }

    /**
     * Get the sort expression for created_at field.
     * Uses entry_date logic: COALESCE(started_at, from, created_at).
     * Priority: started_at (SIRH/import) > from (invitation date) > created_at (fallback)
     * Filters on active financer from context if available.
     */
    private static function getCreatedAtSortExpression(): string
    {
        $financerId = Context::get('financer_id');

        // If we have a financer context, filter on it
        if ($financerId && is_string($financerId) && self::isValidUuid($financerId)) {
            return '(SELECT COALESCE(fu.started_at, fu."from", users.created_at) FROM financer_user fu WHERE fu.user_id = users.id AND fu.financer_id = \''.$financerId.'\' LIMIT 1)';
        }

        // Otherwise, take the most recent financer
        return '(SELECT COALESCE(fu.started_at, fu."from", users.created_at) FROM financer_user fu WHERE fu.user_id = users.id ORDER BY fu."from" DESC LIMIT 1)';
    }

    /**
     * Validate if a string is a valid UUID.
     */
    private static function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Get the SQL expression for searching a virtual field.
     * Returns null if the field should use standard searching.
     */
    public static function getSearchableExpression(string $field): ?string
    {
        return match ($field) {
            'full_name' => "CONCAT(first_name, ' ', last_name)",
            default => null,
        };
    }

    /**
     * Get field mapping for sortable aliases.
     * Maps user-friendly field names to actual sortable field names.
     *
     * @return array<string, string>
     */
    public static function getSortableFieldMap(): array
    {
        return [
            'name' => 'full_name',
        ];
    }

    /**
     * Get/Set the user's locale.
     *
     * @deprecated Use financer_user.language instead
     * This accessor provides backward compatibility during migration
     */
    protected function locale(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): string {
                // Get language from context financer if active
                $financerId = Context::get('financer_id');

                $pivotLanguage = null;
                if ($financerId) {
                    // Load financers relation if not already loaded (avoids N+1)
                    loadRelationIfNotLoaded($this, 'financers');

                    // Now we can safely use the loaded relation
                    $financer = $this->financers->first(function ($financer) use ($financerId): bool {
                        return $financer->id === $financerId && $financer->pivot !== null && $financer->pivot->active;
                    });

                    if ($financer && $financer->pivot !== null) {
                        $pivotLanguage = $financer->pivot->language;
                    }
                }

                // Log divergence for monitoring
                if ($pivotLanguage && $pivotLanguage !== $value) {
                    Log::info('Locale accessor divergence', [
                        'user_id' => $this->id,
                        'db_locale' => $value,
                        'pivot_language' => $pivotLanguage,
                    ]);
                }

                // Return pivot language or fallback
                return $pivotLanguage ?? $value ?? Languages::ENGLISH;
            },
            set: function (?string $value): ?string {
                // Just update the DB column
                // The actual financer language update should be done via UpdateUserLanguageAction
                $this->attributes['locale'] = $value;

                return $value;
            }
        );
    }

    /**
     * Override division relation name for HasDivisionScopes trait.
     */
    protected function divisionRelationName(): string
    {
        return 'divisions';
    }
}
