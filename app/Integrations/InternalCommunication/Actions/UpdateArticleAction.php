<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Actions;

use App;
use App\Integrations\InternalCommunication\DTOs\CreateArticleVersionDTO;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Integrations\InternalCommunication\Services\ArticleService;
use App\Models\LLMRequest;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Str;

class UpdateArticleAction
{
    public function __construct(
        protected CreateArticleVersionAction $createArticleVersionAction,
        protected ArticleService $articleService,
        protected Request $request
    ) {}

    /**
     * Handle the update of an article.
     *
     * @param  Article  $article  The article to update
     * @param  array<string, mixed>  $payload  The payload containing update data
     * @return Article The updated article
     */
    public function handle(Article $article, array $payload): Article
    {
        return DB::transaction(function () use ($article, $payload): Article {
            // Check for illustration_version_number and remove it from payload
            $illustrationVersionNumber = $payload['illustration_version_number'] ?? null;
            unset($payload['illustration_version_number']);

            // Extract fields from the request
            $extractedIllustration = $this->extractIllustration($payload);
            $illustration = $extractedIllustration['illustration'];
            $data = $extractedIllustration['data'];

            $extractedTranslationData = $this->extractTranslationData($data);
            $translationData = $extractedTranslationData['translationData'];
            $data = $extractedTranslationData['data'];

            $extractedLLMResponse = $this->extractLLMResponse($data);
            $llmResponse = $extractedLLMResponse['llmResponse'];
            $data = $extractedLLMResponse['data'];

            // DEBUG: Log payload analysis for orphan versions investigation
            Log::info('[UpdateArticleAction] Payload analysis', [
                'article_id' => $article->id,
                'has_illustration_key' => array_key_exists('illustration', $payload),
                'illustration_value' => $payload['illustration'] ?? 'NOT_SET',
                'illustration_is_null' => array_key_exists('illustration', $payload) ? is_null($payload['illustration']) : 'KEY_NOT_SET',
                'has_llm_response' => $llmResponse !== null,
                'llm_response_length' => $llmResponse ? strlen($llmResponse) : 0,
            ]);

            $this->updateArticleBaseData($article, $data);

            // Handle illustration_version_number if provided
            if ($illustrationVersionNumber !== null && is_numeric($illustrationVersionNumber)) {
                $this->changeActiveIllustrationByVersionNumber($article, (int) $illustrationVersionNumber);
                $article->load('media');
                $illustrationUpdated = true;
            } else {
                $illustrationUpdated = (is_string($illustration) && ! Str::isUrl($illustration, ['http', 'https']))
                    || (Str::isUrl($illustration, ['http', 'https']) && $article->getMedia('illustration')->where('custom_properties.active', true)->first()?->id != request('media_id', '0'))
                    || array_key_exists('illustration', $payload) && is_null($illustration);

                if ($illustrationUpdated) {
                    $this->articleService->updateIllustration($article, $illustration);
                    $article->load('media');
                }
            }

            // DEBUG: Log illustration update decision
            Log::info('[UpdateArticleAction] Illustration check', [
                'article_id' => $article->id,
                'illustrationUpdated' => $illustrationUpdated,
                'illustration_value' => $illustration,
                'current_media_count' => $article->getMedia('illustration')->count(),
                'current_active_media' => $article->getMedia('illustration')->where('custom_properties.active', true)->first()?->id,
            ]);

            if (is_array($translationData['tags'])) {

                $article->tags()->sync($translationData['tags']);
            }

            $result = $this->updateOrCreateTranslation($article, $translationData, $illustrationUpdated);
            $translation = $result['translation'];
            $contentModified = $result['contentModified'];

            if ($translation instanceof ArticleTranslation) {
                // LLMRequest is now created in ArticleChatController via SaveLLMRequestAction
                // to avoid duplicate entries and ensure proper token counting

                // DEBUG: Log version creation decision
                $isArticleGeneration = $this->isArticleGenerationResponse($llmResponse);

                // Determine if we should create a version:
                // 1. Manual modifications (no LLM response) → always create version
                // 2. Article generation (4-tag XML) → always create version
                // 3. Conversational messages (2-tag XML) → NEVER create version (even if contentModified is true)
                $shouldCreateVersion = ($contentModified && $llmResponse === null) || $isArticleGeneration;

                Log::info('[UpdateArticleAction] Version creation decision', [
                    'article_id' => $article->id,
                    'translation_id' => $translation->id,
                    'contentModified' => $contentModified,
                    'illustrationUpdated' => $illustrationUpdated,
                    'llmResponse_is_null' => is_null($llmResponse),
                    'llmResponse_length' => $llmResponse ? strlen($llmResponse) : 0,
                    'isArticleGeneration' => $isArticleGeneration,
                    'will_create_version' => $shouldCreateVersion,
                    'result_from_updateOrCreate' => $result,
                ]);

                // Create versions only for content modifications or article generations (4-tag XML)
                // Conversational messages (2-tag XML) should NOT create versions
                if ($shouldCreateVersion) {
                    $llmRequestId = null;

                    // If llmResponse is provided, find the corresponding LLMRequest
                    if ($llmResponse !== null) {
                        $llmRequest = LLMRequest::where('requestable_type', ArticleTranslation::class)
                            ->where('requestable_id', $translation->id)
                            ->where('response', $llmResponse)
                            ->first();

                        $llmRequestId = $llmRequest?->id;
                    }

                    $this->createVersion($article, $translation, $llmRequestId, $llmResponse);
                }

                $this->logArticleUpdate($article, $translation);
            }

            if (array_key_exists('segment_id', $payload) && $payload['segment_id'] !== null) {
                $article->segment_id = $payload['segment_id'];
                $article->save();
            }

            return $article->refresh();
        });
    }

