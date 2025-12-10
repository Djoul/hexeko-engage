<?php

use App\Integrations\HRTools\Models\Link;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;

return [
    /**
     * Whether demo operations are allowed in this environment
     */
    'allowed' => env('DEMO_ALLOWED', false),

    /**
     * Order for purging demo entities (child → parent)
     * This ensures foreign key constraints are respected
     */
    'purge_order' => [
        Article::class,
        Link::class,
        User::class,
        Financer::class,
        Division::class,
    ],

    /**
     * Chunk size for batch deletion operations
     */
    'chunk' => 500,

    /**
     * Whether to silence model events during purge operations
     */
    'silence_events' => true,
];
