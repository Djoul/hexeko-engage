<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

class SlackAttachment
{
    public ?string $title = null;

    public ?string $url = null;

    public ?string $pretext = null;

    public ?string $content = null;

    public ?string $fallback = null;

    public ?string $color = null;

    /** @var array<int, array{title: string, value: string, short: bool}> */
    public array $fields = [];

    public ?string $footer = null;

    public ?string $footerIcon = null;

    public ?int $timestamp = null;

    public ?string $authorName = null;

    public ?string $authorLink = null;

    public ?string $authorIcon = null;

    public ?string $imageUrl = null;

    public ?string $thumbUrl = null;

    /** @var array<int, array<string, mixed>> */
    public array $blocks = [];

    /**
     * Set the title of the attachment.
     */
    public function title(string $title, ?string $url = null): self
    {
        $this->title = $title;
        $this->url = $url;

        return $this;
    }

    /**
     * Set the pretext of the attachment.
     */
    public function pretext(string $pretext): self
    {
        $this->pretext = $pretext;

        return $this;
    }

    /**
     * Set the content of the attachment.
     */
    public function content(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the fallback text of the attachment.
     */
    public function fallback(string $fallback): self
    {
        $this->fallback = $fallback;

        return $this;
    }

    /**
     * Set the color of the attachment.
     */
    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Add a field to the attachment.
     */
    public function field(string $title, string $content, bool $short = true): self
    {
        $this->fields[] = [
            'title' => $title,
            'value' => $content,
            'short' => $short,
        ];

        return $this;
    }

    /**
     * Add multiple fields to the attachment.
     *
     * @param  array<int, array{title: string, value: string, short: bool}>  $fields
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Set the footer of the attachment.
     */
    public function footer(string $footer, ?string $icon = null): self
    {
        $this->footer = $footer;
        $this->footerIcon = $icon;

        return $this;
    }

    /**
     * Set the timestamp of the attachment.
     */
    public function timestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Set the author of the attachment.
     */
    public function author(string $name, ?string $link = null, ?string $icon = null): self
    {
        $this->authorName = $name;
        $this->authorLink = $link;
        $this->authorIcon = $icon;

        return $this;
    }

    /**
     * Set the image of the attachment.
     */
    public function image(string $url): self
    {
        $this->imageUrl = $url;

        return $this;
    }

    /**
     * Set the thumbnail of the attachment.
     */
    public function thumb(string $url): self
    {
        $this->thumbUrl = $url;

        return $this;
    }

    /**
     * Set block elements for the attachment.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function blocks(array $blocks): self
    {
        $this->blocks = $blocks;

        return $this;
    }
}
