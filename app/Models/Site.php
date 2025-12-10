<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasCreator;
use App\Models\Traits\HasFinancer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends LoggableModel
{
    use HasCreator;
    use HasFactory;
    use HasFinancer;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'sites';

    /** @var array<string> */
    public static array $sortable = [
        'name',
        'created_at',
        'updated_at',
    ];

    public static string $defaultSortField = 'created_at';

    public static string $defaultSortDirection = 'desc';

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'name' => 'array',
        ];
    }

    protected static function logName(): string
    {
        return 'site';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'site_user', 'site_id', 'user_id');
    }
}
