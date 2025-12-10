<?php

namespace App\Services;

use App\Models\User;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Exception\AwsException;
use Exception;
use Illuminate\Support\Facades\Log;

class CognitoUserService
{
    private ?CognitoIdentityProviderClient $cognitoClient = null;

    private ?string $userPoolId;

    public function __construct()
    {
        $poolId = config('services.cognito.user_pool_id');
        $this->userPoolId = is_string($poolId) ? $poolId : null;
    }

    private function getClient(): CognitoIdentityProviderClient
    {
        if (! $this->cognitoClient instanceof CognitoIdentityProviderClient) {
            //            $credentials = [
            //                'key' => config('services.aws.access_key_id'),
            //                'secret' => config('services.aws.secret_access_key'),
            //            ];

            // Vérifier que les credentials sont bien définis
            //            if (! $credentials['key'] || ! $credentials['secret']) {
            //                throw new RuntimeException('AWS credentials are not properly configured. Please check AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in your .env file.');
            //            }

            $this->cognitoClient = new CognitoIdentityProviderClient([
                'region' => config('services.cognito.region'),
                'version' => 'latest',
                //                'credentials' => $credentials,
            ]);
        }

        return $this->cognitoClient;
    }

    /**
     * Vérifie si un utilisateur existe dans le User Pool
     */
    public function userExists(string $username): bool
    {
        try {
            $this->getClient()->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $username,
            ]);

