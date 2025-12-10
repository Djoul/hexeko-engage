<?php

// app/Console/Commands/ConfirmCognitoUser.php

namespace App\Console\Commands\Cognito;

use App\Models\User;
use App\Services\CognitoUserService;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ConfirmCognitoUser extends Command
{
    protected $signature = 'cognito:confirm-user 
                            {username? : Username ou email du user à confirmer (obligatoire si --all n\'est pas utilisé)}
                            {--all : Confirmer tous les utilisateurs avec cognito_id différent de xxx}
                            {--password= : Mot de passe à définir pour l\'utilisateur (par défaut: demo_benef_BAz167ta5)}
                            {--batch-size=50 : Nombre d\'utilisateurs à traiter par lot}
                            {--delay=1 : Délai en secondes entre chaque lot}';

    protected $description = 'Confirm Cognito users and optionally set their password to CONFIRMED status.';

    private CognitoIdentityProviderClient $client;

    private int $successCount = 0;

    private int $errorCount = 0;

    /**
     * @var array<int|string, mixed>
     */
    private array $errors = [];

    private CognitoUserService $cognitoService;

    public function __construct()
    {
        parent::__construct();
        $this->cognitoService = new CognitoUserService;
    }

    public function handle(): int
    {
        $this->client = new CognitoIdentityProviderClient([
            'version' => 'latest',
            'region' => config('services.cognito.region'),
        ]);

        if ($this->option('all')) {
            return $this->confirmAllUsers();
        }

        $username = $this->argument('username');
        if (! $username) {
            $this->error('❌ Le username est obligatoire si --all n\'est pas utilisé.');

            return 1;
        }

        return $this->confirmSingleUser($username);
    }

    private function confirmAllUsers(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $delay = (int) $this->option('delay');

        $this->info('🔄 Démarrage de la confirmation de tous les utilisateurs Cognito...');
        Log::info('Démarrage de la confirmation en masse des utilisateurs Cognito');

        // Récupérer tous les utilisateurs avec cognito_id différent de 'xxx'
        $query = User::where('cognito_id', '!=', 'xxx')
            ->whereNotNull('cognito_id');

        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            $this->info('✅ Aucun utilisateur à confirmer.');

            return 0;
        }

        $this->info("📊 {$totalUsers} utilisateurs à traiter");
        $this->info("📦 Taille des lots : {$batchSize}");
        $this->info("⏱️ Délai entre lots : {$delay} seconde(s)");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s%');
        $progressBar->start();

        $offset = 0;
        $batchNumber = 1;

        while ($offset < $totalUsers) {
            $users = $query->skip($offset)->take($batchSize)->get();

            if ($users->isEmpty()) {
                break;
            }

            Log::info("Traitement du lot {$batchNumber} ({$users->count()} utilisateurs)");

            foreach ($users as $user) {
                $this->confirmUserInCognito($user);
                $progressBar->advance();
            }

            // Pause entre les lots
            if ($delay > 0 && $offset + $batchSize < $totalUsers) {
                sleep($delay);
            }

            $offset += $batchSize;
            $batchNumber++;
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayResults();

        return $this->errorCount > 0 ? 1 : 0;
    }

    private function confirmUserInCognito(User $user): void
    {
        try {
            // Chercher l'utilisateur par email pour obtenir le vrai Username (sub)
            $cognitoUser = $this->cognitoService->getUserByEmail($user->email);

            if ($cognitoUser === null || $cognitoUser === []) {
                throw new Exception("Utilisateur non trouvé dans Cognito pour l'email: {$user->email}");
            }

            $username = $cognitoUser['username'] ?? $cognitoUser['sub'];

            // Vérifier le statut actuel de l'utilisateur
            $userDetails = $this->client->adminGetUser([
                'UserPoolId' => config('services.cognito.user_pool_id'),
                'Username' => $username,
            ]);

            $userStatus = $userDetails['UserStatus'] ?? null;

            // Si l'utilisateur est en FORCE_CHANGE_PASSWORD, définir un mot de passe permanent
            if ($userStatus === 'FORCE_CHANGE_PASSWORD') {
                // Utiliser le mot de passe fourni ou celui par défaut
                $permanentPassword = $this->option('password') ?: 'demo_benef_BAz167ta5';

                $this->client->adminSetUserPassword([
                    'UserPoolId' => config('services.cognito.user_pool_id'),
                    'Username' => $username,
                    'Password' => $permanentPassword,
                    'Permanent' => true,
                ]);

                Log::info("Mot de passe permanent défini pour passer de FORCE_CHANGE_PASSWORD à CONFIRMED : {$user->email}");
            }

            // Confirmer l'utilisateur en mettant à jour ses attributs
            $this->client->adminUpdateUserAttributes([
                'UserPoolId' => config('services.cognito.user_pool_id'),
                'Username' => $username,
                'UserAttributes' => [
                    [
                        'Name' => 'email_verified',
                        'Value' => 'true',
                    ],
                ],
            ]);

            // Confirmer le statut de l'utilisateur si nécessaire
            try {
                $this->client->adminConfirmSignUp([
                    'UserPoolId' => config('services.cognito.user_pool_id'),
                    'Username' => $username,
                ]);
            } catch (Exception $e) {
                // Ignorer les erreurs non-critiques (déjà confirmé ou confirmé via password)
                if (! str_contains($e->getMessage(), 'already confirmed') &&
                    ! str_contains($e->getMessage(), 'Current status is CONFIRMED')) {
                    // Log mais ne pas lever l'exception
                    Log::info('adminConfirmSignUp non critique : '.$e->getMessage());
                }
            }

            $this->successCount++;
            $usernameStr = is_string($username) ? $username : 'unknown';
            Log::info("✅ Utilisateur confirmé : {$user->email} (username: {$usernameStr}, cognito_id_db: {$user->cognito_id})");

        } catch (Exception $e) {
            $this->errorCount++;
            /** @var array<string, mixed> */
            $errorData = [
                'email' => $user->email,
                'cognito_id' => $user->cognito_id,
                'error' => $e->getMessage(),
            ];
            $this->errors[] = $errorData;

            Log::error("❌ Échec confirmation pour {$user->email} : {$e->getMessage()}", [
                'cognito_id' => $user->cognito_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function confirmSingleUser(string $usernameOrEmail): int
    {
        try {
            $username = $usernameOrEmail;

            // Si ça ressemble à un email, chercher l'utilisateur pour obtenir le vrai Username
            if (filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL)) {
                $cognitoUser = $this->cognitoService->getUserByEmail($usernameOrEmail);
                if ($cognitoUser === null || $cognitoUser === []) {
                    throw new Exception("Utilisateur non trouvé dans Cognito pour l'email: {$usernameOrEmail}");
                }
                $username = $cognitoUser['username'] ?? $cognitoUser['sub'];
                $usernameStr = is_string($username) ? $username : 'unknown';
                $this->info("🔍 Utilisateur trouvé : {$usernameOrEmail} -> Username: {$usernameStr}");
            }

            // Vérifier le statut actuel de l'utilisateur
            $userDetails = $this->client->adminGetUser([
                'UserPoolId' => config('services.cognito.user_pool_id'),
                'Username' => $username,
            ]);

            $userDetailsArray = $userDetails->toArray();
            $userStatus = array_key_exists('UserStatus', $userDetailsArray) && is_string($userDetailsArray['UserStatus'])
                ? $userDetailsArray['UserStatus']
                : 'unknown';
            $this->info("📊 Statut actuel : {$userStatus}");

            // Si un mot de passe est fourni ou si l'utilisateur est en FORCE_CHANGE_PASSWORD, définir le mot de passe
            if ($this->option('password') || $userStatus === 'FORCE_CHANGE_PASSWORD') {
                $permanentPassword = $this->option('password') ?: 'demo_benef_BAz167ta5';

                if ($this->option('password')) {
                    $this->info('🔐 Définition du mot de passe personnalisé fourni...');
                } elseif ($userStatus === 'FORCE_CHANGE_PASSWORD') {
                    $this->info("⚠️ L'utilisateur est en statut FORCE_CHANGE_PASSWORD, définition du mot de passe par défaut...");
                }

                $this->client->adminSetUserPassword([
                    'UserPoolId' => config('services.cognito.user_pool_id'),
                    'Username' => $username,
                    'Password' => $permanentPassword,
                    'Permanent' => true,
                ]);

                $this->info('✅ Mot de passe permanent défini, utilisateur passé en statut CONFIRMED');
            }

            // Confirmer l'utilisateur en mettant à jour ses attributs
            $this->client->adminUpdateUserAttributes([
                'UserPoolId' => config('services.cognito.user_pool_id'),
                'Username' => $username,
                'UserAttributes' => [
                    [
                        'Name' => 'email_verified',
                        'Value' => 'true',
                    ],
                ],
            ]);

            // Confirmer le statut de l'utilisateur si nécessaire
            try {
                $this->client->adminConfirmSignUp([
                    'UserPoolId' => config('services.cognito.user_pool_id'),
                    'Username' => $username,
                ]);
                $this->info('✅ adminConfirmSignUp exécuté avec succès');
            } catch (Exception $e) {
                // Ignorer les erreurs non-critiques
                if (str_contains($e->getMessage(), 'already confirmed') ||
                    str_contains($e->getMessage(), 'Current status is CONFIRMED')) {
                    $this->info("ℹ️ L'utilisateur est déjà confirmé");
                } else {
                    // Log mais ne pas lever l'exception pour les autres cas
                    $this->info('ℹ️ adminConfirmSignUp : '.$e->getMessage());
                }
            }

            $usernameStr = is_string($username) ? $username : 'unknown';
            $this->info("✅ User {$usernameOrEmail} (Username: {$usernameStr}) - confirmé avec succès.");
            Log::info("User confirmé avec succès : {$usernameOrEmail} (Username: {$usernameStr})");

            return 0;
        } catch (Exception $e) {
            $this->error('❌ Failed to confirm user: '.$e->getMessage());
            Log::error("Échec confirmation user {$usernameOrEmail} : {$e->getMessage()}");

            return 1;
        }
    }

    private function displayResults(): void
    {
        $this->line('');
        $this->line('═══════════════════════════════════════════════');
        $this->info('📊 RÉSULTATS DE LA CONFIRMATION');
        $this->line('═══════════════════════════════════════════════');

        $this->table(
            ['Métrique', 'Nombre'],
            [
                ['✅ Confirmés avec succès', $this->successCount],
                ['❌ Erreurs', $this->errorCount],
                ['📊 Total traité', $this->successCount + $this->errorCount],
            ]
        );

        if ($this->errorCount > 0) {
            $this->newLine();
            $this->error('❌ Détail des erreurs :');

            $errorTableData = [];
            foreach (array_slice($this->errors, 0, 10) as $error) {
                if (! is_array($error)) {
                    continue;
                }
                $email = array_key_exists('email', $error) && is_string($error['email']) ? $error['email'] : '';
                $cognitoId = array_key_exists('cognito_id', $error) && is_string($error['cognito_id']) ? $error['cognito_id'] : '';
                $errorMsg = array_key_exists('error', $error) && is_string($error['error']) ? $error['error'] : '';
                $errorTableData[] = [
                    $email,
                    $cognitoId,
                    strlen($errorMsg) > 50 ? substr($errorMsg, 0, 50).'...' : $errorMsg,
                ];
            }

            $this->table(['Email', 'Cognito ID (DB)', 'Erreur'], $errorTableData);

            if (count($this->errors) > 10) {
                $remaining = count($this->errors) - 10;
                $this->comment("... et {$remaining} autres erreurs. Consultez les logs pour plus de détails.");
            }
        }

        $this->newLine();
        $this->info("✅ Confirmation terminée : {$this->successCount} succès, {$this->errorCount} erreurs");

        Log::info('Confirmation en masse terminée', [
            'success' => $this->successCount,
            'errors' => $this->errorCount,
            'total' => $this->successCount + $this->errorCount,
        ]);
    }
}
