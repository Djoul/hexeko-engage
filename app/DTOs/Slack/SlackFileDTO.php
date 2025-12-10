<?php

declare(strict_types=1);

namespace App\DTOs\Slack;

use RuntimeException;

final class SlackFileDTO
{
    public function __construct(
        public readonly string $filePath,
        public readonly ?string $filename = null,
        public readonly ?string $title = null,
        public readonly ?string $initialComment = null,
        public readonly ?string $channel = null,
        public readonly ?string $threadTs = null,
        public readonly ?string $fileType = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        $filePath = array_key_exists('file_path', $data) && is_string($data['file_path']) ? $data['file_path'] : '';
        $filename = array_key_exists('filename', $data) && is_string($data['filename']) ? $data['filename'] : null;
        $title = array_key_exists('title', $data) && is_string($data['title']) ? $data['title'] : null;
        $initialComment = array_key_exists('initial_comment', $data) && is_string($data['initial_comment']) ? $data['initial_comment'] : null;
        $channel = array_key_exists('channel', $data) && is_string($data['channel']) ? $data['channel'] : null;
        $threadTs = array_key_exists('thread_ts', $data) && is_string($data['thread_ts']) ? $data['thread_ts'] : null;
        $fileType = array_key_exists('file_type', $data) && is_string($data['file_type']) ? $data['file_type'] : null;

        return new self(
            filePath: $filePath,
            filename: $filename,
            title: $title,
            initialComment: $initialComment,
            channel: $channel,
            threadTs: $threadTs,
            fileType: $fileType,
        );
    }

    public static function create(
        string $filePath,
        ?string $message = null,
        ?string $channel = null,
        ?string $title = null
    ): self {
        return new self(
            filePath: $filePath,
            filename: basename($filePath),
            title: $title,
            initialComment: $message,
            channel: $channel,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->channel !== null) {
            $data['channels'] = $this->channel;
        }

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->initialComment !== null) {
            $data['initial_comment'] = $this->initialComment;
        }

        if ($this->threadTs !== null) {
            $data['thread_ts'] = $this->threadTs;
        }

        if ($this->fileType !== null) {
            $data['filetype'] = $this->fileType;
        }

        return $data;
    }

    public function getFilename(): string
    {
        return $this->filename ?? basename($this->filePath);
    }

    public function exists(): bool
    {
        return file_exists($this->filePath);
    }

    public function getContent(): string
    {
        if (! $this->exists()) {
            throw new RuntimeException("File does not exist: {$this->filePath}");
        }

        $content = file_get_contents($this->filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$this->filePath}");
        }

        return $content;
    }
}