            return true;
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'UserNotFoundException') {
                return false;
            }
            // Relancer l'exception pour d'autres erreurs
            throw $e;
        }
    }

    /**
     * Crée un utilisateur dans Cognito
     *
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    public function createUser(array $userData): array
    {
        try {
            $params = [
                'UserPoolId' => $this->userPoolId,
                'Username' => $userData['username'],
                'MessageAction' => 'SUPPRESS', // Ne pas envoyer d'email de bienvenue
                'TemporaryPassword' => $userData['temporary_password'] ?? $this->generateTemporaryPassword(),
                'UserAttributes' => [
                    [
                        'Name' => 'email',
                        'Value' => $userData['email'],
                    ],
                    [
                        'Name' => 'email_verified',
                        'Value' => 'true',
                    ],
                ],
            ];

            // Ajouter des attributs supplémentaires si fournis
            if (array_key_exists('first_name', $userData)) {
                $params['UserAttributes'][] = [
                    'Name' => 'given_name',
                    'Value' => $userData['first_name'],
                ];
            }

            if (array_key_exists('last_name', $userData)) {
                $params['UserAttributes'][] = [
                    'Name' => 'family_name',
                    'Value' => $userData['last_name'],
                ];
            }

            // Ajouter le custom:global_id si fourni
            if (array_key_exists('global_id', $userData)) {
                $params['UserAttributes'][] = [
                    'Name' => 'custom:global_id',
                    'Value' => $userData['global_id'],
                ];
            }

            $result = $this->getClient()->adminCreateUser($params);

            // Définir le mot de passe permanent si fourni
            if (array_key_exists('permanent_password', $userData) && is_string($userData['permanent_password'])) {
                $username = is_string($userData['username']) ? $userData['username'] : '';
                $this->setPermanentPassword($username, $userData['permanent_password']);
            }

            return [
                'success' => true,
                'username' => $userData['username'],
                'user' => $result['User'],
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'username' => $userData['username'],
                'error' => $e->getAwsErrorMessage(),
                'error_code' => $e->getAwsErrorCode(),
            ];
        }
    }

    /**
     * Crée des utilisateurs par lot avec vérification préalable
     *
     * @param  array<int, array<string, mixed>>  $usersData
     * @return array<string, array<int, mixed>>
     */
    public function createUsersBatch(array $usersData): array
    {
        $results = [
            'created' => [],
            'already_exists' => [],
            'errors' => [],
        ];

        foreach ($usersData as $userData) {
            $usernameValue = $userData['username'] ?? $userData['email'] ?? '';
            $username = is_string($usernameValue) ? $usernameValue : '';

            try {
                // Vérifier si l'utilisateur existe déjà
                if ($username !== '' && $this->userExists($username)) {
                    $results['already_exists'][] = [
                        'username' => $username,
                        'email' => $userData['email'],
                    ];

                    continue;
                }

                // Créer l'utilisateur
                $result = $this->createUser(array_merge($userData, ['username' => $username]));

                if ($result['success']) {
                    $results['created'][] = $result;
                } else {
                    $results['errors'][] = $result;
                }

            } catch (AwsException $e) {
                $results['errors'][] = [
                    'username' => $username,
                    'error' => $e->getAwsErrorMessage(),
                    'error_code' => $e->getAwsErrorCode(),
                ];
            }
        }

        return $results;
    }

    /**
     * Définit un mot de passe permanent pour un utilisateur
     */
    private function setPermanentPassword(string $username, string $password): void
    {
        $this->getClient()->adminSetUserPassword([
            'UserPoolId' => $this->userPoolId,
            'Username' => $username,
            'Password' => $password,
            'Permanent' => true,
        ]);
    }

    /**
     * Génère un mot de passe temporaire
     */
    private function generateTemporaryPassword(): string
    {
        return 'TempPass'.rand(1000, 9999).'!';
    }

    /**
     * Récupère les informations d'un utilisateur
     *
     * @return array<string, mixed>|null
     */
    public function getUser(string $username): ?array
    {
        try {
            $result = $this->getClient()->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $username,
            ]);

            /** @var array<string, mixed> $user */
            $user = $result['User'];

            return $user;
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'UserNotFoundException') {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Liste tous les utilisateurs avec pagination
     *
     * @return array<string, mixed>
     */
    public function listUsers(int $limit = 60, ?string $paginationToken = null): array
    {
        $params = [
            'UserPoolId' => $this->userPoolId,
            'Limit' => $limit,
        ];

        if (! in_array($paginationToken, [null, '', '0'], true)) {
            $params['PaginationToken'] = $paginationToken;
        }

        $result = $this->getClient()->listUsers($params);

        return $result->toArray();
    }

    /**
     * Vérifie si un utilisateur existe par email et retourne ses informations
     *
     * @return array<string, mixed>|null
     */
    public function getUserByEmail(string $email): ?array
    {
        // TOUJOURS chercher par attribut email, jamais par username
        return $this->findUserByEmailAttribute($email);
    }

    /**
     * Recherche un utilisateur par attribut email
     *
     * @return array<string, mixed>|null
     */
    private function findUserByEmailAttribute(string $email): ?array
    {
        try {
            $result = $this->getClient()->listUsers([
                'UserPoolId' => $this->userPoolId,
                'Filter' => "email = \"{$email}\"",
                'Limit' => 10, // Augmenter la limite au cas où
            ]);

            if (empty($result['Users'])) {
                Log::info("Aucun utilisateur trouvé avec l'email: {$email}");

                return null;
            }

            /** @var array<int, mixed> $users */
            $users = $result['Users'];

            // Si plusieurs utilisateurs ont le même email, log un warning
            if (count($users) > 1) {
                Log::warning("Plusieurs utilisateurs Cognito trouvés avec l'email: {$email} - utilisation du premier");
            }

            /** @var array{Attributes?: array<int, array<string, string>>, Username?: string} $user */
            $user = $users[0];
            /** @var array<int, array<string, string>> $attributes */
            $attributes = $user['Attributes'] ?? [];
            $sub = $this->extractAttributeValue($attributes, 'sub');

            if (in_array($sub, [null, '', '0'], true)) {
                Log::warning("Utilisateur Cognito trouvé par email mais sans sub: {$email}");
            }

            $username = $user['Username'] ?? $email;
            Log::info("Utilisateur trouvé par filtre email: {$email} -> Username: {$username}");

            return [
                'sub' => $sub,
                'username' => $username,
                'user_data' => $user,
            ];
        } catch (AwsException $e) {
            Log::error("Erreur lors de la recherche par email {$email}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Extrait la valeur d'un attribut depuis les attributs Cognito
     *
     * @param  array<int, array<string, string>>  $attributes
     */
    private function extractAttributeValue(array $attributes, string $attributeName): ?string
    {
        foreach ($attributes as $attribute) {
            if ($attribute['Name'] === $attributeName) {
                return $attribute['Value'];
            }
        }

        return null;
    }

    /**
     * Met à jour les attributs d'un utilisateur Cognito
     *
     * @param  array<string, string>  $attributes
     * @return array<string, mixed>
     */
    public function updateUserAttributes(string $username, array $attributes): array
    {
        try {
            $params = [
                'UserPoolId' => $this->userPoolId,
                'Username' => $username,
                'UserAttributes' => [],
            ];

            foreach ($attributes as $name => $value) {
                $params['UserAttributes'][] = [
                    'Name' => $name,
                    'Value' => $value,
                ];
            }

            $this->getClient()->adminUpdateUserAttributes($params);

            return [
                'success' => true,
                'username' => $username,
            ];

        } catch (AwsException $e) {
            return [
                'success' => false,
                'username' => $username,
                'error' => $e->getAwsErrorMessage(),
                'error_code' => $e->getAwsErrorCode(),
            ];
        }
    }

    /**
     * Crée un utilisateur dans Cognito et retourne le sub
     *
     * @param  array<string, mixed>  $userData
     * @return array<string, mixed>
     */
    public function createUserAndGetSub(array $userData): array
    {
        $result = $this->createUser($userData);

        if (! $result['success']) {
            return $result;
        }

        // Récupérer le sub depuis les attributs de l'utilisateur créé
        /** @var array<int, array<string, string>> $attributes */
        $attributes = is_array($result['user']) && array_key_exists('Attributes', $result['user']) ? $result['user']['Attributes'] : [];
        $sub = $this->extractAttributeValue($attributes, 'sub');

        return [
            'success' => true,
            'sub' => $sub,
            'username' => $result['username'],
            'user_data' => $result['user'],
        ];
    }

    /**
     * Synchronise une liste d'utilisateurs Laravel avec Cognito
     * Met à jour le champ cognito_id en base de données et le custom:global_id dans Cognito
     *
     * @param  array<int, array<string, mixed>>  $users
     * @return array<string, mixed>
     */
    public function synchronizeUsers(array $users): array
    {
        $results = [
            'updated_existing' => [],
            'created_new' => [],
            'updated_global_id' => [],
            'errors' => [],
            'summary' => [
                'total_processed' => count($users),
                'updated' => 0,
                'created' => 0,
                'updated_global_id' => 0,
                'errors' => 0,
            ],
        ];

        foreach ($users as $user) {
            try {
                $email = is_array($user) ? ($user['email'] ?? null) : ($user->email ?? null);
                $userId = is_array($user) ? ($user['id'] ?? null) : ($user->id ?? null);
                $existingCognitoId = is_array($user) ? ($user['cognito_id'] ?? null) : ($user->cognito_id ?? null);

                // Vérifier que nous avons les données minimales
                if (! $email || ! $userId) {
                    $emailStr = is_string($email) ? $email : 'null';
                    $userIdStr = is_string($userId) ? $userId : 'null';
                    Log::error("Données utilisateur incomplètes - email: {$emailStr}, id: {$userIdStr}");

                    continue;
                }

                // Ignorer complètement les utilisateurs avec cognito_id = 'xxx'
                if ($existingCognitoId === 'xxx') {
                    $emailStr = is_string($email) ? $email : 'unknown';
                    Log::info("Utilisateur ignoré (cognito_id = 'xxx'): {$emailStr}");

                    continue;
                }

                $emailStr = is_string($email) ? $email : '';
                $userIdStr = is_string($userId) ? $userId : '';

                Log::info("Traitement utilisateur: {$emailStr}");

                // Vérifier si l'utilisateur existe dans Cognito
                if (! is_string($email)) {
                    continue;
                }
                $cognitoUser = $this->getUserByEmail($email);

                if ($cognitoUser !== null && $cognitoUser !== []) {
                    // L'utilisateur existe dans Cognito
                    $cognitoSub = $cognitoUser['sub'] ?? null;

                    if (! $cognitoSub) {
                        Log::error("Pas de sub trouvé pour l'utilisateur Cognito: {$email}");

                        continue;
                    }

                    // Récupérer le custom:global_id actuel dans Cognito
                    $userDataAttributes = is_array($cognitoUser) && array_key_exists('user_data', $cognitoUser) && is_array($cognitoUser['user_data']) && array_key_exists('Attributes', $cognitoUser['user_data'])
                        ? $cognitoUser['user_data']['Attributes']
                        : [];

                    /** @var array<int, array<string, string>> $typedAttributes */
                    $typedAttributes = is_array($userDataAttributes) ? $userDataAttributes : [];
                    $currentGlobalId = $this->extractAttributeValue($typedAttributes, 'custom:global_id');

                    // Déterminer quel ID utiliser
                    if (! in_array($currentGlobalId, [null, '', '0'], true)) {
                        // Si custom:global_id existe dans Cognito, c'est lui qui fait autorité
                        // Le cognito_id en DB doit être égal au custom:global_id
                        if ($existingCognitoId !== $currentGlobalId) {
                            $this->updateUserCognitoId($userIdStr, $currentGlobalId);

                            $results['updated_existing'][] = [
                                'user_id' => $userId,
                                'email' => $email,
                                'cognito_sub' => $cognitoSub,
                                'cognito_id_synced' => $currentGlobalId,
                                'action' => 'synced_db_with_global_id',
                            ];
                            $results['summary']['updated']++;

                            $globalIdStr = is_string($currentGlobalId) ? $currentGlobalId : 'null';
                            Log::info("cognito_id DB synchronisé avec custom:global_id pour: {$emailStr} -> {$globalIdStr}");
                        }
                    } else {
                        // Si custom:global_id est null dans Cognito
                        if ($existingCognitoId && $existingCognitoId !== 'xxx') {
                            // Utiliser le cognito_id existant de la DB
                            $targetGlobalId = $existingCognitoId;
                        } else {
                            // Utiliser le sub de Cognito
                            $targetGlobalId = $cognitoSub;
                            // Mettre à jour la DB avec ce sub
                            if (is_string($cognitoSub)) {
                                $this->updateUserCognitoId($userIdStr, $cognitoSub);
                            }
                        }

                        // Mettre à jour le custom:global_id dans Cognito
                        $username = is_array($cognitoUser) && array_key_exists('username', $cognitoUser) && is_string($cognitoUser['username']) ? $cognitoUser['username'] : '';
                        $globalIdValue = is_string($targetGlobalId) ? $targetGlobalId : '';

                        if ($username !== '' && $globalIdValue !== '') {
                            $updateResult = $this->updateUserAttributes($username, [
                                'custom:global_id' => $globalIdValue,
                            ]);
                        } else {
                            $updateResult = ['success' => false, 'error_code' => 'INVALID_PARAMS'];
                        }

                        if ($updateResult['success']) {
                            $results['updated_global_id'][] = [
                                'user_id' => $userId,
                                'email' => $email,
                                'cognito_sub' => $cognitoSub,
                                'global_id_set' => $targetGlobalId,
                                'action' => 'set_global_id',
                            ];
                            $results['summary']['updated_global_id']++;

                            $globalIdStr = is_string($targetGlobalId) ? $targetGlobalId : 'null';
                            Log::info("custom:global_id défini pour: {$emailStr} -> {$globalIdStr}");
                        } else {
                            $results['errors'][] = [
                                'user_id' => $userId,
                                'email' => $email,
                                'error' => 'Impossible de mettre à jour custom:global_id',
                                'error_code' => $updateResult['error_code'] ?? null,
                            ];
                            $results['summary']['errors']++;
                        }

                        $results['updated_existing'][] = [
                            'user_id' => $userId,
                            'email' => $email,
                            'cognito_sub' => $cognitoSub,
                            'cognito_username' => $cognitoUser['username'],
                        ];
                        $results['summary']['updated']++;
                    }

                } else {
                    // L'utilisateur n'existe pas dans Cognito (selon getUserByEmail), essayer de le créer
                    $userData = $this->prepareUserDataForCognito($user);

                    // Utiliser le cognito_id existant comme global_id si disponible et différent de 'xxx'
                    if ($existingCognitoId && $existingCognitoId !== 'xxx') {
                        $userData['global_id'] = $existingCognitoId;
                    }

                    $createResult = $this->createUserAndGetSub($userData);

                    if ($createResult['success']) {
                        // Mettre à jour l'ID Cognito en DB avec le vrai sub
                        if (is_string($userIdStr) && array_key_exists('sub', $createResult) && is_string($createResult['sub'])) {
                            $this->updateUserCognitoId($userIdStr, $createResult['sub']);
                        }

                        // Si pas de global_id défini, le mettre égal au sub
                        if (! array_key_exists('global_id', $userData) && (array_key_exists('username', $createResult) && is_string($createResult['username']) && array_key_exists('sub', $createResult) && is_string($createResult['sub']))) {
                            $this->updateUserAttributes($createResult['username'], [
                                'custom:global_id' => $createResult['sub'],
                            ]);
                        }

                        $results['created_new'][] = [
                            'user_id' => $userId,
                            'email' => $email,
                            'cognito_sub' => $createResult['sub'],
                            'cognito_username' => $createResult['username'],
                            'global_id' => $userData['global_id'] ?? $createResult['sub'],
                        ];
                        $results['summary']['created']++;

                        $subValue = $createResult['sub'] ?? null;
                        $subStr = is_scalar($subValue) ? (string) $subValue : 'null';
                        Log::info("Nouvel utilisateur créé: {$emailStr} -> {$subStr}");

                    } elseif ($createResult['error_code'] === 'UsernameExistsException') {
                        // L'utilisateur existe déjà, essayer de le récupérer et mettre à jour
                        Log::info("Utilisateur existant détecté, mise à jour du custom:global_id: {$emailStr}");

                        // Essayer de récupérer l'utilisateur - d'abord par username, puis par filtre email
                        $existingUser = $this->getUser($email);

                        if ($existingUser === null || $existingUser === []) {
                            // Si pas trouvé par username, chercher par attribut email
                            Log::info("Recherche par attribut email pour: {$emailStr}");
                            $searchResult = $this->findUserByEmailAttribute($email);
                            if ($searchResult && array_key_exists('user_data', $searchResult)) {
                                $existingUser = $searchResult['user_data'];
                            }
                        }

                        if ($existingUser) {
                            $existingAttributes = is_array($existingUser) && array_key_exists('Attributes', $existingUser) ? $existingUser['Attributes'] : [];
                            /** @var array<int, array<string, string>> $typedExistingAttributes */
                            $typedExistingAttributes = is_array($existingAttributes) ? $existingAttributes : [];
                            $existingSub = $this->extractAttributeValue($typedExistingAttributes, 'sub');
                            $existingGlobalId = $this->extractAttributeValue($typedExistingAttributes, 'custom:global_id');

                            // Le username dans Cognito EST le sub
                            // On doit utiliser le sub pour toutes les opérations
                            $realUsername = in_array($existingSub, [null, '', '0'], true) ? (array_key_exists('username', $searchResult ?? []) && is_array($searchResult) ? $searchResult['username'] : null) : ($existingSub);

                            if (! $realUsername) {
                                Log::error("Impossible de déterminer le username (sub) pour: {$email}");
                                $results['errors'][] = [
                                    'user_id' => $userId,
                                    'email' => $email,
                                    'error' => 'Username (sub) introuvable',
                                    'error_code' => 'NoUsername',
                                ];
                                $results['summary']['errors']++;

                                continue;
                            }

                            // Le custom:global_id DOIT être égal au sub
                            // Le cognito_id en DB DOIT être égal au sub
                            $targetGlobalId = $existingSub;
                            $needsGlobalIdUpdate = false;

                            // Mettre à jour custom:global_id si nécessaire
                            if ($targetGlobalId && (in_array($existingGlobalId, [null, '', '0'], true) || $existingGlobalId !== $targetGlobalId)) {
                                $needsGlobalIdUpdate = true;
                                $updateResult = $this->updateUserAttributes(is_string($realUsername) ? $realUsername : '', [
                                    'custom:global_id' => $targetGlobalId,
                                ]);

                                if ($updateResult['success']) {
                                    // Mettre à jour la DB avec le sub si nécessaire
                                    if ($existingSub && $existingCognitoId !== $targetGlobalId) {
                                        $this->updateUserCognitoId(is_string($userId) ? $userId : '', $targetGlobalId);
                                    }

                                    $results['updated_global_id'][] = [
                                        'user_id' => $userId,
                                        'email' => $email,
                                        'cognito_sub' => $existingSub,
                                        'global_id_set' => $targetGlobalId,
                                        'action' => 'set_global_id_for_existing',
                                        'was_null' => in_array($existingGlobalId, [null, '', '0'], true),
                                    ];
                                    $results['summary']['updated_global_id']++;

                                    Log::info("✅ custom:global_id configuré pour: {$email} -> {$targetGlobalId}");
                                } else {
                                    Log::error("Impossible de mettre à jour custom:global_id pour: {$email}");
                                    $results['errors'][] = [
                                        'user_id' => $userId,
                                        'email' => $email,
                                        'error' => 'Impossible de mettre à jour custom:global_id',
                                        'error_code' => $updateResult['error_code'] ?? null,
                                    ];
                                    $results['summary']['errors']++;
                                }
                            } elseif ($existingGlobalId === $targetGlobalId) {
                                // Le global_id est déjà correct
                                Log::info("✅ custom:global_id déjà correct pour: {$email} -> {$existingGlobalId}");
                            }

                            // Mettre à jour la DB avec le cognito_id correct si nécessaire
                            if ($targetGlobalId && $existingCognitoId !== $targetGlobalId) {
                                $userIdStr = is_scalar($userId) ? (string) $userId : '';
                                $this->updateUserCognitoId($userIdStr, $targetGlobalId);
                            }

                            $results['updated_existing'][] = [
                                'user_id' => $userId,
                                'email' => $email,
                                'cognito_sub' => $existingSub,
                                'global_id' => $targetGlobalId,
                                'action' => $needsGlobalIdUpdate ? 'updated_global_id' : 'already_synced',
                            ];
                            $results['summary']['updated']++;

                        } else {
                            Log::error("Impossible de récupérer l'utilisateur existant: {$email}");
                            $results['errors'][] = [
                                'user_id' => $userId,
                                'email' => $email,
                                'error' => 'Utilisateur existe mais impossible de le récupérer',
                                'error_code' => 'RecoveryFailed',
                            ];
                            $results['summary']['errors']++;
                        }

                    } else {
                        $results['errors'][] = [
                            'user_id' => $userId,
                            'email' => $email,
                            'error' => $createResult['error'] ?? 'Erreur inconnue',
                            'error_code' => $createResult['error_code'] ?? null,
                        ];
                        $results['summary']['errors']++;

                        $errorMessage = array_key_exists('error', $createResult) && is_scalar($createResult['error']) ? (string) $createResult['error'] : 'Erreur inconnue';
                        $emailStr2 = is_scalar($email) ? $email : 'unknown';
                        Log::error("Erreur création utilisateur: {$emailStr2} - ".$errorMessage);
                    }
                }

            } catch (Exception $e) {
                $email = is_array($user) ? ($user['email'] ?? 'N/A') : ($user->email ?? 'N/A');
                $userId = is_array($user) ? ($user['id'] ?? 'N/A') : ($user->id ?? 'N/A');

                $results['errors'][] = [
                    'user_id' => $userId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ];
                $results['summary']['errors']++;

                $emailStr3 = is_scalar($email) ? (string) $email : 'unknown';
                Log::error("Exception lors du traitement de l'utilisateur {$emailStr3}: ".$e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Met à jour le cognito_id d'un utilisateur en base de données
     */
    private function updateUserCognitoId(string $userId, string $cognitoId): void
    {
        User::where('id', $userId)->update(['cognito_id' => $cognitoId]);
    }

    /**
     * Prépare les données utilisateur pour Cognito
     *
     * @param  array<string, mixed>|User  $user
     * @return array<string, mixed>
     */
    private function prepareUserDataForCognito(array $user): array
    {
        $email = is_array($user) ? $user['email'] : $user->email;
        $firstName = is_array($user) ? ($user['first_name'] ?? null) : ($user->first_name ?? null);
        $lastName = is_array($user) ? ($user['last_name'] ?? null) : ($user->last_name ?? null);
        $name = is_array($user) ? ($user['name'] ?? null) : ($user->name ?? null);

        // Si first_name et last_name ne sont pas disponibles, essayer de les extraire du name
        if (! $firstName && ! $lastName && $name) {
            $nameParts = explode(' ', $name, 2);
            $firstName = $nameParts[0] ?? null;
            $lastName = $nameParts[1] ?? null;
        }

        return [
            'username' => $email,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'temporary_password' => $this->generateTemporaryPassword(),
        ];
    }
}
