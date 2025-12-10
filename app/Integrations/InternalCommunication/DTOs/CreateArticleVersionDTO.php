<?php

namespace App\Integrations\InternalCommunication\DTOs;

class CreateArticleVersionDTO
{
    /**
     * @var array<string, mixed>
     */
    public $content;

    /**
     * @var int|string
     */
    public $version_number;

    /**
     * @var string|null
     */
    public $prompt;

    /**
     * @var string|null
     */
    public $title;

    /**
     * @var array<mixed, mixed>|string|null
     */
    public $llm_response;

    /**
     * @var string|null
     */
    public $article_translation_id;

    /**
     * @var string|null
     */
    public $language;

    /**
     * @var string|null
     */
    public $llm_request_id;

    /**
     * @var int|string|null
     */
    public $author_id;

    /**
     * @var int|null
     */
    public $illustration_id;

    /**
     * @param  array<string, mixed>  $content
     * @param  array<mixed, mixed>|string|null  $llm_response
     */
    public function __construct(
        array $content,
        ?string $title,
        int|string $version_number,
        ?string $prompt = null,
        array|string|null $llm_response = null,
        ?string $article_translation_id = null,
        ?string $language = null,
        ?string $llmRequestId = null,
        int|string|null $authorId = null,
        ?int $illustrationId = null
    ) {
        $this->content = $content;
        $this->title = $title;
        $this->version_number = $version_number;
        $this->prompt = $prompt;
        $this->llm_response = $llm_response;
        $this->article_translation_id = $article_translation_id;
        $this->language = $language;
        $this->llm_request_id = $llmRequestId; // string uuid
        $this->author_id = $authorId ?? Auth()->id(); // string uuid foreignn user
        $this->illustration_id = $illustrationId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {

        return new self(
            is_array($data['content']) && array_keys($data['content']) === array_filter(array_keys($data['content']), 'is_string') ? $data['content'] : [],
            (array_key_exists('title', $data) && (is_string($data['title']) || is_null($data['title']))) ? $data['title'] : '',
            (array_key_exists('version_number', $data) && is_int($data['version_number'])) ? $data['version_number'] : 1,
            (array_key_exists('prompt', $data) && (is_string($data['prompt']) || is_null($data['prompt']))) ? $data['prompt'] : '',
            (array_key_exists('llm_response', $data) && (is_string($data['llm_response']) || is_array($data['llm_response']) || is_null($data['llm_response']))) ? $data['llm_response'] : null,
            (array_key_exists('article_translation_id', $data) && (is_string($data['article_translation_id']) || is_null($data['article_translation_id']))) ? $data['article_translation_id'] : null,
            (array_key_exists('language', $data) && (is_string($data['language']) || is_null($data['language']))) ? $data['language'] : null,
            (array_key_exists('llm_request_id', $data) && (is_string($data['llm_request_id']) || is_null($data['llm_request_id']))) ? $data['llm_request_id'] : null,
            (array_key_exists('author_id', $data) && (is_string($data['author_id']) || is_null($data['author_id']))) ? $data['author_id'] : null,
            (array_key_exists('illustration_id', $data) && (is_int($data['illustration_id']) || is_null($data['illustration_id']))) ? $data['illustration_id'] : null
        );
    }
}
