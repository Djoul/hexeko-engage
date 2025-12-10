<?php

namespace App\Services;

use App\Models\MobileVersionLog;
use PHLAK\SemVer\Version;

class MobileVersionService
{
    protected array $minimumRequiredVersion;

    public function __construct()
    {
        $this->minimumRequiredVersion = config('version.minimum_required_version', ['ios' => '0.0.1', 'android' => '0.0.1']);
    }

    public function check(array $data): object
    {
        $updateStatus = $this->determineUpdateStatus($data);

        return (object) [
            'platform' => $data['platform'] ?? null,
            'update_type' => $updateStatus['type'],
            'version' => $data['version'] ?? null,
            'minimum_required_version' => $this->minimumRequiredVersion[$data['platform']],
            'should_update' => $updateStatus['required'],
        ];
    }

    /**
     * Determine if an update is required and what type
     *
     * @return array{type: string|null, required: bool}
     */
    private function determineUpdateStatus(array $data): array
    {
        $currentVersion = new Version($data['version']);
        $minimumRequiredVersion = new Version($this->minimumRequiredVersion[$data['platform']]);

        // Check if version requires soft update
        if ($currentVersion->lt($minimumRequiredVersion)) {
            return [
                'type' => 'store_required',
                'required' => true,
            ];
        }

        return [
            'type' => null,
            'required' => false,
        ];
    }

    public function log(array $data): MobileVersionLog
    {
        return MobileVersionLog::create([
            'financer_id' => $data['financer_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'platform' => $data['platform'] ?? null,
            'version' => $data['version'] ?? null,
            'update_type' => $data['update_type'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'minimum_required_version' => $data['minimum_required_version'] ?? null,
            'should_update' => $data['should_update'] ?? null,
        ]);
    }
}
