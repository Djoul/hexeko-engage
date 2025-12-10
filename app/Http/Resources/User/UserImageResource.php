<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The unique identifier of the user.
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'id' => $this->id,

            /**
             * The first name of the user.
             *
             * @example "John"
             */
            'first_name' => $this->first_name,

            /**
             * The last name of the user.
             *
             * @example "Doe"
             */
            'last_name' => $this->last_name,

            /**
             * The URL of the user's profile image (temporary S3 link).
             *
             * @example "https://s3.amazonaws.com/bucket/profile-image-temp-url"
             */
            'profile_image' => $this->getProfileImageUrl(),
        ];
    }
}
