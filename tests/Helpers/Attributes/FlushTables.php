<?php

declare(strict_types=1);

namespace Tests\Helpers\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class FlushTables
{
    /**
     * Enable deterministic DELETE-only flush for this test class.
     * If $tables is empty, a full flush (within current schema) will be performed.
     * scope: 'test' (default) flushes before each test; 'class' flushes once per class.
     * expand: when true, adds dependent tables based on mapping + FK discovery; false = only listed tables.
     */
    public function __construct(
        public bool $enabled = true,
        public array $tables = [],
        public string $scope = 'test',
        public bool $expand = true,
    ) {}
}
