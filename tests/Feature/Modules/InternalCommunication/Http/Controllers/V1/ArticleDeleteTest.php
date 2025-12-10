<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
#[Group('article')]
class ArticleDeleteTest extends ProtectedRouteTestCase
{
    protected string $route = 'articles.destroy';

    protected string $permission = 'delete_article';

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createAuthUser(
            role: RoleDefaults::FINANCER_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );
    }

    #[Test]
    public function it_can_delete_an_article(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        // Ensure Context is set for global scopes
        Context::add('financer_id', $financer->id);

        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson('/api/v1/internal-communication/articles/'.$article->id);

        // Assert
        $response->assertStatus(204);
        $this->assertSoftDeleted('int_communication_rh_articles', ['id' => $article->id]);
    }

    #[Test]
    public function it_returns_500_when_deleting_non_existent_article(): void
    {
        // Arrange

        $nonExistentId = Uuid::uuid4()->toString();

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson('/api/v1/internal-communication/articles/'.$nonExistentId);

        // Assert
        $response->assertStatus(404);
    }
}
