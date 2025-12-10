<?php

// app/Console/Commands/CheckCognitoSync.php

namespace App\Console\Commands\Cognito;

use App\Models\User;
use Illuminate\Console\Command;

class CheckCognitoSync extends Command
{
    protected $signature = 'cognito:check-sync 
                            {--detailed : Affiche des détails sur chaque utilisateur}
                            {--export= : Exporte les résultats vers un fichier CSV}';

    protected $description = 'Vérifie l\'état de la synchronisation entre la base de données et Cognito';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Vérification de la synchronisation Cognito...');

        // Compter les utilisateurs
        $totalUsers = User::count();
        $usersWithCognitoId = User::whereNotNull('cognito_id')->count();
        $usersWithoutCognitoId = User::whereNull('cognito_id')->count();

        $this->displaySummary($totalUsers, $usersWithCognitoId, $usersWithoutCognitoId);

        if ($this->option('detailed')) {
            $this->displayDetailedInfo();
        }

        if ($exportFile = $this->option('export')) {
            $this->exportToCSV($exportFile);
        }

        return 0;
    }

    private function displaySummary(int $total, int $withCognito, int $withoutCognito): void
    {
        $this->table(
            ['État de synchronisation', 'Nombre', 'Pourcentage'],
            [
                ['Total utilisateurs', $total, '100%'],
                ['✅ Synchronisés (avec cognito_id)', $withCognito, round(($withCognito / $total) * 100, 1).'%'],
                ['❌ Non synchronisés (sans cognito_id)', $withoutCognito, round(($withoutCognito / $total) * 100, 1).'%'],
            ]
        );

        if ($withoutCognito > 0) {
            $this->newLine();
            $this->warn("⚠️  {$withoutCognito} utilisateurs ne sont pas synchronisés avec Cognito.");
            $this->line('   Utilisez: <fg=cyan>php artisan cognito:sync-all-users --only-missing</fg=cyan>');
        } else {
            $this->newLine();
            $this->info('🎉 Tous les utilisateurs sont synchronisés avec Cognito !');
        }
    }

    private function displayDetailedInfo(): void
    {
        $this->newLine();
        $this->info('📊 Informations détaillées...');

        // Utilisateurs sans cognito_id
        $usersWithoutCognito = User::whereNull('cognito_id')->limit(10)->get();
        if ($usersWithoutCognito->isNotEmpty()) {
            $this->newLine();
            $this->error('❌ Utilisateurs non synchronisés (premiers 10):');
            $data = $usersWithoutCognito->map(function ($user): array {
                return [
                    $user->id,
                    $user->email,
                    $user->created_at?->format('d/m/Y H:i') ?? 'N/A',
                ];
            })->toArray();

            $this->table(['ID', 'Email', 'Créé le'], $data);
        }

        // Statistiques par date de création
        $this->displayCreationStats();
    }

    private function displayCreationStats(): void
    {
        $this->newLine();
        $this->info('📅 Statistiques par période de création:');

        $stats = [
            'Aujourd\'hui' => User::whereDate('created_at', today())->count(),
            'Cette semaine' => User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'Ce mois' => User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'Cette année' => User::whereYear('created_at', now()->year)->count(),
        ];

        $tableData = [];
        foreach ($stats as $period => $count) {
            $withoutCognito = User::whereNull('cognito_id');

            if ($period === 'Aujourd\'hui') {
                $withoutCognito->whereDate('created_at', today());
            } elseif ($period === 'Cette semaine') {
                $withoutCognito->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($period === 'Ce mois') {
                $withoutCognito->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            } elseif ($period === 'Cette année') {
                $withoutCognito->whereYear('created_at', now()->year);
            }

            $notSynced = $withoutCognito->count();

            $tableData[] = [
                $period,
                $count,
                $count > 0 ? $count - $notSynced : 0,
                $notSynced,
            ];
        }

        $this->table(
            ['Période', 'Total', 'Synchronisés', 'Non synchronisés'],
            $tableData
        );
    }

    private function exportToCSV(string $filename): void
    {
        $this->newLine();
        $this->info("📤 Export vers {$filename}...");

        $users = User::all();
        $csvData = [];

        foreach ($users as $user) {
            $csvData[] = [
                'id' => $user->id,
                'email' => $user->email,
                'full_name' => trim($user->first_name.' '.$user->last_name),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cognito_id' => $user->cognito_id,
                'is_synchronized' => $user->cognito_id ? 'Oui' : 'Non',
                'created_at' => $user->created_at?->format('Y-m-d H:i:s') ?? '',
                'updated_at' => $user->updated_at?->format('Y-m-d H:i:s') ?? '',
            ];
        }

        $fp = fopen($filename, 'w');
        if ($fp === false) {
            $this->error("Failed to create CSV file: $filename");

            return;
        }

        fputcsv($fp, array_keys($csvData[0])); // Header

        foreach ($csvData as $row) {
            fputcsv($fp, $row);
        }

        fclose($fp);

        $this->info("✅ Export terminé : {$filename}");
    }
}
