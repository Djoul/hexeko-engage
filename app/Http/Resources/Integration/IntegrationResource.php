<?php

namespace App\Http\Resources\Integration;

use App\Enums\OrigineInterfaces;
use App\Models\Integration;
use App\Services\Integration\ResourceCountService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

/** @mixin Integration */
class IntegrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $interface = request()->header('x-origin-interface') === OrigineInterfaces::MOBILE ? 'mobile.' : 'web_beneficiary.';

        return [
            'id' => $this->id,
            'module' => $this->module,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'active' => $this->active,
            'settings' => $this->settings,
            'api_endpoint' => $this->api_endpoint,
            'front_endpoint' => $this->front_endpoint,
            'resources_count_raw' => $this->getResourcesCountRaw(),
            'resources_count_unit' => $interface.($this->resources_count_unit ?? 'resources_count_unit'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the raw count of resources for this integration
     */
    private function getResourcesCountRaw(): int
    {
        if (empty($this->resources_count_query)) {
            return 0;
        }

        $service = new ResourceCountService;

        $context = $this->getContext();

        return $service->getCountWithContext($this->resources_count_query, $context);
    }

    /**
     * Build context for dynamic parameters
     *
     * @return array<string, mixed>
     */
    private function getContext(): array
    {
        $context = [];

        $financerId = activeFinancerId();

        if (! in_array($financerId, [null, '', '0'], true)) {
            $context['financer_id'] = $financerId;
        }

        // Add user context if authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $context['user'] = $user;
            // Use locale field for language parameter
            $context['language'] = $user->locale ?? 'en-GB';
            $context['country'] = $user->country ?? null;
        }

        return $context;
    }
}
