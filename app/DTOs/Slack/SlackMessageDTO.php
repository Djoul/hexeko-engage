<?php

declare(strict_types=1);

namespace App\DTOs\Slack;

use Illuminate\Support\Collection;

final class SlackMessageDTO
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $channel = null,
        public readonly ?string $threadTs = null,
        /** @var array<int, mixed>|null */
        public readonly ?array $blocks = null,
        /** @var array<int, mixed>|null */
        public readonly ?array $attachments = null,
        public readonly bool $mrkdwn = true,
        public readonly bool $unfurlLinks = false,
        public readonly bool $unfurlMedia = false,
        public readonly ?string $iconEmoji = null,
        public readonly ?string $username = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            text: is_string($data['text']) ? $data['text'] : '',
            channel: array_key_exists('channel', $data) && is_string($data['channel']) ? $data['channel'] : null,
            threadTs: array_key_exists('thread_ts', $data) && is_string($data['thread_ts']) ? $data['thread_ts'] : null,
            blocks: array_key_exists('blocks', $data) && is_array($data['blocks']) ? self::castBlocks($data['blocks']) : null,
            attachments: array_key_exists('attachments', $data) && is_array($data['attachments']) ? self::castAttachments($data['attachments']) : null,
            mrkdwn: array_key_exists('mrkdwn', $data) && is_bool($data['mrkdwn']) ? $data['mrkdwn'] : true,
            unfurlLinks: array_key_exists('unfurl_links', $data) && is_bool($data['unfurl_links']) && $data['unfurl_links'],
            unfurlMedia: array_key_exists('unfurl_media', $data) && is_bool($data['unfurl_media']) && $data['unfurl_media'],
            iconEmoji: array_key_exists('icon_emoji', $data) && is_string($data['icon_emoji']) ? $data['icon_emoji'] : null,
            username: array_key_exists('username', $data) && is_string($data['username']) ? $data['username'] : null,
        );
    }

    public static function simple(string $text, ?string $channel = null): self
    {
        return new self(text: $text, channel: $channel);
    }

    public static function thread(string $text, string $threadTs, ?string $channel = null): self
    {
        return new self(text: $text, channel: $channel, threadTs: $threadTs);
    }

    /**
     * @param  array<int, mixed>  $blocks
     */
    public static function rich(array $blocks, string $fallbackText, ?string $channel = null): self
    {
        return new self(
            text: $fallbackText,
            channel: $channel,
            blocks: $blocks
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['text' => $this->text];

        if ($this->channel !== null) {
            $data['channel'] = $this->channel;
        }

        if ($this->threadTs !== null) {
            $data['thread_ts'] = $this->threadTs;
        }

        if ($this->blocks !== null) {
            $data['blocks'] = $this->blocks;
        }

        if ($this->attachments !== null) {
            $data['attachments'] = $this->attachments;
        }

        if (! $this->mrkdwn) {
            $data['mrkdwn'] = false;
        }

        if ($this->unfurlLinks) {
            $data['unfurl_links'] = true;
        }

        if ($this->unfurlMedia) {
            $data['unfurl_media'] = true;
        }

        if ($this->iconEmoji !== null) {
            $data['icon_emoji'] = $this->iconEmoji;
        }

        if ($this->username !== null) {
            $data['username'] = $this->username;
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $userIds
     */
    public function withMentions(array $userIds): self
    {
        $mentions = Collection::make($userIds)
            ->map(fn (string $userId): string => "<@{$userId}>")
            ->implode(' ');

        $textWithMentions = "{$mentions} {$this->text}";

        return new self(
            text: $textWithMentions,
            channel: $this->channel,
            threadTs: $this->threadTs,
            blocks: $this->blocks,
            attachments: $this->attachments,
            mrkdwn: $this->mrkdwn,
            unfurlLinks: $this->unfurlLinks,
            unfurlMedia: $this->unfurlMedia,
            iconEmoji: $this->iconEmoji,
            username: $this->username,
        );
    }

    /**
     * @param  array<mixed, mixed>  $blocks
     * @return array<int, mixed>
     */
    private static function castBlocks(array $blocks): array
    {
        return $blocks;
    }

    /**
     * @param  array<mixed, mixed>  $attachments
     * @return array<int, mixed>
     */
    private static function castAttachments(array $attachments): array
    {
        return $attachments;
    }
}
