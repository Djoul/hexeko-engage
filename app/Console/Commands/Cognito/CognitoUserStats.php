<?php

namespace App\Console\Commands\Cognito;

use App\Models\User;
use App\Services\CognitoUserService;
use Countable;
use Exception;
use Illuminate\Console\Command;

class CognitoUserStats extends Command
{
    protected $signature = 'cognito:stats 
                            {--refresh : Rafraîchir les statistiques depuis Cognito}';

    protected $description = 'Affiche les statistiques détaillées de synchronisation Cognito';

    private CognitoUserService $cognitoService;

    public function __construct(CognitoUserService $cognitoService)
    {
        parent::__construct();
        $this->cognitoService = $cognitoService;
    }

    public function handle(): int
    {
        $this->line('');
        $this->line('📊 <fg=cyan>═══════════════════════════════════════════════════════════════</fg=cyan>');
        $this->line('📊 <fg=cyan>                    STATISTIQUES COGNITO</fg=cyan>');
        $this->line('📊 <fg=cyan>═══════════════════════════════════════════════════════════════</fg=cyan>');
        $this->line('');

        // Statistiques de base
        $this->displayBasicStats();

        // Statistiques par période
        $this->displayPeriodStats();

        // Statistiques de domaines email
        $this->displayEmailDomainStats();

        if ($this->option('refresh')) {
            $this->displayCognitoStats();
        }

        return 0;
    }

    private function displayBasicStats(): void
    {
        $totalUsers = User::count();
        $syncedUsers = User::whereNotNull('cognito_id')->count();
        $notSyncedUsers = User::whereNull('cognito_id')->count();

        $this->info('🏠 Statistiques base de données:');
        $this->table(
            ['Métrique', 'Valeur', 'Pourcentage'],
            [
                ['Total utilisateurs', number_format($totalUsers), '100%'],
                ['Synchronisés avec Cognito', number_format($syncedUsers), $totalUsers > 0 ? round(($syncedUsers / $totalUsers) * 100, 1).'%' : '0%'],
                ['Non synchronisés', number_format($notSyncedUsers), $totalUsers > 0 ? round(($notSyncedUsers / $totalUsers) * 100, 1).'%' : '0%'],
            ]
        );
    }

    private function displayPeriodStats(): void
    {
        $this->newLine();
        $this->info('📅 Statistiques par période:');

        $periods = [
            'Dernières 24h' => now()->subDay(),
            '7 derniers jours' => now()->subWeek(),
            '30 derniers jours' => now()->subMonth(),
            '6 derniers mois' => now()->subMonths(6),
            'Cette année' => now()->startOfYear(),
        ];

        $tableData = [];
        foreach ($periods as $label => $date) {
            $total = User::where('created_at', '>=', $date)->count();
            $synced = User::where('created_at', '>=', $date)->whereNotNull('cognito_id')->count();
            $notSynced = $total - $synced;

            $tableData[] = [
                $label,
                $total,
                $synced,
                $notSynced,
                $total > 0 ? round(($synced / $total) * 100, 1).'%' : '0%',
            ];
        }

        $this->table(
            ['Période', 'Total', 'Synchronisés', 'Non sync.', '% Sync.'],
            $tableData
        );
    }

    private function displayEmailDomainStats(): void
    {
        $this->newLine();
        $this->info('📧 Top 10 domaines email:');

        $domains = User::selectRaw('SUBSTRING_INDEX(email, "@", -1) as domain, COUNT(*) as count, SUM(CASE WHEN cognito_id IS NOT NULL THEN 1 ELSE 0 END) as synced')
            ->groupBy('domain')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        $tableData = [];
        foreach ($domains as $domain) {
            $countAttr = $domain->getAttribute('count');
            $syncedAttr = $domain->getAttribute('synced');
            $domainAttr = $domain->getAttribute('domain');

            $count = is_numeric($countAttr) ? (int) $countAttr : 0;
            $synced = is_numeric($syncedAttr) ? (int) $syncedAttr : 0;
            $domainName = is_string($domainAttr) ? $domainAttr : (is_scalar($domainAttr) ? (string) $domainAttr : '');

            $syncPercentage = $count > 0 ? round(($synced / $count) * 100, 1) : 0;
            $tableData[] = [
                $domainName,
                $count,
                $synced,
                $count - $synced,
                $syncPercentage.'%',
            ];
        }

        $this->table(
            ['Domaine', 'Total', 'Synchronisés', 'Non sync.', '% Sync.'],
            $tableData
        );
    }

    private function displayCognitoStats(): void
    {
        $this->newLine();
        $this->info('☁️  Statistiques depuis Cognito (peut prendre un moment)...');

        try {
            // Cette méthode pourrait être ajoutée au service pour récupérer des stats depuis Cognito
            $cognitoUsers = $this->cognitoService->listUsers(60); // Premier lot
            $users = $cognitoUsers['Users'] ?? [];
            $totalInCognito = is_array($users) || $users instanceof Countable ? count($users) : 0;

            $this->table(
                ['Métrique Cognito', 'Valeur'],
                [
                    ['Utilisateurs dans le pool (échantillon)', $totalInCognito],
                    ['Pagination disponible', array_key_exists('PaginationToken', $cognitoUsers) ? 'Oui' : 'Non'],
                ]
            );

        } catch (Exception $e) {
            $this->error("❌ Impossible de récupérer les stats Cognito: {$e->getMessage()}");
        }
    }
}
