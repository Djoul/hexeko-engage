<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Financer;
use App\Models\MobileVersionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('mobile')]
#[Group('version')]
class MobileVersionLogTest extends TestCase
{
    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $log = new MobileVersionLog;

        $this->assertFalse($log->getIncrementing());
        $this->assertEquals('string', $log->getKeyType());
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $log = new MobileVersionLog;

        $this->assertEquals('mobile_version_logs', $log->getTable());
    }

    #[Test]
    public function it_has_correct_casts(): void
    {
        $log = new MobileVersionLog;
        $casts = $log->getCasts();

        $this->assertArrayHasKey('id', $casts);
        $this->assertEquals('string', $casts['id']);

        $this->assertArrayHasKey('user_id', $casts);
        $this->assertEquals('string', $casts['user_id']);

        $this->assertArrayHasKey('financer_id', $casts);
        $this->assertEquals('string', $casts['financer_id']);

        $this->assertArrayHasKey('platform', $casts);
        $this->assertEquals('string', $casts['platform']);

        $this->assertArrayHasKey('version', $casts);
        $this->assertEquals('string', $casts['version']);

        $this->assertArrayHasKey('minimum_required_version', $casts);
        $this->assertEquals('string', $casts['minimum_required_version']);

        $this->assertArrayHasKey('should_update', $casts);
        $this->assertEquals('boolean', $casts['should_update']);

        $this->assertArrayHasKey('update_type', $casts);
        $this->assertEquals('string', $casts['update_type']);

        $this->assertArrayHasKey('ip_address', $casts);
        $this->assertEquals('string', $casts['ip_address']);

        $this->assertArrayHasKey('user_agent', $casts);
        $this->assertEquals('string', $casts['user_agent']);

        $this->assertArrayHasKey('metadata', $casts);
        $this->assertEquals('array', $casts['metadata']);
    }

    #[Test]
    public function it_belongs_to_user(): void
    {
        $log = new MobileVersionLog;
        $relation = $log->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(User::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_belongs_to_financer(): void
    {
        $log = new MobileVersionLog;
        $relation = $log->financer();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(Financer::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_can_create_log_with_minimal_data(): void
    {
        // Arrange
        $data = [
            'platform' => 'ios',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
            'update_type' => null,
        ];

        // Act
        $log = MobileVersionLog::create($data);

        // Assert
        $this->assertInstanceOf(MobileVersionLog::class, $log);
        $this->assertTrue(Str::isUuid($log->id));
        $this->assertEquals('ios', $log->platform);
        $this->assertEquals('1.0.0', $log->version);
        $this->assertEquals('1.0.0', $log->minimum_required_version);
        $this->assertFalse($log->should_update);
        $this->assertNull($log->update_type);
        $this->assertDatabaseHas('mobile_version_logs', [
            'id' => $log->id,
            'platform' => 'ios',
            'version' => '1.0.0',
        ]);
    }

    #[Test]
    public function it_can_create_log_with_user_and_financer(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $data = [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'platform' => 'android',
            'version' => '2.0.0',
            'minimum_required_version' => '1.5.0',
            'should_update' => false,
            'update_type' => null,
        ];

        // Act
        $log = MobileVersionLog::create($data);

        // Assert
        $this->assertInstanceOf(MobileVersionLog::class, $log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertEquals($financer->id, $log->financer_id);
        $this->assertDatabaseHas('mobile_version_logs', [
            'id' => $log->id,
            'user_id' => $user->id,
            'financer_id' => $financer->id,
        ]);
    }

    #[Test]
    public function it_can_create_log_with_update_required(): void
    {
        // Arrange
        $data = [
            'platform' => 'ios',
            'version' => '0.9.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => true,
            'update_type' => 'store_required',
        ];

        // Act
        $log = MobileVersionLog::create($data);

        // Assert
        $this->assertTrue($log->should_update);
        $this->assertEquals('store_required', $log->update_type);
        $this->assertDatabaseHas('mobile_version_logs', [
            'id' => $log->id,
            'should_update' => true,
            'update_type' => 'store_required',
        ]);
    }

    #[Test]
    public function it_stores_ip_address_and_user_agent(): void
    {
        // Arrange
        $data = [
            'platform' => 'android',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)',
        ];

        // Act
        $log = MobileVersionLog::create($data);

        // Assert
        $this->assertEquals('192.168.1.1', $log->ip_address);
        $this->assertEquals('Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', $log->user_agent);
        $this->assertDatabaseHas('mobile_version_logs', [
            'id' => $log->id,
            'ip_address' => '192.168.1.1',
        ]);
    }

    #[Test]
    public function it_casts_metadata_to_array(): void
    {
        // Arrange
        $metadata = [
            'device' => [
                'type' => 'mobile',
                'model' => 'iPhone 13',
                'os_version' => '15.0',
            ],
            'app_info' => [
                'build_number' => '123',
                'environment' => 'production',
            ],
        ];

        $data = [
            'platform' => 'ios',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
            'metadata' => $metadata,
        ];

        // Act
        $log = MobileVersionLog::create($data);
        $log->refresh();

        // Assert
        $this->assertIsArray($log->metadata);
        $this->assertEquals($metadata, $log->metadata);
        $this->assertEquals('mobile', $log->metadata['device']['type']);
        $this->assertEquals('iPhone 13', $log->metadata['device']['model']);
        $this->assertEquals('production', $log->metadata['app_info']['environment']);
    }

    #[Test]
    public function it_can_store_null_metadata(): void
    {
        // Arrange
        $data = [
            'platform' => 'ios',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
            'metadata' => null,
        ];

        // Act
        $log = MobileVersionLog::create($data);

        // Assert
        $this->assertNull($log->metadata);
    }

    #[Test]
    public function it_can_retrieve_user_relationship(): void
    {
        // Arrange
        $user = ModelFactory::createUser();
        $log = MobileVersionLog::create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
        ]);

        // Act
        $retrievedUser = $log->user;

        // Assert
        $this->assertInstanceOf(User::class, $retrievedUser);
        $this->assertEquals($user->id, $retrievedUser->id);
        $this->assertEquals($user->email, $retrievedUser->email);
    }

    #[Test]
    public function it_can_retrieve_financer_relationship(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $log = MobileVersionLog::create([
            'financer_id' => $financer->id,
            'platform' => 'android',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
        ]);

        // Act
        $retrievedFinancer = $log->financer;

        // Assert
        $this->assertInstanceOf(Financer::class, $retrievedFinancer);
        $this->assertEquals($financer->id, $retrievedFinancer->id);
        $this->assertEquals($financer->name, $retrievedFinancer->name);
    }

    #[Test]
    public function it_can_use_factory_model(): void
    {
        // Act
        $log = MobileVersionLog::factory()->create();

        // Assert
        $this->assertInstanceOf(MobileVersionLog::class, $log);
        $this->assertTrue(Str::isUuid($log->id));
        $this->assertNotNull($log->platform);
        $this->assertNotNull($log->version);
        $this->assertNotNull($log->minimum_required_version);
        $this->assertIsBool($log->should_update);
        $this->assertDatabaseHas('mobile_version_logs', [
            'id' => $log->id,
        ]);
    }

    #[Test]
    public function it_can_create_multiple_logs_for_same_user(): void
    {
        // Arrange
        $user = ModelFactory::createUser();

        // Act
        $log1 = MobileVersionLog::create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
        ]);

        $log2 = MobileVersionLog::create([
            'user_id' => $user->id,
            'platform' => 'ios',
            'version' => '1.1.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
        ]);

        // Assert
        $this->assertNotEquals($log1->id, $log2->id);
        $this->assertEquals($user->id, $log1->user_id);
        $this->assertEquals($user->id, $log2->user_id);
        $this->assertEquals(2, MobileVersionLog::where('user_id', $user->id)->count());
    }

    #[Test]
    public function it_stores_different_platforms(): void
    {
        // Arrange & Act
        $iosLog = MobileVersionLog::create([
            'platform' => 'ios',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
        ]);

        $androidLog = MobileVersionLog::create([
            'platform' => 'android',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
        ]);

        // Assert
        $this->assertEquals('ios', $iosLog->platform);
        $this->assertEquals('android', $androidLog->platform);
        $this->assertDatabaseHas('mobile_version_logs', ['platform' => 'ios']);
        $this->assertDatabaseHas('mobile_version_logs', ['platform' => 'android']);
    }

    #[Test]
    public function it_has_timestamps(): void
    {
        // Arrange & Act
        $log = MobileVersionLog::create([
            'platform' => 'ios',
            'version' => '1.0.0',
            'minimum_required_version' => '1.0.0',
            'should_update' => false,
        ]);

        // Assert
        $this->assertNotNull($log->created_at);
        $this->assertNotNull($log->updated_at);
        $this->assertInstanceOf(Carbon::class, $log->created_at);
        $this->assertInstanceOf(Carbon::class, $log->updated_at);
    }

    #[Test]
    public function it_has_correct_log_name(): void
    {
        // Act
        $logName = MobileVersionLog::logName();

        // Assert
        $this->assertEquals('mobile_version_log', $logName);
    }
}
