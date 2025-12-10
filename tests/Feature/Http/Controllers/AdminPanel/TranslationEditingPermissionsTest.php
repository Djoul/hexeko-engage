<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\EnvironmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('translation')]
class TranslationEditingPermissionsTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected bool $checkAuth = false;

    #[Test]
    public function it_blocks_translation_creation_on_dev_environment(): void
    {
        $this->swapEnvironmentService('dev');

        $user = $this->createAuthUser();

        $response = $this->actingAs($user)->postJson('/api/v1/translations', [
            'key' => 'test.blocked.dev',
            'values' => ['fr' => 'Test'],
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment([
            'message' => 'Translation editing not allowed in this environment',
        ]);

        $this->assertDatabaseMissing('translation_keys', ['key' => 'test.blocked.dev']);
    }

    #[Test]
    public function it_blocks_translation_creation_on_production_environment(): void
    {
        $this->swapEnvironmentService('production');

        $user = $this->createAuthUser();

        $response = $this->actingAs($user)->postJson('/api/v1/translations', [
            'key' => 'test.blocked.prod',
            'values' => ['fr' => 'Test'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('translation_keys', ['key' => 'test.blocked.prod']);
    }

    #[Test]
    public function it_allows_translation_creation_on_staging_environment(): void
    {
        $this->swapEnvironmentService('staging');

        $user = $this->createAuthUser();

        $response = $this->actingAs($user)->postJson('/api/v1/translations', [
            'key' => 'test.allowed.staging',
            'values' => [
                'fr' => 'Test staging',
                'en' => 'Staging test',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('translation_keys', ['key' => 'test.allowed.staging']);
    }

    #[Test]
    public function it_allows_translation_creation_on_local_when_enabled(): void
    {
        Config::set('translations.allow_local_editing', true);
        $this->swapEnvironmentService('local');

        $user = $this->createAuthUser();

        $response = $this->actingAs($user)->postJson('/api/v1/translations', [
            'key' => 'test.allowed.local',
            'values' => ['fr' => 'Local'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('translation_keys', ['key' => 'test.allowed.local']);
    }

    #[Test]
    public function it_blocks_translation_update_on_dev_environment(): void
    {
        $this->swapEnvironmentService('dev');
        $user = $this->createAuthUser();
        $translationKey = TranslationKey::factory()->create(['key' => 'app.update']);
        TranslationValue::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'value' => 'Ancienne valeur',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/translations/{$translationKey->id}", [
            'values' => ['fr' => 'Nouvelle valeur'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'value' => 'Ancienne valeur',
        ]);
    }

    #[Test]
    public function it_allows_translation_update_on_staging_environment(): void
    {
        $this->swapEnvironmentService('staging');
        $user = $this->createAuthUser();
        $translationKey = TranslationKey::factory()->create(['key' => 'app.update.allowed']);
        TranslationValue::factory()->create([
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'value' => 'Ancienne valeur',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/translations/{$translationKey->id}", [
            'values' => ['fr' => 'Nouvelle valeur'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $translationKey->id,
            'locale' => 'fr',
            'value' => 'Nouvelle valeur',
        ]);
    }

    private function swapEnvironmentService(string $environment): void
    {
        $service = new EnvironmentService($environment);
        app()->instance(EnvironmentService::class, $service);
    }
}