    /**
     * Create a new version of the article.
     *
     * @param  Article  $article  The article to create a version for
     * @param  ArticleTranslation|null  $translation  The translation to use
     * @param  string|null  $llmRequestId  The LLM request ID
     * @param  string|null  $llmResponse  The LLM response
     */
    protected function createVersion(Article $article, ?ArticleTranslation $translation = null, ?string $llmRequestId = null, ?string $llmResponse = null): void
    {
        // Ensure we have a translation
        if (! $translation instanceof ArticleTranslation) {
            $translation = $this->getFirstTranslationTyped($article);
        }

        if (! $translation instanceof ArticleTranslation) {
            return; // No translation available
        }

        $nextVersion = $this->calculateNextVersionNumber($translation);
        $content = $this->getTranslationContent($translation);
        $title = $this->getTranslationTitle($translation);
        $language = $this->determineLanguage($translation);
        $prompt = $this->getPromptFromRequest();
        $authId = Auth()->id();

        $dto = new CreateArticleVersionDTO(
            $content,
            $title,
            $nextVersion,
            $prompt,
            $llmResponse,
            (string) $translation->id,
            $language,
            $llmRequestId,
            $authId,
            $article->fresh()?->getMedia('illustration')->where('custom_properties.active', true)->first()?->id
        );

        $this->createArticleVersionAction->handle($article, $dto);
    }

    /**
     * Calculate the next version number for a translation.
     */
    private function calculateNextVersionNumber(ArticleTranslation $translation): int
    {
        $maxVersion = $translation->versions()->max('version_number') ?? 0;

        return (is_int($maxVersion) ? $maxVersion : 0) + 1;
    }

    /**
     * Get the content from a translation.
     *
     * @return array<string, mixed>
     */
    private function getTranslationContent(ArticleTranslation $translation): array
    {
        return is_array($translation->content) ? $translation->content : [];
    }

    /**
     * Get the title from a translation.
     */
    private function getTranslationTitle(ArticleTranslation $translation): string
    {
        return is_string($translation->title) ? $translation->title : '';
    }

    /**
     * Determine the language to use for the version.
     */
    private function determineLanguage(ArticleTranslation $translation): string
    {
        if ($this->request->has('language')) {
            $requestLanguage = $this->request->language;
            if (is_string($requestLanguage)) {
                return $requestLanguage;
            }
            if (is_numeric($requestLanguage) || is_bool($requestLanguage)) {
                return (string) $requestLanguage;
            }
        }

        return $translation->language;
    }

