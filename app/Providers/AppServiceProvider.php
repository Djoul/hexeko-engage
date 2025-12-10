<?php

declare(strict_types=1);

namespace App\Providers;

use App;
use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Policies\LinkPolicy;
use App\Models\Invoice;
use App\Models\NotificationTopic;
use App\Models\User;
use App\Policies\InvoicePolicy;
use App\Policies\NotificationTopicPolicy;
use Carbon\CarbonImmutable;
use DB;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configureCommands();

        //        $this->registerTestingSchema();

        // Register policies
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(NotificationTopic::class, NotificationTopicPolicy::class);
        Gate::policy(Link::class, LinkPolicy::class);

        Gate::define('viewPulse', function (User $user): true {
            return true;
        });
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );

                // Sort tags alphabetically
                $tags = $openApi->tags ?? [];
                usort($tags, fn ($a, $b): int => strcasecmp($a->name, $b->name));
                $openApi->tags = $tags;
            });

        // TODO: Uncomment this when we agree on a way to handle dates
        // Date::use(CarbonImmutable::class);
    }

    public function configureModels(): void
    {
        // preventLazyLoading
        // preventSilentlyDiscardingAttributes
        // preventAccessingMissingAttributes
        Model::shouldBeStrict(App::environment() == 'local');

        Model::unguard();
    }

    public function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }
}
