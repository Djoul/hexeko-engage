<?php

namespace App\Console\Commands\Cognito;

use App\Models\User;
use App\Services\CognitoUserService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SyncAllUsersWithCognito extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cognito:sync-all-users 
                            {--batch-size=10 : Nombre d\'utilisateurs à traiter par lot}
                            {--only-missing : Synchroniser seulement les utilisateurs sans cognito_id}
                            {--dry-run : Mode test - affiche ce qui serait fait sans l\'exécuter}
                            {--delay=1 : Délai en secondes entre chaque lot (pour éviter les limites de débit)}
                            {--force : Exécuter sans demander de confirmation}
                            {--v : Affiche plus de détails}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronise tous les utilisateurs de la base de données avec AWS Cognito';

    private CognitoUserService $cognitoService;

    private int $totalProcessed = 0;

    private int $totalUpdated = 0;

    private int $totalCreated = 0;

    private int $totalGlobalIdUpdated = 0;

    private int $totalErrors = 0;

    /**
     * @var array<int|string, mixed>
     */
    private array $allErrors = [];

    public function __construct(CognitoUserService $cognitoService)
    {
        parent::__construct();
        $this->cognitoService = $cognitoService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        \Log::info('Start Syncing all users with Cognito');
        $this->displayHeader();

        $batchSize = (int) $this->option('batch-size');
        $onlyMissing = $this->option('only-missing');
        $dryRun = $this->option('dry-run');
        $delay = (int) $this->option('delay');

        // Validation des options
        if ($batchSize <= 0 || $batchSize > 100) {
            $this->error('❌ La taille du lot doit être entre 1 et 100.');

            return 1;
        }

        try {
            // Compter le nombre total d'utilisateurs (exclure ceux avec cognito_id = 'xxx')
            $query = User::where('cognito_id', '!=', 'xxx');
            if ($onlyMissing) {
                $query->whereNull('cognito_id');
            }

            $totalUsers = $query->count();

            if ($totalUsers === 0) {
                $message = $onlyMissing ?
                    'Tous les utilisateurs ont déjà un cognito_id !' :
                    'Aucun utilisateur trouvé dans la base de données.';

                $this->info("✅ {$message}");

                return 0;
            }

            $this->displaySummary($totalUsers, $batchSize, $onlyMissing, $dryRun);

            if (! $this->confirmExecution($dryRun)) {
                $this->info('🚫 Opération annulée.');

                return 0;
            }

            // Créer la barre de progression
            $progressBar = $this->output->createProgressBar($totalUsers);
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $progressBar->start();

            // Traitement par lots
            $offset = 0;
            $batchNumber = 1;

            while ($offset < $totalUsers) {
                $users = $query->skip($offset)->take($batchSize)->get();

                if ($users->isEmpty()) {
                    break;
                }

                if ($this->option('v')) {
                    $this->newLine();
                    $this->info("📦 Traitement du lot {$batchNumber} ({$users->count()} utilisateurs)...");
                }

                if (! $dryRun) {
                    $this->processBatch($users);
                } else {
                    $this->simulateBatch($users);
                }

                $progressBar->advance($users->count());

                // Délai entre les lots pour respecter les limites de débit AWS
                if ($delay > 0 && $offset + $batchSize < $totalUsers) {
                    if ($this->option('v')) {
                        $this->newLine();
                        $this->comment("⏳ Pause de {$delay} seconde(s)...");
                    }
                    sleep($delay);
                }

                $offset += $batchSize;
                $batchNumber++;
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->displayFinalResults($dryRun);
            Log::info('Finish Syncing all users with Cognito');
        } catch (Exception $e) {
            $this->error("❌ Erreur critique : {$e->getMessage()}");
            Log::error('Erreur dans SyncAllUsersWithCognito', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        return 0;
    }

    private function displayHeader(): void
    {
        $this->line('');
        $this->line('🔄 <fg=cyan>═══════════════════════════════════════════════════════════════</fg=cyan>');
        $this->line('🔄 <fg=cyan>              SYNCHRONISATION COGNITO - TOUS LES UTILISATEURS</fg=cyan>');
        $this->line('🔄 <fg=cyan>═══════════════════════════════════════════════════════════════</fg=cyan>');
        $this->line('');
    }

    private function displaySummary(int $totalUsers, int $batchSize, bool $onlyMissing, bool $dryRun): void
    {
        $this->info("📊 Résumé de l'opération :");
        $this->line("   • Utilisateurs à traiter : <fg=yellow>{$totalUsers}</fg=yellow>");
        $this->line("   • Taille des lots : <fg=yellow>{$batchSize}</fg=yellow>");
        $this->line('   • Mode : <fg=yellow>'.($onlyMissing ? 'Seulement les utilisateurs sans cognito_id' : 'Tous les utilisateurs').'</fg=yellow>');
        $this->line('   • Type : <fg=yellow>'.($dryRun ? 'SIMULATION (dry-run)' : 'EXÉCUTION RÉELLE').'</fg=yellow>');

        $estimatedBatches = ceil($totalUsers / $batchSize);
        $this->line("   • Nombre de lots estimé : <fg=yellow>{$estimatedBatches}</fg=yellow>");
        $this->line('');
    }

    private function confirmExecution(bool $dryRun): bool
    {
        if ($dryRun) {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        return $this->confirm('🤔 Êtes-vous sûr de vouloir continuer ?', true);
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function processBatch(Collection $users): void
    {
        try {
            /** @var array<int, array<string, mixed>> $usersArray */
            $usersArray = $users->toArray();
            $results = $this->cognitoService->synchronizeUsers($usersArray);

            $summary = array_key_exists('summary', $results) && is_array($results['summary']) ? $results['summary'] : [];
            $this->totalProcessed += array_key_exists('total_processed', $summary) ? (int) $summary['total_processed'] : 0;
            $this->totalUpdated += array_key_exists('updated', $summary) ? (int) $summary['updated'] : 0;
            $this->totalCreated += array_key_exists('created', $summary) ? (int) $summary['created'] : 0;
            $this->totalGlobalIdUpdated += array_key_exists('updated_global_id', $summary) ? (int) $summary['updated_global_id'] : 0;
            $this->totalErrors += array_key_exists('errors', $summary) ? (int) $summary['errors'] : 0;

            // Collecter toutes les erreurs
            if (! empty($results['errors'])) {
                /** @var array<int|string, mixed> */
                $errors = $results['errors'];
                $this->allErrors = array_merge($this->allErrors, $errors);
            }

            if ($this->option('v')) {
                /** @var array{summary: array<string, mixed>, errors?: array<string, mixed>} */
                $typedResults = $results;
                $this->displayBatchResults($typedResults);
            }

        } catch (Exception $e) {
            $this->totalErrors += $users->count();

            // Ajouter toutes les erreurs du lot
            foreach ($users as $user) {
                /** @var array<string, mixed> */
                $errorData = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ];
                $this->allErrors[] = $errorData;
            }

            if ($this->option('v')) {
                $this->newLine();
                $this->error("❌ Erreur lors du traitement du lot : {$e->getMessage()}");
            }

            Log::error('Erreur lors du traitement d\'un lot d\'utilisateurs', [
                'users_count' => $users->count(),
                'error' => $e->getMessage(),
                'users_ids' => $users->pluck('id')->toArray(),
            ]);
        }
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function simulateBatch(Collection $users): void
    {
        // Simulation : on fait comme si on traitait les utilisateurs
        foreach ($users as $user) {
            if ($user->cognito_id) {
                $this->totalUpdated++;
            } else {
                $this->totalCreated++;
            }
        }

        $this->totalProcessed += $users->count();

        if ($this->option('v')) {
            $this->newLine();
            $this->comment("🔍 SIMULATION - Lot de {$users->count()} utilisateurs traité");
        }
    }

    /**
     * @param  array{summary: array<string, mixed>, errors?: array<string, mixed>}  $results
     */
    private function displayBatchResults(array $results): void
    {
        $summary = $results['summary'];
        $this->newLine();
        $updatedValue = array_key_exists('updated', $summary) ? $summary['updated'] : 0;
        $createdValue = array_key_exists('created', $summary) ? $summary['created'] : 0;
        $updated = is_scalar($updatedValue) ? (string) $updatedValue : '0';
        $created = is_scalar($createdValue) ? (string) $createdValue : '0';
        $line = "   ✅ Mis à jour: {$updated} | 🆕 Créés: {$created}";
        if (array_key_exists('updated_global_id', $summary) && $summary['updated_global_id'] > 0) {
            $globalIdValue = $summary['updated_global_id'];
            $globalId = is_scalar($globalIdValue) ? (string) $globalIdValue : '0';
            $line .= " | 🔄 Global ID: {$globalId}";
        }
        $errorsValue = array_key_exists('errors', $summary) ? $summary['errors'] : 0;
        $errors = is_scalar($errorsValue) ? (string) $errorsValue : '0';
        $line .= " | ❌ Erreurs: {$errors}";
        $this->line($line);
    }

    private function displayFinalResults(bool $dryRun): void
    {
        $prefix = $dryRun ? '[SIMULATION] ' : '';

        $this->line('🎉 <fg=green>═══════════════════════════════════════════════════════════════</fg=green>');
        $this->line("🎉 <fg=green>                    {$prefix}SYNCHRONISATION TERMINÉE</fg=green>");
        $this->line('🎉 <fg=green>═══════════════════════════════════════════════════════════════</fg=green>');
        $this->line('');

        // Tableau des résultats
        $tableData = [
            [
                'Total traité',
                $this->totalProcessed,
                '100%',
            ],
            [
                '✅ Mis à jour (existants dans Cognito)',
                $this->totalUpdated,
                $this->totalProcessed > 0 ? round(($this->totalUpdated / $this->totalProcessed) * 100, 1).'%' : '0%',
            ],
            [
                '🆕 Créés (nouveaux dans Cognito)',
                $this->totalCreated,
                $this->totalProcessed > 0 ? round(($this->totalCreated / $this->totalProcessed) * 100, 1).'%' : '0%',
            ],
        ];

        if ($this->totalGlobalIdUpdated > 0) {
            $tableData[] = [
                '🔄 Global ID mis à jour',
                $this->totalGlobalIdUpdated,
                $this->totalProcessed > 0 ? round(($this->totalGlobalIdUpdated / $this->totalProcessed) * 100, 1).'%' : '0%',
            ];
        }

        $tableData[] = [
            '❌ Erreurs',
            $this->totalErrors,
            $this->totalProcessed > 0 ? round(($this->totalErrors / $this->totalProcessed) * 100, 1).'%' : '0%',
        ];

        $this->table(['Métrique', 'Nombre', 'Pourcentage'], $tableData);

        // Afficher quelques exemples d'erreurs s'il y en a
        if ($this->allErrors !== []) {
            $this->newLine();
            $this->error('❌ Détail des erreurs :');

            $errorSample = array_slice($this->allErrors, 0, 10); // Montrer seulement les 10 premières

            $errorTableData = [];
            foreach ($errorSample as $error) {
                if (! is_array($error)) {
                    continue;
                }
                $email = array_key_exists('email', $error) && is_scalar($error['email']) ? (string) $error['email'] : 'N/A';
                $errorMsg = array_key_exists('error', $error) && is_scalar($error['error']) ? (string) $error['error'] : '';
                $errorCode = array_key_exists('error_code', $error) && is_scalar($error['error_code']) ? (string) $error['error_code'] : 'N/A';

                $errorTableData[] = [
                    $email,
                    strlen($errorMsg) > 50 ? substr($errorMsg, 0, 50).'...' : $errorMsg,
                    $errorCode,
                ];
            }

            $this->table(['Email', 'Erreur', 'Code'], $errorTableData);

            if (count($this->allErrors) > 10) {
                $remaining = count($this->allErrors) - 10;
                $this->comment("... et {$remaining} autres erreurs. Consultez les logs pour plus de détails.");
            }

            // Log toutes les erreurs
            Log::warning('Erreurs lors de la synchronisation Cognito', [
                'total_errors' => count($this->allErrors),
                'errors' => $this->allErrors,
            ]);
        }

        // Conseils
        $this->newLine();
        $this->info('💡 Conseils :');

        if ($this->totalErrors > 0) {
            $this->line('   • Consultez les logs Laravel pour plus de détails sur les erreurs');
            $this->line('   • Vous pouvez relancer la commande avec --only-missing pour traiter seulement les utilisateurs en échec');
        }

        if (! $dryRun && ($this->totalCreated > 0 || $this->totalUpdated > 0)) {
            $this->line('   • Vérifiez dans la console AWS Cognito que les utilisateurs ont été correctement synchronisés');
        }

        if ($dryRun) {
            $this->line('   • Relancez sans --dry-run pour exécuter réellement la synchronisation');
        }

        $this->line('');
    }
}

// Pour enregistrer la commande, ajouter dans app/Console/Kernel.php :

/*
protected $commands = [
    Commands\SyncAllUsersWithCognito::class,
];
*/

// Ou si vous utilisez Laravel 5.5+, la commande sera automatiquement découverte

// Exemples d'utilisation :

/*
# Synchroniser tous les utilisateurs (mode interactif)
php artisan cognito:sync-all-users

# Synchroniser seulement les utilisateurs sans cognito_id
php artisan cognito:sync-all-users --only-missing

# Mode simulation (ne fait rien, montre juste ce qui serait fait)
php artisan cognito:sync-all-users --dry-run

# Avec une taille de lot personnalisée et verbose
php artisan cognito:sync-all-users --batch-size=25 --verbose

# Avec un délai entre les lots pour éviter les limites de débit
php artisan cognito:sync-all-users --delay=2

# Combinaison d'options
php artisan cognito:sync-all-users --only-missing --batch-size=30 --delay=1 --verbose
*/
