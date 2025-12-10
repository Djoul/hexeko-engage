<?php

declare(strict_types=1);

namespace App\Providers;

use App\Integrations\Payments\Stripe\Contracts\StripeClientInterface;
use App\Integrations\Payments\Stripe\Services\StripeClientAdapter;
use Illuminate\Support\ServiceProvider;

class StripeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StripeClientInterface::class, StripeClientAdapter::class);
    }
}
