<?php

namespace App\Http\Controllers;

use App\Events\PublicMessageEvent;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TestReverbController extends Controller
{
    public function sendMessage(): JsonResponse
    {
        $timestamp = now()->toISOString();

        try {
            // Log avant l'envoi
            Log::info('Tentative d\'envoi de message Reverb', [
                'timestamp' => $timestamp,
                'channel' => 'public-messages',
                'event' => 'public.message',
            ]);

            // Envoi de l'événement
            event(new PublicMessageEvent(
                'Test Reverb',
                "Message envoyé à {$timestamp}",
                'success'
            ));

            // Log après l'envoi
            Log::info('Message Reverb envoyé avec succès');

            return response()->json([
                'status' => 'sent',
                'timestamp' => $timestamp,
                'channel' => 'public-messages',
                'event' => 'public.message',
                'reverb_config' => [
                    'host' => config('reverb.apps.apps.0.options.host'),
                    'port' => config('reverb.apps.apps.0.options.port'),
                    'driver' => config('broadcasting.default'),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Erreur lors de l\'envoi du message Reverb', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => $timestamp,
            ], 500);
        }
    }

    public function testConnection(): JsonResponse
    {
        $results = [];

        // Test 1: Configuration
        $results['configuration'] = [
            'broadcast_driver' => config('broadcasting.default'),
            'reverb_host' => config('reverb.apps.apps.0.options.host'),
            'reverb_port' => config('reverb.apps.apps.0.options.port'),
            'reverb_scheme' => config('reverb.apps.apps.0.options.scheme'),
            'app_key_exists' => ! empty(config('broadcasting.connections.reverb.key')),
        ];

        // Test 2: Connectivité interne
        try {
            $hostConfig = config('reverb.apps.apps.0.options.host', 'reverb_engage');
            $host = is_scalar($hostConfig) ? (string) $hostConfig : 'reverb_engage';
            $portConfig = config('reverb.apps.apps.0.options.port', 8080);
            $port = is_numeric($portConfig) ? (int) $portConfig : 8080;

            $errno = 0;
            $errstr = '';
            $connection = fsockopen($host, $port, $errno, $errstr, 1);

            if ($connection) {
                $results['internal_connectivity'] = [
                    'status' => 'success',
                    'message' => "Connexion réussie à {$host}:{$port}",
                ];
                fclose($connection);
            } else {
                $results['internal_connectivity'] = [
                    'status' => 'error',
                    'message' => "Impossible de se connecter à {$host}:{$port}",
                    'error' => $errstr,
                ];
            }
        } catch (Exception $e) {
            $results['internal_connectivity'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Test 3: Redis
        try {
            $redis = app('redis');
            $redis->ping();
            $results['redis'] = [
                'status' => 'success',
                'message' => 'Redis connecté',
            ];
        } catch (Exception $e) {
            $results['redis'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        return response()->json([
            'timestamp' => now()->toISOString(),
            'tests' => $results,
            'overall_status' => $this->determineOverallStatus($results),
        ]);
    }

    /**
     * @param  array<string, mixed>  $results
     */
    private function determineOverallStatus(array $results): string
    {
        foreach ($results as $test) {
            if (is_array($test) && array_key_exists('status', $test) && $test['status'] === 'error') {
                return 'error';
            }
        }

        if (is_array($results['configuration'] ?? null) && ($results['configuration']['broadcast_driver'] ?? null) !== 'reverb') {
            return 'warning';
        }

        return 'success';
    }
}
