<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\Translation\TranslationController;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('translation')]
#[Group('translation-crud')]
class TranslationValueCrudTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_creates_a_translation_value(): void
    {
        $key = TranslationKey::factory()->create();
        $payload = [
            'value' => 'Send+',
            'locale' => 'en-GB',
        ];
        $response = $this->postJson("/api/v1/translations/{$key->id}/values", $payload);
        $response->assertCreated();
        $this->assertDatabaseHas('translation_values', [
            'translation_key_id' => $key->id,
            'value' => 'Send+',
            'locale' => 'en-GB',
        ]);
    }

    #[Test]
    public function it_updates_a_translation_value(): void
    {
        $key = TranslationKey::factory()->create();
        $value = TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'en-GB',
            'value' => 'Old value',
        ]);
        $payload = [
            'value' => 'Updated value',
            'locale' => 'en-GB',
        ];
        $response = $this->putJson("/api/v1/translations/{$key->id}/values", $payload);
        $response->assertOk();
        $this->assertDatabaseHas('translation_values', [
            'id' => $value->id,
            'translation_key_id' => $key->id,
            'value' => 'Updated value',
            'locale' => 'en-GB',
        ]);
    }
}