    /**
     * Get the prompt from the request.
     */
    private function getPromptFromRequest(): ?string
    {
        if (! $this->request->has('prompt')) {
            return null;
        }

        $prompt = $this->request->prompt;
        if (is_string($prompt)) {
            return $prompt;
        }

        if (is_numeric($prompt) || is_bool($prompt)) {
            return (string) $prompt;
        }

        return null;
    }

    /**
     * Determine if the LLM response is an article generation (4-tag XML) or a conversational message (2-tag XML).
     *
     * Article generation (4-tag): <response><opening>...</opening><title>...</title><content>...</content><closing>...</closing></response>
     * Conversational message (2-tag): <response><opening>...</opening><closing>...</closing></response>
     *
     * @param  string|null  $llmResponse  The LLM response XML
     * @return bool True if article generation (4-tag), false if conversational (2-tag) or null
     */
    private function isArticleGenerationResponse(?string $llmResponse): bool
    {
        if ($llmResponse === null) {
            return false;
        }

        try {
            // Suppress XML errors and use internal error handling
            libxml_use_internal_errors(true);

            // Wrap in root element if not already wrapped (LLM may return multiple root-level tags)
            $wrappedXml = '<root>'.$llmResponse.'</root>';
            $xml = simplexml_load_string($wrappedXml);

            // Clear XML errors
            libxml_clear_errors();

            if ($xml === false) {
                return false;
            }

            // Article generation = presence of both <title> AND <content>
            // Note: <content> may contain HTML child nodes (e.g., <h2>, <p>), so check for both text content and child nodes
            // Check if wrapped in <response> tag first
            $targetXml = property_exists($xml, 'response') ? $xml->response : $xml;

            $hasTitle = property_exists($targetXml, 'title') && $targetXml->title !== null && ! empty((string) $targetXml->title);
            $hasContent = property_exists($targetXml, 'content') && $targetXml->content !== null && (! empty((string) $targetXml->content) || $targetXml->content->count() > 0);

            return $hasTitle && $hasContent;
        } catch (Exception $e) {
            // If XML parsing fails, consider it as non-article generation
            Log::warning('[UpdateArticleAction] Failed to parse LLM response XML', [
                'error' => $e->getMessage(),
                'llm_response_length' => strlen($llmResponse),
            ]);

            return false;
        }
    }

    private function getFirstTranslationTyped(Article $article): ?ArticleTranslation
    {
        $first = $article->translations()->first();

        return $first instanceof ArticleTranslation ? $first : null;
    }

    /**
     * Extract illustration from data.
     *
     * @param  array<string, mixed>  $data
     * @return array{illustration: string|null, data: array<string, mixed>}
     */
    private function extractIllustration(array $data): array
    {
        $illustration = $data['illustration'] ?? null;
        unset($data['illustration']);

        // Return the illustration as is if it's a string or explicitly null
        // This allows null to be passed to updateIllustration
        return [
            'illustration' => is_string($illustration) || is_null($illustration) ? $illustration : null,
            'data' => $data,
        ];
    }

    /**
     * Extract translation data from the input array and remove translation fields from the original array.
     *
     * @param  array<string, mixed>  $data
     * @return array{translationData: array<string, mixed>, data: array<string, mixed>}
     */
    private function extractTranslationData(array $data): array
    {
        $translationData = [
            'language' => $data['language'] ?? App::currentLocale(),
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'tags' => $data['tags'] ?? [],
            'status' => $data['status'] ?? StatusArticleEnum::DRAFT, // Default to DRAFT if not provided
        ];

        // Remove translation fields from the original data
        unset($data['language'], $data['title'], $data['content'], $data['tags'], $data['status']);

        return [
            'translationData' => $translationData,
            'data' => $data,
        ];
    }

    /**
     * Extract LLM response from data.
     *
     * @param  array<string, mixed>  $data
     * @return array{llmResponse: string|null, data: array<string, mixed>}
     */
    private function extractLLMResponse(array $data): array
    {
        $llmResponse = $data['llm_response'] ?? null;

        // Remove LLM-related fields that should not be persisted to Article model
        unset($data['llm_response'], $data['prompt'], $data['prompt_system']);

        return [
            'llmResponse' => is_string($llmResponse) ? $llmResponse : null,
            'data' => $data,
        ];
    }

