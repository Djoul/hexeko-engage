<?php

namespace App\Integrations\InternalCommunication\Services;

use App\AI\Clients\OpenAIStreamerClient;
use App\AI\Exceptions\LLMClientException;
use App\AI\LLMRouterService;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Models\User;
use Exception;
use Generator;
use League\HTMLToMarkdown\HtmlConverter;
use Log;
use Nadar\ProseMirror\Parser;
use Ramsey\Uuid\Uuid;

class ArticleGeneratorService
{
    protected LLMRouterService $router;

    protected OpenAIStreamerClient $client;

    public function __construct(
        LLMRouterService $router,
        OpenAIStreamerClient $client,
    ) {
        $this->router = $router;
        $this->client = $client;
    }

    /**
     * Retrieves or creates an article with a translation for the specified (or current local) language.
     */
    public function getOrCreateArticle(?string $id, User $user, string $language, string $financer_id): Article
    {
        $articleId = $id ?? Uuid::uuid7()->toString();

        // First, try to find the article by ID (without translation constraint)
        $article = Article::find($articleId);

        if (! $article) {
            $article = new Article;
            $article->id = $articleId;
            $article->financer_id = $financer_id;
            $article->author_id = $user->id;
            $article->save();
        }

        // Creates the translation if it doesn't already exist for this language
        if (! $article->translations()->where('language', $language)->exists()) {
            $article->translations()->create([
                'language' => $language,
                'title' => 'New Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::DRAFT,
            ]);
        }

        return $article;
    }

    /**
     * Generates article content from a streaming-only prompt (PrismStreamerClient).
     *
     * @param  Article  $article  The article to generate content for
     * @param  string  $prompt  The prompt data containing user input
     * @return Generator<string> Streamed chunk generator
     */
    public function generateFromPrompt(Article $article, string $prompt, string $lang): Generator
    {
        $translation = $article->translations()->where('language', $lang)->first();
        if (! $translation instanceof ArticleTranslation) {
            // Create a default translation if none exists
            $translation = $article->translations()->create([
                'language' => $lang,
                'title' => 'New Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::DRAFT,
            ]);
        }

        /** @var ArticleTranslation $translation */
        /** @var array<string, mixed> $messagesWithSystem */
        $messagesWithSystem = $this->manageMessages(
            promptUser: $prompt,
            articleTranslation: $translation,
            language: $lang
        );

        Log::debug('messages', ['messages' => $messagesWithSystem]);

        // Cast pour satisfaire PHPStan
        return $this->client->streamPrompt((array) $messagesWithSystem, ['dual_output' => true]);
    }

    /**
     * @param  array<string, mixed>|string  $promptUser
     * @param  array<string, mixed>|null  $currentContent  Optional current content from frontend (for manual edit preservation)
     * @return array<int, array<string, string>>
     */
    public function manageMessages(
        array|string $promptUser,
        ArticleTranslation $articleTranslation,
        string $language,
        bool $withHistory = true,
        ?array $currentContent = null,
    ): array {
        //  Get user input

        $messages = $this->getSystemMessage($language);

        // Solution 1: Check if the latest version is a manual edit
        // If so, skip conversation history to prevent LLM from using outdated content
        $latestVersion = $articleTranslation->versions()->latest('version_number')->first();
        $isManualEdit = $latestVersion instanceof ArticleVersion && $latestVersion->llm_request_id === null;

        if ($withHistory && ! $isManualEdit) {
            // Only include conversation history if there's no manual edit
            // This prevents the LLM from being confused by outdated content in the history
            $lastRequests = $articleTranslation->llmRequests()
                ->latest()
                ->get()
                ->reverse();

            foreach ($lastRequests as $lastRequest) {
                $prompt = $lastRequest->prompt ?? '';
                $response = $lastRequest->response ?? '';

                $messages[] = [
                    'role' => 'user',
                    'content' => (string) $prompt,
                ];
                $messages[] = [
                    'role' => 'assistant',
                    'content' => (string) $response,
                ];
            }
        }

        // CRITICAL: Place current_content RIGHT BEFORE the user prompt to give it maximum priority
        // This ensures the LLM uses the most recent version (including manual edits) as the base
        // The system prompt explicitly instructs to use <current_content> over conversation history
        if ($withHistory) {
            // Use current content from frontend if provided (preserves manual edits)
            // Otherwise fall back to database content
            $content = $currentContent ?? $articleTranslation->content;
            $contentArray = is_array($content) ? $content : null;

            // Convert TipTap JSON to Markdown so LLM can read and preserve the content
            $markdownContent = $this->convertTipTapToMarkdown($contentArray);

            $messages[] = [
                'role' => 'assistant',
                'content' => '<current_content>'.$markdownContent.'</current_content>',
            ];
        }

        // Filtering to guarantee array<string, string>|string to getUserMessage
        $messages[] = $this->getUserMessage(is_array($promptUser) ? array_filter($promptUser, 'is_string') : $promptUser);

        return $messages;
    }

