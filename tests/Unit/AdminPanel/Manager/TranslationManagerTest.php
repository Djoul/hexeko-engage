<?php

declare(strict_types=1);

namespace Tests\Unit\AdminPanel\Manager;

use App\Livewire\AdminPanel\Manager\Translation\TranslationExport;
use App\Livewire\AdminPanel\Manager\Translation\TranslationManager;
use App\Models\Role;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('admin-panel')]
class TranslationManagerTest extends TestCase
{
    use DatabaseTransactions;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user with GOD role
        $this->adminUser = ModelFactory::createUser([
            'email' => 'admin@test.com',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);

        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }

        $this->adminUser->setRelation('currentTeam', $team);
        $this->adminUser->assignRole('GOD');
    }

    #[Test]
    public function it_loads_translation_manager_component(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(TranslationManager::class)
            ->assertViewIs('livewire.admin-panel.manager.translation.manager')
            ->assertSet('selectedInterface', 'web_financer');
    }

    #[Test]
    public function it_displays_translation_keys(): void
    {
        $this->actingAs($this->adminUser);

        // Create test translation data
        $key = TranslationKey::factory()->create([
            'key' => 'test.welcome',
            'interface_origin' => 'web_financer',
        ]);

        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'fr-FR',
            'value' => 'Bienvenue',
        ]);

        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'pt-PT',
            'value' => 'Bem-vindo',
        ]);

        Livewire::test(TranslationManager::class)
            ->assertSee('test.welcome')
            ->assertSee('Bienvenue')
            ->assertSee('Bem-vindo');
    }

    #[Test]
    public function it_filters_translations_by_interface(): void
    {
        $this->actingAs($this->adminUser);

        // Create test translations for different interfaces
        TranslationKey::factory()->create([
            'key' => 'web.title',
            'interface_origin' => 'web_financer',
        ]);

        TranslationKey::factory()->create([
            'key' => 'mobile.title',
            'interface_origin' => 'mobile',
        ]);

        Livewire::test(TranslationManager::class)
            ->set('selectedInterface', 'web_financer')
            ->assertSee('web.title')
            ->assertDontSee('mobile.title');
    }

    #[Test]
    public function it_searches_translations_by_key(): void
    {
        $this->actingAs($this->adminUser);

        // Create test translations with interface_origin
        TranslationKey::factory()->create([
            'key' => 'dashboard.title',
            'interface_origin' => 'web_financer',
        ]);

        TranslationKey::factory()->create([
            'key' => 'settings.label',
            'interface_origin' => 'web_financer',
        ]);

        Livewire::test(TranslationManager::class)
            ->set('search', 'dashboard')
            ->assertSee('dashboard.title')
            ->assertDontSee('settings.label');
    }

    #[Test]
    public function it_can_toggle_export_modal(): void
    {
        $this->actingAs($this->adminUser);

        // Test Export component separately
        $exportComponent = Livewire::test(TranslationExport::class);
        $exportComponent->assertSet('showExportModal', false)
            ->call('openModal')
            ->assertSet('showExportModal', true)
            ->call('closeModal')
            ->assertSet('showExportModal', false);
    }

    #[Test]
    public function it_can_create_new_translation_key(): void
    {
        $this->actingAs($this->adminUser);

        $component = Livewire::test(TranslationManager::class)
            ->set('newKey', 'test.new.key')
            ->set('newValues', ['en' => 'New Value', 'fr' => 'Nouvelle Valeur'])
            ->call('addKey');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('translation_keys', [
            'key' => 'new.key',
            'group' => 'test',
            'interface_origin' => 'web_financer',
        ]);
    }

    #[Test]
    public function it_validates_translation_key_format(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(TranslationManager::class)
            ->set('newKey', '')
            ->set('newValues', [])
            ->call('addKey')
            ->assertHasErrors(['newKey']);
    }

    #[Test]
    public function it_can_update_translation_value(): void
    {
        $this->actingAs($this->adminUser);

        $key = TranslationKey::factory()->create([
            'key' => 'test.update',
            'interface_origin' => 'web_financer',
        ]);

        $value = TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'en',
            'value' => 'Original',
        ]);

        Livewire::test(TranslationManager::class)
            ->set('editingValues', ['en' => 'Updated'])
            ->set('editingKey', $key)
            ->call('saveTranslations')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('translation_values', [
            'id' => $value->id,
            'value' => 'Updated',
        ]);
    }

    #[Test]
    public function it_can_delete_translation_key(): void
    {
        $this->actingAs($this->adminUser);

        // Create a key to delete
        $key = TranslationKey::factory()->create([
            'key' => 'test.delete',
            'interface_origin' => 'web_financer',
        ]);

        Livewire::test(TranslationManager::class)
            ->call('deleteKey', $key->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('translation_keys', ['id' => $key->id]);
    }
}
