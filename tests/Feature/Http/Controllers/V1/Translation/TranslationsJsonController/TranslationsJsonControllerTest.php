<?php

namespace Tests\Feature\Http\Controllers\V1\Translation\TranslationsJsonController;

use App\Models\TranslationKey;
use App\Models\TranslationValue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('translation')]
#[Group('translation-api')]
class TranslationsJsonControllerTest extends ProtectedRouteTestCase
{
    #[Test]
    public function all_locales_endpoint_returns_nested_json(): void
    {
        // Get initial counts to track changes
        $initialKeyCount = TranslationKey::count();
        $initialValueCount = TranslationValue::count();

        $key1 = TranslationKey::factory()->create([
            'key' => 'login',
            'group' => 'auth',
            'interface_origin' => 'web_financer',
        ]);
        $key2 = TranslationKey::factory()->create([
            'key' => 'deep.key',
            'group' => 'messages',
            'interface_origin' => 'web_financer',
        ]);

        TranslationValue::factory()->create([
            'translation_key_id' => $key1->id,
            'locale' => 'fr-FR',
            'value' => 'Connexion',
        ]);
        TranslationValue::factory()->create([
            'translation_key_id' => $key2->id,
            'locale' => 'fr-FR',
            'value' => 'Profondeur',
        ]);

        // Assert we created exactly 2 new records of each type
        $this->assertEquals($initialKeyCount + 2, TranslationKey::count());
        $this->assertEquals($initialValueCount + 2, TranslationValue::count());

        $response = $this->withHeaders(['x-origin-interface' => 'web_financer'])
            ->getJson('/api/v1/translations/json');
        $response->assertOk();
        $json = $response->json();
        $this->assertEquals('Connexion', $json['fr-FR']['auth']['login']);
        $this->assertEquals('Profondeur', $json['fr-FR']['messages']['deep']['key']);
    }

    #[Test]
    public function for_locale_endpoint_returns_only_requested_locale_and_nested_json(): void
    {
        $key = TranslationKey::factory()->create([
            'key' => 'logout',
            'group' => 'auth',
            'interface_origin' => 'web_financer',
        ]);
        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'en-GB',
            'value' => 'Logout',
        ]);
        $response = $this->withHeaders(['x-origin-interface' => 'web_financer'])
            ->getJson('/api/v1/translations/json/en-GB');
        $response->assertOk();
        $json = $response->json();
        $this->assertEquals('Logout', $json['auth']['logout']);
        $this->assertArrayNotHasKey('fr-FR', $json);
    }

    #[Test]
    public function deep_dot_notation_keys_are_nested_properly(): void
    {
        $key = TranslationKey::factory()->create([
            'key' => 'a.b.c.d.e',
            'group' => 'root',
            'interface_origin' => 'web_financer',
        ]);
        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'fr-FR',
            'value' => 'ProfondeurMax',
        ]);
        $response = $this->withHeaders(['x-origin-interface' => 'web_financer'])
            ->getJson('/api/v1/translations/json/fr-FR');
        $response->assertOk();
        $json = $response->json();
        $this->assertEquals('ProfondeurMax', $json['root']['a']['b']['c']['d']['e']);
    }
}
