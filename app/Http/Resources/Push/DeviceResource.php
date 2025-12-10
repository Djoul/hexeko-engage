<?php

namespace App\Http\Resources\Push;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subscription_id' => $this->subscription_id,
            'user_id' => $this->user_id,
            'device_type' => $this->device_type,
            'device_model' => $this->device_model,
            'device_os' => $this->device_os,
            'app_version' => $this->app_version,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'tags' => $this->tags ?? [],
            'notification_preferences' => $this->notification_preferences ?? [],
            'push_enabled' => $this->push_enabled,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
