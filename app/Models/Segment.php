<?php

namespace App\Models;

use App\Models\Traits\HasCreator;
use App\Models\Traits\HasFinancer;
use App\Services\SegmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Segment extends LoggableModel
{
    use HasCreator;
    use HasFactory;
    use HasFinancer;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'segments';

    /** @var array<string> */
    public static array $sortable = ['created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'financer_id' => 'string',
            'filters' => 'array',
        ];
    }

    protected static function logName(): string
    {
        return 'segment';
    }

    /**
     * Computed attribute for the number of users in the segment.
     */
    protected function computedUsersCount(): Attribute
    {
        return Attribute::make(
            get: fn (): int => once(function (): int {
                return round($this->computedUsers()->count());
            })
        );
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'segment_user', 'segment_id', 'user_id');
    }

    /**
     * Computed attribute for the users in the segment.
     */
    public function computedUsers(): Builder
    {
        return $this->getUsersQuery();
    }

    /**
     * Get the base query for users filtered by this segment
     */
    protected function getUsersQuery(): Builder
    {
        $query = User::query();

        // Filter by financer - only get users associated with this segment's financer
        if ($this->financer_id) {
            $query->whereHas('financers', function (Builder $q): void {
                $q->where('financers.id', $this->financer_id)
                    ->where('financer_user.active', true);
            });
        }

        // Apply segment filters using the service
        return $this->applyFilters($query);
    }

    /**
     * Apply this segment's filters to a query
     */
    public function applyFilters(Builder $query): Builder
    {
        if (empty($this->filters)) {
            return $query;
        }

        $service = app(SegmentService::class);

        return $service->applyFiltersToQuery($query, $this->filters);
    }

    /**
     * Scope to filter segments by financer
     */
    public function scopeForFinancer(Builder $query, string $financerId): Builder
    {
        return $query->where('financer_id', $financerId);
    }
}
