<?php

namespace App\Models\Traits\Financer;

use App\Enums\IDP\RoleDefaults;

trait FinancerAccessorsAndHelpers
{
    protected static function logName(): string
    {
        return 'financer';
    }

    public function attachUser(string $userId, string $role = RoleDefaults::BENEFICIARY): void
    {
        $this->users()->attach($userId, ['active' => true, 'role' => $role]);

        activity('financer')
            ->performedOn($this)
            ->log("Utilisateur ID {$userId} attaché au financier {$this->name}");
    }

    public function detachUser(string $userId): void
    {
        $this->users()->detach($userId);

        activity('financer')
            ->performedOn($this)
            ->log("Utilisateur ID {$userId} détaché du financier {$this->name}");
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    public function attachIntegration(string $integrationId, array $pivot): void
    {
        $this->integrations()->attach($integrationId, $pivot !== [] ? $pivot : ['active' => true]);

        activity('financer')
            ->performedOn($this)
            ->log("Integration ID {$integrationId} attachée au financier {$this->name}");
    }

    public function detachIntegration(string $integrationId): void
    {
        $this->integrations()->detach($integrationId);

        activity('financer')
            ->performedOn($this)
            ->log("Integration ID {$integrationId} détachée du financier {$this->name}");
    }

    public function getLogoUrl(): ?string
    {
        if (! $this->hasMedia('logo')) {
            return null;
        }

        $media = $this->getFirstMedia('logo');
        if (! $media) {
            return null;
        }

        // For S3 disk, generate a temporary URL
        if (in_array($media->disk, ['s3', 's3-local'])) {
            return $media->getTemporaryUrl(now()->addHour());
        }

        return $media->getFullUrl();
    }
}
