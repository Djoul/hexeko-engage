<?php

declare(strict_types=1);

namespace App\Http\Resources\LLMRequest;

use App\Integrations\InternalCommunication\Services\ArticleStreamParser;
use App\Models\LLMRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LLMRequest
 */
class LLMRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fullResponse = $this->response ?? '';

        return [
            'id' => $this->id,
            'prompt' => $this->prompt,
            'response' => $this->parseResponse($fullResponse),
            'prompt_system' => $this->prompt_system,
            'financer_id' => $this->financer_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'usage' => [
                'token_usage' => $this->tokens_used,
                'engine' => $this->engine_used,
            ],
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
    private function parseResponse(string $response): array
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
