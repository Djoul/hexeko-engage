<?php

namespace App\Http\Controllers\V1;

use App\Events\Metrics\ModuleAccessed;
use App\Events\Metrics\ModuleUsed;
use App\Events\Metrics\SessionFinished;
use App\Events\Metrics\SessionStarted;
use App\Events\Metrics\UserAccountActivated;
use App\Http\Controllers\Controller;
use App\Http\Requests\TrackMetricsEventRequest;
use App\Integrations\HRTools\Events\Metrics\LinkAccessed;
use App\Integrations\HRTools\Events\Metrics\LinkClicked;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleClosedWithoutInteraction;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleLiked;
use App\Integrations\InternalCommunication\Events\Metrics\ArticleViewed;
use App\Integrations\InternalCommunication\Events\Metrics\CommunicationSectionVisited;
use App\Integrations\InternalCommunication\Models\Article;
use Illuminate\Http\JsonResponse;

class TrackMetricsEventController extends Controller
{
    public function handle(TrackMetricsEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $event = $data['event'];

        $userId = array_key_exists('user_id', $data) ? (is_scalar($data['user_id']) ? (string) $data['user_id'] : '') : (string) auth()->id();

        // Validate article exists for article-related events
        if (in_array($event, ['ArticleViewed', 'ArticleLiked', 'ArticleClosedWithoutInteraction']) && (array_key_exists('article_id', $data) && is_scalar($data['article_id']))) {
            $articleId = (string) $data['article_id'];
            if (! Article::find($articleId)) {
                return response()->json(['error' => 'Article not found'], 404);
            }
        }

        match ($event) {
            'UserAccountActivated' => event(new UserAccountActivated($userId)),
            'ModuleAccessed' => event(new ModuleAccessed($userId, array_key_exists('module_id', $data) && is_scalar($data['module_id']) ? (string) $data['module_id'] : '')),
            'ModuleUsed' => event(new ModuleUsed($userId, array_key_exists('module_id', $data) && is_scalar($data['module_id']) ? (string) $data['module_id'] : '')),

            'ArticleViewed' => event(new ArticleViewed($userId, array_key_exists('article_id', $data) && is_scalar($data['article_id']) ? (string) $data['article_id'] : '')),
            'ArticleLiked' => event(new ArticleLiked($userId, array_key_exists('article_id', $data) && is_scalar($data['article_id']) ? (string) $data['article_id'] : '')),
            'ArticleClosedWithoutInteraction' => event(new ArticleClosedWithoutInteraction($userId, array_key_exists('article_id', $data) && is_scalar($data['article_id']) ? (string) $data['article_id'] : '')),
            'CommunicationSectionVisited' => event(new CommunicationSectionVisited($userId)),

            'LinkAccessed' => event(new LinkAccessed($userId, array_key_exists('link_id', $data) && is_scalar($data['link_id']) ? (string) $data['link_id'] : '')),
            'LinkClicked' => event(new LinkClicked($userId, array_key_exists('link_id', $data) && is_scalar($data['link_id']) ? (string) $data['link_id'] : '')),
            'SessionStarted' => event(new SessionStarted($userId)),
            'SessionFinished' => event(new SessionFinished($userId)),

            default => abort(422, 'Unsupported event type.'),
        };

        return response()->json(['success' => true]);
    }
}
