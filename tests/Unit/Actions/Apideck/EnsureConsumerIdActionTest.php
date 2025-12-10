<?php

namespace Tests\Unit\Actions\Apideck;

use App\Actions\Apideck\EnsureConsumerIdAction;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

#[Group('apideck')]
class EnsureConsumerIdActionTest extends TestCase
{
    private EnsureConsumerIdAction $action;

    private User $user;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new EnsureConsumerIdAction;
        $this->user = User::factory()->create([
            'email' => 'test-'.uniqid().'@example.com',
        ]);
        $this->financer = Financer::factory()->create([
            'name' => 'Test Financer',
            'external_id' => null,
        ]);
    }

    #[Test]
    public function it_returns_existing_consumer_id_when_present(): void
    {
        // Given: Un financeur avec un consumer_id existant
        $existingConsumerId = 'prod-existing-financer-12345678';
        $this->financer->update([
            'external_id' => [
                'sirh' => [
                    'consumer_id' => $existingConsumerId,
                    'created_at' => '2025-06-23T10:00:00Z',
                    'created_by' => $this->user->id,
                    'provider' => 'apideck',
                ],
            ],
        ]);

        // When: On exécute l'action
        $result = $this->action->execute($this->financer, $this->user);

        // Then: Le consumer_id existant est retourné sans modification
        $this->assertEquals($existingConsumerId, $result);

        // Vérifier qu'aucune modification n'a été faite
        $this->financer->refresh();
        $this->assertEquals($existingConsumerId, $this->financer->external_id['sirh']['consumer_id']);

        // Vérifier qu'aucune activité n'a été loggée
        $this->assertDatabaseMissing('activity_log', [
            'event' => 'consumer_id.created',
            'subject_id' => $this->financer->id,
        ]);
    }

    #[Test]
    public function it_generates_new_consumer_id_when_absent(): void
    {
        // Given: Un financeur sans consumer_id
        $this->assertNull($this->financer->external_id);

        // When: On exécute l'action
        $result = $this->action->execute($this->financer, $this->user);

        // Then: Un nouveau consumer_id est généré et sauvegardé
        $this->assertNotEmpty($result);

        $this->financer->refresh();
        $this->assertNotNull($this->financer->external_id);
        $this->assertEquals($result, $this->financer->external_id['sirh']['consumer_id']);
        $this->assertEquals($this->user->id, $this->financer->external_id['sirh']['created_by']);
        $this->assertEquals('apideck', $this->financer->external_id['sirh']['provider']);
        $this->assertNotNull($this->financer->external_id['sirh']['created_at']);
    }

    #[Test]
    public function it_follows_naming_convention_for_consumer_id(): void
    {
        // Given: Un financeur en environnement spécifique
        $this->financer->update(['name' => 'Acme Corporation']);

        // Set the environment through config instead of detectEnvironment
        Config::set('app.env', 'production');

        // When: Un consumer_id est généré
        $result = $this->action->execute($this->financer, $this->user);

        // Then: Il suit le format "production-{slug}-{uuid}"
        $this->assertMatchesRegularExpression(
            '/^production-acme-corporation-[a-f0-9]{8}$/',
            $result
        );
    }

    #[Test]
    public function it_uses_dev_prefix_in_non_production_environment(): void
    {
        // Given: Un financeur en environnement de développement
        Config::set('app.env', 'local');

        // When: Un consumer_id est généré
        $result = $this->action->execute($this->financer, $this->user);

        // Then: Il utilise le préfixe "local" (environment prefix based on APP_ENV)
        $this->assertStringStartsWith('local-', $result);
    }

    #[Test]
    public function it_logs_consumer_id_creation_activity(): void
    {
        if (! config('activitylog.enabled')) {
            $this->markTestSkipped('Activity logging is not enabled');
        }
        // Given: Un financeur sans consumer_id
        $this->assertNull($this->financer->external_id);

        // When: On génère un nouveau consumer_id
        $consumerId = $this->action->execute($this->financer, $this->user);

        // Then: L'activité est loggée avec Spatie Activity Log
        $activity = Activity::where('event', 'consumer_id.created')
            ->where('subject_id', $this->financer->id)
            ->where('subject_type', Financer::class)
            ->where('causer_id', $this->user->id)
            ->where('causer_type', User::class)
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Consumer ID automatiquement généré pour le financeur', $activity->description);
        $this->assertEquals($consumerId, $activity->properties['consumer_id']);
        $this->assertEquals('auto_generated', $activity->properties['method']);
    }

    #[Test]
    public function it_preserves_existing_external_id_data(): void
    {
        // Given: Un financeur avec d'autres données dans external_id
        $existingData = [
            'other_system' => [
                'id' => '123456',
                'data' => 'some data',
            ],
            'another_field' => 'value',
        ];
        $this->financer->update(['external_id' => $existingData]);

        // When: On ajoute un consumer_id
        $consumerId = $this->action->execute($this->financer, $this->user);

        // Then: Les autres données sont préservées
        $this->financer->refresh();
        $externalId = $this->financer->external_id;

        $this->assertEquals('123456', $externalId['other_system']['id']);
        $this->assertEquals('some data', $externalId['other_system']['data']);
        $this->assertEquals('value', $externalId['another_field']);
        $this->assertEquals($consumerId, $externalId['sirh']['consumer_id']);
    }

    #[Test]
    public function it_handles_financer_with_long_name(): void
    {
        // Given: Un financeur avec un nom très long
        $longName = 'This is a very long financer name that should be deleted to fit the consumer ID format properly';
        $this->financer->update(['name' => $longName]);

        // When: Un consumer_id est généré
        $result = $this->action->execute($this->financer, $this->user);

        // Then: Le slug est tronqué et le format est respecté
        $parts = explode('-', $result);
        $this->assertGreaterThanOrEqual(3, count($parts)); // At least env-slug-uuid

        // Vérifier que le slug total ne dépasse pas une longueur raisonnable
        // Extract just the slug part (everything between env and last 8 char uuid)
        $pattern = '/^(local|staging|production|testing)-(.+)-([a-f0-9]{8})$/';
        $this->assertMatchesRegularExpression($pattern, $result);

        preg_match($pattern, $result, $matches);
        $slugPart = $matches[2];
        $this->assertLessThanOrEqual(30, strlen($slugPart));
    }

    #[Test]
    public function it_handles_financer_with_special_characters(): void
    {
        // Given: Un financeur avec des caractères spéciaux
        $this->financer->update(['name' => 'Financeur & Associés (Test) #1']);

        // When: Un consumer_id est généré
        $result = $this->action->execute($this->financer, $this->user);

        // Then: Les caractères spéciaux sont correctement slugifiés
        $this->assertMatchesRegularExpression(
            '/^(local|staging|production|testing)-financeur-associes-test-1-[a-f0-9]{8}$/',
            $result
        );
    }

    #[Test]
    public function it_generates_unique_consumer_id_for_financers_with_same_name(): void
    {
        // Given: Deux financeurs avec le même nom
        $financer2 = Financer::factory()->create(['name' => $this->financer->name]);

        // When: On génère des consumer_ids pour chaque financeur
        $consumerId1 = $this->action->execute($this->financer, $this->user);
        $consumerId2 = $this->action->execute($financer2, $this->user);

        // Then: Les consumer_ids sont différents grâce au UUID
        $this->assertNotEquals($consumerId1, $consumerId2);

        // Mais ils ont le même préfixe
        $prefix1 = substr($consumerId1, 0, -9); // Enlever le UUID et le tiret
        $prefix2 = substr($consumerId2, 0, -9);
        $this->assertEquals($prefix1, $prefix2);
    }
}
