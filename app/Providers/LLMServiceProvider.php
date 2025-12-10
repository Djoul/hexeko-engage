<?php

namespace App\Providers;

use App\AI\Clients\OpenAIStreamerClient;
use App\AI\Contracts\LLMClientInterface;
use App\AI\LLMRouterService;
use Illuminate\Support\ServiceProvider;

class LLMServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        // Register LLMRouterService with all available clients
        $this->app->singleton(LLMRouterService::class, function ($app): LLMRouterService {
            $clients = [
                $app->make(OpenAIStreamerClient::class),
                // add other clients here if needed
            ];

            return new LLMRouterService($clients);
        });

        // Link a default AI client to the interface (useful in some cases)
        $this->app->bind(LLMClientInterface::class, function ($app) {
            // Utiliser le client Prism par défaut
            return $app->make(LLMRouterService::class)->select('openAI');
        });
    }
}
