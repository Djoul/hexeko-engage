<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Events\Testing\TestChannelBroadcast;

echo "Testing Reverb broadcast...\n\n";

// Test 1: Public channel
echo "1. Testing public channel 'notifications'...\n";
$event1 = new TestChannelBroadcast('notifications', 'test.public', [
    'message' => 'Hello from public channel',
    'timestamp' => now()->toISOString(),
]);
broadcast($event1);
echo "✅ Broadcast dispatched to queue\n\n";

// Test 2: Using PublicNotificationEvent
echo "2. Testing PublicNotificationEvent...\n";
$event2 = new \App\Events\Testing\PublicNotificationEvent('test', [
    'message' => 'Hello from PublicNotificationEvent',
    'source' => 'test-script',
]);
broadcast($event2);
echo "✅ Broadcast dispatched to queue\n\n";

// Process queue
echo "3. Processing queue...\n";
\Illuminate\Support\Facades\Artisan::call('queue:work', [
    '--stop-when-empty' => true,
    '--tries' => 1,
]);
echo "✅ Queue processed\n\n";

echo "Check your dashboard at http://localhost/reverb-test-dashboard.html\n";
echo "Make sure to:\n";
echo "- Set App Key to: qvou8nwiyg3h4rjbp3q7\n";
echo "- Set Channel to: notifications\n";
echo "- Click 'Se connecter'\n";
