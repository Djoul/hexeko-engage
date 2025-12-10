<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The platform of the mobile app.
             *
             * @var string
             *
             * @example "android" or "ios"
             */
            'platform' => $this->platform,
            /**
             * The version of the mobile app for the provided platform.
             *
             * @var string
             *
             * @example "1.0.0"
             */
            'version' => $this->version,
            /**
             * The minimum required version of the mobile app for the provided platform.
             *
             * @var string
             *
             * @example "1.0.0"
             */
            'minimum_required_version' => $this->minimum_required_version,
            /**
             * Whether the mobile app should update.
             *
             * @var bool
             *
             * @example true or false
             */
            'should_update' => $this->should_update,
            /**
             * The type of update required.
             *
             * @var string
             *
             * @example "store_required" or "soft_required"
             */
            'update_type' => $this->update_type,
        ];
    }
}
