<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class RequiresPermission
{
    /**
     * @param  string|array<int, string>  $permission
     */
    public function __construct(public string|array $permission) {}
}
