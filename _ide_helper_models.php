<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Integrations\HRTools\Models{
/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $url
 * @property string|null $logo_url
 * @property string|null $api_endpoint
 * @property string|null $front_endpoint
 * @property string $financer_id
 * @property int $position
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Financer|null $financer
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @method static Builder<static>|Link newModelQuery()
 * @method static Builder<static>|Link newQuery()
 * @method static Builder<static>|Link onlyTrashed()
 * @method static Builder<static>|Link query()
 * @method static Builder<static>|Link whereApiEndpoint($value)
 * @method static Builder<static>|Link whereCreatedAt($value)
 * @method static Builder<static>|Link whereDeletedAt($value)
 * @method static Builder<static>|Link whereDescription($value)
 * @method static Builder<static>|Link whereFinancerId($value)
 * @method static Builder<static>|Link whereFrontEndpoint($value)
 * @method static Builder<static>|Link whereId($value)
 * @method static Builder<static>|Link whereLogoUrl($value)
 * @method static Builder<static>|Link whereName($value)
 * @method static Builder<static>|Link wherePosition($value)
 * @method static Builder<static>|Link whereUpdatedAt($value)
 * @method static Builder<static>|Link whereUrl($value)
 * @method static Builder<static>|Link withTrashed()
 * @method static Builder<static>|Link withoutTrashed()
 * @method static Builder<static>|Link related()
 * @method static Builder<static>|Link pipeFiltered()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $pinnedByUsers
 * @property-read int|null $pinned_by_users_count
 * @property-read mixed $translations
 * @property-read \App\Models\Division|null $division
 * @method static \App\Integrations\HRTools\Database\factories\LinkFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Link whereLocales(string $column, array $locales)
 */
	class Link extends \Eloquent {}
}

namespace App\Integrations\InternalCommunication\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string $author_id
 * @property string $title
 * @property string $content
 * @property-read Collection<int, Tag> $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Financer $financer
 * @property-read User $author
 * @property-read MediaCollection<int, Media> $media
 * @property-read Collection<int, ArticleInteraction> $interactions
 * @property-read int|null $interactions_count
 * @method static ArticleFactory factory($count = null, $state = [])
 * @method static Builder<self> newModelQuery()
 * @method static Builder<self> newQuery()
 * @method static Builder<self> onlyTrashed()
 * @method static Builder<self> query()
 * @method static Builder<self> whereAuthorId($value)
 * @method static Builder<self> whereContent($value)
 * @method static Builder<self> whereCreatedAt($value)
 * @method static Builder<self> whereDeletedAt($value)
 * @method static Builder<self> whereFinancerId($value)
 * @method static Builder<self> whereId($value)
 * @method static Builder<self> wherePublishedAt($value)
 * @method static Builder<self> whereStatus($value)
 * @method static Builder<self> whereTags($value)
 * @method static Builder<self> whereTitle($value)
 * @method static Builder<self> whereUpdatedAt($value)
 * @method static Builder<self> withTrashed()
 * @method static Builder<self> withoutTrashed()
 * @mixin \Eloquent
 * @property string|null $segment_id
 * @property-read mixed $active_illustration_url
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LLMRequest> $llmRequests
 * @property-read int|null $llm_requests_count
 * @property-read int|null $media_count
 * @property-read \App\Models\Segment|null $segment
 * @property-read int|null $tags_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\InternalCommunication\Models\ArticleTranslation> $translations
 * @property-read int|null $translations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\InternalCommunication\Models\ArticleVersion> $versions
 * @property-read int|null $versions_count
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Article forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Article forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Article pipeFiltered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Article whereSegmentId($value)
 */
	class Article extends \Eloquent {}
}

