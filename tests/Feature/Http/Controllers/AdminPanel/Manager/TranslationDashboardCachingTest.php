<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel\Manager;

use App\Models\Role;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('admin-panel')]
class TranslationDashboardCachingTest extends ProtectedRouteTestCase
{
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->withoutMiddleware(['admin.cognito']);

        $this->adminUser = ModelFactory::createUser([
            'email' => 'admin-dashboard@test.com',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Dashboard Team']);

        $this->adminUser->forceFill(['team_id' => $team->id])->save();
        setPermissionsTeamId($team->id);

        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }

        $this->adminUser->setRelation('currentTeam', $team);
        $this->adminUser->assignRole('GOD');
    }

    #[Test]
    public function it_caches_translation_dashboard_sections_for_six_hours(): void
    {
        Date::setTestNow(Date::create(2025, 10, 4, 12));

        $this->seedInitialTranslations();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.manager.translations.index'));

        $response->assertOk();
        $response->assertSee('data-testid="stats-card-count" data-value="1"', false);

        $firstCachedAt = $this->extractDataAttribute($response->getContent(), 'stats-last-updated');
        $this->assertNotNull($firstCachedAt);

        TranslationKey::factory()->create([
            'key' => 'manager.cached.test',
            'interface_origin' => 'web_financer',
        ]);

        Date::setTestNow(Date::now()->addHour());

        $cachedResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.manager.translations.index'));

        $cachedResponse->assertOk();
        $cachedResponse->assertSee('data-testid="stats-card-count" data-value="1"', false);
        $this->assertSame(
            $firstCachedAt,
            $this->extractDataAttribute($cachedResponse->getContent(), 'stats-last-updated')
        );

        Date::setTestNow();
    }

    #[Test]
    public function it_refreshes_requested_section_and_updates_cache_timestamp(): void
    {
        Date::setTestNow(Date::create(2025, 10, 4, 9));

        $this->seedInitialTranslations();

        $initialResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.manager.translations.index'));

        $initialResponse->assertOk();
        $initialTimestamp = $this->extractDataAttribute($initialResponse->getContent(), 'stats-last-updated');
        $this->assertNotNull($initialTimestamp);

        TranslationKey::factory()->create([
            'key' => 'manager.refresh.test',
            'interface_origin' => 'web_financer',
        ]);

        Date::setTestNow(Date::now()->addHours(2));

        $this->actingAs($this->adminUser)
            ->post(route('admin.manager.translations.refresh'), ['section' => 'stats'])
            ->assertRedirect(route('admin.manager.translations.index'));

        $updatedResponse = $this->actingAs($this->adminUser)
            ->get(route('admin.manager.translations.index'));

        $updatedResponse->assertOk();
        $updatedResponse->assertSee('data-testid="stats-card-count" data-value="2"', false);
        $this->assertNotSame(
            $initialTimestamp,
            $this->extractDataAttribute($updatedResponse->getContent(), 'stats-last-updated')
        );

        Date::setTestNow();
    }

    #[Test]
    public function each_dashboard_card_displays_refresh_button_and_last_updated_timestamp(): void
    {
        Date::setTestNow(Date::create(2025, 10, 4, 15));

        $this->seedInitialTranslations();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.manager.translations.index'));

        $response->assertOk();

        $response->assertSee('data-testid="stats-refresh-button"', false);
        $response->assertSee('data-testid="coverage-refresh-button"', false);
        $response->assertSee('data-testid="recent-activity-refresh-button"', false);
        $response->assertSee('data-testid="missing-refresh-button"', false);

        $this->assertNotNull($this->extractDataAttribute($response->getContent(), 'stats-last-updated'));
        $this->assertNotNull($this->extractDataAttribute($response->getContent(), 'coverage-last-updated'));
        $this->assertNotNull($this->extractDataAttribute($response->getContent(), 'recent-activity-last-updated'));
        $this->assertNotNull($this->extractDataAttribute($response->getContent(), 'missing-last-updated'));

        Date::setTestNow();
    }

    private function seedInitialTranslations(): void
    {
        $key = TranslationKey::factory()->create([
            'key' => 'manager.test.key',
            'interface_origin' => 'web_financer',
        ]);

        TranslationValue::factory()->create([
            'translation_key_id' => $key->id,
            'locale' => 'en',
            'value' => 'Dashboard Value',
        ]);
    }

    private function extractDataAttribute(string $html, string $testId): ?string
    {
        $pattern = '/data-testid="'.preg_quote($testId, '/').'"[^>]*data-value="([^"]+)"/';

        if (preg_match($pattern, $html, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
