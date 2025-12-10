<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

echo "🚀 Test complet des canaux Reverb\n";
echo "=================================\n\n";

// Configuration
$processQueue = true; // Mettre à false pour tester manuellement avec make queue

// 1. TESTS CANAUX PUBLICS
echo "📢 TESTS CANAUX PUBLICS\n";
echo "-----------------------\n\n";

// Test 1.1: Message public simple
echo "1.1 Message public simple sur 'notifications':\n";
broadcast(new \App\Events\Testing\PublicMessageEvent(
    'Test Public',
    'Ceci est un message public de test',
    'success'
));
echo "✅ Event dispatché\n\n";

// Test 1.2: Statistiques publiques
echo "1.2 Statistiques publiques sur 'public-stats' et 'dashboard':\n";
broadcast(new \App\Events\Testing\PublicStatsEvent([
    'users_online' => rand(10, 100),
    'active_sessions' => rand(50, 200),
    'total_revenue' => rand(1000, 10000),
    'conversion_rate' => round(rand(10, 90) / 100, 2),
], 'hourly'));
echo "✅ Event dispatché\n\n";

// Test 1.3: Notification Apideck publique
echo "1.3 Notification Apideck publique:\n";
broadcast(new \App\Events\Testing\PublicApideckSyncCompleted('123', [
    'created' => 5,
    'updated' => 3,
    'failed' => 0,
]));
echo "✅ Event dispatché\n\n";

// 2. TESTS CANAUX PRIVÉS
echo "\n🔒 TESTS CANAUX PRIVÉS\n";
echo "-----------------------\n";
echo "⚠️  Note: Les canaux privés nécessitent une authentification côté client\n\n";

// Créer un utilisateur de test
$user = User::first();
if (! $user) {
    echo "❌ Aucun utilisateur trouvé. Création d'un utilisateur de test...\n";
    $user = User::factory()->create([
        'email' => 'test@reverb.local',
        'name' => 'Test Reverb User',
    ]);
}
echo "👤 Utilisateur de test: {$user->email} (ID: {$user->id})\n\n";

// Test 2.1: Notification utilisateur privée
echo "2.1 Notification privée pour user.{$user->id}:\n";
broadcast(new \App\Events\Testing\PrivateUserNotification(
    $user,
    'Notification privée',
    'Ceci est une notification privée pour vous',
    'warning',
    [
        ['label' => 'Voir', 'url' => '/notifications'],
        ['label' => 'Ignorer', 'action' => 'dismiss'],
    ]
));
echo "✅ Event dispatché\n\n";

// Test 2.2: Mise à jour d'équipe
$team = Team::first();
if ($team) {
    echo "2.2 Mise à jour privée pour team.{$team->id}:\n";
    broadcast(new \App\Events\Testing\PrivateTeamUpdate(
        $team,
        'members_updated',
        [
            'added_members' => 2,
            'removed_members' => 1,
            'total_members' => $team->users()->count(),
        ]
    ));
    echo "✅ Event dispatché\n\n";
}

// Test 2.3: Activité financer
echo "2.3 Activité financer privée pour financer.123:\n";
broadcast(new \App\Events\Testing\PrivateFinancerActivity(
    '123',
    'credit_updated',
    [
        'previous_balance' => 1000,
        'new_balance' => 1500,
        'transaction_type' => 'deposit',
        'amount' => 500,
    ],
    $user->id
));
echo "✅ Event dispatché\n\n";

// 3. TRAITEMENT DE LA QUEUE
if ($processQueue) {
    echo "\n⚙️  TRAITEMENT DE LA QUEUE\n";
    echo "-------------------------\n";
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--tries' => 1,
    ]);
    echo "✅ Queue traitée\n\n";
}

// 4. INSTRUCTIONS DE TEST
echo "\n📋 INSTRUCTIONS DE TEST\n";
echo "======================\n\n";

echo "1. POUR TESTER LES CANAUX PUBLICS:\n";
echo "   - Ouvrez http://localhost:1310/reverb-test-dashboard.html\n";
echo "   - App Key: qvou8nwiyg3h4rjbp3q7\n";
echo "   - Testez ces canaux:\n";
echo "     • notifications\n";
echo "     • public-messages\n";
echo "     • public-stats\n";
echo "     • dashboard\n";
echo "     • apideck-sync\n\n";

echo "2. POUR TESTER LES CANAUX PRIVÉS:\n";
echo "   - Nécessite une authentification JWT\n";
echo "   - User ID de test: {$user->id}\n";
echo "   - Email: {$user->email}\n";
echo "   - Canaux privés à tester:\n";
echo "     • private-user.{$user->id}\n";
echo "     • private-App.Models.User.{$user->id}\n";
if ($team) {
    echo "     • private-team.{$team->id}\n";
}
echo "     • private-financer.123\n\n";

echo "3. COMMANDES UTILES:\n";
echo "   # Lancer la queue manuellement\n";
echo "   make queue\n\n";
echo "   # Tester un event spécifique\n";
echo "   docker compose exec app_engage php artisan tinker\n";
echo "   >>> broadcast(new \\App\\Events\\Testing\\PublicMessageEvent('Test', 'Message'))\n\n";

echo "4. DÉBUGGAGE:\n";
echo "   # Voir les logs Reverb\n";
echo "   docker compose logs -f reverb_engage\n\n";
echo "   # Vérifier la queue\n";
echo "   docker compose exec app_engage php artisan queue:listen -vvv\n";
