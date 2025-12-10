<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\Http\Resources;

use App\Http\Resources\User\UserResource;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Integrations\InternalCommunication\Services\ArticleStreamParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ArticleVersion
 *
 * @property int $id
 * @property int $article_id
 * @property int $version_number
 * @property string $content
 * @property array<string, mixed>|string|null $prompt
 * @property array<string, mixed>|null $llm_response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ArticleVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ArticleVersion $version */
        $version = $this->resource;

        return [
            'id' => $version->id,
            'article_id' => $version->article_id,
            'version_number' => $version->version_number,
            'content' => $version->content,
            'title' => $version->title,
            'prompt' => $version->prompt,
            'llm_response' => $this->parseLlmResponse($version->llm_response ?? ''),
            'llm_request_id' => $version->llm_request_id ?? null,
            'illustration' => $version->media?->getUrl() ?? null,
            'author' => new UserResource($this->whenLoaded('author')),
            'created_at' => $version->created_at instanceof Carbon ? $version->created_at->toIso8601String() : null,
            'updated_at' => $version->updated_at instanceof Carbon ? $version->updated_at->toIso8601String() : null,
        ];
    }

    /**
     * Parse the LLM response into structured sections
     *
     * Supports XML format (new) with fallback to § separator (legacy)
     *
     * @return array{
     *     opening: ?string,
     *     title: ?string,
     *     content: ?string,
     *     closing: ?string,
     *     full: string
     * }
     */
    private function parseLlmResponse(string $response): array
    {
        $emptyResponse = [
            'opening' => null,
            'title' => null,
            'content' => null,
            'closing' => null,
            'full' => $response,
        ];

        if ($response === '') {
            return $emptyResponse;
        }

        // XML format detection (new format)
        if ($this->isXmlFormat($response)) {
            return $this->parseXmlResponse($response);
        }

        // Legacy § separator format fallback
        if (str_contains($response, '§')) {
            return $this->parseLegacyResponse($response);
        }

        return $emptyResponse;
    }

    private function isXmlFormat(string $response): bool
    {
        return str_contains($response, '<opening>') ||
               str_contains($response, '<title>') ||
               str_contains($response, '<content>');
    }

    /**
     * @return array{
     *     opening: ?string,
     *     title: ?string,
     *     content: ?string,
     *     closing: ?string,
     *     full: string
     * }
     */
    private function parseXmlResponse(string $response): array
    {
        $parser = new ArticleStreamParser;
        $parser->parseChunk($response);
        $sections = $parser->getSections();

        return [
            'opening' => $sections['opening'] !== '' ? $sections['opening'] : null,
            'title' => $sections['title'] !== '' ? $sections['title'] : null,
            'content' => $sections['content'] !== '' ? $sections['content'] : null,
            'closing' => $sections['closing'] !== '' ? $sections['closing'] : null,
            'full' => $response,
        ];
    }

    /**
     * Parse legacy § separator format (for backward compatibility with old data)
     *
     * @return array{
     *     opening: ?string,
     *     title: ?string,
     *     content: ?string,
     *     closing: ?string,
     *     full: string
     * }
     */
    private function parseLegacyResponse(string $response): array
    {
        $parts = explode('§', $response);

        if (count($parts) >= 4) {
            return [
                'opening' => trim($parts[0]) !== '' ? trim($parts[0]) : null,
                'title' => trim($parts[1]) !== '' ? trim($parts[1]) : null,
                'content' => trim($parts[2]) !== '' ? trim($parts[2]) : null,
                'closing' => trim($parts[3]) !== '' ? trim($parts[3]) : null,
                'full' => $response,
            ];
        }

        return [
            'opening' => null,
            'title' => null,
            'content' => null,
            'closing' => null,
            'full' => $response,
        ];
    }
}
