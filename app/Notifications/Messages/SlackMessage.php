<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use Closure;

class SlackMessage
{
    public string $content = '';

    public ?string $channel = null;

    public ?string $username = null;

    public ?string $icon = null;

    /** @var array<int, SlackAttachment> */
    public array $attachments = [];

    public bool $linkNames = false;

    public bool $unfurlLinks = false;

    public bool $unfurlMedia = false;

    /** @var array<string, mixed> */
    public array $http = [];

    /**
     * Set the Slack channel the message should be sent to.
     */
    public function to(string $channel): self
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Set the content of the Slack message.
     */
    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the username the message should be sent from.
     */
    public function from(string $username, ?string $icon = null): self
    {
        $this->username = $username;

        if (! is_null($icon)) {
            $this->icon = $icon;
        }

        return $this;
    }

    /**
     * Set the icon emoji of the Slack message.
     */
    public function image(string $image): self
    {
        $this->icon = $image;

        return $this;
    }

    /**
     * Add an attachment to the message.
     */
    public function attachment(Closure $callback): self
    {
        $attachment = new SlackAttachment;
        $callback($attachment);
        $this->attachments[] = $attachment;

        return $this;
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, SlackAttachment>
     */
    public function attachments(): array
    {
        return $this->attachments;
    }

    /**
     * Enable link names for the message.
     */
    public function linkNames(): self
    {
        $this->linkNames = true;

        return $this;
    }

    /**
     * Enable unfurling of links for the message.
     */
    public function unfurlLinks(bool $unfurlLinks = true): self
    {
        $this->unfurlLinks = $unfurlLinks;

        return $this;
    }

    /**
     * Enable unfurling of media for the message.
     */
    public function unfurlMedia(bool $unfurlMedia = true): self
    {
        $this->unfurlMedia = $unfurlMedia;

        return $this;
    }

    /**
     * Set additional HTTP options for the message.
     *
     * @param  array<string, mixed>  $options
     */
    public function http(array $options): self
    {
        $this->http = $options;

        return $this;
    }
}
