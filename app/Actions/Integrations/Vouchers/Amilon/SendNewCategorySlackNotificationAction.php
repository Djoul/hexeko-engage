<?php

namespace App\Actions\Integrations\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Notifications\SlackMessageWithAttachment;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class SendNewCategorySlackNotificationAction
{
    /**
     * Send a Slack notification when a new Amilon category is created.
     */
    public function execute(Category $category): void
    {
        try {
            $channel = config('services.slack.notifications.channel');

            if (! is_string($channel)) {
                Log::warning('Slack channel not configured for Amilon category notifications');

                return;
            }

            $translatedNames = [];
            foreach ($category->getTranslations('name') as $locale => $name) {
                $translatedNames[] = "• {$locale}: {$name}";
            }

            $message = sprintf(
                "🆕 *New Amilon Category Created*\n\n".
                "*ID:* `%s`\n".
                "*Translations:*\n%s\n".
                '*Created at:* %s',
                $category->id,
                implode("\n", $translatedNames),
                $category->created_at?->format('Y-m-d H:i:s') ?? 'N/A'
            );

            Notification::route('slack', $channel)
                ->notify(SlackMessageWithAttachment::text($message, $channel));

            Log::info('Slack notification sent for new Amilon category', [
                'category_id' => $category->id,
                'category_name' => $category->name,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send Slack notification for new Amilon category', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
