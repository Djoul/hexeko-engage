<?php

declare(strict_types=1);

namespace App\Integrations\Payments\Stripe\DTO;

readonly class WebhookEventDTO
{
    public function __construct(
        public string $payload,
        public string $signature,
        public string $secret,
    ) {}
}
