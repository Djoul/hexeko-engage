<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\ArticleReactionsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class ArticleReactionsCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private ArticleReactionsCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ArticleReactionsCalculator;
        $this->financer = Financer::factory()->create();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::ARTICLE_REACTIONS,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_article_reactions_by_day(): void
    {
        // Create users and articles
        $users = User::factory()->count(3)->create();
        $articles = collect();
        // Create 4 articles to avoid conflicts
        for ($i = 0; $i < 4; $i++) {
            $articles->push(resolve(ArticleFactory::class)->create([
                'financer_id' => $this->financer->id,
                'author_id' => $users[0]->id,
            ]));
        }

        foreach ($users as $user) {
            FinancerUser::create([
                'financer_id' => $this->financer->id,
                'user_id' => $user->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        $now = Carbon::now();

        // Day 1: 5 reactions
        // User 0 likes 2 articles
        ArticleInteraction::create([
            'user_id' => $users[0]->id,
            'article_id' => $articles[0]->id,
            'reaction' => 'like',
            'is_favorite' => false,
            'created_at' => $now->copy()->startOfDay()->addHours(1),
        ]);

        ArticleInteraction::create([
            'user_id' => $users[0]->id,
            'article_id' => $articles[1]->id,
            'reaction' => 'love',
            'is_favorite' => false,
            'created_at' => $now->copy()->startOfDay()->addHours(2),
        ]);

        // User 1 likes 2 articles (can't like same article multiple times due to unique constraint)
        ArticleInteraction::create([
            'user_id' => $users[1]->id,
            'article_id' => $articles[0]->id,
            'reaction' => 'like',
            'is_favorite' => false,
            'created_at' => $now->copy()->startOfDay()->addHours(3),
        ]);

        ArticleInteraction::create([
            'user_id' => $users[1]->id,
            'article_id' => $articles[1]->id,
            'reaction' => 'heart',
            'is_favorite' => false,
            'created_at' => $now->copy()->startOfDay()->addHours(4),
        ]);

        // User 2 likes 1 article on day 1
        ArticleInteraction::create([
            'user_id' => $users[2]->id,
            'article_id' => $articles[0]->id,
            'reaction' => 'thumbsup',
            'is_favorite' => false,
            'created_at' => $now->copy()->startOfDay()->addHours(5),
        ]);

        // Day 2: 2 reactions (use different articles to avoid conflicts)
        ArticleInteraction::create([
            'user_id' => $users[2]->id,
            'article_id' => $articles[2]->id,
            'reaction' => 'celebrate',
            'is_favorite' => false,
            'created_at' => $now->copy()->addDay()->startOfDay()->addHours(1),
        ]);

        ArticleInteraction::create([
            'user_id' => $users[2]->id,
            'article_id' => $articles[3]->id,
            'reaction' => 'like',
            'is_favorite' => false,
            'created_at' => $now->copy()->addDay()->startOfDay()->addHours(2),
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDay()->endOfDay(),
            'daily'
        );

        // Check structure
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('total', $result);

        // Check daily data
        $this->assertCount(2, $result['daily']);

        // Day 1: 5 reactions
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);
        $this->assertEquals(5, $result['daily'][0]['count']);

        // Day 2: 2 reactions
        $this->assertEquals($now->copy()->addDay()->toDateString(), $result['daily'][1]['date']);
        $this->assertEquals(2, $result['daily'][1]['count']);

        // Check total
        $this->assertEquals(7, $result['total']);
    }

    #[Test]
    public function it_returns_empty_data_when_no_financer_users(): void
    {
        $result = $this->calculator->calculate(
            $this->financer->id,
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            'daily'
        );

        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEmpty($result['daily']);
        $this->assertEquals(0, $result['total']);
    }

    #[Test]
    public function it_only_counts_active_financer_users(): void
    {
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create();
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $this->financer->id,
            'author_id' => $activeUser->id,
        ]);

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $activeUser->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $inactiveUser->id,
            'active' => false,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create reactions for both users
        ArticleInteraction::create([
            'user_id' => $activeUser->id,
            'article_id' => $article->id,
            'reaction' => 'like',
            'is_favorite' => false,
            'created_at' => $now,
        ]);

        ArticleInteraction::create([
            'user_id' => $inactiveUser->id,
            'article_id' => $article->id,
            'reaction' => 'love',
            'is_favorite' => false,
            'created_at' => $now,
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should only count active user's reaction
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_only_counts_reactions_not_favorites(): void
    {
        $user = User::factory()->create();
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $this->financer->id,
            'author_id' => $user->id,
        ]);

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create interaction with reaction
        ArticleInteraction::create([
            'user_id' => $user->id,
            'article_id' => $article->id,
            'reaction' => 'like',
            'is_favorite' => false,
            'created_at' => $now,
        ]);

        // Create interaction without reaction (just favorite)
        $article2 = resolve(ArticleFactory::class)->create([
            'financer_id' => $this->financer->id,
            'author_id' => $user->id,
        ]);
        ArticleInteraction::create([
            'user_id' => $user->id,
            'article_id' => $article2->id,
            'reaction' => null,
            'is_favorite' => true,
            'created_at' => $now,
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should only count the reaction, not the favorite
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_returns_zero_for_days_without_reactions(): void
    {
        $user = User::factory()->create();
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $this->financer->id,
            'author_id' => $user->id,
        ]);

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create reaction only on first day
        ArticleInteraction::create([
            'user_id' => $user->id,
            'article_id' => $article->id,
            'reaction' => 'like',
            'is_favorite' => false,
            'created_at' => $now,
        ]);

        // Calculate for 3 days
        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDays(2)->endOfDay(),
            'daily'
        );

        $this->assertCount(3, $result['daily']);

        // First day has reaction
        $this->assertEquals(1, $result['daily'][0]['count']);

        // Other days should be 0
        $this->assertEquals(0, $result['daily'][1]['count']);
        $this->assertEquals(0, $result['daily'][2]['count']);
    }

    #[Test]
    public function it_counts_all_reaction_types(): void
    {
        $user = User::factory()->create();
        $articles = collect();
        for ($i = 0; $i < 4; $i++) {
            $articles->push(resolve(ArticleFactory::class)->create([
                'financer_id' => $this->financer->id,
                'author_id' => $user->id,
            ]));
        }

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();
        $reactionTypes = ['like', 'love', 'celebrate', 'insightful'];

        // Create different reaction types
        foreach ($reactionTypes as $index => $reaction) {
            ArticleInteraction::create([
                'user_id' => $user->id,
                'article_id' => $articles[$index]->id,
                'reaction' => $reaction,
                'is_favorite' => false,
                'created_at' => $now,
            ]);
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should count all reaction types
        $this->assertEquals(4, $result['total']);
        $this->assertEquals(4, $result['daily'][0]['count']);
    }
}
