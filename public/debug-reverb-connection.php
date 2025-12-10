<?php

// require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Pusher\Pusher;

// Get configuration
$key = config('reverb.apps.apps.0.key');
$secret = config('reverb.apps.apps.0.secret');
$appId = config('reverb.apps.apps.0.app_id');
$host = config('reverb.apps.apps.0.options.host');
$port = config('reverb.apps.apps.0.options.port');

echo "Testing Reverb connection...\n";
echo "Host: $host:$port\n";
echo "App ID: $appId\n";
echo "Key: $key\n\n";

// Create Pusher client with Reverb configuration
$options = [
    'host' => $host,
    'port' => $port,
    'scheme' => 'http',
    'encrypted' => false,
    'useTLS' => false,
];

try {
    $pusher = new Pusher(
        $key,
        $secret,
        $appId,
        $options
    );

    // Test sending a message directly
    $data = ['message' => 'Direct test from PHP'];
    $result = $pusher->trigger('notifications', 'test-event', $data);

    echo 'Direct Pusher result: ';
    var_dump($result);

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
