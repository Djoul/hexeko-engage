<?php

namespace Tests\Feature\Http\Controllers\V1\Translation\TranslationController;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['translation_keys'], scope: 'test')]

#[Group('translation')]
#[Group('translation-crud')]
class TranslationCrudTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_can_list_translation_keys(): void
    {
        // Get initial count
        $initialCount = TranslationKey::count();

        // Create 3 new keys with interface_origin
        TranslationKey::factory()->count(3)->create(['interface_origin' => 'web_financer']);

        $response = $this->withHeaders(['x-origin-interface' => 'web_financer'])
            ->getJson('/api/v1/translations');
        $response->assertOk();
        $response->assertJsonStructure(['data']);

        // Assert we have 3 more than initial
        $this->assertCount($initialCount + 3, $response->json('data'));
    }

    #[Test]
    public function it_can_create_a_translation_key_with_values(): void
    {
        $payload = [
            'key' => 'greeting',
            'group' => 'messages',
            'values' => [
                'fr-FR' => 'Bonjour',
                'en-GB' => 'Hello',
            ],
        ];
        $response = $this->postJson('/api/v1/translations', $payload);
        $response->assertCreated();
        $this->assertDatabaseHas('translation_keys', [
            'key' => 'greeting',
            'group' => 'messages',
        ]);
        $this->assertDatabaseHas('translation_values', [
            'locale' => 'fr-FR',
            'value' => 'Bonjour',
        ]);
        $this->assertDatabaseHas('translation_values', [
            'locale' => 'en-GB',
            'value' => 'Hello',
        ]);
    }

    #[Test]
    public function it_can_show_a_translation_key(): void
    {
        $key = TranslationKey::factory()->create(['key' => 'logout', 'group' => 'auth']);
        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'en-GB',
            'value' => 'Logout',
        ]);
        $response = $this->getJson('/api/v1/translations/'.$key->id);
        $response->assertOk();
        $response->assertJsonFragment(['key' => 'logout']);
    }

    #[Test]
    public function it_can_update_a_translation_key_and_values(): void
    {
        $key = TranslationKey::factory()->create(['key' => 'bye', 'group' => 'messages']);
        $payload = [
            'key' => 'bye_updated',
            'group' => 'messages',
            'values' => [
                'fr-FR' => 'Au revoir',
                'en-GB' => 'Goodbye',
            ],
        ];
        $response = $this->putJson('/api/v1/translations/'.$key->id, $payload);
        $response->assertOk();

        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $key->id,
            'locale' => 'fr-FR',
            'value' => 'Au revoir',
        ]);
        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $key->id,
            'locale' => 'en-GB',
            'value' => 'Goodbye',
        ]);
    }

    #[Test]
    public function it_can_delete_a_translation_key_and_its_values(): void
    {
        $key = TranslationKey::factory()->create(['key' => 'to_delete', 'group' => 'misc']);
        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'fr-FR',
            'value' => 'Supprimer',
        ]);
        $response = $this->deleteJson('/api/v1/translations/'.$key->id);
        $response->assertNoContent();
        $this->assertDatabaseMissing('translation_keys', ['id' => $key->id]);
        $this->assertDatabaseMissing('translation_values', ['translation_key_id' => $key->id]);
    }
}
