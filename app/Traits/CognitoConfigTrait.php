<?php

namespace App\Traits;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use UnexpectedValueException;

trait CognitoConfigTrait
{
    /**
     * @var array{region: string, client_id: string, client_secret: string, user_pool_id: string}
     */
    protected array $config;

    protected CognitoIdentityProviderClient $client;

    public function __construct()
    {
        $this->setCognitoConfig();
        $this->initCognitoIdentityProviderClient();
    }

    protected function setCognitoConfig(): void
    {
        $config = config('services.cognito');

        if (
            ! is_array($config) ||
            ! array_key_exists('region', $config) ||
            ! array_key_exists('client_id', $config) ||
            ! array_key_exists('client_secret', $config) ||
            ! array_key_exists('user_pool_id', $config) ||
            ! is_string($config['region']) ||
            ! is_string($config['client_id']) ||
            ! is_string($config['client_secret']) ||
            ! is_string($config['user_pool_id'])
        ) {
            throw new UnexpectedValueException('Invalid Cognito configuration.');
        }

        $this->config = $config;
    }

    protected function initCognitoIdentityProviderClient(): void
    {
        $this->client = new CognitoIdentityProviderClient([
            'region' => $this->config['region'],
            'version' => 'latest',
            /*'credentials' => [
                'key' => $this->config['client_id'],
                'secret' => $this->config['client_secret'],
            ],*/
        ]);
    }
}
