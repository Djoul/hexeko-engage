<?php

namespace App\Providers;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;

class MinioFilesystemServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Storage::extend('minio', function ($app, array $config): \Illuminate\Filesystem\AwsS3V3Adapter {
            // For internal operations, use the internal endpoint
            $s3Config = [
                'credentials' => [
                    'key' => $config['key'],
                    'secret' => $config['secret'],
                ],
                'region' => $config['region'],
                'version' => 'latest',
                'endpoint' => $config['endpoint'],
                'use_path_style_endpoint' => $config['use_path_style_endpoint'] ?? true,
            ];

            $client = new S3Client($s3Config);

            // Create adapter with internal client
            $adapter = new AwsS3V3Adapter(
                $client,
                $config['bucket'],
                $config['root'] ?? '',
            );

            // Create filesystem
            $filesystem = new Filesystem($adapter, $config);

            // For temporary URLs, we need to use the external endpoint
            // We'll create a second client configured for external access
            if (array_key_exists('external_endpoint', $config)) {
                $externalConfig = $s3Config;
                $externalConfig['endpoint'] = $config['external_endpoint'];

                $externalClient = new S3Client($externalConfig);

                // Store the external client in the config for later use
                $config['external_client'] = $externalClient;
            }

            // Return custom storage adapter
            return new class($filesystem, $adapter, $config, $client) extends \Illuminate\Filesystem\AwsS3V3Adapter
            {
                protected ?S3Client $externalClient = null;

                protected string $bucket;

                /** @var array<string, mixed> */
                protected array $customConfig;

                /**
                 * @param  array<string, mixed>  $config
                 */
                public function __construct(FilesystemOperator $filesystem, AwsS3V3Adapter $adapter, array $config, S3Client $client)
                {
                    parent::__construct($filesystem, $adapter, $config, $client);
                    $this->externalClient = array_key_exists('external_client', $config) && $config['external_client'] instanceof S3Client
                        ? $config['external_client']
                        : null;
                    $this->bucket = is_string($config['bucket']) ? $config['bucket'] : '';
                    $this->customConfig = $config;
                }

                /**
                 * @param  array<string, mixed>  $options
                 */
                public function temporaryUrl($path, $expiration, array $options = [])
                {
                    // If we have an external client, use it for generating URLs
                    if ($this->externalClient instanceof S3Client) {
                        $command = $this->externalClient->getCommand('GetObject', array_merge([
                            'Bucket' => $this->bucket,
                            'Key' => $this->prefixer->prefixPath($path),
                        ], $options));

                        $uri = $this->externalClient->createPresignedRequest(
                            $command,
                            $expiration,
                            $options
                        )->getUri();

                        return (string) $uri;
                    }

                    // Otherwise, use the parent implementation
                    return parent::temporaryUrl($path, $expiration, $options);
                }
            };
        });
    }
}
