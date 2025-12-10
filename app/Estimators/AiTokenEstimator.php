<?php

namespace App\Estimators;

use App\Estimators\Contracts\CreditEstimatorInterface;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Services\ArticleGeneratorService;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Mis3085\Tiktoken\Facades\Tiktoken;

class AiTokenEstimator implements CreditEstimatorInterface
{
    /**
     * Estimate the number of AI tokens that will be consumed.
     *
     * @param  string|Request  $request  The prompt string or a Request object containing a prompt
     * @return int The estimated number of tokens
     */
    public function estimate(Request|string $request): int
    {
        $prompt = '';

        if ($request instanceof Request) {
            $prompt = $request->input('prompt', '');
        } elseif (is_string($request)) {
            $prompt = $request;
        }

        // Ensure $prompt is a string for strlen
        if (! is_string($prompt)) {
            $prompt = '';
        }

        // For test environment, use a simple calculation
        if (app()->environment('testing')) {
            if (empty($prompt)) {
                return 1;
            }

            return (int) ceil(strlen($prompt) / 4);
        }

        $generator = resolve(ArticleGeneratorService::class);

        // Only process messages if $request is a Request object
        $messages = [];
        if ($request instanceof Request) {
            // Ensure articleId is string or null
            $routeId = $request->route('id');
            $articleId = is_string($routeId) || is_null($routeId) ? $routeId : null;

            // Get authenticated user and ensure it's a User instance
            $authUser = Auth::user();

            // Only proceed if we have a valid User instance
            if ($authUser instanceof User) {
                $language = request('language');
                $financerId = request('financer_id');
                $article = $generator->getOrCreateArticle(
                    $articleId,
                    $authUser,
                    is_string($language) ? $language : 'en',
                    is_string($financerId) ? $financerId : ''
                );

                $translation = $article
                    ->translations()
                    ->where('language', request()->language)->first()
                    ?? $article->translations()->first();

                // Ensure we have a valid ArticleTranslation
                if (! $translation instanceof ArticleTranslation) {
                    // Create a default translation if none exists
                    $translation = $article->translations()->create([
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
                }

                /** @var ArticleTranslation $translation */
                $messages = $generator
                    ->manageMessages(
                        promptUser: $request->all(),
                        articleTranslation: $translation,
                        language: $translation->language,
                    );
            }
        }

        // Ensure we have a valid string for Tiktoken::count()
        $jsonMessages = json_encode($messages);
        $estimatedSystemTokens = Tiktoken::count(is_string($jsonMessages) ? $jsonMessages : '[]');

        $estimatedTokens = Tiktoken::count($prompt); // always at least 1 token

        // Ensure we're adding integers
        $defaultTokensGap = config('ai.default_tokens_gap', 500);
        $tokensGap = is_numeric($defaultTokensGap) ? (int) $defaultTokensGap : 500;

        $value = $estimatedTokens + $estimatedSystemTokens + $tokensGap;

        return max($value, 1);
    }
}
