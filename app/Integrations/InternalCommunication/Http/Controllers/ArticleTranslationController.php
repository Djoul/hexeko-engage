<?php

namespace App\Integrations\InternalCommunication\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\Languages;
use App\Events\CreditConsumed;
use App\Http\Controllers\Controller;
use App\Integrations\InternalCommunication\Actions\CreateArticleVersionAction;
use App\Integrations\InternalCommunication\Actions\UpdateArticleAction;
use App\Integrations\InternalCommunication\DTOs\CreateArticleVersionDTO;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Services\ArticleGeneratorService;
use App\Integrations\InternalCommunication\Services\ArticleStreamParser;
use App\Models\Financer;
use App\Models\LLMRequest;
use App\Models\User;
use Auth;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Group('Modules/internal-communication')]
class ArticleTranslationController extends Controller
{
    public function __construct(
        protected ArticleGeneratorService $generator,
        protected UpdateArticleAction $updateArticleAction,
        protected CreateArticleVersionAction $createArticleVersionAction
    ) {}

    /**
     * Translate an article to the specified language
     */
    #[RequiresPermission(PermissionDefaults::CREATE_ARTICLE)]
    public function translate(Request $request, ?string $id): StreamedResponse
    {

        $validatedRequest = $request->validate([
            'language' => [
                'required',
                'string',
                'in:'.implode(',', Languages::asArray()),
            ],
            'financer_id' => [
                'required',
                'string',
                'exists:financers,id',
                Rule::in(authorizationContext()->financerIds()),
            ],
        ]);

        $user = Auth::user();

        if (! $user) {
            abort(401, 'User must be authenticated');
        }

        if (! $user instanceof User) {
            abort(500, 'Invalid user type');
        }

        $financerId = $validatedRequest['financer_id'] ?? $user->financers->first()?->id;
        if (! $financerId) {
            abort(400, 'No financer available');
        }

        $article = $this->generator->getOrCreateArticle($id, $user, $validatedRequest['language'], $financerId);

        $parser = new ArticleStreamParser;

        return response()->stream(function () use ($article, $validatedRequest, $parser): void {
            $openedBuffer = false;

            if (ob_get_level() === 0) {
                ob_start();
                $openedBuffer = true;
            }

            $stream = $this->generator->translateArticle($article, $validatedRequest['language']);
            $fullResponse = '';
            $hasValidatedOpening = false;

            foreach ($stream as $chunk) {
                $fullResponse .= $chunk;

                // Early validation: check for <opening> tag after 2000 bytes
                if (! $hasValidatedOpening && strlen($fullResponse) > 2000) {
                    if (! $parser->hasOpeningTag(2000)) {
                        Log::error('Missing <opening> tag in translation LLM response', [
                            'prompt' => "Translate to {$validatedRequest['language']}",
                            'buffer_start' => substr($fullResponse, 0, 500),
                        ]);
                    }
                    $hasValidatedOpening = true;
                }

                // Parse chunk incrementally
                $parser->parseChunk($chunk);

                // Stream the raw chunk to client
                $this->streamAndFlush($chunk);
            }

            // Final validation: check if all sections are present
            if (! $parser->isComplete()) {
                $missingSections = $parser->getMissingSections();
                Log::error('Incomplete translation LLM response - missing sections', [
                    'missing_sections' => $missingSections,
                    'extraction_status' => $parser->getExtractionStatus(),
                    'prompt' => "Translate to {$validatedRequest['language']}",
                    'bytes_processed' => $parser->getBytesProcessed(),
                ]);
            }

            // Create version, log LLM request and consume credit after streaming completes
            $this->createVersion($article, "Translate to {$validatedRequest['language']}", $fullResponse);
            $this->logLLMRequest($article, $validatedRequest['language'], $fullResponse);
            $this->consumeCredit($article->financer_id);

            // End output buffering
            if ($openedBuffer && ob_get_level() > 0) {
                ob_end_flush();
            }
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Stream and flush the buffer
     */
    protected function streamAndFlush(string $buffer): void
    {
        // In testing environment, don't output directly
        if (app()->environment('testing')) {
            return;
        }

        echo $buffer;
        ob_flush();
        flush();
    }

    /**
     * Format the Markdown content to JSON
     *
     * @param  string  $markdownContent  The markdown content to format
     * @return array<string, mixed> The formatted content as JSON structure
     */
    protected function formatContentToJson(string $markdownContent): array
    {
        // Simple conversion of markdown to JSON structure
        // In a real implementation, this would parse the markdown properly
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $markdownContent],
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a new version for the article
     */
    protected function createVersion(Article $article, ?string $prompt, ?string $chatResponse): void
    {
        $versions = $article->versions();
        $maxVersion = $versions->max('version_number');
        $nextVersion = (is_int($maxVersion) ? $maxVersion : 0) + 1;

        // Get the content from the translation or use an empty array
        $translation = $article->translation();
        $content = $translation instanceof ArticleTranslation ? $translation->content : [];

        // Ensure content is always an array<string, mixed>
        $contentArray = is_array($content) ? $content : ['content' => $content];

        $dto = new CreateArticleVersionDTO(
            $contentArray,
            $translation instanceof ArticleTranslation ? (string) $translation->title : '',
            $nextVersion,
            $prompt,
            $chatResponse,
            $translation instanceof ArticleTranslation ? (string) $translation->id : null,
            $translation instanceof ArticleTranslation ? (string) $translation->language : null
        );

        $this->createArticleVersionAction->handle($article, $dto);
    }

    /**
     * Log the LLM request
     */
    protected function logLLMRequest(Article $article, string $targetLanguage, string $response): void
    {
        // Get the translation for the target language
        $translation = $article->translations()->where('language', $targetLanguage)->first();

        if (! $translation instanceof ArticleTranslation) {
            // If translation doesn't exist, create it
            $translation = $article->translations()->create([
                'language' => $targetLanguage,
                'title' => 'Untitled',
                'content' => [],
            ]);
        }

        // Ensure $translation is an instance of ArticleTranslation
        if ($translation instanceof ArticleTranslation) {
            LLMRequest::create([
                'requestable_type' => ArticleTranslation::class,
                'requestable_id' => $translation->id,
                'financer_id' => $article->financer_id,
                'prompt' => "Translate to {$targetLanguage}",
                'response' => $response,
                'tokens_used' => (int) (strlen($response) / 4), // Rough estimate, cast to integer
                'engine_used' => config('ai.default_engine'),
            ]);
        }
    }

    /**
     * Consume a credit for the translation
     */
    protected function consumeCredit(?string $financerId = null): void
    {
        $financerId = $financerId ?? activeFinancerID();

        // Ensure financerId is a string
        if (is_array($financerId)) {
            $financerId = $financerId[0] ?? null;
        }

        if (! in_array($financerId, [null, '', '0'], true) && is_string($financerId)) {
            event(new CreditConsumed(
                Financer::class,
                $financerId,
                'ai_token',
                1
            ));
        }
    }
}
