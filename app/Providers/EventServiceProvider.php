<?php

namespace App\Providers;

use App\Events\InvitationCreated;
use App\Events\Metrics\ModuleAccessed;
use App\Events\Metrics\ModuleUsed;
use App\Events\Metrics\SessionFinished;
use App\Events\Metrics\SessionStarted;
use App\Events\Metrics\UserAccountActivated;
use App\Events\UserAuthenticated;
use App\Integrations\HRTools\Events\Metrics\LinkAccessed;
use App\Integrations\HRTools\Events\Metrics\LinkClicked;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleClosedWithoutInteraction;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleLiked;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleViewed;
use App\Integrations\InternalCommunication\Events\Metrics\CommunicationSectionVisited;
use App\Integrations\Vouchers\Amilon\Events\Metrics\OrderCreated;
use App\Listeners\Auth\RoleAttachedListener;
use App\Listeners\Auth\RoleDetachedListener;
use App\Listeners\AutoSyncTranslationMigrationsListener;
use App\Listeners\Metrics\LogEngagementFromEvent;
use App\Listeners\NotifyUserAuthenticationListener;
use App\Listeners\RunAfterMigrate;
use App\Listeners\SyncUserAttributesListener;
use App\Subscribers\LogCommandSubscriber;
use App\Subscribers\LogJobSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

class EventServiceProvider extends ServiceProvider
{
    protected $subscribe = [
        LogJobSubscriber::class,
        LogCommandSubscriber::class,
    ];

    protected $listen = [
        'Illuminate\Database\Events\MigrationsEnded' => [
            RunAfterMigrate::class,
            AutoSyncTranslationMigrationsListener::class,
        ],
        RoleAttached::class => [
            RoleAttachedListener::class,
        ],
        RoleDetached::class => [
            RoleDetachedListener::class,
        ],
        InvitationCreated::class => [
            SyncUserAttributesListener::class,
        ],
        UserAuthenticated::class => [
            NotifyUserAuthenticationListener::class,
        ],

        UserAccountActivated::class => [
            LogEngagementFromEvent::class,
        ],
        ModuleAccessed::class => [
            LogEngagementFromEvent::class,
        ],
        ModuleUsed::class => [
            LogEngagementFromEvent::class,
        ],

        SessionStarted::class => [
            LogEngagementFromEvent::class,
        ],
        SessionFinished::class => [
            LogEngagementFromEvent::class,
        ],

        // region  Internal-communication
        ArticleLiked::class => [
            LogEngagementFromEvent::class,
        ],
        ArticleViewed::class => [
            LogEngagementFromEvent::class,
        ],
        ArticleClosedWithoutInteraction::class => [
            LogEngagementFromEvent::class,
        ],
        CommunicationSectionVisited::class => [
            LogEngagementFromEvent::class,
        ],
        // endregion

        // region HRTools
        LinkClicked::class => [
            LogEngagementFromEvent::class,
        ],
        LinkAccessed::class => [
            LogEngagementFromEvent::class,
        ],
        // endregion

        // region Vouchers/Amilon
        OrderCreated::class => [
            LogEngagementFromEvent::class,
        ],
        // endregion
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Conditionally disable AutoSyncTranslationMigrationsListener in tests
        // unless explicitly enabled via ALLOW_AUTO_SYNC_LISTENER_TESTING=true
        if ($this->shouldDisableAutoSyncListener()) {
            $this->listen['Illuminate\Database\Events\MigrationsEnded'] = array_filter(
                $this->listen['Illuminate\Database\Events\MigrationsEnded'],
                fn (string $listener): bool => $listener !== AutoSyncTranslationMigrationsListener::class
            );
        }

        parent::boot();
    }

    /**
     * Determine if AutoSyncTranslationMigrationsListener should be disabled
     */
    private function shouldDisableAutoSyncListener(): bool
    {
        $allowInTests = filter_var(env('ALLOW_AUTO_SYNC_LISTENER_TESTING', false), FILTER_VALIDATE_BOOL);

        return (defined('PHPUNIT_RUNNING') || $this->app->runningUnitTests()) && ! $allowInTests;
    }
}
