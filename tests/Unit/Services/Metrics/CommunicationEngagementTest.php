<?php

namespace Tests\Unit\Services\Metrics;

use App\Integrations\InternalCommunication\Events\Metrics\ArticleClosedWithoutInteraction;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleLiked;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleViewed;
use App\Integrations\InternalCommunication\Events\Metrics\CommunicationSectionVisited;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('article')]
#[Group('internal-communication')]
class CommunicationEngagementTest extends TestCase
{
    #[Test]
    public function it_logs_article_viewed_event(): void
    {

        $user = User::factory()->create();
        $articleId = 123;

        event(new ArticleViewed($user->id, $articleId));

        $this->artisan('queue:work --once');

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'article:123',
        ]);
    }

    #[Test]
    public function it_logs_article_liked_event(): void
    {
        $user = User::factory()->create();
        $articleId = 456;

        event(new ArticleLiked($user->id, $articleId));

        $this->artisan('queue:work --once');

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'ArticleLiked',
            'target' => 'article:456',
        ]);
    }

    #[Test]
    public function it_logs_article_closed_without_interaction_event(): void
    {

        $user = User::factory()->create();
        $articleId = 789;

        event(new ArticleClosedWithoutInteraction($user->id, $articleId));

        $this->artisan('queue:work --once');

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'ArticleClosedWithoutInteraction',
            'target' => 'article:789',
        ]);
    }

    #[Test]
    public function it_logs_communication_section_visited_event(): void
    {
        $user = User::factory()->create();

        event(new CommunicationSectionVisited($user->id));

        $this->artisan('queue:work --once');

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'CommunicationSectionVisited',
            'target' => 'internal-communication',
        ]);
    }
}
