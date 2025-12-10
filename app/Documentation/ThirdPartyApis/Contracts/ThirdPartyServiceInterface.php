<?php

declare(strict_types=1);

namespace App\Documentation\ThirdPartyApis\Contracts;

interface ThirdPartyServiceInterface
{
    public function getProviderName(): string;

    public function getApiVersion(): string;

    public function isHealthy(): bool;
}
