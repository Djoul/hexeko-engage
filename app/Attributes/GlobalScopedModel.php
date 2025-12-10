<?php

namespace App\Attributes;

use Attribute;

/**
 * Indicates that a model is global and not scoped to a specific financer.
 * Used by the audit system to determine whether to track financer_id.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class GlobalScopedModel {}
