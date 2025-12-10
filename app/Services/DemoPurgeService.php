<?php

namespace App\Services;

use App\Integrations\HRTools\Models\Link;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\DemoEntity;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoPurgeService
{
    /** @var array<string, int> */
    protected array $statistics = [
        'articles_deleted' => 0,
        'links_deleted' => 0,
        'users_deleted' => 0,
        'financers_deleted' => 0,
        'divisions_deleted' => 0,
        'demo_entities_deleted' => 0,
    ];

    /**
     * Purge all demo data from the database
     *
     * @return array<string, int>
     */
    public function purge(bool $dryRun = false): array
    {
        if (app()->environment('production') && ! config('demo.allowed')) {
            throw new Exception('Demo purge is not allowed in production!');
        }

        Log::info('Starting demo data purge', ['dry_run' => $dryRun]);

        return DB::transaction(function () use ($dryRun): array {
            $this->purgeDemoData($dryRun);

            if ($dryRun) {
                DB::rollBack();
                Log::info('Dry run completed, no data was actually deleted');
            }

            return $this->statistics;
        });
    }

    /**
     * Purge demo data in the correct order
     */
    protected function purgeDemoData(bool $dryRun): void
    {
        // Get all demo entities
        $demoEntities = DemoEntity::all();

        // Group by entity type for ordered deletion
        $groupedEntities = $demoEntities->groupBy('entity_type');

        // Delete in reverse order of dependencies
        $purgeOrderConfig = config('demo.purge_order', [
            'App\\Models\\User',
            'App\\Models\\Financer',
        ]);
        $purgeOrder = is_array($purgeOrderConfig) ? $purgeOrderConfig : [];

        foreach ($purgeOrder as $entityType) {
            if (! is_string($entityType)) {
                continue;
            }
            if (! array_key_exists($entityType, $groupedEntities->toArray())) {
                continue;
            }
            $this->purgeEntityType($entityType, $groupedEntities[$entityType], $dryRun);
        }

        // Delete demo entity records
        if (! $dryRun) {
            $count = DemoEntity::count();
            DemoEntity::truncate();
            $this->statistics['demo_entities_deleted'] = $count;
        } else {
            $this->statistics['demo_entities_deleted'] = DemoEntity::count();
        }
    }

    /**
     * Purge a specific entity type
     *
     * @param  Collection<int, DemoEntity>  $entities
     */
    protected function purgeEntityType(string $entityType, Collection $entities, bool $dryRun): void
    {
        foreach ($entities as $demoEntity) {
            $model = $entityType::find($demoEntity->entity_id);

            if (! $model) {
                continue;
            }

            // Track statistics
            $this->updateStatistics($entityType);

            if (! $dryRun) {
                // For users, detach from financers first
                if ($model instanceof User) {
                    $model->financers()->detach();
                }

                // For financers, delete associated tags first to respect FK constraints
                if ($model instanceof Financer) {
                    Tag::where('financer_id', $model->id)->forceDelete();
                }

                // Delete the model
                $model->forceDelete();

                Log::info("Deleted {$entityType}", ['id' => $demoEntity->entity_id]);
            } else {
                Log::info("Would delete {$entityType}", ['id' => $demoEntity->entity_id]);
            }
        }
    }

    /**
     * Update statistics for reporting
     */
    protected function updateStatistics(string $entityType): void
    {
        if ($entityType === User::class) {
            $this->statistics['users_deleted']++;
        } elseif ($entityType === Financer::class) {
            $this->statistics['financers_deleted']++;
        } elseif ($entityType === Division::class) {
            $this->statistics['divisions_deleted']++;
        } elseif ($entityType === Link::class) {
            $this->statistics['links_deleted']++;
        } elseif ($entityType === Article::class) {
            $this->statistics['articles_deleted']++;
        }
    }

    /**
     * Get soft delete candidates (for soft deletion mode)
     *
     * @return array<int, array{type: string, id: int, name: string}>
     */
    public function getSoftDeleteCandidates(): array
    {
        $demoEntities = DemoEntity::all();
        $candidates = [];

        foreach ($demoEntities as $demoEntity) {
            $modelClass = $demoEntity->entity_type;

            // Handle both regular models and integration models
            if ($modelClass === Link::class || str_contains($modelClass, 'Link')) {
                $model = Link::find($demoEntity->entity_id);
            } else {
                $model = $modelClass::find($demoEntity->entity_id);
            }

            if ($model) {
                $candidates[] = [
                    'type' => class_basename($modelClass),
                    'id' => $demoEntity->entity_id,
                    'name' => $this->getModelDisplayName($model),
                ];
            }
        }

        return $candidates;
    }

    /**
     * Perform soft deletion of demo data
     *
     * @return array<string, int>
     */
    public function softDelete(): array
    {
        if (app()->environment('production') && ! config('demo.allowed')) {
            throw new Exception('Demo soft delete is not allowed in production!');
        }

        return DB::transaction(function (): array {
            $demoEntities = DemoEntity::all();
            $statistics = [
                'articles_soft_deleted' => 0,
                'links_soft_deleted' => 0,
                'users_soft_deleted' => 0,
                'financers_soft_deleted' => 0,
                'divisions_soft_deleted' => 0,
            ];

            foreach ($demoEntities as $demoEntity) {
                $modelClass = $demoEntity->entity_type;
                $model = $modelClass::withTrashed()->find($demoEntity->entity_id);

                if ($model && ! $model->trashed()) {
                    $model->delete();

                    if ($model instanceof User) {
                        $statistics['users_soft_deleted']++;
                    } elseif ($model instanceof Financer) {
                        $statistics['financers_soft_deleted']++;
                    } elseif ($model instanceof Division) {
                        $statistics['divisions_soft_deleted']++;
                    } elseif ($model instanceof Link) {
                        $statistics['links_soft_deleted']++;
                    } elseif ($model instanceof Article) {
                        $statistics['articles_soft_deleted']++;
                    }
                }
            }

            return $statistics;
        });
    }

    /**
     * Get display name for a model
     */
    protected function getModelDisplayName(Model $model): string
    {
        if ($model instanceof User) {
            return $model->email;
        }
        if ($model instanceof Financer) {
            return $model->name;
        }
        if ($model instanceof Division) {
            return $model->name;
        }
        if ($model instanceof Link) {
            return $model->name ?? $model->title ?? 'Link #'.$model->id;
        }
        if ($model instanceof Article) {
            return $model->title ?? 'Article #'.$model->id;
        }

        return 'Unknown';
    }
}
