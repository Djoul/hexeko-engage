<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ArticleVersionResourceCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ArticleVersionResource::class;

    /**
     * Create a new resource instance.
     *
     * @return void
     */
    public function __construct($resource)
    {
        parent::__construct($resource ?? collect());
    }
}
