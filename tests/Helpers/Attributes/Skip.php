<?php

declare(strict_types=1);

namespace Tests\Helpers\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Skip
{
    /**
     * Skip all tests in this class with a reason.
     *
     * @param  string  $reason  The reason why the tests are skipped
     */
    public function __construct(
        public string $reason = 'Skipped'
    ) {}
}
