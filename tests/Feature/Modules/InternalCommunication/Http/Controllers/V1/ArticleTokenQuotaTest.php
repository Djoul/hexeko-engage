<?php

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Enums\OrigineInterfaces;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Models\CreditBalance;
use App\Models\Financer;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('credit')]
#[Group('internal-communication')]
#[Group('article')]

class ArticleTokenQuotaTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_shows_ai_token_quota_in_articles_collection_meta(): void
    {
        // Arrange
        $user = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $financer->id);

        // Create some articles for the financer
        resolve(ArticleFactory::class)->count(3)->create([
            'financer_id' => $financer->id,
            'author_id' => $user->id,
        ]);

        // Create AI token balance for financer using factory
        CreditBalance::factory()
            ->forFinancer($financer)
            ->create([
                'type' => 'ai_token',
                'balance' => 150000,
                'context' => [
                    'initial_quota' => 200000,
                    'consumed' => 50000,
                ],
            ]);

        // Verify the balance was created correctly
        $this->assertDatabaseHas('credit_balances', [
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'ai_token',
            'balance' => 150000,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->withHeaders(
                [
                    'x-origine-inteface' => OrigineInterfaces::WEB_FINANCER,
                ])
            ->getJson("/api/v1/internal-communication/articles?financer_id={$financer->id}");

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'ai_token_quota' => [
                    'total',
                    'consumed',
                    'remaining',
                    'percentage_used',
                ],
            ],
        ]);

        $response->assertJson([
            'meta' => [
                'ai_token_quota' => [
                    'division_id' => $financer->division_id,
                    'division_name' => $financer->division->name,
                    'total' => 200000,
                    'consumed' => 50000,
                    'remaining' => 150000,
                    'percentage_used' => 25.0,
                ],
            ],
        ]);
    }

    #[Test]
    public function it_shows_zero_quota_when_no_ai_token_balance_exists(): void
    {
        // Arrange
        $user = $this->createAuthUser(withContext: true, returnDetails: true);

        // Set the active financer in Context for global scopes
        $financer = $user->financers->first();
        if ($financer) {
            Context::add('financer_id', $financer->id);
        }

        // Act
        $response = $this->actingAs($user)
            ->getJson('/api/v1/internal-communication/articles');

        // Assert
        $response->assertOk();
        $response->assertJson([
            'meta' => [
                'ai_token_quota' => [
                    'division_id' => null,
                    'division_name' => null,
                    'total' => 0,
                    'consumed' => 0,
                    'remaining' => 0,
                    'percentage_used' => 0,
                ],
            ],
        ]);
    }

    #[Test]
    public function it_shows_ai_token_quota_in_single_article_response(): void
    {
        // Arrange
        $user = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $financer->id);

        $article = resolve(ArticleFactory::class)
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $user->id,
            ]);

        // Create AI token balance using factory
        CreditBalance::factory()
            ->forFinancer($financer)
            ->create([
                'type' => 'ai_token',
                'balance' => 75000,
                'context' => [
                    'initial_quota' => 100000,
                    'consumed' => 25000,
                ],
            ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/v1/internal-communication/articles/{$article->id}?financer_id={$financer->id}");

        // Assert
        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'ai_token_quota' => [
                    'total',
                    'consumed',
                    'remaining',
                    'percentage_used',
                ],
            ],
        ]);
    }

    #[Test]
    public function it_calculates_correct_percentage_used(): void
    {
        // Arrange
        $user = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $financer->id);

        // Create balance with specific values for percentage calculation using factory
        CreditBalance::factory()
            ->forFinancer($financer)
            ->create([
                'type' => 'ai_token',
                'balance' => 25000, // remaining
                'context' => [
                    'initial_quota' => 100000,
                    'consumed' => 75000, // 75% used
                ],
            ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/v1/internal-communication/articles?financer_id={$financer->id}");

        // Assert
        $response->assertJson([
            'meta' => [
                'ai_token_quota' => [
                    'division_id' => $financer->division_id,
                    'division_name' => $financer->division->name,
                    'total' => 100000,
                    'consumed' => 75000,
                    'remaining' => 25000,
                    'percentage_used' => 75.0,
                ],
            ],
        ]);
    }

    #[Test]
    public function it_handles_division_between_zero_total_quota(): void
    {
        // Arrange
        $user = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $financer->id);

        // Create balance with zero initial quota using factory
        CreditBalance::factory()
            ->forFinancer($financer)
            ->create([
                'type' => 'ai_token',
                'balance' => 0,
                'context' => [
                    'initial_quota' => 0,
                    'consumed' => 0,
                ],
            ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/v1/internal-communication/articles?financer_id={$financer->id}");

        // Assert
        $response->assertJson([
            'meta' => [
                'ai_token_quota' => [
                    'division_id' => $financer->division_id,
                    'division_name' => $financer->division->name,
                    'total' => 0,
                    'consumed' => 0,
                    'remaining' => 0,
                    'percentage_used' => 0,
                ],
            ],
        ]);
    }
}
