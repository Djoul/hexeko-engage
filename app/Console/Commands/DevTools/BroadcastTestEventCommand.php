<?php

namespace App\Console\Commands\DevTools;

use App\Events\Testing\TestChannelBroadcast;
use App\Models\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class BroadcastTestEventCommand extends Command
{
    protected $signature = 'broadcast:test-event
                           {event : The event class or "list" to see available events}
                           {user? : The user ID to broadcast to (deprecated, use --channel)}
                           {--channel= : The channel to broadcast to (e.g., private-user.123, public-notifications)}
                           {--force-channel : Force broadcasting to the specified channel, overriding event defaults}
                           {--data=* : Additional data to pass to the event (key:value format)}';

    protected $description = 'Broadcast existing events to specific channels for testing';

    public function handle(): int
    {
        $eventType = $this->argument('event');

        // List available events
        if ($eventType === 'list') {
            $this->listAvailableEvents();

            return self::SUCCESS;
        }

        $channel = $this->option('channel');
        $userId = $this->argument('user');
        $forceChannel = $this->option('force-channel');
        $additionalData = $this->parseAdditionalData();

        // Validate channel format if provided
        if ($channel && ! $forceChannel && ! $this->isValidChannelFormat($channel)) {
            $this->error('Invalid channel name. Channels must start with \'private-\' or \'public-\'. Use --force-channel to override.');

            return self::FAILURE;
        }

        // Handle backwards compatibility
        if (! $channel && $userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found.");

                return self::FAILURE;
            }
            $this->info("Broadcasting event '{$eventType}' to user {$userId}...");
        } elseif ($channel) {
            $suffix = $forceChannel ? ' (forced)' : '';
            $this->info("Broadcasting event '{$eventType}' to channel '{$channel}'{$suffix}...");
        } else {
            $this->info("Broadcasting event '{$eventType}' to default channels...");
        }

        try {
            $eventClass = $this->getEventClass($eventType);

            if (! class_exists($eventClass)) {
                $this->error("Event class '{$eventClass}' does not exist.");
                $this->info('Available events:');
                $this->listAvailableEvents();

                return self::FAILURE;
            }

            // For forced channel broadcasting, create TestChannelBroadcast directly
            if ($channel && $forceChannel) {
                $this->broadcastToForcedChannel($eventClass, $channel, $additionalData);
                $this->info('✅ Event broadcasted successfully!');
                $this->displayEventDetails($eventClass, $channel, $additionalData, $userId);
            } else {
                // Create and broadcast the actual event
                $event = $this->createEventWithChannel($eventClass, $additionalData, $userId);

                if ($event) {
                    broadcast($event)->toOthers();
                    $this->info('✅ Event broadcasted successfully!');
                    $this->displayEventDetails($eventClass, $channel, $additionalData, $userId);
                } else {
                    $this->error('Failed to create event instance.');

                    return self::FAILURE;
                }
            }

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Error broadcasting event: '.$e->getMessage());
            Log::error('Broadcast test event failed', [
                'event' => $eventType,
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAdditionalData(): array
    {
        $data = [];
        $options = $this->option('data') ?: [];

        foreach ($options as $option) {
            if (is_string($option) && str_contains($option, ':')) {
                [$key, $value] = explode(':', $option, 2);
                $data[$key] = $this->castValue($value);
            }
        }

        return $data;
    }

    private function castValue(string $value): mixed
    {
        // Try to cast to appropriate type
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private function getEventClass(string $eventType): string
    {
        // If already a full class name, return it
        if (str_contains($eventType, '\\')) {
            return $eventType;
        }

        // Otherwise, prepend namespace
        return "App\\Events\\{$eventType}";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createEventWithChannel(string $eventClass, array $data, ?string $userId = null): mixed
    {
        // Reflect on the event class to understand its constructor
        /** @var class-string $eventClass */
        $reflection = new ReflectionClass($eventClass);
        $constructor = $reflection->getConstructor();

        if (! $constructor) {
            // No constructor, try creating instance
            return new $eventClass;
        }

        // Get constructor parameters
        $params = $constructor->getParameters();
        $args = [];

        try {
            // Try to match data to constructor parameters
            foreach ($params as $param) {
                $paramName = $param->getName();

                if (array_key_exists($paramName, $data)) {
                    $value = $data[$paramName];
                    $args[] = is_string($value) ? $this->parseValue($value) : $value;
                } elseif ($paramName === 'user' && $userId) {
                    $args[] = User::find($userId);
                } elseif ($paramName === 'userId' && $userId) {
                    $args[] = $userId;
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    // Try to create mock data based on type
                    $args[] = $this->createMockDataForType($param);
                }
            }

            $event = $reflection->newInstanceArgs($args);
        } catch (Exception $e) {
            $this->warn("Cannot auto-create event '{$eventClass}'. Please provide required data:");
            foreach ($params as $param) {
                $this->line("  - {$param->getName()}: {$param->getType()}");
            }
            throw $e;
        }

        return $event;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function broadcastToForcedChannel(string $eventClass, string $channel, array $data): void
    {
        // Use the event class name as the broadcast event name
        $broadcastAs = class_basename($eventClass);

        // Use TestChannelBroadcast to force broadcasting to specific channel
        $testEvent = new TestChannelBroadcast(
            $channel,
            $broadcastAs,
            $data
        );

        broadcast($testEvent)->toOthers();
    }

    private function createMockDataForType(ReflectionParameter $param): mixed
    {
        $type = $param->getType();
        if (! $type instanceof ReflectionNamedType) {
            return null;
        }

        $typeName = $type->getName();

        // Handle built-in types
        $value = match ($typeName) {
            'string' => 'test-string',
            'int' => 1,
            'float' => 1.0,
            'bool' => true,
            'array' => [],
            default => null
        };

        if ($value !== null) {
            return $value;
        }

        // Handle model types
        if (class_exists($typeName) && is_subclass_of($typeName, Model::class)) {
            // For models, try to create a factory instance
            /** @var class-string<Model> $typeName */
            $factoryClass = str_replace('App\\Models\\', 'Database\\Factories\\', $typeName).'Factory';
            if (class_exists($factoryClass)) {
                try {
                    // Use reflection to call factory method safely
                    $modelReflection = new ReflectionClass($typeName);
                    if ($modelReflection->hasMethod('factory')) {
                        $factoryMethod = $modelReflection->getMethod('factory');
                        if ($factoryMethod->isStatic()) {
                            /** @var Factory<Model> $factory */
                            $factory = $factoryMethod->invoke(null);

                            return $factory->create();
                        }
                    }
                } catch (Exception $e) {
                    // Factory not available, continue to null handling
                }
            }
        }

        // Handle nullable types
        if ($type->allowsNull()) {
            return null;
        }

        throw new RuntimeException("Cannot create mock data for type: {$typeName}");
    }

    private function parseValue(string $value): mixed
    {
        // Try to parse JSON
        if ((str_starts_with($value, '{') && str_ends_with($value, '}')) ||
            (str_starts_with($value, '[') && str_ends_with($value, ']'))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Parse booleans
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }

        // Parse numbers
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private function listAvailableEvents(): void
    {
        $this->info('Available broadcast events:');
        $this->line('');

        // List events in the Events directory
        $eventsPath = app_path('Events');
        $events = $this->findBroadcastableEvents($eventsPath, 'App\\Events');

        // List voucher events
        $voucherEventsPath = app_path('Events/Vouchers');
        if (is_dir($voucherEventsPath)) {
            $voucherEvents = $this->findBroadcastableEvents($voucherEventsPath, 'App\\Events\\Vouchers');
            $events = array_merge($events, $voucherEvents);
        }

        // List integration events
        $integrationsPath = app_path('Integrations');
        if (is_dir($integrationsPath)) {
            foreach (scandir($integrationsPath) as $integration) {
                if ($integration === '.') {
                    continue;
                }
                if ($integration === '..') {
                    continue;
                }
                $integrationEventsPath = $integrationsPath.'/'.$integration.'/Events';
                if (is_dir($integrationEventsPath)) {
                    $integrationEvents = $this->findBroadcastableEvents(
                        $integrationEventsPath,
                        "App\\Integrations\\{$integration}\\Events"
                    );
                    $events = array_merge($events, $integrationEvents);
                }
            }
        }

        // Display events
        foreach ($events as $eventClass => $description) {
            $this->line("  - <info>{$eventClass}</info>");
            if ($description) {
                $this->line("    {$description}");
            }
        }

        $this->line('');
        $this->info('Usage examples:');
        $this->line('  php artisan broadcast:test-event "App\\Events\\ApideckSyncCompleted" --data="financer_id:123"');
        $this->line('  php artisan broadcast:test-event "App\\Events\\InvitationCreated" --channel="public-notifications" --force-channel');
    }

    /**
     * @return array<string, string>
     */
    private function findBroadcastableEvents(string $path, string $namespace): array
    {
        $events = [];

        if (! is_dir($path)) {
            return $events;
        }

        foreach (scandir($path) as $file) {
            if (! str_ends_with($file, '.php')) {
                continue;
            }

            $className = $namespace.'\\'.str_replace('.php', '', $file);

            try {
                if (! class_exists($className)) {
                    continue;
                }
                /** @var class-string $className */
                $reflection = new ReflectionClass($className);
                if ($reflection->implementsInterface(ShouldBroadcast::class)) {
                    $description = $this->getEventDescription($reflection);
                    $events[$className] = $description;
                }
            } catch (Exception $e) {
                // Skip if class cannot be reflected
            }
        }

        return $events;
    }

    /**
     * @param  ReflectionClass<object>  $reflection
     */
    private function getEventDescription(ReflectionClass $reflection): string
    {
        $description = '';

        // Get constructor parameters
        $constructor = $reflection->getConstructor();
        if ($constructor) {
            $params = $constructor->getParameters();
            if (count($params) > 0) {
                $paramList = [];
                foreach ($params as $param) {
                    $type = $param->getType();
                    $typeName = 'mixed';
                    if ($type instanceof ReflectionNamedType) {
                        $typeName = $type->getName();
                    }
                    $paramList[] = $param->getName().':'.basename(str_replace('\\', '/', $typeName));
                }
                $description = 'Parameters: '.implode(', ', $paramList);
            }
        }

        return $description;
    }

    private function isValidChannelFormat(string $channel): bool
    {
        return str_starts_with($channel, 'private-') || str_starts_with($channel, 'public-');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function displayEventDetails(string $eventClass, ?string $channel, array $data, ?string $userId): void
    {
        $this->info('Event details:');
        $details = [
            ['Event Class', $eventClass],
        ];

        if (! in_array($channel, [null, '', '0'], true)) {
            $details[] = ['Channel', $channel];
        } elseif (! in_array($userId, [null, '', '0'], true)) {
            $user = User::find($userId);
            $details[] = ['User ID', $userId];
            if ($user) {
                $details[] = ['User Email', $user->email];
            }
        }

        if ($data !== []) {
            $details[] = ['Data', json_encode($data)];
        }

        $this->table(['Key', 'Value'], $details);
    }
}
