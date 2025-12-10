<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Resources;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Integrations\InternalCommunication\Services\TagService;
use App\Integrations\InternalCommunication\Services\TokenQuotaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ArticleResourceCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ArticleResource::class;

    protected TagService $tagService;

    protected TokenQuotaService $tokenQuotaService;

    /**
     * Create a new resource instance.
     *
     * @return void
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->tagService = app(TagService::class);
        $this->tokenQuotaService = app(TokenQuotaService::class);
    }

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $visibleTags = $this->getVisibleTags();

        $aiTokenQuota = $this->getAiTokenQuota();

        return [
            'data' => $this->collection->values(),
            'meta' => [
                'total' => $this->collection->count(),
                'current_page' => request()->page ?? 1,
                'per_page' => request()->per_page ?? 20,
                'all_tags' => TagResource::collection($visibleTags),
                'ai_token_quota' => $aiTokenQuota,
            ],
        ];
    }

    /**
     * Get tags visible to the authenticated user based on their role.
     * Optimized with caching to prevent repeated queries.
     */
    protected function getVisibleTags(): Collection
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        $financerId = activeFinancerID($user);

        if (! empty($financerId)) {
            $tags = $this->tagService->findByFinancer($financerId);

            return collect($tags);
        }

        // God and Super Admin see all tags
        if ($user->hasRole(RoleDefaults::GOD) || $user->hasRole(RoleDefaults::HEXEKO_SUPER_ADMIN)) {
            return Tag::select('id', 'label', 'financer_id', 'created_at', 'updated_at')->get();
        }

        // Division Admin sees tags of their financers
        if ($user->hasRole(RoleDefaults::DIVISION_ADMIN)) {
            $financerIds = $user->financers()->pluck('id')->toArray();

            return Tag::whereIn('financer_id', $financerIds)
                ->select('id', 'label', 'financer_id', 'created_at', 'updated_at')
                ->get();
        }

        // Financer Admin sees tags of the active financer
        if ($user->hasRole(RoleDefaults::FINANCER_ADMIN)) {
            $financerId = activeFinancerID();
            if (! empty($financerId)) {
                return $this->tagService->findByFinancer($financerId);
            }

            return collect();
        }

        // Default: return empty collection
        return collect();
    }

    /**
     * Get AI token quota information for the active financer.
     *
     * @return array{total: int, consumed: int, remaining: int, percentage_used: float}
     */
    protected function getAiTokenQuota(): array
    {
        $requestedFinancerId = request()->input('financer_id');
        $financerId = is_string($requestedFinancerId) && $requestedFinancerId !== ''
            ? $requestedFinancerId
            : activeFinancerID();

        if (in_array($financerId, [null, '', '0'], true)) {
            return [
                'division_id' => null,
                'division_name' => null,
                'total' => 0,
                'consumed' => 0,
                'remaining' => 0,
                'percentage_used' => 0.0,
            ];
        }

        return $this->tokenQuotaService->getQuotaForFinancer($financerId);
    }
}