    /**
     * Get the system message for the LLM prompt
     *
     * @param  array<int, array<string, string>>  $messages
     * @return array<int, array<string, string>> The modified messages and prompt
     */
    protected function getSystemMessage(string $language, array $messages = []): array
    {
        $messageSystem = [
            'role' => 'system',
            'content' => '',
        ];

        $systemBaseMessageValue = config('ai_optimized.internal_communication.prompt_system');

        // Convert to string if needed
        if (is_string($systemBaseMessageValue)) {
            $systemBaseMessage = str_replace('{language}', $language, $systemBaseMessageValue);
        } elseif (is_numeric($systemBaseMessageValue) || is_bool($systemBaseMessageValue)) {
            $systemBaseMessage = (string) $systemBaseMessageValue;
        } elseif (is_array($systemBaseMessageValue) || is_object($systemBaseMessageValue)) {
            $systemBaseMessage = json_encode($systemBaseMessageValue) ?: '';
        } else {
            $systemBaseMessage = '';
        }

        $messageSystem['content'] = $systemBaseMessage;

        $result = [$messageSystem];
        foreach ($messages as $message) {
            $result[] = [
                'role' => (string) ($message['role'] ?? 'user'),
                'content' => (string) ($message['content'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * Format the user message for the LLM prompt
     *
     * @param  array<string, string>|string  $prompt  The prompt data
     * @return array{role: string, content: string}
     */
    protected function getUserMessage(array|string $prompt): array
    {
        $user_input = [
            'role' => 'user',
            'content' => '',
        ];

        if (is_array($prompt) && array_key_exists('user_input', $prompt)) {
            $user_input['content'] = $prompt['user_input'];
        } elseif (is_array($prompt) && array_key_exists('prompt', $prompt)) {
            $user_input['content'] = $prompt['prompt'];
        } elseif (is_string($prompt)) {
            $user_input['content'] = $prompt;
        }

        if (is_array($prompt) && array_key_exists('selected_text', $prompt) && ! empty($prompt['selected_text'])) {
            $user_input['content'] .= "\n<selected_text>{$prompt['selected_text']}</selected_text>";
        }

        return $user_input;
    }

    /**
     * @param  array<string, mixed>|string  $prompt
     */
    public function countMessagesPromptTokens(Article $article, array|string $prompt): string
    {
        $translation = $article->translations()->first();
        if (! $translation instanceof ArticleTranslation) {
            // Create a default translation if none exists
            $translation = $article->translations()->create([
                'language' => app()->getLocale(),
                'title' => 'New Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => ''],
                            ],
                        ],
                    ],
                ],
                'status' => StatusArticleEnum::DRAFT,
            ]);
        }

        /** @var ArticleTranslation $translation */
        $json = json_encode($this->manageMessages(promptUser: $prompt, articleTranslation: $translation, language: $translation->language));

        return $json !== false ? (string) strlen($json) : '0';
    }

    /**
     * Check if the prompt concerns selected text
     *
     * @param  array<string, mixed>|string  $prompt  The prompt data
     */
    protected function concernSelectedText(array|string $prompt): bool
    {
        return is_array($prompt) && ! empty($prompt['selected_text']);
    }

    /**
     * Translates an article to the specified language using OpenAI.
     *
     * @param  Article  $article  The article to translate
     * @param  string  $targetLanguage  The target language code (e.g., 'nl-BE', 'en-GB')
     * @return Generator<string> Streamed chunk generator
     *
     * @throws LLMClientException If there's an error with the JSON encoding
     */
    public function translateArticle(Article $article, string $targetLanguage): Generator
    {
        $systemMessage = [
            'role' => 'system',
            'content' => config('ai.internal_communication.prompt_system_translate'),
        ];

        $firstTranslation = $article->translations()->first();
        $title = 'Untitled Article';
        $content = null;

        if ($firstTranslation instanceof ArticleTranslation) {
            $title = $firstTranslation->title ?? 'Untitled Article';
            $content = $firstTranslation->content ?? null;
        }

        $markdownContent = '';
        if ($content !== null) {
            // Step 1: Convert JSON to HTML using ProseMirror Parser
            $parser = new Parser;
            $contentArray = is_array($content) ? $content : json_decode((string) json_encode($content), true);
            /** @var array<string, mixed> $safeContentArray */
            $safeContentArray = is_array($contentArray) ? $contentArray : [];
            $html = $parser->toHtml($safeContentArray);
            // Step 2: Convert HTML to Markdown
            $converter = new HtmlConverter;
            $markdownContent = $converter->convert($html);
        }

        $userMessage = [
            'role' => 'user',
            'content' => "translate this article to {$targetLanguage}: Title: {$title} Content: {$markdownContent}",
        ];

        $messages = [$systemMessage, $userMessage];

        $jsonPayload = json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (json_last_error() === JSON_ERROR_NONE) {
            $cleanJsonPayload = (string) $jsonPayload;
            Log::debug('messages', ['json' => str_replace(["\n", '\n'], '', $cleanJsonPayload)]);

            /** @var array<string, mixed> $messagesTyped */
            $messagesTyped = ['messages' => $messages];

            return $this->client->streamPrompt($messagesTyped, ['dual_output' => true]);
        }
        $errorMsg = "Erreur d'encodage JSON lors de la traduction: ".json_last_error_msg();
        Log::error($errorMsg);
        throw new LLMClientException($errorMsg);
    }

    /**
     * Convert TipTap JSON content to Markdown format
     *
     * @param  array<string, mixed>|null  $content  The TipTap JSON content
     * @return string The content converted to Markdown
     */
    private function convertTipTapToMarkdown(?array $content): string
    {
        if ($content === null || $content === []) {
            return '';
        }

        try {
            // Step 1: Convert JSON to HTML using ProseMirror Parser
            $parser = new Parser;
            $contentArray = is_array($content) ? $content : json_decode((string) json_encode($content), true);

            /** @var array<string, mixed> $safeContentArray */
            $safeContentArray = is_array($contentArray) ? $contentArray : [];
            $html = $parser->toHtml($safeContentArray);

            // Step 2: Convert HTML to Markdown
            $converter = new HtmlConverter;

            return $converter->convert($html);
        } catch (Exception $e) {
            Log::error('Failed to convert TipTap to Markdown', [
                'error' => $e->getMessage(),
                'content' => $content,
            ]);

            // Fallback: return JSON as string
            return json_encode($content) ?: '';
        }
    }
}
