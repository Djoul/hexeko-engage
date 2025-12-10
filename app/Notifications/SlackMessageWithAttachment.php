<?php

declare(strict_types=1);

namespace App\Notifications;

use App\DTOs\Slack\SlackFileDTO;
use App\Exceptions\SlackException;
use App\Notifications\Messages\SlackMessage;
use App\Services\SlackService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SlackMessageWithAttachment extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $message;

    public readonly ?string $filePath;

    public readonly ?string $fileName;

    public readonly ?string $title;

    public readonly ?string $channel;

    public readonly ?string $threadTs;

    /** @var array<int, mixed>|null */
    public readonly ?array $blocks;

    /** @var array<int, string>|null */
    public readonly ?array $userMentions;

    /**
     * @param  array<int, mixed>|null  $blocks
     * @param  array<int, string>|null  $userMentions
     */
    public function __construct(
        string $message,
        ?string $filePath = null,
        ?string $fileName = null,
        ?string $title = null,
        ?string $channel = null,
        ?string $threadTs = null,
        ?array $blocks = null,
        ?array $userMentions = null
    ) {
        $this->message = $message;
        $this->filePath = $filePath;
        $this->fileName = $fileName ?? (in_array($filePath, [null, '', '0'], true) ? null : basename($filePath));
        $this->title = $title;
        $this->channel = $channel;
        $this->threadTs = $threadTs;
        $this->blocks = $blocks;
        $this->userMentions = $userMentions;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(mixed $notifiable): SlackMessage
    {
        $channel = $this->channel ?? config('services.slack.notifications.channel');

        // Create base Slack message
        $slackMessage = (new SlackMessage)
            ->content($this->message)
            ->to(is_string($channel) ? $channel : '');

        // Add blocks if provided
        if ($this->blocks !== null && $this->blocks !== []) {
            $slackMessage->attachment(function ($attachment): void {
                $attachment->blocks($this->blocks);
            });
        }

        // Handle file upload separately if provided
        if (! in_array($this->filePath, [null, '', '0'], true)) {
            try {
                $this->uploadFileToSlack();
            } catch (SlackException $e) {
                Log::error('Failed to upload file to Slack', [
                    'error' => $e->getMessage(),
                    'file' => $this->filePath,
                ]);
            }
        }

        return $slackMessage;
    }

    /**
     * Upload file to Slack using the SlackService
     *
     * @return array<string, mixed>
     */
    protected function uploadFileToSlack(): array
    {
        $slackService = app(SlackService::class);

        try {
            $channel = $this->channel ?? config('services.slack.notifications.channel');
            $fileDto = new SlackFileDTO(
                filePath: $this->filePath ?? '',
                filename: $this->fileName,
                title: $this->title,
                initialComment: $this->message,
                channel: is_string($channel) ? $channel : null,
                threadTs: $this->threadTs
            );

            $response = $slackService->upload($fileDto);

            if (! $response['ok']) {
                Log::error('Erreur upload Slack', ['response' => $response]);
            }

            return $response;
        } catch (Exception $e) {
            Log::error('Exception during Slack file upload', [
                'error' => $e->getMessage(),
                'file' => $this->filePath,
            ]);
            throw $e;
        }
    }

    /**
     * Create a simple text notification
     */
    public static function text(string $message, ?string $channel = null): self
    {
        return new self($message, null, null, null, $channel);
    }

    /**
     * Create a notification with file attachment
     */
    public static function withFile(
        string $message,
        string $filePath,
        ?string $title = null,
        ?string $channel = null
    ): self {
        return new self($message, $filePath, null, $title, $channel);
    }

    /**
     * Create a thread reply notification
     */
    public static function thread(
        string $message,
        string $threadTs,
        ?string $channel = null,
        ?string $filePath = null
    ): self {
        return new self($message, $filePath, null, null, $channel, $threadTs);
    }

    /**
     * Create a rich notification with blocks
     */
    public static function rich(
        string $message,
        array $blocks,
        ?string $channel = null,
        ?string $filePath = null
    ): self {
        return new self($message, $filePath, null, null, $channel, null, $blocks);
    }

    /**
     * Create a notification with user mentions
     *
     * @param  array<int, string>  $userIds
     */
    public static function withMentions(
        string $message,
        array $userIds,
        ?string $channel = null
    ): self {
        return new self($message, null, null, null, $channel, null, null, $userIds);
    }
}