    /**
     * Update the base data of an article.
     *
     * @param  array<string, mixed>  $data
     */
    private function updateArticleBaseData(Article $article, array $data): void
    {
        $article->fill($data);
        $article->save();
    }

    /**
     * Update or create a translation for an article.
     *
     * @param  array<string, mixed>  $translationData
     * @return array{translation: ArticleTranslation, contentModified: bool}
     */
    private function updateOrCreateTranslation(Article $article, array $translationData, bool $illustrationUpdated): array
    {
        // Check if the translation already exists
        $existingTranslation = $article->translations()
            ->where('language', $translationData['language'])
            ->first();

        // Check if content is modified
        $contentModified = true;
        if ($existingTranslation instanceof ArticleTranslation) {
            // Use JSON comparison for deep equality check (prevents duplicate versions from auto-saves)
            $existingContentJson = json_encode($existingTranslation->content);
            $newContentJson = json_encode($translationData['content']);
            $contentChanged = $existingContentJson !== $newContentJson;

            $titleChanged = $existingTranslation->title !== $translationData['title'];

            $contentModified = $contentChanged || $titleChanged || $illustrationUpdated;
        }

        // Prepare translation data
        $translationUpdateData = [
            'title' => $translationData['title'],
            'content' => $translationData['content'],
            'status' => $translationData['status'],

        ];

        // Set published_at if needed
        $translationUpdateData = $this->setPublishedAtIfNeeded($article, $translationData, $translationUpdateData);

        // Update or create translation for the specified language
        $translation = $article->translations()->updateOrCreate(
            [
                'language' => $translationData['language'],
            ],
            $translationUpdateData
        );

        // Ensure the translation is of the correct type
        if (! $translation instanceof ArticleTranslation) {
            $translation = ArticleTranslation::find($translation->getAttribute('id'));
        }

        // Ensure we have a valid ArticleTranslation
        if (! $translation instanceof ArticleTranslation) {
            throw new RuntimeException('Failed to get ArticleTranslation');
        }

        return [
            'translation' => $translation,
            'contentModified' => $contentModified || $illustrationUpdated,
        ];
    }

    /**
     * Set published_at timestamp if the article is being published.
     *
     * @param  array<string, mixed>  $translationData
     * @param  array<string, mixed>  $translationUpdateData
     * @return array<string, mixed>
     */
    private function setPublishedAtIfNeeded(Article $article, array $translationData, array $translationUpdateData): array
    {
        if ($translationData['status'] === StatusArticleEnum::PUBLISHED) {
            // Get existing translation to check if it's already published
            $existingTranslation = $article->translations()
                ->where('language', $translationData['language'])
                ->first();

            // Only set published_at if it's not already set
            if (! $existingTranslation instanceof ArticleTranslation || $existingTranslation->published_at === null) {
                $translationUpdateData['published_at'] = now();
            }
        }

        return $translationUpdateData;
    }

    /**
     * Change the active illustration based on version number.
     */
    private function changeActiveIllustrationByVersionNumber(Article $article, int $versionNumber): void
    {
        // Find the version with the specified version number
        $version = ArticleVersion::where('article_id', $article->id)
            ->where('version_number', $versionNumber)
            ->first();

        if (! $version instanceof ArticleVersion || ! $version->illustration_id) {
            return;
        }

        // Get all media for the article
        $medias = $article->getMedia('illustration');

        // Deactivate all media
        foreach ($medias as $media) {
            $customProperties = $media->custom_properties;
            $customProperties['active'] = false;
            $media->custom_properties = $customProperties;
            $media->save();
        }

        // Find and activate the media from the version
        $targetMedia = $medias->firstWhere('id', $version->illustration_id);
        if ($targetMedia) {
            $customProperties = $targetMedia->custom_properties;
            $customProperties['active'] = true;
            $targetMedia->custom_properties = $customProperties;
            $targetMedia->save();
        }
    }

    /**
     * Log the article update activity.
     */
    private function logArticleUpdate(Article $article, ArticleTranslation $translation): void
    {
        activity('article')
            ->performedOn($article)
            ->log("Article '{$translation->title}' (lang: {$translation->language}) updated");
    }
}
