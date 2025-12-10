<?php

declare(strict_types=1);

namespace App\Models\AdminPanel;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminAuditLog extends Model
{
    protected $table = 'admin_audit_logs';

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'timestamp' => 'datetime',
    ];

    protected $dates = [
        'timestamp',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the entity that was affected (polymorphic)
     */
    public function auditable()
    {
        return $this->morphTo('entity');
    }

    /**
     * Scope for filtering by action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for filtering by entity type
     */
    public function scopeByEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope for filtering by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('timestamp', [$startDate, $endDate]);
    }

    /**
     * Get human-readable action name
     */
    public function getActionLabelAttribute(): string
    {
        $labels = [
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            'import' => 'Imported',
            'export' => 'Exported',
            'auth_attempt' => 'Authentication Attempt',
            'navigate' => 'Navigation',
        ];

        return $labels[$this->action] ?? $this->action;
    }

    /**
     * Get the changes made
     */
    public function getChangesAttribute(): array
    {
        $changes = [];

        if ($this->old_values && $this->new_values) {
            foreach ($this->new_values as $key => $newValue) {
                $oldValue = $this->old_values[$key] ?? null;

                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }

        return $changes;
    }
}
