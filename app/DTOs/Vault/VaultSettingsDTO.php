<?php

namespace App\DTOs\Vault;

class VaultSettingsDTO
{
    /**
     * @var array<string>
     */
    public array $unifiedApis;

    public bool $isolationMode;

    public ?bool $hideResourceSettings;

    public ?bool $sandboxMode;

    public ?string $sessionLength;

    public ?bool $showLogs;

    public ?bool $showSuggestions;

    public ?bool $showSidebar;

    public ?bool $autoRedirect;

    public ?bool $hideGuides;

    /**
     * @var array<string>|null
     */
    public ?array $allowActions;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $customConsumerSettings;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data = [])
    {
        $unifiedApisData = $data['unified_apis'] ?? null;
        /** @var array<string> $unifiedApis */
        $unifiedApis = is_array($unifiedApisData) ? array_values(array_filter($unifiedApisData, 'is_string')) : ['hris'];
        $this->unifiedApis = $unifiedApis;
        $this->isolationMode = (bool) ($data['isolation_mode'] ?? false);
        $this->hideResourceSettings = is_bool($data['hide_resource_settings'] ?? null) ? $data['hide_resource_settings'] : null;
        $this->sandboxMode = is_bool($data['sandbox_mode'] ?? null) ? $data['sandbox_mode'] : null;
        $this->sessionLength = is_string($data['session_length'] ?? null) ? $data['session_length'] : null;
        $this->showLogs = is_bool($data['show_logs'] ?? null) ? $data['show_logs'] : null;
        $this->showSuggestions = is_bool($data['show_suggestions'] ?? null) ? $data['show_suggestions'] : null;
        $this->showSidebar = is_bool($data['show_sidebar'] ?? null) ? $data['show_sidebar'] : null;
        $this->autoRedirect = is_bool($data['auto_redirect'] ?? null) ? $data['auto_redirect'] : null;
        $this->hideGuides = is_bool($data['hide_guides'] ?? null) ? $data['hide_guides'] : null;

        $allowActionsData = $data['allow_actions'] ?? null;
        if (is_array($allowActionsData)) {
            /** @var array<string> $filteredActions */
            $filteredActions = array_values(array_filter($allowActionsData, 'is_string'));
            $this->allowActions = $filteredActions;
        } else {
            $this->allowActions = null;
        }

        $customConsumerSettingsData = $data['custom_consumer_settings'] ?? null;
        /** @var array<string, mixed>|null $customConsumerSettings */
        $customConsumerSettings = is_array($customConsumerSettingsData) ? $customConsumerSettingsData : null;
        $this->customConsumerSettings = $customConsumerSettings;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $settings = [
            'unified_apis' => $this->unifiedApis,
            'isolation_mode' => $this->isolationMode,
        ];

        if ($this->hideResourceSettings !== null) {
            $settings['hide_resource_settings'] = $this->hideResourceSettings;
        }

        if ($this->sandboxMode !== null) {
            $settings['sandbox_mode'] = $this->sandboxMode;
        }

        if ($this->sessionLength !== null) {
            $settings['session_length'] = $this->sessionLength;
        }

        if ($this->showLogs !== null) {
            $settings['show_logs'] = $this->showLogs;
        }

        if ($this->showSuggestions !== null) {
            $settings['show_suggestions'] = $this->showSuggestions;
        }

        if ($this->showSidebar !== null) {
            $settings['show_sidebar'] = $this->showSidebar;
        }

        if ($this->autoRedirect !== null) {
            $settings['auto_redirect'] = $this->autoRedirect;
        }

        if ($this->hideGuides !== null) {
            $settings['hide_guides'] = $this->hideGuides;
        }

        if ($this->allowActions !== null) {
            $settings['allow_actions'] = $this->allowActions;
        }

        if ($this->customConsumerSettings !== null) {
            $settings['custom_consumer_settings'] = $this->customConsumerSettings;
        }

        return $settings;
    }
}
