<?php

namespace Tests\Feature\Http\Controllers\V1\TrackMetricsEventController;

use App\Events\Metrics\ModuleAccessed;
use App\Events\Metrics\ModuleUsed;
use App\Events\Metrics\UserAccountActivated;
use App\Integrations\HRTools\Events\Metrics\LinkAccessed;
use App\Integrations\HRTools\Events\Metrics\LinkClicked;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleClosedWithoutInteraction;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleLiked;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleViewed;
use App\Integrations\InternalCommunication\Events\Metrics\CommunicationSectionVisited;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('metrics')]
class TrackMetricsEventTest extends ProtectedRouteTestCase
{
    public $auth;

    #[Test]
    public function it_triggers_account_activated_event(): void
    {
        Event::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/metrics/track', [
            'event' => 'UserAccountActivated',
            'user_id' => $user->id,
        ]);

        $response->assertOk();
        Event::assertDispatched(UserAccountActivated::class);
    }

    #[Test]
    public function it_triggers_module_accessed_event_with_module(): void
    {
        Event::fake();

        $response = $this->postJson('/api/v1/metrics/track', [
            'event' => 'ModuleAccessed',
            'module_id' => 'communication-rh',
        ]);

        $response->assertOk();
        Event::assertDispatched(ModuleAccessed::class);
    }

    #[Test]
    public function it_triggers_module_used_event(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'ModuleUsed',
            'module_id' => 'openAI',
        ]);
        $response->assertOk();
        Event::assertDispatched(ModuleUsed::class);
    }

    #[Test]
    public function it_triggers_article_viewed_event(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $financer = $this->auth->financers->first();
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->id,
        ]);
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'ArticleViewed',
            'article_id' => $article->id,
        ]);
        $response->assertOk();
        Event::assertDispatched(ArticleViewed::class);
    }

    #[Test]
    public function it_triggers_article_liked_event(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $financer = $this->auth->financers->first();
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->id,
        ]);
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'ArticleLiked',
            'article_id' => $article->id,
        ]);
        $response->assertOk();
        Event::assertDispatched(ArticleLiked::class);
    }

    #[Test]
    public function it_triggers_article_closed_without_interaction_event(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $financer = $this->auth->financers->first();
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->id,
        ]);
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'ArticleClosedWithoutInteraction',
            'article_id' => $article->id,
        ]);
        $response->assertOk();
        Event::assertDispatched(ArticleClosedWithoutInteraction::class);
    }

    #[Test]
    public function it_triggers_communication_section_visited_event(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'CommunicationSectionVisited',
        ]);
        $response->assertOk();
        Event::assertDispatched(CommunicationSectionVisited::class);
    }

    #[Test]
    public function it_triggers_link_accessed_event(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'LinkAccessed',
            'link_id' => 'zoom',
        ]);
        $response->assertOk();
        Event::assertDispatched(LinkAccessed::class);
    }

    #[Test]
    public function it_triggers_link_clicked_event(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'LinkClicked',
            'link_id' => 'zoom',
        ]);
        $response->assertOk();
        Event::assertDispatched(LinkClicked::class);
    }

    #[Test]
    public function it_fails_if_missing_required_attributes(): void
    {
        $response = $this->postJson('/api/v1/metrics/track', [
            'event' => 'ModuleAccessed',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['module_id']);
    }

    #[Test]
    public function it_fails_if_event_is_invalid(): void
    {
        $response = $this->postJson('/api/v1/metrics/track', [
            'event' => 'HackTheWorld',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event']);
    }

    #[Test]
    public function it_returns_404_when_tracking_article_event_with_non_existent_article(): void
    {
        $user = User::factory()->create();
        $nonExistentArticleId = '00000000-0000-0000-0000-000000000000';

        // Test ArticleViewed event
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'ArticleViewed',
            'article_id' => $nonExistentArticleId,
        ]);

        $response->assertNotFound();
        $response->assertJson(['error' => 'Article not found']);

        // Test ArticleLiked event
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'ArticleLiked',
            'article_id' => $nonExistentArticleId,
        ]);

        $response->assertNotFound();
        $response->assertJson(['error' => 'Article not found']);

        // Test ArticleClosedWithoutInteraction event
        $response = $this->actingAs($user)->postJson('/api/v1/metrics/track', [
            'event' => 'ArticleClosedWithoutInteraction',
            'article_id' => $nonExistentArticleId,
        ]);

        $response->assertNotFound();
        $response->assertJson(['error' => 'Article not found']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create user with financer
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $this->auth = ModelFactory::createUser([
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $financer->id);

        $this->actingAs($this->auth);
        //        $this->withoutExceptionHandling();
    }
}
