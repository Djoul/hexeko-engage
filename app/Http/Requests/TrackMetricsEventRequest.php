<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @group Metrics
 *
 * Data validation for tracking metrics events
 */
class TrackMetricsEventRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * The type of event to track.
             *
             * @var string
             *
             * @example "ModuleAccessed"
             */
            'event' => ['required', Rule::in([
                'UserAccountActivated',
                'ModuleAccessed',
                'ModuleUsed',
                'ArticleViewed',
                'ArticleLiked',
                'ArticleClosedWithoutInteraction',
                'CommunicationSectionVisited',
                'LinkAccessed',
                'LinkClicked',
                'SessionStarted',
                'SessionFinished',
                // Add other event names here
            ])],

            /**
             * The ID of the user who triggered the event. If not provided, the authenticated user's ID will be used.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'user_id' => ['sometimes', 'string'],

            /**
             * The id of the module being accessed or used.
             * Required when event is 'ModuleAccessed' or 'ModuleUsed'.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'module_id' => ['required_if:event,ModuleAccessed,ModuleUsed', 'string'],

            /**
             * The ID of the article being viewed, liked, or closed.
             * Required when event is 'ArticleViewed', 'ArticleLiked', or 'ArticleClosedWithoutInteraction'.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'article_id' => ['required_if:event,ArticleViewed,ArticleLiked,ArticleClosedWithoutInteraction', 'string'],

            /**
             * The URL of the link being accessed or clicked.
             * Required when event is 'LinkAccessed' or 'LinkClicked'.
             *
             * @var string
             *
             * @example "123e4567-e89b-12d3-a456-426614174000"
             */
            'link_id' => ['required_if:event,LinkAccessed,LinkClicked', 'string'],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
