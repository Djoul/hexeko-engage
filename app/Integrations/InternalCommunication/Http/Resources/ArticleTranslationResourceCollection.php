<?php

namespace App\Integrations\InternalCommunication\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/** @see \App\Integrations\InternalCommunication\Models\ArticleTranslation */
class ArticleTranslationResourceCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
