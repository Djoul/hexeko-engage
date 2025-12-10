<?php

namespace App\Http\Resources\Me;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $model = $this->resource->model ?? $this->resource;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'data' => $this->getTypeSpecificData($model),
        ];
    }

    /**
     * Get type-specific data based on the model type
     */
    private function getTypeSpecificData($model): array
    {
        if ($this->type === 'article' && $model) {
            $translation = $model->translation();
            $userInteractions = $model->interactionsForUser(Auth::user());

            return [
                'content' => $translation?->content ?? null,
                'tags' => $model->tags ?? [],
                'status' => $translation?->status ?? null,
                'published_at' => $translation?->published_at ?? null,
                'is_favorite' => $userInteractions->isNotEmpty() && $userInteractions->contains(function ($interaction) {
                    return $interaction->is_favorite;
                }) ?? false,
                'favorites_count' => $model->interactions->where('is_favorite', true)->count() ?? 0,
                'author' => [
                    'id' => $model->author?->id,
                    'first_name' => $model->author?->first_name,
                    'last_name' => $model->author?->last_name,
                ],
            ];
        }

        if ($this->type === 'survey' && $model) {
            return [
                'description' => $model->description ?? null,
                'starts_at' => $model->starts_at ?? null,
                'ends_at' => $model->ends_at ?? null,
                'users_count' => $model->users_count ?? 0,
                'questions_count' => $model->questions_count ?? 0,
            ];
        }

        return [];
    }
}
