<?php

namespace App\AI\Clients;

use App\AI\Contracts\LLMClientInterface;
use App\AI\DTOs\LLMResponse;
use App\AI\Exceptions\LLMClientException;
use App\Estimators\AiTokenEstimator;
use Carbon\CarbonPeriod;
use Exception;
use Generator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Client;
use OpenAI\Factory;

class OpenAIStreamerClient implements LLMClientInterface
{
    protected string $defaultModel;

    protected Client $client;

    public function __construct(
        protected AiTokenEstimator $tokenEstimator
    ) {
        $defaultProvider = config('prism.default_provider', 'openai');
        $defaultModels = config('prism.default_models.openai');
        $this->defaultModel = is_array($defaultModels) && is_string($defaultProvider) && array_key_exists(
            $defaultProvider,
            $defaultModels
        ) && is_string($defaultModels[$defaultProvider]) ? $defaultModels[$defaultProvider] : 'gpt-4o';

        $factory = new Factory;
        $apiKey = config('openai.api_key', 'test-key');
        $apiKey = is_string($apiKey) ? $apiKey : 'test-key';

        $organization = config('openai.organization');
        $organization = is_string($organization) ? $organization : null;

        $this->client = $factory
            ->withApiKey($apiKey)
            ->withOrganization($organization)
            ->withHttpHeader('OpenAI-Beta', 'assistants=v2')
            ->make();
    }

    /**
     * Envoie un prompt à OpenAI en mode streaming.
     * Retourne un générateur qui yield les chunks de texte.
     *
     * @param  array<string, mixed>  $messages
     * @param  array<string, mixed>  $params
     * @return Generator<string>
     *
     * @throws LLMClientException
     */
    public function streamPrompt(array $messages, array $params = []): Generator
    {
        $model = $params['model'] ?? $this->defaultModel;
        $temperature = $params['temperature'] ?? 0.7;
        // Use configured max_tokens from config/ai.php (default: 3000 tokens ≈ 2250 words)
        // This can be overridden per request by passing 'max_tokens' in $params
        $maxTokens = $params['max_tokens'] ?? config('ai.max_tokens', 4500);

        if (array_key_exists('messages', $messages)) {
            $openaiMessages = $messages['messages'];
        } else {
            $openaiMessages = $this->formatOpenAIMessages($messages);
        }
        try {
            $stream = $this->client->chat()->createStreamed([
                'model' => $model,
                'messages' => $openaiMessages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

            foreach ($stream as $response) {
                // Check if response is an object with the expected properties
                if (is_object($response) &&
                    property_exists($response, 'choices') &&
                    is_array($response->choices) &&
                    count($response->choices) > 0 &&
                    array_key_exists(0, $response->choices) &&
                    is_object($response->choices[0]) &&
                    property_exists($response->choices[0], 'delta') &&
                    is_object($response->choices[0]->delta) &&
                    property_exists($response->choices[0]->delta, 'content')) {

                    $content = $response->choices[0]->delta->content;
                    if ($content !== null) {
                        yield $content;
                    }
                }
            }
        } catch (Exception $e) {
            throw new LLMClientException('Erreur lors du streaming OpenAI: '.$e->getMessage());
        }
    }

    /**
     * Format messages for OpenAI API
     *
     * @param  array<string, mixed>  $messages
     * @return array<int, array<string, string>>
     */
    public function formatOpenAIMessages(array $messages): array
    {
        /** @var array<int, array<string, string>> $result */
        $result = [];

        // Check if messages is an array of arrays or a flat array
        if (array_key_exists('system_message', $messages) && is_string($messages['system_message'])) {
            // Add system message if present
            $result[] = [
                'role' => 'system',
                'content' => $messages['system_message'],
            ];

            // Remove system_message from array to process the rest
            unset($messages['system_message']);
        }

        // If we have a flat array with keys like 'user_input'
        if (array_key_exists('user_input', $messages) && is_string($messages['user_input'])) {
            $result[] = [
                'role' => 'user',
                'content' => $messages['user_input'],
            ];
        } elseif (array_key_exists('selected_text', $messages) && is_string($messages['selected_text'])) {
            $result[] = [
                'role' => 'user',
                'content' => $messages['selected_text'],
            ];
        } else {
            // Process array of message objects
            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $role = array_key_exists('role', $message) ? (string) $message['role'] : '';
                $content = array_key_exists('content', $message) ? (string) $message['content'] : '';

                if (in_array($role, ['system', 'user', 'assistant'], true) && $content !== '') {
                    $result[] = [
                        'role' => $role,
                        'content' => $content,
                    ];
                }
            }
        }

        // If no valid messages were found, add a default one
        if ($result === []) {
            $result[] = [
                'role' => 'user',
                'content' => 'No valid messages found',
            ];
        }

        return $result;
    }

    public function sendPrompt(array $messages, array $params = []): LLMResponse
    {
        throw new LLMClientException('Use streamPrompt() for streaming responses.');
    }

    public function getName(): string
    {
        return 'OpenAI';
    }

    /**
     * Get OpenAI usage costs and calculate remaining balance
     * Uses day-by-day aggregation for maximum compatibility
     *
     * @return array{total_cost: float, budget_limit: float, remaining: float, currency: string, period_start: string, period_end: string}|null
     */
    public function getBalance(): ?array
    {
        try {
            $apiKey = config('openai.api_key', 'test-key');
            if (! is_string($apiKey) || $apiKey === 'test-key') {
                return null;
            }

            // Get budget limit from config (default: 200 USD)
            $budgetLimit = config('openai.budget_limit', 200.0);
            $budgetLimit = is_numeric($budgetLimit) ? (float) $budgetLimit : 200.0;

            // Define period: start of current month to today
            $startDate = now()->startOfMonth();
            $endDate = now();

            // Create period iterator for each day
            $period = CarbonPeriod::create($startDate, $endDate);

            $totalCost = 0.0;
            // Aggregate usage day by day (robust fallback)
            foreach ($period as $day) {
                $params = ['date' => $day->toDateString()];

                // Add project_id if configured
                $projectId = config('openai.project_id');
                if (is_string($projectId) && $projectId !== '') {
                    $params['project_id'] = $projectId;
                }

                $response = Http::withHeaders([
                    'Authorization' => "Bearer $apiKey",
                ])->timeout(10)->get('https://api.openai.com/v1/usage', $params);

                // Skip failed requests (continue aggregation)
                if (! $response->successful()) {
                    continue;
                }

                $data = $response->json();

                if (! is_array($data)) {
                    continue;
                }

                // Extract costs from daily usage data
                // Structure: data[] -> costs -> usd
                if (array_key_exists('data', $data) && is_array($data['data'])) {
                    foreach ($data['data'] as $row) {
                        if (is_array($row)) {
                            // Try different possible structures
                            if (array_key_exists('costs', $row) && is_array($row['costs'])) {
                                $cost = is_numeric($row['costs']['usd'] ?? 0) ? (float) $row['costs']['usd'] : 0.0;
                                $totalCost += $cost;
                            } elseif (array_key_exists('cost', $row)) {
                                $cost = is_numeric($row['cost']) ? (float) $row['cost'] : 0.0;
                                $totalCost += $cost;
                            }
                        }
                    }
                }
            }
            $remaining = $budgetLimit - $totalCost;

            return [
                'total_cost' => $totalCost,
                'budget_limit' => $budgetLimit,
                'remaining' => $remaining,
                'currency' => 'USD',
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch OpenAI usage', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
