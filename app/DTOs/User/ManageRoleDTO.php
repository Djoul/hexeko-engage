<?php

declare(strict_types=1);

namespace App\DTOs\User;

/**
 * DTO for role management operations (assign, remove, sync)
 *
 * Type-safe data transfer for user role operations.
 */
final readonly class ManageRoleDTO
{
    /**
     * @param  array<string>|null  $roles
     */
    public function __construct(
        public string $userId,
        public ?string $roleId = null,
        public ?string $roleName = null,
        public ?array $roles = null,
    ) {}

    /**
     * Create DTO for single role operation (assign/remove).
     *
     * @param  array{user_id: string, role_id?: string, role_name?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            roleId: $data['role_id'] ?? null,
            roleName: $data['role_name'] ?? null,
            roles: $data['roles'] ?? null,
        );
    }

    /**
     * Check if this is a single role operation.
     */
    public function isSingleRole(): bool
    {
        return $this->roleId !== null || $this->roleName !== null;
    }

    /**
     * Check if this is a sync operation (multiple roles).
     */
    public function isSyncOperation(): bool
    {
        return $this->roles !== null && is_array($this->roles);
    }

    /**
     * Get the role identifier (ID or name).
     */
    public function getRoleIdentifier(): ?string
    {
        return $this->roleId ?? $this->roleName;
    }

    /**
     * Get roles array for sync operation.
     *
     * @return array<string>
     */
    public function getRoles(): array
    {
        return $this->roles ?? [];
    }
}
