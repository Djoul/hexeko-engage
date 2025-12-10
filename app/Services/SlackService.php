<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Slack\SlackFileDTO;
use App\DTOs\Slack\SlackMessageDTO;
use App\Exceptions\SlackException;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlackService
{
    private const SLACK_API_BASE = 'https://slack.com/api';

    private const TIMEOUT = 30;

    protected readonly string $token;

    protected readonly string $defaultChannel;

    protected readonly ?string $defaultUsername;

    protected readonly ?string $defaultIconEmoji;

    public function __construct()
    {
        $tokenConfig = config('services.slack.notifications.bot_user_oauth_token', '');
        $this->token = is_string($tokenConfig) ? $tokenConfig : '';

        $channelConfig = config('services.slack.notifications.channel', '#general');
        $this->defaultChannel = is_string($channelConfig) ? $channelConfig : '#general';

        $usernameConfig = config('services.slack.notifications.username');
        $this->defaultUsername = is_string($usernameConfig) ? $usernameConfig : null;

        $iconConfig = config('services.slack.notifications.icon_emoji');
        $this->defaultIconEmoji = is_string($iconConfig) ? $iconConfig : null;
    }

    /**
     * Send a simple text message
     */
    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $message, ?string $channel = null): array
    {
        $dto = SlackMessageDTO::simple($message, $channel ?? $this->defaultChannel);

        return $this->send($dto);
    }

    /**
     * Send a message using DTO for full control
     */
    /**
     * @return array<string, mixed>
     */
    public function send(SlackMessageDTO $message): array
    {
        $data = $message->toArray();

        if (! array_key_exists('channel', $data)) {
            $data['channel'] = $this->defaultChannel;
        }

        if ($this->defaultUsername !== null && ! array_key_exists('username', $data)) {
            $data['username'] = $this->defaultUsername;
        }

        if ($this->defaultIconEmoji !== null && ! array_key_exists('icon_emoji', $data)) {
            $data['icon_emoji'] = $this->defaultIconEmoji;
        }

        $response = $this->client()
            ->post('/chat.postMessage', $data);

        return $this->handleResponse($response);
    }

    /**
     * Send a message to a thread
     */
    /**
     * @return array<string, mixed>
     */
    public function sendToThread(string $message, string $threadTs, ?string $channel = null): array
    {
        $dto = SlackMessageDTO::thread($message, $threadTs, $channel ?? $this->defaultChannel);

        return $this->send($dto);
    }

    /**
     * Send a rich message with blocks
     */
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<string, mixed>
     */
    public function sendRichMessage(array $blocks, ?string $channel = null, ?string $fallbackText = null): array
    {
        $dto = SlackMessageDTO::rich(
            $blocks,
            $fallbackText ?? 'New message',
            $channel ?? $this->defaultChannel
        );

        return $this->send($dto);
    }

    /**
     * Send a message with user mentions
     */
    /**
     * @param  array<int, string>  $userIds
     * @return array<string, mixed>
     */
    public function sendWithMentions(string $message, array $userIds, ?string $channel = null): array
    {
        $dto = SlackMessageDTO::simple($message, $channel ?? $this->defaultChannel)
            ->withMentions($userIds);

        return $this->send($dto);
    }

    /**
     * Upload a file to Slack
     */
    /**
     * @return array<string, mixed>
     */
    public function uploadFile(
        string $filePath,
        ?string $message = null,
        ?string $channel = null,
        ?string $title = null
    ): array {
        $dto = SlackFileDTO::create($filePath, $message, $channel ?? $this->defaultChannel, $title);

        return $this->upload($dto);
    }

    /**
     * Upload file using DTO for full control
     */
    /**
     * @return array<string, mixed>
     */
    public function upload(SlackFileDTO $file): array
    {
        if (! $file->exists()) {
            throw new SlackException("File does not exist: {$file->filePath}");
        }

        $response = $this->client()
            ->attach('file', $file->getContent(), $file->getFilename())
            ->post('/files.upload', $file->toArray());

        return $this->handleResponse($response);
    }

    /**
     * Send a message with file attachment
     */
    /**
     * @return array<string, mixed>
     */
    public function sendMessageWithFile(
        string $message,
        string $filePath,
        ?string $channel = null,
        ?string $title = null
    ): array {
        return $this->uploadFile($filePath, $message, $channel, $title);
    }

    /**
     * Send to a private channel
     */
    /**
     * @return array<string, mixed>
     */
    public function sendToPrivateChannel(string $message, string $channelId): array
    {
        // Private channels should use ID, not name
        if (! str_starts_with($channelId, 'C') && ! str_starts_with($channelId, 'G')) {
            throw new SlackException('Private channel must be specified by ID (starts with C or G)');
        }

        return $this->sendMessage($message, $channelId);
    }

    /**
     * Send to a public channel
     */
    /**
     * @return array<string, mixed>
     */
    public function sendToPublicChannel(string $message, string $channelName): array
    {
        // Ensure channel name has # prefix
        $channel = str_starts_with($channelName, '#') ? $channelName : "#{$channelName}";

        return $this->sendMessage($message, $channel);
    }

    /**
     * Send a direct message to a user
     */
    /**
     * @return array<string, mixed>
     */
    public function sendDirectMessage(string $message, string $userId): array
    {
        // Direct messages use user ID
        if (! str_starts_with($userId, 'U') && ! str_starts_with($userId, '@')) {
            $userId = "@{$userId}";
        }

        return $this->sendMessage($message, $userId);
    }

    /**
     * Get list of channels
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getChannels(bool $excludeArchived = true): Collection
    {
        try {
            // Try conversations.list first (newer API)
            $response = $this->client()
                ->get('/conversations.list', [
                    'exclude_archived' => $excludeArchived,
                    'types' => 'public_channel',
                    'limit' => 1000,
                ]);

            $data = $this->handleResponse($response);

            /** @var array<int, array<string, mixed>> $conversations */
            $conversations = $data['conversations'] ?? [];

            return collect($conversations);
        } catch (SlackException $e) {
            // If missing_scope, fallback to channels.list (legacy but works with channels:read)
            if (str_contains($e->getMessage(), 'missing_scope')) {
                try {
                    $response = $this->client()
                        ->get('/channels.list', [
                            'exclude_archived' => $excludeArchived,
                            'limit' => 1000,
                        ]);

                    $data = $this->handleResponse($response);

                    /** @var array<int, array<string, mixed>> $channels */
                    $channels = $data['channels'] ?? [];

                    return collect($channels);
                } catch (Exception $fallbackError) {
                    // If both fail, return empty collection with warning
                    Log::warning('Unable to fetch channels', [
                        'error' => $fallbackError->getMessage(),
                    ]);

                    return collect([]);
                }
            }

            throw $e;
        }
    }

    /**
     * Get list of users
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getUsers(): Collection
    {
        $response = $this->client()
            ->get('/users.list', [
                'limit' => 1000,
            ]);

        $data = $this->handleResponse($response);

        /** @var array<int, array<string, mixed>> $members */
        $members = $data['members'] ?? [];
        /** @var Collection<int, array<string, mixed>> $filtered */
        $filtered = collect($members)
            ->filter(fn ($user): bool => ! $user['deleted'] && ! $user['is_bot']);

        return $filtered;
    }

    /**
     * Test the connection to Slack
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->client()->get('/auth.test');
            $data = $this->handleResponse($response);

            return $data['ok'] === true;
        } catch (Exception $e) {
            Log::error('Slack connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Create the HTTP client for Slack API
     */
    protected function client(): PendingRequest
    {
        return Http::withToken($this->token)
            ->baseUrl(self::SLACK_API_BASE)
            ->timeout(self::TIMEOUT)
            ->retry(3, 100);
    }

    /**
     * Handle Slack API response
     */
    /**
     * @return array<string, mixed>
     */
    protected function handleResponse(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            Log::error('Invalid Slack API response', ['response' => $response->body()]);
            throw new SlackException('Invalid response from Slack API');
        }

        if (! ($data['ok'] ?? false)) {
            $error = $data['error'] ?? 'Unknown error';
            Log::error('Slack API error', [
                'error' => $error,
                'response' => $data,
            ]);
            throw new SlackException("Slack API error: {$error}");
        }

        return $data;
    }
}
