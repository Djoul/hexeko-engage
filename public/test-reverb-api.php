<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get Reverb configuration
$appId = config('reverb.apps.apps.0.app_id');
$key = config('reverb.apps.apps.0.key');
$secret = config('reverb.apps.apps.0.secret');
$host = config('reverb.apps.apps.0.options.host');
$port = config('reverb.apps.apps.0.options.port');
$scheme = config('reverb.apps.apps.0.options.scheme');

echo "Reverb Configuration:\n";
echo "App ID: $appId\n";
echo "Key: $key\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "Scheme: $scheme\n\n";

// Test broadcasting a message
$channel = 'public-notifications';
$event = 'test-message';
$data = ['message' => 'Hello from PHP script!', 'timestamp' => now()->toISOString()];

echo "Attempting to broadcast to channel: $channel\n";

try {
    broadcast(new \App\Events\Testing\PublicNotificationEvent('test', ['message' => 'Direct test from script']));
    echo "✅ Event dispatched successfully!\n";
} catch (\Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
}

// Check queue
echo "\nChecking queue for broadcast jobs...\n";
$jobs = \DB::table('jobs')->where('queue', 'default')->get();
echo 'Jobs in queue: '.$jobs->count()."\n";

if ($jobs->count() > 0) {
    echo "Latest job payload: \n";
    $latestJob = $jobs->last();
    $payload = json_decode($latestJob->payload, true);
    echo 'Job: '.$payload['displayName']."\n";
}
