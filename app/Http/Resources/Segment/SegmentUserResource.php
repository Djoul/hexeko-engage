<?php

namespace App\Http\Resources\Segment;

use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Segment */
class SegmentUserResource extends JsonResource
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
             * The unique identifier of the user.
             *
             * @var string
             */
            'id' => $this->id,

            /**
             * The email address of the user.
             *
             * @var string
             */
            'email' => $this->email,

            /**
             * The first name of the user.
             *
             * @var string
             */
            'first_name' => $this->first_name,

            /**
             * The last name of the user.
             *
             * @var string
             */
            'last_name' => $this->last_name,

            /**
             * The gender of the user.
             *
             * @var string
             */
            'gender' => $this->gender,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