namespace App\Integrations\InternalCommunication\Models{
/**
 * ArticleInteraction model for tracking user interactions with articles.
 *
 * @property string $id
 * @property string $user_id
 * @property string $article_id
 * @property string|null $article_translation_id
 * @property string|null $reaction
 * @property bool $is_favorite
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $emoji
 * @property-read User $user
 * @property-read Article $article
 * @property-read ArticleTranslation|null $translation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @method static \App\Integrations\InternalCommunication\Database\factories\ArticleInteractionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereArticleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereArticleTranslationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereIsFavorite($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereReaction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleInteraction whereUserId($value)
 */
	class ArticleInteraction extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Integrations\InternalCommunication\Models{
/**
 * @property string $title
 * @property string $content
 * @property array<string>|null $tags
 * @property string $language
 * @property string $status
 * @property string $id
 * @property string $article_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \App\Integrations\InternalCommunication\Models\Article $article
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\InternalCommunication\Models\ArticleInteraction> $interactions
 * @property-read int|null $interactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LLMRequest> $llmRequests
 * @property-read int|null $llm_requests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\InternalCommunication\Models\ArticleVersion> $versions
 * @property-read int|null $versions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereArticleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleTranslation withoutTrashed()
 */
	class ArticleTranslation extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Integrations\InternalCommunication\Models{
/**
 * @property string $id
 * @property string $article_id
 * @property int|string $version_number
 * @property array<string, mixed> $content
 * @property string|null $title
 * @property string|null $prompt
 * @property string|null $llm_response
 * @property string|null $llm_request_id
 * @property int|null $illustration_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $article_translation_id
 * @property string|null $language
 * @property string|null $author_id
 * @property-read \App\Integrations\InternalCommunication\Models\Article $article
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $author
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Media|null $media
 * @property-read \App\Integrations\InternalCommunication\Models\ArticleTranslation|null $translation
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereArticleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereArticleTranslationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereIllustrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereLlmRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereLlmResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion wherePrompt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ArticleVersion whereVersionNumber($value)
 */
	class ArticleVersion extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Integrations\InternalCommunication\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property array<string, string> $label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Financer $financer
 * @property-read Collection<int, Article> $articles
 * @property-read int|null $articles_count
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @method static Builder<self> forFinancer(string $financerId)
 * @method static Builder<self> searchLabel(string $search)
 * @method static Builder<self> usedInArticles()
 * @method static Builder<self> unused()
 * @method static Builder<self> newModelQuery()
 * @method static Builder<self> newQuery()
 * @method static Builder<self> query()
 * @method static Builder<self> whereId($value)
 * @method static Builder<self> whereFinancerId($value)
 * @method static Builder<self> whereLabel($value)
 * @method static Builder<self> whereCreatedAt($value)
 * @method static Builder<self> whereUpdatedAt($value)
 * @method static Builder<self> whereDeletedAt($value)
 * @method static Builder<self> onlyTrashed()
 * @method static Builder<self> withTrashed()
 * @method static Builder<self> withoutTrashed()
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read mixed $translations
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereLocales(string $column, array $locales)
 */
	class Tag extends \Eloquent {}
}

namespace App\Integrations\Payments\Stripe\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string|null $stripe_payment_id
 * @property string|null $stripe_checkout_id
 * @property string $status
 * @property numeric $amount
 * @property string $currency
 * @property int $credit_amount
 * @property string $credit_type
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property-read string $formatted_amount
 * @property-read string|null $product_name
 * @property-read \App\Models\User $user
 * @method static \App\Integrations\Payments\Stripe\Database\factories\StripePaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereCreditAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereCreditType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereStripeCheckoutId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereStripePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StripePayment whereUserId($value)
 */
	class StripePayment extends \Eloquent {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string $submission_id
 * @property string $question_id
 * @property array<string, mixed> $answer
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read User $user
 * @property-read Submission $submission
 * @property-read Question $question
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read string $financer_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @method static \App\Integrations\Survey\Database\factories\AnswerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereSubmissionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Answer withoutTrashed()
 */
	class Answer extends \Eloquent {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string $id
 * @property string $user_id
 * @property string $survey_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read User $user
 * @property-read Survey $survey
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @method static \App\Integrations\Survey\Database\factories\FavoriteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Favorite withoutTrashed()
 */
	class Favorite extends \Eloquent {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string $manager_id
 * @property string $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $financer_id
 * @property-read \App\Models\Financer|null $financer
 * @property-read \App\Models\User $manager
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser whereManagerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ManagerUser whereUserId($value)
 */
	class ManagerUser extends \Eloquent {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string|null $financer_id
 * @property int|null $original_question_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $archived_at
 * @property array<string, string> $text
 * @property array<string, string> $help_text
 * @property string $type
 * @property array<string, mixed> $metadata
 * @property int $id
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer|null $financer
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property string|null $theme_id
 * @property string|null $parent_question_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Answer> $answers
 * @property-read int|null $answers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\QuestionOption> $options
 * @property-read int|null $options_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Questionnaire> $questionnaires
 * @property-read int|null $questionnaires_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Survey> $surveys
 * @property-read int|null $surveys_count
 * @property-read \App\Integrations\Survey\Models\Theme|null $theme
 * @property-read mixed $translations
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question byTheme(string $themeId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question default()
 * @method static \App\Integrations\Survey\Database\factories\QuestionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question onlyArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question pipeFiltered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereHelpText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereOriginalQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereParentQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereThemeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question withArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question withoutArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question withoutTrashed()
 */
	class Question extends \Eloquent implements \App\Contracts\Searchable {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string $question_id
 * @property string|null $original_question_option_id
 * @property int $position
 * @property Carbon|null $deleted_at
 * @property array<string, string> $text
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string $id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Integrations\Survey\Models\Question $question
 * @property-read mixed $translations
 * @method static \App\Integrations\Survey\Database\factories\QuestionOptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereOriginalQuestionOptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionOption withoutTrashed()
 */
	class QuestionOption extends \Eloquent {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string|null $financer_id
 * @property string $type
 * @property array<string, mixed> $settings
 * @property bool $is_default
 * @property Carbon|null $deleted_at
 * @property Carbon|null $archived_at
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property array<string, string> $instructions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer|null $financer
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property string $id
 * @property string $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Question> $questions
 * @property-read int|null $questions_count
 * @property-read mixed $translations
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire byType(string $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire default()
 * @method static \App\Integrations\Survey\Database\factories\QuestionnaireFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire onlyArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire pipeFiltered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereInstructions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire withArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire withoutArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Questionnaire withoutTrashed()
 */
	class Questionnaire extends \Eloquent implements \App\Contracts\Searchable {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string $user_id
 * @property string $survey_id
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer $financer
 * @property-read User $user
 * @property-read Survey $survey
 * @property-read Collection<int, Answer> $answers
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read int|null $answers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Division|null $division
 * @method static \App\Integrations\Survey\Database\factories\SubmissionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereUser(string $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Submission withoutTrashed()
 */
	class Submission extends \Eloquent {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string|null $financer_id
 * @property string $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property array<string, string> $title
 * @property array<string, string> $description
 * @property array<string, string> $welcome_message
 * @property array<string, string> $thank_you_message
 * @property array<string, mixed> $settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $archived_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property-read float $response_rate
 * @property-read float $completion_rate
 * @property string $id
 * @property string|null $segment_id
 * @property-read int|null $users_count
 * @property-read int|null $submissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Answer> $answers
 * @property-read int|null $answers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Favorite> $favorites
 * @property-read int|null $favorites_count
 * @property-read \App\Models\Financer $financer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Question> $questions
 * @property-read int|null $questions_count
 * @property-read \App\Models\Segment|null $segment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Submission> $submissions
 * @property-read mixed $translations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey closed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey draft()
 * @method static \App\Integrations\Survey\Database\factories\SurveyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey new()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey onlyArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereArchivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereEndsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereSegmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereStartsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereSubmissionsCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereThankYouMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereUsersCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey whereWelcomeMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey withArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey withinPeriod(\Illuminate\Support\Carbon|string $startDate, \Illuminate\Support\Carbon|string $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey withoutArchived()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Survey withoutTrashed()
 */
	class Survey extends \Eloquent implements \App\Contracts\Searchable {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string $survey_id
 * @property string $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Integrations\Survey\Models\Survey $survey
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyUser whereSurveyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SurveyUser whereUserId($value)
 */
	class SurveyUser extends \Eloquent {}
}

namespace App\Integrations\Survey\Models{
/**
 * @property string|null $financer_id
 * @property bool $is_default
 * @property int $position
 * @property array<string, string> $name
 * @property array<string, string> $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property-read Financer|null $financer
 * @property-read User|null $creator
 * @property-read User|null $updater
 * @property string $id
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Question> $defaultQuestions
 * @property-read int|null $default_questions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Question> $questions
 * @property-read int|null $questions_count
 * @property-read mixed $translations
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme default()
 * @method static \App\Integrations\Survey\Database\factories\ThemeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme pipeFiltered()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Theme withoutTrashed()
 */
	class Theme extends \Eloquent implements \App\Contracts\Searchable {}
}

namespace App\Integrations\Vouchers\Amilon\Models{
/**
 * @property string $id
 * @property array<string, string> $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Vouchers\Amilon\Models\Merchant> $merchants
 * @property-read int|null $merchants_count
 * @property-read int|null $products_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 */
	class Category extends \Eloquent {}
}

namespace App\Integrations\Vouchers\Amilon\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $country
 * @property string $merchant_id
 * @property string|null $description
 * @property string|null $image_url
 * @property array<int, float>|null $available_amounts
 * @property float|null $average_discount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Vouchers\Amilon\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property-read string|null $category
 * @property-read string|null $retailer_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Vouchers\Amilon\Models\Product> $products
 * @property-read int|null $products_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant byCountry(string $country)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant byMerchantId(string $merchantId)
 * @method static \App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant orderByName(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant searchByName(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereAverageDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant withCategory(int $categoryId)
 */
	class Merchant extends \Eloquent {}
}

namespace App\Integrations\Vouchers\Amilon\Models{
/**
 * @property string $id
 * @property string $merchant_id
 * @property float $amount
 * @property string $external_order_id
 * @property string|null $order_id
 * @property string|null $status
 * @property float|null $price_paid
 * @property string|null $voucher_url
 * @property string|null $user_id
 * @property string|null $payment_id
 * @property string|null $product_id
 * @property float|null $total_amount
 * @property string|null $payment_method
 * @property string|null $stripe_payment_id
 * @property float|null $balance_amount_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $order_date
 * @property string|null $order_status
 * @property float|null $gross_amount
 * @property float|null $net_amount
 * @property int|null $total_requested_codes
 * @property array<string, mixed>|null $metadata
 * @property string|null $voucher_code
 * @property string|null $voucher_pin
 * @property string|null $product_name
 * @property string $currency
 * @property int $recovery_attempts
 * @property string|null $last_error
 * @property Carbon|null $last_recovery_attempt
 * @property Carbon|null $next_retry_at
 * @property string|null $order_recovered_id
 * @property-read User|null $user
 * @property-read Merchant|null $merchant
 * @property-read Product|null $product
 * @property-read Collection<int, OrderItem> $items
 * @property-read Order|null $recoveredOrder
 * @property-read Order|null $newOrder
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order byExternalOrderId(string $externalOrderId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order byMerchantId(string $merchantId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order byOrderId(string $orderId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order byUserId(string $userId)
 * @method static \App\Integrations\Vouchers\Amilon\Database\factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order orderByAmount(string $direction = 'desc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order orderByCreatedAt(string $direction = 'desc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order status(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereBalanceAmountUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereExternalOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereGrossAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereLastRecoveryAttempt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNetAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNextRetryAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderRecoveredId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePricePaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereRecoveryAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereStripePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalRequestedCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereVoucherCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereVoucherPin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereVoucherUrl($value)
 */
	class Order extends \Eloquent {}
}

namespace App\Integrations\Vouchers\Amilon\Models{
/**
 * @property string $id
 * @property string $order_id
 * @property string $product_id
 * @property int $quantity
 * @property float|null $price
 * @property array<string, mixed>|null $vouchers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order $order
 * @property-read Product $product
 * @method static \App\Integrations\Vouchers\Amilon\Database\factories\OrderItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem forOrder(string $orderId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem forProduct(string $productId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereVouchers($value)
 */
	class OrderItem extends \Eloquent {}
}

namespace App\Integrations\Vouchers\Amilon\Models{
/**
 * @property int $id
 * @property string $event_id
 * @property string $event_type
 * @property \Illuminate\Support\Carbon $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProcessedWebhookEvent whereUpdatedAt($value)
 */
	class ProcessedWebhookEvent extends \Eloquent {}
}

namespace App\Integrations\Vouchers\Amilon\Models{
/**
 * Product model for Amilon voucher products.
 * 
 * IMPORTANT: All monetary amounts (price, net_price) are stored in CENTS.
 * 1 euro = 100 cents. This avoids floating point precision issues.
 * Discount is stored as percentage * 100 in DB (e.g., 6.67% = 667 in DB).
 *
 * @property string $id
 * @property string $name
 * @property string|null $product_code
 * @property string|null $category_id
 * @property string $merchant_id
 * @property int|null $price Price in cents (e.g., 1000 = €10.00)
 * @property int|null $net_price Net price in cents after discount
 * @property float|null $discount Discount as percentage (e.g., 6.67 for 6.67%)
 * @property string|null $currency
 * @property string|null $country
 * @property string|null $description
 * @property string|null $image_url
 * @property bool $is_available
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Merchant|null $merchant
 * @property-read Category|null $category
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product byCountry(string $country)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product byId(string $id)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product byMerchantId(string $merchantId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product category(string $category)
 * @method static \App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product orderByCategory(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product orderByName(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product orderByPrice(string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product priceRange(float $min, float $max)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product searchByName(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereNetPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereProductCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Product whereUpdatedAt($value)
 */
	class Product extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $log_name
 * @property string $description
 * @property string|null $subject_id
 * @property string|null $subject_type
 * @property string|null $causer_id
 * @property string|null $causer_type
 * @property \Illuminate\Support\Collection<array-key, mixed>|null $properties
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $event
 * @property string|null $batch_uuid
 * @property-read \Illuminate\Database\Eloquent\Model|null $causer
 * @property-read \Illuminate\Support\Collection $changes
 * @property-read \Illuminate\Database\Eloquent\Model|null $subject
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity causedBy(\Illuminate\Database\Eloquent\Model $causer)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity forBatch(string $batchUuid)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity forEvent(string $event)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity forSubject(\Illuminate\Database\Eloquent\Model $subject)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity hasBatch()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity inLog(...$logNames)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereBatchUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCauserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCauserType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereEvent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereLogName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereProperties($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereSubjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereSubjectType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Activity whereUpdatedAt($value)
 */
	class Activity extends \Eloquent {}
}

namespace App\Models\AdminPanel{
/**
 * @property int $id
 * @property string|null $user_id
 * @property string $action
 * @property string $entity_type
 * @property string|null $entity_id
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $timestamp
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $auditable
 * @property-read string $action_label
 * @property-read array $changes
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog byAction(string $action)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog byEntityType(string $entityType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog byUser(int $userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog inDateRange($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereTimestamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdminAuditLog whereUserId($value)
 */
	class AdminAuditLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $user_type
 * @property string|null $user_id
 * @property string $event
 * @property string $auditable_type
 * @property string $auditable_id
 * @property array<array-key, mixed>|null $old_values
 * @property array<array-key, mixed>|null $new_values
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $tags
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $financer_id
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $auditable
 * @property-read \App\Models\Financer|null $financer
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $user
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereAuditableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereAuditableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereEvent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereNewValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereOldValues($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Audit whereUserType($value)
 */
	class Audit extends \Eloquent {}
}

namespace App\Models{
/**
 * Cognito Audit Log Model - RGPD-compliant encrypted audit trail
 * 
 * Stores encrypted audit logs for Cognito SMS/Email notifications.
 * - Payload is encrypted using Laravel Crypt (PII protection)
 * - Identifiers are SHA256 hashed (RGPD compliant)
 * - 90-day automatic retention via PostgreSQL trigger
 * - No updated_at column (immutable logs)
 *
 * @property int $id
 * @property string $identifier_hash SHA256 hash of email/phone
 * @property string $type sms or email
 * @property string $trigger_source Cognito trigger source
 * @property string $locale Language code (e.g., 'fr-FR')
 * @property string $status queued, sent, failed, retrying
 * @property string $encrypted_payload Laravel Crypt encrypted JSON
 * @property string|null $error_message Error details if failed
 * @property string|null $source_ip IP address (IPv4/IPv6)
 * @property Carbon $created_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereEncryptedPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereIdentifierHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereSourceIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereTriggerSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CognitoAuditLog whereType($value)
 */
	class CognitoAuditLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string|null $apideck_id
 * @property array<array-key, mixed> $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\ContractTypeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereApideckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContractType withoutTrashed()
 */
	class ContractType extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $owner_type
 * @property string $owner_id
 * @property string $type
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property array<array-key, mixed>|null $context
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Division|null $division
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $owner
 * @method static \Database\Factories\CreditBalanceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereContext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CreditBalance whereUpdatedAt($value)
 */
	class CreditBalance extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $entity_type
 * @property string $entity_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DemoEntity whereUpdatedAt($value)
 */
	class DemoEntity extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $financer_id
 * @property string|null $apideck_id
 * @property array<array-key, mixed> $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\DepartmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereApideckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Department withoutTrashed()
 */
	class Department extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string $country
 * @property string $currency
 * @property string $timezone
 * @property string $language
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read bool|null $use_factory
 * @method static DivisionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Division newModelQuery()
 * @method static Builder<static>|Division newQuery()
 * @method static Builder<static>|Division onlyTrashed()
 * @method static Builder<static>|Division pipeFiltered()
 * @method Builder<static>|Division pipeFiltered()
 * @method static Builder<static>|Division query()
 * @method static Builder<static>|Division whereCountry($value)
 * @method static Builder<static>|Division whereCreatedAt($value)
 * @method static Builder<static>|Division whereCurrency($value)
 * @method static Builder<static>|Division whereDeletedAt($value)
 * @method static Builder<static>|Division whereDescription($value)
 * @method static Builder<static>|Division whereId($value)
 * @method static Builder<static>|Division whereLanguage($value)
 * @method static Builder<static>|Division whereName($value)
 * @method static Builder<static>|Division whereTimezone($value)
 * @method static Builder<static>|Division whereUpdatedAt($value)
 * @method static Builder<static>|Division withTrashed()
 * @method static Builder<static>|Division withoutTrashed()
 * @property string|null $remarks
 * @method static Builder<static>|Division whereRemarks($value)
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read DivisionModule|DivisionIntegration|null $pivot
 * @property-read Collection<int, Integration> $integrations
 * @property-read int|null $integrations_count
 * @property-read Collection<int, Module> $modules
 * @property-read int|null $modules_count
 * @mixin Eloquent
 * @property string $status
 * @property int|null $core_package_price Price in euro cents for core modules package
 * @property string|null $vat_rate
 * @property string|null $contract_start_date
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Financer> $financers
 * @property-read int|null $financers_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Division whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Division whereCorePackagePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Division whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Division whereVatRate($value)
 */
	class Division extends \Eloquent implements \App\Contracts\Searchable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $division_id
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $last_invoice_at
 * @property \Illuminate\Support\Carbon|null $last_payment_at
 * @property \Illuminate\Support\Carbon|null $last_credit_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Division $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereDivisionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereLastCreditAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereLastInvoiceAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereLastPaymentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionBalance whereUpdatedAt($value)
 */
	class DivisionBalance extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string $user_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|FinancerUser newModelQuery()
 * @method static Builder<static>|FinancerUser newQuery()
 * @method static Builder<static>|FinancerUser query()
 * @method static Builder<static>|FinancerUser whereActive($value)
 * @method static Builder<static>|FinancerUser whereCreatedAt($value)
 * @method static Builder<static>|FinancerUser whereFinancerId($value)
 * @method static Builder<static>|FinancerUser whereId($value)
 * @method static Builder<static>|FinancerUser whereUpdatedAt($value)
 * @method static Builder<static>|FinancerUser whereUserId($value)
 * @property string $division_id
 * @property string $integration_id
 * @method static Builder<static>|DivisionIntegration whereDivisionId($value)
 * @method static Builder<static>|DivisionIntegration whereIntegrationId($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Division|null $division
 */
	class DivisionIntegration extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string $user_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|FinancerUser newModelQuery()
 * @method static Builder<static>|FinancerUser newQuery()
 * @method static Builder<static>|FinancerUser query()
 * @method static Builder<static>|FinancerUser whereActive($value)
 * @method static Builder<static>|FinancerUser whereCreatedAt($value)
 * @method static Builder<static>|FinancerUser whereFinancerId($value)
 * @method static Builder<static>|FinancerUser whereId($value)
 * @method static Builder<static>|FinancerUser whereUpdatedAt($value)
 * @method static Builder<static>|FinancerUser whereUserId($value)
 * @property string $division_id
 * @property string $module_id
 * @method static Builder<static>|DivisionModule whereDivisionId($value)
 * @method static Builder<static>|DivisionModule whereModuleId($value)
 * @mixin \Eloquent
 * @property int|null $price_per_beneficiary Price in euro cents per beneficiary for this module
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DivisionModule wherePricePerBeneficiary($value)
 */
	class DivisionModule extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $user_id
 * @property string $type
 * @property string|null $target
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon $logged_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\EngagementLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereLoggedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementLog whereUserId($value)
 */
	class EngagementLog extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property \Illuminate\Support\Carbon $date_from
 * @property string $metric
 * @property string|null $financer_id
 * @property array<array-key, mixed> $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon $date_to
 * @property string $period
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereDateFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereDateTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereMetric($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EngagementMetric whereUpdatedAt($value)
 */
	class EngagementMetric extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property array<array-key, mixed>|null $external_id
 * @property string $timezone
 * @property string|null $registration_number
 * @property string|null $registration_country
 * @property string|null $website
 * @property string|null $iban
 * @property string|null $bic
 * @property string|null $vat_number
 * @property string|null $representative_id
 * @property string $division_id
 * @property bool $active
 * @property string $status
 * @property string $company_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read Division $division
 * @property-read FinancerUser|FinancerModule|FinancerIntegration|null $pivot
 * @property-read Collection<int, Integration> $integrations
 * @property-read int|null $integrations_count
 * @property-read Collection<int, Module> $modules
 * @property-read int|null $modules_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @method static FinancerFactory factory($count = null, $state = [])
 * @method static Builder<static>|Financer newModelQuery()
 * @method static Builder<static>|Financer newQuery()
 * @method static Builder<static>|Financer onlyTrashed()
 * @method static Builder<static>|Financer pipeFiltered()
 * @method Builder<static>|Financer pipeFiltered()
 * @method static Builder<static>|Financer query()
 * @method static Builder<static>|Financer whereCreatedAt($value)
 * @method static Builder<static>|Financer whereDeletedAt($value)
 * @method static Builder<static>|Financer whereDivisionId($value)
 * @method static Builder<static>|Financer whereExternalId($value)
 * @method static Builder<static>|Financer whereIban($value)
 * @method static Builder<static>|Financer whereId($value)
 * @method static Builder<static>|Financer whereName($value)
 * @method static Builder<static>|Financer whereRegistrationCountry($value)
 * @method static Builder<static>|Financer whereRegistrationNumber($value)
 * @method static Builder<static>|Financer whereRepresentativeId($value)
 * @method static Builder<static>|Financer whereTimezone($value)
 * @method static Builder<static>|Financer whereUpdatedAt($value)
 * @method static Builder<static>|Financer whereVatNumber($value)
 * @method static Builder<static>|Financer whereWebsite($value)
 * @method static Builder<static>|Financer withTrashed()
 * @method static Builder<static>|Financer withoutTrashed()
 * @mixin \Eloquent
 * @property array<array-key, mixed> $available_languages
 * @property int|null $core_package_price Price in euro cents for core modules package (overrides division price)
 * @property string|null $contract_start_date
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditBalance> $credits
 * @property-read int|null $credits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\JobLevel> $jobLevels
 * @property-read int|null $job_levels_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\JobTitle> $jobTitles
 * @property-read int|null $job_titles_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\WorkMode> $workModes
 * @property-read int|null $work_modes_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer whereAvailableLanguages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer whereBic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer whereCompanyNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer whereCorePackagePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Financer whereStatus($value)
 */
	class Financer extends \Eloquent implements \Spatie\MediaLibrary\HasMedia, \App\Contracts\Searchable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $last_invoice_at
 * @property \Illuminate\Support\Carbon|null $last_payment_at
 * @property \Illuminate\Support\Carbon|null $last_credit_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereLastCreditAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereLastInvoiceAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereLastPaymentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerBalance whereUpdatedAt($value)
 */
	class FinancerBalance extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string $user_id
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|FinancerUser newModelQuery()
 * @method static Builder<static>|FinancerUser newQuery()
 * @method static Builder<static>|FinancerUser query()
 * @method static Builder<static>|FinancerUser whereActive($value)
 * @method static Builder<static>|FinancerUser whereCreatedAt($value)
 * @method static Builder<static>|FinancerUser whereFinancerId($value)
 * @method static Builder<static>|FinancerUser whereId($value)
 * @method static Builder<static>|FinancerUser whereUpdatedAt($value)
 * @method static Builder<static>|FinancerUser whereUserId($value)
 * @property string $integration_id
 * @method static Builder<static>|FinancerIntegration whereIntegrationId($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Financer|null $financer
 * @property-read \App\Models\Division|null $division
 */
	class FinancerIntegration extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * FinancerMetric Model
 * 
 * Extends EngagementMetric to provide financer-specific metrics functionality
 * with Redis Cluster caching support.
 *
 * @property string $id
 * @property string $date_from
 * @property string $date_to
 * @property string $metric
 * @property string|null $financer_id
 * @property string $period
 * @property array<string, mixed> $data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Financer|null $financer
 * @method static Builder<static> byFinancer(string $financerId)
 * @method static Builder<static> byDateRange(Carbon $startDate, Carbon $endDate)
 * @method static Builder<static> byMetricType(string $metricType)
 * @method static Builder<static> latest()
 * @method static static|null findCached(string $id, array<int, string> $with = [])
 * @method static Collection<int, static> allCached(array<int, string> $with = [])
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereDateFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereDateTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereMetric($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerMetric whereUpdatedAt($value)
 */
	class FinancerMetric extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string $module_id
 * @property bool $active
 * @property bool $promoted
 * @property int|null $price_per_beneficiary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|FinancerModule newModelQuery()
 * @method static Builder<static>|FinancerModule newQuery()
 * @method static Builder<static>|FinancerModule query()
 * @method static Builder<static>|FinancerModule whereActive($value)
 * @method static Builder<static>|FinancerModule whereCreatedAt($value)
 * @method static Builder<static>|FinancerModule whereFinancerId($value)
 * @method static Builder<static>|FinancerModule whereId($value)
 * @method static Builder<static>|FinancerModule whereModuleId($value)
 * @method static Builder<static>|FinancerModule wherePricePerBeneficiary($value)
 * @method static Builder<static>|FinancerModule wherePromoted($value)
 * @method static Builder<static>|FinancerModule whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Financer|null $financer
 * @property-read \App\Models\Division|null $division
 */
	class FinancerModule extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string $user_id
 * @property bool $active
 * @property array|null $roles
 * @property string|null $sirh_id
 * @property string|null $language
 * @property Carbon $from
 * @property Carbon|null $to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder<static>|FinancerUser newModelQuery()
 * @method static Builder<static>|FinancerUser newQuery()
 * @method static Builder<static>|FinancerUser query()
 * @method static Builder<static>|FinancerUser whereActive($value)
 * @method static Builder<static>|FinancerUser whereCreatedAt($value)
 * @method static Builder<static>|FinancerUser whereFinancerId($value)
 * @method static Builder<static>|FinancerUser whereId($value)
 * @method static Builder<static>|FinancerUser whereUpdatedAt($value)
 * @method static Builder<static>|FinancerUser whereUserId($value)
 * @method static Builder<static>|FinancerUser whereSirhId($value)
 * @method static Builder<static>|FinancerUser whereFrom($value)
 * @method static Builder<static>|FinancerUser whereTo($value)
 * @mixin \Eloquent
 * @property string|null $work_mode_id
 * @property string|null $job_title_id
 * @property string|null $job_level_id
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property string $role
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\JobLevel|null $jobLevel
 * @property-read \App\Models\JobTitle|null $jobTitle
 * @property-read \App\Models\User $user
 * @property-read \App\Models\WorkMode|null $workMode
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerUser whereJobLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerUser whereJobTitleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerUser whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerUser whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerUser whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FinancerUser whereWorkModeId($value)
 */
	class FinancerUser extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $module_id
 * @property string $name
 * @property string $type
 * @property string|null $description
 * @property bool $active
 * @property array<array-key, mixed>|null $settings
 * @property string|null $resources_count_query
 * @property string|null $resources_count_unit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read FinancerModule|DivisionModule|null $pivot
 * @property-read Collection<int, Division> $divisions
 * @property-read int|null $divisions_count
 * @property-read Collection<int, Financer> $financers
 * @property-read int|null $financers_count
 * @property-read Module|null $module
 * @method static IntegrationFactory factory($count = null, $state = [])
 * @method static Builder<static>|Integration newModelQuery()
 * @method static Builder<static>|Integration newQuery()
 * @method static Builder<static>|Integration onlyTrashed()
 * @method static Builder<static>|Integration pipeFiltered()
 * @method Builder<static>|Integration pipeFiltered()
 * @method static Builder<static>|Integration query()
 * @method static Builder<static>|Integration whereActive($value)
 * @method static Builder<static>|Integration whereCreatedAt($value)
 * @method static Builder<static>|Integration whereDeletedAt($value)
 * @method static Builder<static>|Integration whereDescription($value)
 * @method static Builder<static>|Integration whereId($value)
 * @method static Builder<static>|Integration whereModuleId($value)
 * @method static Builder<static>|Integration whereName($value)
 * @method static Builder<static>|Integration whereSettings($value)
 * @method static Builder<static>|Integration whereType($value)
 * @method static Builder<static>|Integration whereUpdatedAt($value)
 * @method static Builder<static>|Integration withTrashed()
 * @method static Builder<static>|Integration withoutTrashed()
 * @mixin \Eloquent
 * @property string|null $api_endpoint
 * @property string|null $front_endpoint
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Integration whereApiEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Integration whereFrontEndpoint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Integration whereResourcesCountQuery($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Integration whereResourcesCountUnit($value)
 */
	class Integration extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $invoice_number
 * @property string $invoice_type
 * @property string $issuer_type
 * @property string|null $issuer_id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property \Illuminate\Support\Carbon $billing_period_start
 * @property \Illuminate\Support\Carbon $billing_period_end
 * @property int $subtotal_htva
 * @property numeric $vat_rate
 * @property int $vat_amount
 * @property int $total_ttc
 * @property string $currency
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $due_date
 * @property string|null $notes
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceItem> $items
 * @property-read int|null $items_count
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $recipient
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice accessibleByUser(\App\Models\User $user)
 * @method static \Database\Factories\InvoiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBillingPeriodEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereBillingPeriodStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereInvoiceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssuerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereIssuerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereRecipientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereRecipientType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereSubtotalHtva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereTotalTtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereVatAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice whereVatRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invoice withoutTrashed()
 */
	class Invoice extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $batch_id
 * @property string $month_year
 * @property int $total_invoices
 * @property int $completed_count
 * @property int $failed_count
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereCompletedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereFailedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereMonthYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereTotalInvoices($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceGenerationBatch whereUpdatedAt($value)
 */
	class InvoiceGenerationBatch extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $invoice_id
 * @property string $item_type
 * @property string|null $module_id
 * @property array<string, string|null> $label
 * @property array<string, string|null>|null $description
 * @property int|null $beneficiaries_count
 * @property int $unit_price_htva
 * @property int $quantity
 * @property int $subtotal_htva
 * @property numeric|null $vat_rate
 * @property int|null $vat_amount
 * @property int|null $total_ttc
 * @property numeric|null $prorata_percentage
 * @property int|null $prorata_days
 * @property int|null $total_days
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Invoice $invoice
 * @property-read \App\Models\Module|null $module
 * @property-read mixed $translations
 * @method static \Database\Factories\InvoiceItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereBeneficiariesCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereItemType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereLocales(string $column, array $locales)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereModuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereProrataDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereProrataPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereSubtotalHtva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTotalDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereTotalTtc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUnitPriceHtva($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereVatAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InvoiceItem whereVatRate($value)
 */
	class InvoiceItem extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string|null $apideck_id
 * @property array<array-key, mixed> $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\JobLevelFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereApideckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobLevel withoutTrashed()
 */
	class JobLevel extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string|null $apideck_id
 * @property array<array-key, mixed> $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\JobTitleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereApideckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|JobTitle withoutTrashed()
 */
	class JobTitle extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $prompt
 * @property string $response
 * @property int $tokens_used
 * @property string $engine_used
 * @property string $financer_id
 * @property string $requestable_id
 * @property string $requestable_type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $prompt_system
 * @property array<array-key, mixed>|null $messages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\Financer $financer
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $requestable
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereEngineUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereMessages($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest wherePrompt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest wherePromptSystem($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereRequestableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereRequestableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereTokensUsed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LLMRequest whereUpdatedAt($value)
 */
	class LLMRequest extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $financer_id
 * @property string|null $user_id
 * @property string|null $platform
 * @property string|null $version
 * @property string|null $minimum_required_version
 * @property bool $should_update
 * @property string|null $update_type
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<array-key, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Financer|null $financer
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\MobileVersionLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereMinimumRequiredVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereShouldUpdate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereUpdateType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereUserAgent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MobileVersionLog whereVersion($value)
 */
	class MobileVersionLog extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property array<string, string> $name
 * @property array<string, string|null>|null $description
 * @property bool $active
 * @property array<array-key, mixed>|null $settings
 * @property string $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read FinancerModule|DivisionModule|null $pivot
 * @property-read Collection<int, Division> $divisions
 * @property-read int|null $divisions_count
 * @property-read Collection<int, Financer> $financers
 * @property-read int|null $financers_count
 * @method static ModuleFactory factory($count = null, $state = [])
 * @method static Builder<static>|Module newModelQuery()
 * @method static Builder<static>|Module newQuery()
 * @method static Builder<static>|Module onlyTrashed()
 * @method static Builder<static>|Module query()
 * @method static Builder<static>|Module whereActive($value)
 * @method static Builder<static>|Module whereCategory($value)
 * @method static Builder<static>|Module whereCreatedAt($value)
 * @method static Builder<static>|Module whereDeletedAt($value)
 * @method static Builder<static>|Module whereDescription($value)
 * @method static Builder<static>|Module whereId($value)
 * @method static Builder<static>|Module whereName($value)
 * @method static Builder<static>|Module whereSettings($value)
 * @method static Builder<static>|Module whereUpdatedAt($value)
 * @method static Builder<static>|Module withTrashed()
 * @method static Builder<static>|Module withoutTrashed()
 * @mixin \Eloquent
 * @property bool $is_core
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Integration> $integrations
 * @property-read int|null $integrations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $pinnedByUsers
 * @property-read int|null $pinned_by_users_count
 * @property-read mixed $translations
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereIsCore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereJsonContainsLocale(string $column, string $locale, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereJsonContainsLocales(string $column, array $locales, ?mixed $value, string $operand = '=')
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereLocale(string $column, string $locale)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Module whereLocales(string $column, array $locales)
 */
	class Module extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $module_id
 * @property string $entity_id Division or Financer ID
 * @property string $entity_type division or financer
 * @property int|null $old_price Previous price in euro cents
 * @property int|null $new_price New price in euro cents
 * @property string $price_type core_package or module_price
 * @property string|null $changed_by User ID who made the change
 * @property string|null $reason Reason for price change
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property-read \App\Models\User|null $changedBy
 * @property-read \App\Models\Module $module
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory activeOn(?mixed $date)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory current()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory forEntity(string $entityId, string $entityType)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereChangedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereEntityType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereModuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereNewPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereOldPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory wherePriceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModulePricingHistory whereValidUntil($value)
 */
	class ModulePricingHistory extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property string|null $financer_id
 * @property bool $is_active
 * @property int $subscriber_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Financer|null $financer
 * @property-read \App\Models\NotificationTopicSubscription|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PushSubscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read \App\Models\Division|null $division
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic active()
 * @method static \Database\Factories\NotificationTopicFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic forFinancer($financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic global()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereSubscriberCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopic whereUpdatedAt($value)
 */
	class NotificationTopic extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $notification_topic_id
 * @property string $push_subscription_id
 * @property \Illuminate\Support\Carbon|null $subscribed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription whereNotificationTopicId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription wherePushSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription whereSubscribedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationTopicSubscription whereUpdatedAt($value)
 */
	class NotificationTopicSubscription extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_protected
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \App\Models\Permission|null $use_factory
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @method static PermissionFactory factory($count = null, $state = [])
 * @method static Builder<static>|Permission newModelQuery()
 * @method static Builder<static>|Permission newQuery()
 * @method static Builder<static>|Permission permission($permissions, $without = false)
 * @method static Builder<static>|Permission query()
 * @method static Builder<static>|Permission role($roles, $guard = null, $without = false)
 * @method static Builder<static>|Permission whereCreatedAt($value)
 * @method static Builder<static>|Permission whereDeletedAt($value)
 * @method static Builder<static>|Permission whereGuardName($value)
 * @method static Builder<static>|Permission whereId($value)
 * @method static Builder<static>|Permission whereIsProtected($value)
 * @method static Builder<static>|Permission whereName($value)
 * @method static Builder<static>|Permission whereUpdatedAt($value)
 * @method static Builder<static>|Permission withoutPermission($permissions)
 * @method static Builder<static>|Permission withoutRole($roles, $guard = null)
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 */
	class Permission extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $push_notification_id
 * @property string|null $push_subscription_id
 * @property \BenSampo\Enum\Enum $event_type
 * @property string|null $event_id
 * @property array<array-key, mixed> $event_data
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\PushNotification $pushNotification
 * @property-read \App\Models\PushSubscription|null $pushSubscription
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent byType(\App\Enums\PushEventTypes $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent engagement()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent failed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent inDateRange($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent successful()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereEventData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereOccurredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent wherePushNotificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent wherePushSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushEvent whereUserAgent($value)
 */
	class PushEvent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $notification_id
 * @property string|null $external_id
 * @property string $delivery_type
 * @property int $device_count
 * @property \BenSampo\Enum\Enum $type
 * @property string $title
 * @property string $body
 * @property string|null $url
 * @property string|null $image
 * @property string|null $icon
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $buttons
 * @property string $priority
 * @property int $ttl
 * @property string $status
 * @property int $recipient_count
 * @property int $delivered_count
 * @property int $opened_count
 * @property int $clicked_count
 * @property \Illuminate\Support\Carbon|null $scheduled_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 * @property string|null $author_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PushEvent> $pushEvents
 * @property-read int|null $push_events_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification byStatus(string $status)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification byType(\App\Enums\NotificationTypes $type)
 * @method static \Database\Factories\PushNotificationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification scheduled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification sent()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereButtons($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereClickedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereDeliveredCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereDeliveryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereDeviceCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereExternalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereNotificationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereOpenedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereRecipientCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereScheduledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereTtl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushNotification whereUrl($value)
 */
	class PushNotification extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $user_id
 * @property string $subscription_id
 * @property \BenSampo\Enum\Enum $device_type
 * @property string|null $device_model
 * @property string|null $device_os
 * @property string|null $app_version
 * @property string|null $timezone
 * @property string|null $language
 * @property array<array-key, mixed> $notification_preferences
 * @property bool $push_enabled
 * @property bool $sound_enabled
 * @property bool $vibration_enabled
 * @property array<array-key, mixed> $tags
 * @property array<array-key, mixed> $metadata
 * @property \Illuminate\Support\Carbon|null $last_active_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PushEvent> $pushEvents
 * @property-read int|null $push_events_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\NotificationTopic> $topics
 * @property-read int|null $topics_count
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription byDeviceType(\App\Enums\DeviceTypes $type)
 * @method static \Database\Factories\PushSubscriptionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription forUser($userId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereAppVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereDeviceModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereDeviceOs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereDeviceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereLastActiveAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereNotificationPreferences($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription wherePushEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereSoundEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereTimezone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription whereVibrationEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PushSubscription withoutTrashed()
 */
	class PushSubscription extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $team_id
 * @property string $name
 * @property string $guard_name
 * @property bool $is_protected
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read \App\Models\Role|null $use_factory
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @method static RoleFactory factory($count = null, $state = [])
 * @method static Builder<static>|Role newModelQuery()
 * @method static Builder<static>|Role newQuery()
 * @method static Builder<static>|Role onlyTrashed()
 * @method static Builder<static>|Role permission($permissions, $without = false)
 * @method static Builder<static>|Role query()
 * @method static Builder<static>|Role whereCreatedAt($value)
 * @method static Builder<static>|Role whereDeletedAt($value)
 * @method static Builder<static>|Role whereGuardName($value)
 * @method static Builder<static>|Role whereId($value)
 * @method static Builder<static>|Role whereIsProtected($value)
 * @method static Builder<static>|Role whereName($value)
 * @method static Builder<static>|Role whereTeamId($value)
 * @method static Builder<static>|Role whereUpdatedAt($value)
 * @method static Builder<static>|Role withTrashed()
 * @method static Builder<static>|Role withoutPermission($permissions)
 * @method static Builder<static>|Role withoutTrashed()
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 */
	class Role extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string|null $financer_id
 * @property string $name
 * @property string|null $description
 * @property array<array-key, mixed> $filters
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read int $computed_users_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer|null $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\SegmentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereFilters($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Segment withoutTrashed()
 */
	class Segment extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string|null $apideck_id
 * @property array<array-key, mixed> $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\SiteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereApideckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Site withoutTrashed()
 */
	class Site extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string|null $apideck_id
 * @property array<array-key, mixed> $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\TagFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereApideckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tag withoutTrashed()
 */
	class Tag extends \Eloquent {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TeamTypes|null $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team|null $use_factory
 * @method static TeamFactory factory($count = null, $state = [])
 * @method static Builder<static>|Team newModelQuery()
 * @method static Builder<static>|Team newQuery()
 * @method static Builder<static>|Team onlyTrashed()
 * @method static Builder<static>|Team query()
 * @method static Builder<static>|Team whereCreatedAt($value)
 * @method static Builder<static>|Team whereDeletedAt($value)
 * @method static Builder<static>|Team whereId($value)
 * @method static Builder<static>|Team whereName($value)
 * @method static Builder<static>|Team whereSlug($value)
 * @method static Builder<static>|Team whereType($value)
 * @method static Builder<static>|Team whereUpdatedAt($value)
 * @method static Builder<static>|Team withTrashed()
 * @method static Builder<static>|Team withoutTrashed()
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 */
	class Team extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TestAuditModel whereUpdatedAt($value)
 */
	class TestAuditModel extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string|null $user_id
 * @property string $action
 * @property string $target_type
 * @property int $target_id
 * @property string|null $locale
 * @property array<array-key, mixed>|null $before
 * @property array<array-key, mixed>|null $after
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\TranslationActivityLogFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereTargetType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationActivityLog whereUserId($value)
 */
	class TranslationActivityLog extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $key
 * @property string|null $group
 * @property string $interface_origin
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, TranslationValue> $values
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TranslationActivityLog> $activityLogs
 * @property-read int|null $activity_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read int|null $values_count
 * @method static \Database\Factories\TranslationKeyFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey forInterface(string $interface)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey whereGroup($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey whereInterfaceOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationKey whereUpdatedAt($value)
 */
	class TranslationKey extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $filename
 * @property string $interface_origin
 * @property string $version
 * @property string $checksum
 * @property array<array-key, mixed> $metadata
 * @property string $status
 * @property int|null $batch_number
 * @property \Illuminate\Support\Carbon|null $executed_at
 * @property \Illuminate\Support\Carbon|null $rolled_back_at
 * @property string|null $error_message
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration completed()
 * @method static \Database\Factories\TranslationMigrationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration forInterface(string $interface)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereBatchNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereExecutedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereInterfaceOrigin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereRolledBackAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationMigration whereVersion($value)
 */
	class TranslationMigration extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $translation_key_id
 * @property string $locale
 * @property string $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TranslationKey $key
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TranslationActivityLog> $activityLogs
 * @property-read int|null $activity_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @method static \Database\Factories\TranslationValueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue whereTranslationKeyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TranslationValue whereValue($value)
 */
	class TranslationValue extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $cognito_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property bool $force_change_email
 * @property string|null $birthdate
 * @property bool $terms_confirmed
 * @property bool $enabled
 * @property string $locale
 * @property string $currency
 * @property string|null $timezone
 * @property string|null $stripe_id
 * @property string|null $sirh_id
 * @property string|null $last_login
 * @property bool $opt_in
 * @property string|null $phone
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $invitation_status
 * @property string|null $invitation_token
 * @property Carbon|null $invitation_expires_at
 * @property string|null $invited_by
 * @property Carbon|null $invited_at
 * @property Carbon|null $invitation_accepted_at
 * @property array|null $invitation_metadata
 * @property string|null $original_invited_user_id
 * @property-read \App\Models\User|null $use_factory
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder<static>|User newModelQuery()
 * @method static Builder<static>|User newQuery()
 * @method static Builder<static>|User pipeFiltered()
 * @method Builder<static>|User pipeFiltered()
 * @method static Builder<static>|User query()
 * @method static Builder<static>|User whereBirthdate($value)
 * @method static Builder<static>|User whereCognitoId($value)
 * @method static Builder<static>|User whereCreatedAt($value)
 * @method static Builder<static>|User whereCurrency($value)
 * @method static Builder<static>|User whereDeletedAt($value)
 * @method static Builder<static>|User whereEmail($value)
 * @method static Builder<static>|User whereEmailVerifiedAt($value)
 * @method static Builder<static>|User whereEnabled($value)
 * @method static Builder<static>|User whereExternalId($value)
 * @method static Builder<static>|User whereFirstName($value)
 * @method static Builder<static>|User whereForceChangeEmail($value)
 * @method static Builder<static>|User whereId($value)
 * @method static Builder<static>|User whereLastLogin($value)
 * @method static Builder<static>|User whereLastName($value)
 * @method static Builder<static>|User whereLocale($value)
 * @method static Builder<static>|User whereOptIn($value)
 * @method static Builder<static>|User wherePassword($value)
 * @method static Builder<static>|User wherePhone($value)
 * @method static Builder<static>|User whereRememberToken($value)
 * @method static Builder<static>|User whereStripeId($value)
 * @method static Builder<static>|User whereTermsConfirmed($value)
 * @method static Builder<static>|User whereTimezone($value)
 * @method static Builder<static>|User whereUpdatedAt($value)
 * @property string|null $team_id
 * @property-read FinancerUser|null $pivot
 * @property-read Collection<int, Financer> $financers
 * @property-read int|null $financers_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Team|null $team
 * @method static Builder<static>|User permission($permissions, $without = false)
 * @method static Builder<static>|User role($roles, $guard = null, $without = false)
 * @method static Builder<static>|User whereTeamId($value)
 * @method static Builder<static>|User withoutPermission($permissions)
 * @method static Builder<static>|User withoutRole($roles, $guard = null)
 * @property string|null $temp_password
 * @property-read Collection<int, Activity> $activities
 * @property-read int|null $activities_count
 * @property-read string $full_name
 * @method static Builder<static>|User whereTempPassword($value)
 * @mixin \Eloquent
 * @property string|null $description
 * @property string|null $gender
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\InternalCommunication\Models\Article> $authoredArticles
 * @property-read int|null $authored_articles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContractType> $contractTypes
 * @property-read int|null $contract_types_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditBalance> $credits
 * @property-read int|null $credits_count
 * @property-read mixed $current_financer_id
 * @property-read \App\Models\FinancerUser|null $currentFinancerPivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Department> $departments
 * @property-read int|null $departments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\EngagementLog> $engagementLogs
 * @property-read int|null $engagement_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\InternalCommunication\Models\Article> $favoriteArticles
 * @property-read int|null $favorite_articles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Integration> $integrations
 * @property-read int|null $integrations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\InternalCommunication\Models\Article> $interactedArticles
 * @property-read int|null $interacted_articles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $invitedUsers
 * @property-read int|null $invited_users_count
 * @property-read User|null $inviter
 * @property-read \App\Models\JobLevel|null $jobLevel
 * @property-read \App\Models\JobTitle|null $jobTitle
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $managers
 * @property-read int|null $managers_count
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\HRTools\Models\Link> $pinnedHRToolsLinks
 * @property-read int|null $pinned_h_r_tools_links_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Module> $pinnedModules
 * @property-read int|null $pinned_modules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PushSubscription> $pushSubscriptions
 * @property-read int|null $push_subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Segment> $segments
 * @property-read int|null $segments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Site> $sites
 * @property-read int|null $sites_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Integrations\Survey\Models\Survey> $surveys
 * @property-read int|null $surveys_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @property-read \App\Models\WorkMode|null $workMode
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Division> $divisions
 * @property-read int|null $divisions_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User active()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User expiredInvitations()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User invited()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User invitedBy(string $inviterId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User pendingInvitations()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User userRelated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitationAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitationExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitationMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitationToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereInvitedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereOriginalInvitedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereSirhId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 */
	class User extends \Eloquent implements \OwenIt\Auditing\Contracts\Auditable, \Spatie\MediaLibrary\HasMedia, \App\Contracts\Searchable {}
}

namespace App\Models{
/**
 * @property string $id
 * @property string $financer_id
 * @property string|null $apideck_id
 * @property array<array-key, mixed> $name
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Activitylog\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Audit> $audits
 * @property-read int|null $audits_count
 * @property-read \App\Models\User|null $creator
 * @property-read \App\Models\Financer $financer
 * @property-read \App\Models\User|null $updater
 * @property-read \App\Models\Division|null $division
 * @method static \Database\Factories\WorkModeFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode forDivision(array|string $divisionIds)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode forFinancer(string $financerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereApideckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereFinancerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WorkMode withoutTrashed()
 */
	class WorkMode extends \Eloquent {}
}

