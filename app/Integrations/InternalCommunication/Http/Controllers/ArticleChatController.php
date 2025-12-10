<?php

namespace App\Integrations\InternalCommunication\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\InternalCommunication\Actions\CreateArticleVersionAction;
use App\Integrations\InternalCommunication\Actions\SaveLLMRequestAction;
use App\Integrations\InternalCommunication\Actions\UpdateArticleAction;
use App\Integrations\InternalCommunication\DTOs\SaveLLMRequestDTO;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Http\Requests\GenerateArticleRequest;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Integrations\InternalCommunication\Services\ArticleGeneratorService;
use App\Integrations\InternalCommunication\Services\ArticleStreamParser;
use App\Models\LLMRequest;
use Dedoc\Scramble\Attributes\Group;
use Gate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Mis3085\Tiktoken\Facades\Tiktoken;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Modules/internal-communication')]
class ArticleChatController extends Controller
{
    private const HTTP_OK = 200;

    public function __construct(
        protected ArticleGeneratorService $generator,
        protected UpdateArticleAction $updateArticleAction,
        protected CreateArticleVersionAction $createArticleVersionAction,
        protected SaveLLMRequestAction $saveLLMRequestAction,
    ) {
        $this->authorizeResource(Article::class, 'article');
    }

    /**
     * Generate an article via AI OR update an existing article.
     *
     * This endpoint streams the AI-generated content in real-time using Server-Sent Events (SSE).
     * The response uses a structured XML-like format with four required sections.
     *
     * @response 200 scenario="Article generation streamed successfully" {
     *   "headers": {
     *     "Content-Type": "text/event-stream",
     *     "Cache-Control": "no-cache",
     *     "X-Accel-Buffering": "no"
     *   },
     *   "body": "Stream of text chunks in the following XML-like format:\n\n<opening>\nIntroductory text that hooks the reader and introduces the topic.\nExample: Employee engagement is a crucial topic for any organization.\n</opening>\n\n<title>\nThe article title in markdown format (typically H1).\nExample: # Understanding Employee Engagement\n</title>\n\n<content>\nMain article content in markdown format with sections, lists, etc.\nExample:\n## What is Employee Engagement?\n\nEmployee engagement refers to the emotional commitment employees have to their organization and its goals.\n\n## Key Benefits\n\n- Increased productivity\n- Better retention rates\n- Higher customer satisfaction\n</content>\n\n<closing>\nClosing remarks or call-to-action.\nExample: Would you like me to add specific strategies for improving engagement in your organization?\n</closing>\n\nNOTE: Content is streamed incrementally as chunks arrive from the AI. The client should parse these tags to extract the four sections. All sections are required for a complete response."
     * }
     *
     * @bodyParam prompt string required The user's prompt to generate or update the article content. Example: What is employee engagement?
     * @bodyParam financer_id string required The UUID of the financer organization. Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam language string required The language code for the article (must be in financer's available languages). Example: en
     * @bodyParam segment_id string The UUID of the target segment for this article. Example: 650e8400-e29b-41d4-a716-446655440001
     * @bodyParam title string The article title (max 255 characters). Example: Employee Engagement Guide
     * @bodyParam prompt_system string Custom system prompt for the AI (overrides default). Example: You are an expert HR consultant.
     * @bodyParam selected_text string Previously selected text to refine or continue from. Example: Employee engagement is...
     */
    public function generate(GenerateArticleRequest $request, ?string $id = null): StreamedResponse
    {
        $languageValue = $request->language;
        $language = is_string($languageValue) ? $languageValue : 'en';

        $financerIdValue = $request->financer_id;
        $financerId = is_string($financerIdValue) ? $financerIdValue : '';

        $user = auth()->user();
        if (! $user) {
            abort(401, 'Unauthorized');
        }

        $article = $this->generator->getOrCreateArticle($id, $user, $language, $financerId);

        // Authorize update on the actual article instance
        Gate::authorize('update', $article);

        if ($request->has('segment_id')) {
            $article->segment_id = $request->input('segment_id');
            $article->save();
        }

        $promptInput = $request->input('prompt');
        $prompt = is_string($promptInput) ? $promptInput : '';

        $generator = $this->generator;
        $stream = $generator->generateFromPrompt($article, $prompt, $language);
        $parser = new ArticleStreamParser;

        return response()->stream(function () use ($stream, $parser, $prompt, $article): void {
            $openedBuffer = false;

            if (ob_get_level() === 0) {
                ob_start();
                $openedBuffer = true;
            }

            $fullResponse = '';
            $hasValidatedOpening = false;

            foreach ($stream as $chunk) {
                $fullResponse .= $chunk;

                // Early validation: check for <opening> tag after 2000 bytes
                if (! $hasValidatedOpening && strlen($fullResponse) > 2000) {
                    if (! $parser->hasOpeningTag(2000)) {
                        Log::error('Missing <opening> tag in LLM response', [
                            'prompt' => $prompt,
                            'buffer_start' => substr($fullResponse, 0, 500),
                        ]);
                        // Still continue streaming but log the error
                    }
                    $hasValidatedOpening = true;
                }

                // Parse chunk incrementally
                $completedSections = $parser->parseChunk($chunk);

                // Stream the raw chunk to client (maintain backward compatibility)
                // Client can parse tags on their end too
                $this->streamAndFlush($chunk);
            }
            // Final validation: check if all sections are present
            if (! $parser->isComplete()) {
                $missingSections = $parser->getMissingSections();
                Log::error('Incomplete LLM response - missing sections', [
                    'missing_sections' => $missingSections,
                    'extraction_status' => $parser->getExtractionStatus(),
                    'prompt' => $prompt,
                    'bytes_processed' => $parser->getBytesProcessed(),
                ]);
            }

            $this->saveLLMRequestAndConsumeCredits($prompt, $article, $fullResponse);

            if ($openedBuffer && ob_get_level() > 0) {
                ob_end_flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    protected function streamAndflush(string $buffer): void
    {
        // In testing environment, still echo for ob_get_contents() to work
        if (app()->environment('testing')) {
            echo $buffer;

            return;
        }

        echo $buffer;
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Save the LLM request and consume credits after generation
     *
     * @param  array<string, mixed>|string  $prompt  The prompt data
     * @param  Article  $article  The article being processed
     * @param  string  $llmResponse  The response from the LLM
     * @return LLMRequest The created LLM request
     */
    protected function saveLLMRequestAndConsumeCredits(array|string $prompt, Article $article, string $llmResponse): LLMRequest
    {
        $translation = $this->resolveTranslation($article);

        // Use current content from request if provided (for manual edit preservation)
        $currentContent = request()->has('content') ? request()->input('content') : null;

        /** @var array<string, mixed>|null $currentContentArray */
        $currentContentArray = is_array($currentContent) ? $currentContent : null;

        $messages = $this->generator->manageMessages(
            promptUser: $prompt,
            articleTranslation: $translation,
            language: $translation->language,
            withHistory: true,
            currentContent: $currentContentArray
        );
        $tokensUsed = $this->countTokens($prompt, $translation, $llmResponse, $currentContentArray);
        $promptString = is_array($prompt) ? ($prompt['prompt'] ?? json_encode($prompt)) : $prompt;

        return $this->saveLLMRequestAction->execute(new SaveLLMRequestDTO(
            translationId: $translation->id,
            financerId: $article->financer_id,
            prompt: is_string($promptString) ? $promptString : '',
            response: $llmResponse,
            tokensUsed: $tokensUsed,
            messages: $messages,
        ));
    }

    /**
     * Resolve the translation for the article, creating one if necessary
     */
    private function resolveTranslation(Article $article): ArticleTranslation
    {
        $translation = $article
            ->translations()
            ->where('language', request()->language)->first()
            ?? $article->translations()->first();

        if ($translation instanceof ArticleTranslation) {
            return $translation;
        }

        /** @var ArticleTranslation $newTranslation */
        $newTranslation = $article->translations()->create([
            'language' => request()->language ?? app()->getLocale(),
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

        return $newTranslation;
    }

    /**
     * Count tokens used for prompt and response
     *
     * @param  array<string, mixed>|string  $prompt
     * @param  array<string, mixed>|null  $currentContent
     */
    private function countTokens(array|string $prompt, ArticleTranslation $translation, string $llmResponse, ?array $currentContent = null): int
    {
        $encodedMessages = json_encode(
            $this->generator->manageMessages(
                promptUser: $prompt,
                articleTranslation: $translation,
                language: $translation->language,
                withHistory: true,
                currentContent: $currentContent
            )
        );

        $tokenUsedForPrompt = Tiktoken::count($encodedMessages !== false ? $encodedMessages : '');
        $tokenUsedForResponse = Tiktoken::count($llmResponse);

        return $tokenUsedForPrompt + $tokenUsedForResponse;
    }

    /**
     * Accept the selected text and update the article.
     *
     * @return JsonResponse
     */
    #[RequiresPermission(PermissionDefaults::CREATE_ARTICLE)]
    public function acceptSelectedText(Request $request, Article $article, ArticleVersion $version)
    {
        Gate::authorize('update', Article::class);

        $langInput = $request->input('language');
        $lang = is_string($langInput) ? $langInput : (string) app()->getLocale();
        $validated = $request->validate([
            'content' => 'required|string',
            'llm_response' => ['sometimes', 'string', 'nullable'],
        ]);
        $translation = $article->translation($lang);
        if ($translation instanceof ArticleTranslation) {
            $translation->update(['content' => $validated['content']]);

            // Create LLM request linked to ArticleTranslation
            if (request()->has('llm_response')) {
                $llmResponse = $validated['llm_response'];

                // Get system message from request or config
                if (request()->has('prompt_system')) {
                    $systemMessage = request()->input('prompt_system');
                } else {
                    $systemMessage = config('ai.internal_communication.prompt_system');
                }

                // Create LLM request
                LLMRequest::create([
                    'financer_id' => activeFinancerID(),
                    'requestable_id' => $translation->id,
                    'requestable_type' => ArticleTranslation::class,
                    'prompt' => $version->prompt ?? '',
                    'response' => $llmResponse,
                    'prompt_system' => $systemMessage,
                    'engine_used' => 'OpenAI',
                    'tokens_used' => 0, // We don't have token count here
                ]);
            }
        }

        // Get the existing prompt value or use an empty string if it doesn't exist
        $existingPrompt = $version->getAttribute('prompt') ?? '';

        $version->update([
            'content' => $validated['content'],
            'prompt' => $existingPrompt,
            'article_translation_id' => $translation instanceof ArticleTranslation ? $translation->id : null,
            'language' => $lang,
        ]);

        return response()->json([
            'message' => 'Selected text accepted and article updated successfully.',
        ], self::HTTP_OK);
    }
}
