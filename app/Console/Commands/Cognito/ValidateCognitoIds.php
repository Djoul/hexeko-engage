<?php

// app/Console/Commands/ValidateCognitoIds.php

namespace App\Console\Commands\Cognito;

use App\Models\User;
use App\Services\CognitoUserService;
use Exception;
use Illuminate\Console\Command;

class ValidateCognitoIds extends Command
{
    protected $signature = 'cognito:validate-ids 
                            {--fix : Corrige automatiquement les IDs invalides}
                            {--limit=100 : Nombre d\'utilisateurs à vérifier}';

    protected $description = 'Valide que les cognito_id en base correspondent bien aux utilisateurs dans Cognito';

    private CognitoUserService $cognitoService;

    public function __construct(CognitoUserService $cognitoService)
    {
        parent::__construct();
        $this->cognitoService = $cognitoService;
    }

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $shouldFix = $this->option('fix');

        $this->info('🔍 Validation des IDs Cognito...');

        $users = User::whereNotNull('cognito_id')->limit($limit)->get();

        if ($users->isEmpty()) {
            $this->info('✅ Aucun utilisateur avec cognito_id à valider.');

            return 0;
        }

        $progressBar = $this->output->createProgressBar($users->count());
        $progressBar->start();

        $validIds = [];
        $invalidIds = [];
        $errors = [];

        foreach ($users as $user) {
            try {
                $cognitoUser = $this->cognitoService->getUserByEmail($user->email);

                if ($cognitoUser && $cognitoUser['sub'] === $user->cognito_id) {
                    $validIds[] = $user;
                } elseif ($cognitoUser !== null && $cognitoUser !== []) {
                    // L'utilisateur existe mais l'ID ne correspond pas
                    $invalidIds[] = [
                        'user' => $user,
                        'current_id' => $user->cognito_id,
                        'correct_id' => $cognitoUser['sub'],
                        'type' => 'mismatch',
                    ];
                } else {
                    // L'utilisateur n'existe pas dans Cognito
                    $invalidIds[] = [
                        'user' => $user,
                        'current_id' => $user->cognito_id,
                        'correct_id' => null,
                        'type' => 'not_found',
                    ];
                }

            } catch (Exception $e) {
                $errors[] = [
                    'user' => $user,
                    'error' => $e->getMessage(),
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayValidationResults($validIds, $invalidIds, $errors);

        if ($shouldFix && $invalidIds !== []) {
            $this->fixInvalidIds($invalidIds);
        }

        return 0;
    }

    /**
     * @param  array<int, mixed>  $valid
     * @param  array<int, mixed>  $invalid
     * @param  array<int, mixed>  $errors
     */
    private function displayValidationResults(array $valid, array $invalid, array $errors): void
    {
        $this->table(
            ['Résultat', 'Nombre'],
            [
                ['✅ IDs valides', count($valid)],
                ['❌ IDs invalides', count($invalid)],
                ['🚨 Erreurs', count($errors)],
            ]
        );

        if ($invalid !== []) {
            $this->newLine();
            $this->error('❌ IDs invalides détectés:');

            $tableData = [];
            foreach (array_slice($invalid, 0, 10) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $currentId = array_key_exists('current_id', $item) && is_string($item['current_id']) ? $item['current_id'] : '';
                $correctId = array_key_exists('correct_id', $item) && is_string($item['correct_id']) ? $item['correct_id'] : '';
                $user = $item['user'] ?? null;
                $email = $user instanceof User ? $user->email : 'unknown';
                $type = array_key_exists('type', $item) ? $item['type'] : 'unknown';
                $tableData[] = [
                    $email,
                    substr($currentId, 0, 20).'...',
                    $correctId !== '' && $correctId !== '0' ? substr($correctId, 0, 20).'...' : 'N/A',
                    $type === 'mismatch' ? 'ID différent' : 'Inexistant',
                ];
            }

            $this->table(['Email', 'ID actuel', 'ID correct', 'Problème'], $tableData);

            if (count($invalid) > 10) {
                $this->comment('... et '.(count($invalid) - 10).' autres');
            }

            if (! $this->option('fix')) {
                $this->newLine();
                $this->comment('💡 Utilisez --fix pour corriger automatiquement les IDs invalides');
            }
        }

        if ($errors !== []) {
            $this->newLine();
            $this->error('🚨 Erreurs rencontrées:');
            foreach (array_slice($errors, 0, 5) as $error) {
                if (! is_array($error)) {
                    continue;
                }
                $user = $error['user'] ?? null;
                $email = $user instanceof User ? $user->email : 'unknown';
                $errorMsg = array_key_exists('error', $error) ? (string) $error['error'] : 'Unknown error';
                $this->line("   • {$email}: {$errorMsg}");
            }
        }
    }

    /**
     * @param  array<int, mixed>  $invalidIds
     */
    private function fixInvalidIds(array $invalidIds): void
    {
        $this->newLine();
        $this->info('🔧 Correction des IDs invalides...');

        $fixed = 0;
        $fixErrors = 0;

        foreach ($invalidIds as $item) {
            if (! is_array($item)) {
                continue;
            }
            $user = null;
            try {
                $user = $item['user'] ?? null;
                if (! $user instanceof User) {
                    continue;
                }
                $type = $item['type'] ?? null;
                $correctId = $item['correct_id'] ?? null;

                if ($type === 'mismatch' && $correctId) {
                    // Corriger l'ID
                    $user->update(['cognito_id' => $correctId]);
                    $fixed++;
                    $this->line("   ✅ Corrigé: {$user->email}");
                } elseif ($type === 'not_found') {
                    // Supprimer l'ID car l'utilisateur n'existe pas dans Cognito
                    $user->update(['cognito_id' => null]);
                    $fixed++;
                    $this->line("   🗑️  ID supprimé: {$user->email} (n'existe pas dans Cognito)");
                }
            } catch (Exception $e) {
                $fixErrors++;
                $email = $user instanceof User ? $user->email : 'unknown';
                $this->error("   ❌ Erreur pour {$email}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("✅ {$fixed} IDs corrigés, {$fixErrors} erreurs");
    }
}

// app/Console/Commands/CognitoUserStats.php

// Pour enregistrer toutes ces commandes dans app/Console/Kernel.php :

/*
protected $commands = [
    Commands\SyncAllUsersWithCognito::class,
    Commands\CheckCognitoSync::class,
    Commands\ValidateCognitoIds::class,
    Commands\CognitoUserStats::class,
];
*/
