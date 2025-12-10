<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Models\MobileVersionLog;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('mobile')]
#[Group('version')]
class MobileVersionControllerTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set minimum_required_version to 1.0.0 for both platforms to ensure tests are independent of config
        Config::set('version.minimum_required_version', [
            'ios' => '1.0.0',
            'android' => '1.0.0',
        ]);

        $this->auth = $this->createAuthUser(RoleDefaults::DIVISION_SUPER_ADMIN);
    }

    #[Test]
    public function it_checks_update_status_when_version_is_current(): void
    {
        // Arrange
        $payload = [
            'platform' => 'ios',
            'version' => '1.0.0',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'platform',
                    'version',
                    'minimum_required_version',
                    'should_update',
                    'update_type',
                ],
            ])
            ->assertJson([
                'data' => [
                    'platform' => 'ios',
                    'version' => '1.0.0',
                    'minimum_required_version' => '1.0.0',
                    'should_update' => false,
                    'update_type' => null,
                ],
            ]);
    }

    #[Test]
    public function it_checks_update_status_when_version_is_outdated(): void
    {
        // Arrange
        $payload = [
            'platform' => 'android',
            'version' => '0.9.0',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'platform' => 'android',
                    'version' => '0.9.0',
                    'minimum_required_version' => '1.0.0',
                    'should_update' => true,
                    'update_type' => 'store_required',
                ],
            ]);
    }

    #[Test]
    public function it_creates_log_entry_when_checking_version(): void
    {
        // Arrange
        $payload = [
            'platform' => 'ios',
            'version' => '1.0.0',
        ];

        $initialCount = MobileVersionLog::count();

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk();

        $this->assertEquals($initialCount + 1, MobileVersionLog::count());

        $log = MobileVersionLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('ios', $log->platform);
        $this->assertEquals('1.0.0', $log->version);
        $this->assertEquals('1.0.0', $log->minimum_required_version);
        $this->assertFalse($log->should_update);
        $this->assertNull($log->update_type);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
    }

    #[Test]
    public function it_accepts_optional_financer_id_and_user_id(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $payload = [
            'platform' => 'ios',
            'version' => '1.0.0',
            'financer_id' => $financer->id,
            'user_id' => $user->id,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk();

        $log = MobileVersionLog::latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals($financer->id, $log->financer_id);
        $this->assertEquals($user->id, $log->user_id);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform', 'version']);
    }

    #[Test]
    public function it_validates_platform_is_valid(): void
    {
        // Arrange
        $payload = [
            'platform' => 'windows',
            'version' => '1.0.0',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    #[Test]
    public function it_validates_financer_id_exists(): void
    {
        // Arrange
        $payload = [
            'platform' => 'ios',
            'version' => '1.0.0',
            'financer_id' => '00000000-0000-0000-0000-000000000000',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['financer_id']);
    }

    #[Test]
    public function it_validates_user_id_exists(): void
    {
        // Arrange
        $payload = [
            'platform' => 'ios',
            'version' => '1.0.0',
            'user_id' => '00000000-0000-0000-0000-000000000000',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    #[Test]
    public function it_handles_android_platform(): void
    {
        // Arrange
        $payload = [
            'platform' => 'android',
            'version' => '1.0.0',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'platform' => 'android',
                    'version' => '1.0.0',
                    'minimum_required_version' => '1.0.0',
                    'should_update' => false,
                    'update_type' => null,
                ],
            ]);
    }

    #[Test]
    public function it_compares_versions_correctly_for_minor_updates(): void
    {
        // Arrange - Version is slightly behind
        $payload = [
            'platform' => 'ios',
            'version' => '0.9.9',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'should_update' => true,
                    'update_type' => 'store_required',
                ],
            ]);
    }

    #[Test]
    public function it_compares_versions_correctly_for_major_updates(): void
    {
        // Arrange - Version is significantly behind
        $payload = [
            'platform' => 'ios',
            'version' => '0.5.0',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'should_update' => true,
                    'update_type' => 'store_required',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_versions_ahead_of_minimum(): void
    {
        // Arrange - Version is ahead of minimum
        $payload = [
            'platform' => 'ios',
            'version' => '2.0.0',
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/mobile-version/check', $payload);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'version' => '2.0.0',
                    'should_update' => false,
                    'update_type' => null,
                ],
            ]);
    }
}
