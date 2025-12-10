<?php

namespace App\Http\Resources\User;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class CreatorResource extends JsonResource
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
             * The email address of the user.
             *
             * @example "john.doe@example.com"
             */
            'email' => $this->email,

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
             * The date and time when the user was created.
             *
             * @example "2024-01-15T10:30:45.000000Z"
             */
            'created_at' => $this->created_at,

            /**
             * The date and time when the user was last updated.
             *
             * @example "2024-11-05T14:22:30.000000Z"
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
