<?php

namespace App\Traits;

use OwenIt\Auditing\Auditable;

/**
 * Trait AuditableModel
 *
 * This trait provides auditing functionality for Eloquent models.
 * It centralizes the configuration for the owen-it/laravel-auditing package.
 */
trait AuditableModel
{
    use Auditable;

    /**
     * Attributes to exclude from the Audit.
     *
     * @var array<string>
     */
    protected $auditExclude = [
        'updated_at',
        'created_at',
        'deleted_at',
        'remember_token',
        'password',
    ];

    /**
     * Audit threshold.
     *
     * @var int
     */
    protected $auditThreshold = 100;

    /**
     * Whether to audit events that don't change any attributes.
     *
     * @var bool
     */
    protected $auditEmptyValues = false;

    /**
     * Audit events.
     *
     * @var array<string>
     */
    protected $auditEvents = [
        'created',
        'updated',
        'deleted',
        'restored',
    ];
}
