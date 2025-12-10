<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TagResourceCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = TagResource::class;
}
