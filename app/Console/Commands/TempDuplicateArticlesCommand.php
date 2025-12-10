<?php

namespace App\Console\Commands;

use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Models\DemoEntity;
use App\Models\Financer;
use App\Models\LLMRequest;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Ramsey\Uuid\Uuid;

class TempDuplicateArticlesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:duplicate-articles 
                            {--source= : Source financer UUID (default: 19780701-a82f-4c9e-91ab-2ddfbc720102)}
                            {--timezone= : Target financers by timezone (e.g., Europe/Paris)}
                            {--targets=* : Target financer UUIDs (comma-separated or multiple --targets)}
                            {--dry-run : Show what would be duplicated without actually doing it}
                            {--force : Skip confirmation prompt}
                            {--seeder-env=staging : Environment for seeder generation (staging or dev)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Duplicate articles from source financer to target financers and mark them as demo';

    /**
     * @var array<string, int>
     */
    /** @var array<string, int> */
    protected array $statistics = [
        'articles_duplicated' => 0,
        'translations_duplicated' => 0,
        'versions_duplicated' => 0,
        'tags_duplicated' => 0,
        'llm_requests_duplicated' => 0,
        'demo_entities_created' => 0,
    ];

    /**
     * @var array{
     *   articles?: array<int, array<string, mixed>>,
     *   translations?: array<int, array<string, mixed>>,
     *   versions?: array<int, array<string, mixed>>,
     *   tags?: array<int, array<string, mixed>>,
     *   article_tags?: array<int, array<string, mixed>>,
     *   llm_requests?: array<int, array<string, mixed>>,
     *   demo_entities?: array<int, array<string, mixed>>
     * }
     */
    /** @var array{articles?: array<int, array<string, mixed>>, translations?: array<int, array<string, mixed>>, versions?: array<int, array<string, mixed>>, tags?: array<int, array<string, mixed>>, article_tags?: array<int, array<string, mixed>>, llm_requests?: array<int, array<string, mixed>>, demo_entities?: array<int, array<string, mixed>>} */
    protected array $seederData = [];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected array $articleIdMapping = [];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected array $translationMapping = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sourceFinancerId = $this->option('source') ?? '19780701-a82f-4c9e-91ab-2ddfbc720102';
        $timezone = $this->option('timezone');
        $targets = $this->option('targets');
        $isDryRun = $this->option('dry-run');
        $environmentOption = $this->option('seeder-env');
        $environment = is_string($environmentOption) ? $environmentOption : 'dev';

        if (! in_array($environment, ['staging', 'dev'])) {
            $this->error('Environment must be either "staging" or "dev"');

            return self::FAILURE;
        }

        // Get source financer
        $sourceFinancer = Financer::find($sourceFinancerId);
        if (! $sourceFinancer) {
            $this->error("Source financer {$sourceFinancerId} not found");

            return self::FAILURE;
        }

        // Get target financers
        $targetsArray = is_array($targets) ? array_filter($targets, 'is_string') : [];
        $targetFinancers = $this->getTargetFinancers($timezone, $targetsArray, $sourceFinancerId);
        if ($targetFinancers->isEmpty()) {
            $this->error('No target financers found');

            return self::FAILURE;
        }

        // Get source articles with all relationships
        $sourceArticles = Article::where('financer_id', $sourceFinancerId)
            ->with(['translations', 'translations.versions', 'tags', 'llmRequests'])
            ->get();

        if ($sourceArticles->isEmpty()) {
            $this->warn("No articles found for source financer {$sourceFinancer->name}");

            return self::SUCCESS;
        }

        $this->info("Source financer: {$sourceFinancer->name} ({$sourceFinancerId})");
        $this->info("Found {$sourceArticles->count()} articles to duplicate");
        $this->info("Target financers: {$targetFinancers->count()}");

        $this->table(
            ['Financer Name', 'Financer ID', 'Timezone'],
            $targetFinancers->map(fn (Financer $f): array => [$f->name, $f->id, $f->division->timezone ?? 'N/A'])->toArray()
        );

        if ($isDryRun) {
            $this->info('Running in DRY RUN mode - no data will be duplicated');
            $this->info("Would duplicate {$sourceArticles->count()} articles to {$targetFinancers->count()} financers");
            $this->info('Total articles to create: '.($sourceArticles->count() * $targetFinancers->count()));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with duplication?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($sourceArticles, $targetFinancers, $environment): void {
                foreach ($targetFinancers as $targetFinancer) {
                    if (! $targetFinancer instanceof Financer) {
                        continue;
                    }
                    $this->duplicateArticlesToFinancer($sourceArticles, $targetFinancer, $environment);
                }
            });

            $this->displayResults();

            $this->generateSeederFiles($environment);

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('Failed to duplicate articles: '.$e->getMessage());
            $this->error('File: '.$e->getFile().' Line: '.$e->getLine());

            return self::FAILURE;
        }
    }

    /**
     * Get target financers based on timezone or UUIDs
     *
     * @param  array<int, string>  $targets
     * @return Collection<int, Financer>
     */
    protected function getTargetFinancers(?string $timezone, array $targets, string $source): Collection
    {
        $query = Financer::with('division')->where('id', '!=', $source);

        if (! in_array($timezone, [null, '', '0'], true)) {
            $query->whereHas('division', function ($q) use ($timezone): void {
                $q->where('timezone', $timezone);
            });
        } elseif ($targets !== []) {
            // Handle both comma-separated and multiple --targets options
            $uuids = [];
            foreach ($targets as $target) {
                $uuids = array_merge($uuids, explode(',', $target));
            }
            $query->whereIn('id', array_map('trim', $uuids));
        } else {
            return collect();
        }

        return $query->get();
    }

    /**
     * Duplicate articles to a specific financer
     *
     * @param  Collection<int, Article>  $sourceArticles
     */
    protected function duplicateArticlesToFinancer(Collection $sourceArticles, Financer $targetFinancer, string $environment): void
    {
        $this->info("Duplicating to {$targetFinancer->name}...");

        foreach ($sourceArticles as $sourceArticle) {
            if (! $sourceArticle instanceof Article) {
                continue;
            }
            $newArticleId = Uuid::uuid7()->toString();

            // Store mapping for reference
            $this->articleIdMapping[$sourceArticle->id] = $newArticleId;

            // Create new article
            $newArticle = $sourceArticle->replicate();
            $newArticle->id = $newArticleId;
            $newArticle->financer_id = $targetFinancer->id;
            $newArticle->created_at = now();
            $newArticle->updated_at = now();
            $newArticle->save();

            $this->statistics['articles_duplicated']++;

            // Store for seeder generation
            $this->seederData['articles'][] = [
                'id' => $newArticleId,
                'financer_id' => $targetFinancer->id,
                'author_id' => $newArticle->author_id,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
                'deleted_at' => null,
            ];

            // Register as demo entity
            DemoEntity::create([
                'id' => Uuid::uuid7()->toString(),
                'entity_type' => Article::class,
                'entity_id' => $newArticleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->statistics['demo_entities_created']++;

            // Duplicate translations (this will also handle versions)
            $this->duplicateTranslations($sourceArticle, $newArticle, $environment);

            // Duplicate tags
            //            $this->duplicateTags($sourceArticle, $newArticle, $environment);

            // Duplicate LLM requests
            $this->duplicateLlmRequests($sourceArticle, $newArticle, $environment);
        }
    }

    /**
     * Duplicate article translations
     */
    protected function duplicateTranslations(Article $sourceArticle, Article $newArticle, string $environment): void
    {
        $translations = ArticleTranslation::where('article_id', $sourceArticle->id)->get();

        // Store current article translation mappings
        $currentArticleTranslationMapping = [];

        foreach ($translations as $translation) {
            $newTranslationId = Uuid::uuid7()->toString();

            // Store the mapping for versions (both in current and global)
            $currentArticleTranslationMapping[$translation->id] = $newTranslationId;
            $this->translationMapping[$translation->id] = $newTranslationId;

            $newTranslation = $translation->replicate();
            $newTranslation->id = $newTranslationId;
            $newTranslation->article_id = $newArticle->id;
            $newTranslation->created_at = now();
            $newTranslation->updated_at = now();
            $newTranslation->save();

            $this->statistics['translations_duplicated']++;

            $this->seederData['translations'][] = [
                'id' => $newTranslationId,
                'article_id' => $newArticle->id,
                'language' => $newTranslation->language,
                'title' => $newTranslation->title,
                'content' => $newTranslation->content,
                'status' => $newTranslation->status,
                'published_at' => $newTranslation->published_at?->format('Y-m-d H:i:s'),
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
                'deleted_at' => null,
            ];

            // Duplicate LLM requests for this translation
            $this->duplicateLlmRequestsForTranslation($translation, $newTranslation, $newArticle);
        }

        // Pass the current article's translation mapping to duplicateVersions
        $this->duplicateVersions($sourceArticle, $newArticle, $environment, $currentArticleTranslationMapping);
    }

    /**
     * Duplicate article versions (through translations)
     */
    /**
     * @param  array<string, string>  $currentArticleTranslationMapping
     */
    protected function duplicateVersions(Article $sourceArticle, Article $newArticle, string $environment, array $currentArticleTranslationMapping = []): void
    {
        // Get versions only for the current article's translations
        $versions = ArticleVersion::whereIn('article_translation_id', array_keys($currentArticleTranslationMapping))->get();

        foreach ($versions as $version) {
            $newVersionId = Uuid::uuid7()->toString();

            // Skip if we don't have a mapping for this translation
            $translationId = $version->getAttribute('article_translation_id');
            if (! is_string($translationId)) {
                continue;
            }
            if (! array_key_exists($translationId, $currentArticleTranslationMapping)) {
                continue;
            }

            $newVersion = $version->replicate();
            $newVersion->id = $newVersionId;
            $newVersion->article_id = $newArticle->id; // Set the correct new article ID
            $newVersion->setAttribute('article_translation_id', $currentArticleTranslationMapping[$translationId]);
            $newVersion->created_at = now();
            $newVersion->updated_at = now();
            $newVersion->save();

            $this->statistics['versions_duplicated']++;

            $this->seederData['versions'][] = [
                'id' => $newVersionId,
                'article_id' => $newArticle->id,
                'content' => $newVersion->content,
                'prompt' => $newVersion->prompt,
                'llm_response' => $newVersion->llm_response,
                'version_number' => $newVersion->version_number,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
                'article_translation_id' => $newVersion->getAttribute('article_translation_id'),
                'language' => $newVersion->getAttribute('language'),
                'title' => $newVersion->title,
                'llm_request_id' => $newVersion->llm_request_id,
                'author_id' => $newVersion->author_id,
                'illustration_id' => $newVersion->illustration_id,
            ];
        }
    }

    /**
     * Duplicate article tags (many-to-many relationship)
     */
    protected function duplicateTags(Article $sourceArticle, Article $newArticle, string $environment): void
    {
        // Get tags through the many-to-many relationship
        $tags = $sourceArticle->tags;

        foreach ($tags as $tag) {
            // Attach the same tag to the new article
            $newArticle->tags()->attach($tag->id, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->statistics['tags_duplicated']++;

            $this->seederData['article_tags'][] = [
                'article_id' => $newArticle->id,
                'tag_id' => $tag->id,
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Duplicate LLM requests for a translation
     */
    protected function duplicateLlmRequestsForTranslation(ArticleTranslation $sourceTranslation, ArticleTranslation $newTranslation, Article $newArticle): void
    {
        // Get LLM requests for this translation
        $llmRequests = LLMRequest::where('requestable_type', ArticleTranslation::class)
            ->where('requestable_id', $sourceTranslation->id)
            ->get();

        foreach ($llmRequests as $request) {
            $newRequestId = Uuid::uuid7()->toString();

            $newRequest = $request->replicate();
            $newRequest->id = $newRequestId;
            $newRequest->requestable_type = ArticleTranslation::class;
            $newRequest->requestable_id = $newTranslation->id;
            $newRequest->financer_id = $newArticle->financer_id;
            $newRequest->created_at = now();
            $newRequest->updated_at = now();
            $newRequest->save();

            $this->statistics['llm_requests_duplicated']++;

            $this->seederData['llm_requests'][] = [
                'id' => $newRequestId,
                'prompt' => $newRequest->prompt,
                'response' => $newRequest->response,
                'tokens_used' => $newRequest->tokens_used ?? 0,
                'engine_used' => $newRequest->engine_used ?? 'OpenAI',
                'financer_id' => $newArticle->financer_id,
                'requestable_id' => $newTranslation->id,
                'requestable_type' => 'App\\Integrations\\InternalCommunication\\Models\\ArticleTranslation',
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
                'prompt_system' => $newRequest->prompt_system,
            ];
        }
    }

    /**
     * Duplicate article LLM requests (keeping for backward compatibility but likely not used)
     */
    protected function duplicateLlmRequests(Article $sourceArticle, Article $newArticle, string $environment): void
    {
        // This method is kept for backward compatibility
        // Most LLM requests are actually linked to ArticleTranslation, not Article
        // But we check just in case
        $llmRequests = LLMRequest::where('requestable_type', Article::class)
            ->where('requestable_id', $sourceArticle->id)
            ->get();

        foreach ($llmRequests as $request) {
            $newRequestId = Uuid::uuid7()->toString();

            $newRequest = $request->replicate();
            $newRequest->id = $newRequestId;
            $newRequest->requestable_type = Article::class;
            $newRequest->requestable_id = $newArticle->id;
            $newRequest->financer_id = $newArticle->financer_id;
            $newRequest->created_at = now();
            $newRequest->updated_at = now();
            $newRequest->save();

            $this->statistics['llm_requests_duplicated']++;

            $this->seederData['llm_requests'][] = [
                'id' => $newRequestId,
                'prompt' => $newRequest->prompt,
                'response' => $newRequest->response,
                'tokens_used' => $newRequest->tokens_used ?? 0,
                'engine_used' => $newRequest->engine_used ?? 'OpenAI',
                'financer_id' => $newArticle->financer_id,
                'requestable_id' => $newArticle->id,
                'requestable_type' => 'App\\Integrations\\InternalCommunication\\Models\\Article',
                'created_at' => now()->format('Y-m-d H:i:s'),
                'updated_at' => now()->format('Y-m-d H:i:s'),
                'prompt_system' => $newRequest->prompt_system,
            ];
        }
    }

    /**
     * Display duplication results
     */
    protected function displayResults(): void
    {
        $this->info('Duplication completed successfully!');

        $this->table(
            ['Entity', 'Count'],
            [
                ['Articles', $this->statistics['articles_duplicated']],
                ['Translations', $this->statistics['translations_duplicated']],
                ['Versions', $this->statistics['versions_duplicated']],
                ['Tags', $this->statistics['tags_duplicated']],
                ['LLM Requests', $this->statistics['llm_requests_duplicated']],
                ['Demo Entities', $this->statistics['demo_entities_created']],
            ]
        );
    }

    /**
     * Append data directly to existing seeder files
     */
    protected function generateSeederFiles(string $environment): void
    {
        // Determine seeder file paths based on environment
        $seederPaths = $this->getSeederPaths($environment);

        // Append articles to seeder
        if (! empty($this->seederData['articles'])) {
            $this->appendToSeeder(
                $seederPaths['articles'],
                $this->seederData['articles'],
                'int_communication_rh_articles'
            );
        }

        // Append translations to seeder
        if (! empty($this->seederData['translations'])) {
            $this->appendToSeeder(
                $seederPaths['translations'],
                $this->seederData['translations'],
                'int_communication_rh_article_translations'
            );
        }

        // Append versions to seeder
        if (! empty($this->seederData['versions'])) {
            $this->appendToSeeder(
                $seederPaths['versions'],
                $this->seederData['versions'],
                'int_communication_rh_article_versions'
            );
        }

        // Append article_tags to seeder (if applicable)
        if (! empty($this->seederData['article_tags'])) {
            $this->appendToSeeder(
                $seederPaths['article_tags'] ?? null,
                $this->seederData['article_tags'],
                'int_communication_rh_article_tag'
            );
        }

        // Append LLM requests to seeder
        if (! empty($this->seederData['llm_requests'])) {
            $this->appendToSeeder(
                $seederPaths['llm_requests'],
                $this->seederData['llm_requests'],
                'llm_requests'
            );
        }

        // Append demo entities to seeder
        if (! empty($this->seederData['articles'])) {
            $this->appendDemoEntitiesToSeeder(
                $seederPaths['demo_entities'],
                $this->seederData['articles']
            );
        }

        $this->info("Data successfully appended to {$environment} seeders!");
        $this->info('Modified files:');
        foreach ($seederPaths as $path) {
            if ($path) {
                $this->info("  - {$path}");
            }
        }
    }

    /**
     * Get seeder file paths based on environment
     *
     * @return array<string, string>
     */
    protected function getSeederPaths(string $environment): array
    {
        $prefix = $environment === 'staging' ? 'Staging' : 'Dev';

        $paths = [
            'articles' => database_path("seeders/{$prefix}IntCommunicationRhArticlesTableSeeder.php"),
            'translations' => database_path("seeders/{$prefix}IntCommunicationRhArticleTranslationsTableSeeder.php"),
            'versions' => database_path("seeders/{$prefix}IntCommunicationRhArticleVersionsTableSeeder.php"),
            'demo_entities' => database_path("seeders/{$prefix}DemoEntitiesTableSeeder.php"),
            'llm_requests' => database_path("seeders/{$prefix}LlmRequestsTableSeeder.php"),
        ];

        // Only add article_tags if path exists (not specified in requirements)
        $articleTagsPath = database_path("seeders/{$prefix}ArticleTagsTableSeeder.php");
        if (file_exists($articleTagsPath)) {
            $paths['article_tags'] = $articleTagsPath;
        }

        return $paths;
    }

    /**
     * Append data to existing seeder file
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function appendToSeeder(?string $filePath, array $data, string $table): void
    {
        if (in_array($filePath, [null, '', '0'], true) || ! File::exists($filePath)) {
            if (! in_array($filePath, [null, '', '0'], true)) {
                $this->warn("Seeder file not found: {$filePath}");
            }

            return;
        }

        // Read existing file content
        $content = File::get($filePath);

        // Find the position to insert new data (before the last ]);)
        $insertPosition = strrpos($content, ']);');

        if ($insertPosition === false) {
            $this->error("Could not find insert position in {$filePath}");

            return;
        }

        // Generate the array items for insertion
        $newItems = $this->generateArrayItems($data);

        // Don't add comma at the beginning - the file already has trailing commas
        // Just add the new items directly

        // Insert the new data
        $newContent = substr($content, 0, $insertPosition).$newItems.substr($content, $insertPosition);

        // Write back to file
        File::put($filePath, $newContent);
    }

    /**
     * Append demo entities to existing seeder file
     *
     * @param  array<int, array<string, mixed>>  $articles
     */
    protected function appendDemoEntitiesToSeeder(string $filePath, array $articles): void
    {
        if (! File::exists($filePath)) {
            $this->warn("Seeder file not found: {$filePath}");

            return;
        }

        // Read existing file content
        $content = File::get($filePath);

        // Find the position to insert new data (before the last ]);)
        $insertPosition = strrpos($content, ']);');

        if ($insertPosition === false) {
            $this->error("Could not find insert position in {$filePath}");

            return;
        }

        // Generate demo entities
        $newItems = '';
        $isFirst = true;
        foreach ($articles as $article) {
            if (! $isFirst) {
                $newItems .= ",\n";
            }
            $isFirst = false;
            $newItems .= "            [\n";
            $newItems .= "                'id' => '".Uuid::uuid7()->toString()."',\n";
            $newItems .= "                'entity_type' => 'App\\\\Integrations\\\\InternalCommunication\\\\Models\\\\Article',\n";
            $articleId = is_scalar($article['id']) ? (string) $article['id'] : 'unknown';
            $newItems .= "                'entity_id' => '{$articleId}',\n";
            $createdAt = is_scalar($article['created_at']) ? (string) $article['created_at'] : 'unknown';
            $newItems .= "                'created_at' => '{$createdAt}',\n";
            $updatedAt = is_scalar($article['updated_at']) ? (string) $article['updated_at'] : 'unknown';
            $newItems .= "                'updated_at' => '{$updatedAt}',\n";
            $newItems .= '            ]';
        }

        // Insert the new data
        $newContent = substr($content, 0, $insertPosition).$newItems.substr($content, $insertPosition);

        // Write back to file
        File::put($filePath, $newContent);
    }

    /**
     * Generate array items for insertion into seeder
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function generateArrayItems(array $data): string
    {
        $items = '';

        // We need to get the last index from the existing file to continue numbering
        // For now, we'll just add without indexes (they're optional in PHP)

        foreach ($data as $item) {
            $items .= "            [\n";
            foreach ($item as $key => $value) {
                // Format value based on type
                if (is_null($value)) {
                    $valueStr = 'null';
                } elseif (is_bool($value)) {
                    $valueStr = $value ? 'true' : 'false';
                } elseif (is_array($value)) {
                    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (! is_string($json)) {
                        $json = '[]';
                    }
                    // Escape single quotes in JSON and wrap with single quotes
                    $escaped = str_replace("'", "\\'", $json);
                    $valueStr = "'{$escaped}'";
                } elseif (is_numeric($value)) {
                    $valueStr = $value;
                } else {
                    // For strings, escape single quotes properly
                    $strValue = is_scalar($value) ? $value : '';
                    $escaped = str_replace("'", "\\'", $strValue);
                    $valueStr = "'{$escaped}'";
                }
                $items .= "                '{$key}' => {$valueStr},\n";
            }
            $items .= "            ],\n"; // Each item ends with ],
        }

        return $items;
    }

    /**
     * Generate seeder content
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function generateSeederContent(array $data, string $table): string
    {
        $content = "<?php\n\n// Add these entries to your {$table} seeder\n\n";
        $content .= "DB::table('{$table}')->insert([\n";

        foreach ($data as $item) {
            $content .= "    [\n";
            foreach ($item as $key => $value) {
                // Format value based on type
                if (is_null($value)) {
                    $valueStr = 'null';
                } elseif (is_bool($value)) {
                    $valueStr = $value ? 'true' : 'false';
                } elseif (is_array($value)) {
                    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if (! is_string($json)) {
                        $json = '[]';
                    }
                    // Escape single quotes in JSON and wrap with single quotes
                    $escaped = str_replace("'", "\\'", $json);
                    $valueStr = "'{$escaped}'";
                } elseif (is_numeric($value)) {
                    $valueStr = $value;
                } else {
                    // For strings, escape single quotes properly
                    $strValue = is_scalar($value) ? $value : '';
                    $escaped = str_replace("'", "\\'", $strValue);
                    $valueStr = "'{$escaped}'";
                }
                $content .= "        '{$key}' => {$valueStr},\n";
            }
            $content .= "    ],\n";
        }

        return $content."]);\n";
    }

    /**
     * Generate demo entities seeder
     */
    protected function generateDemoEntitiesSeeder(): string
    {
        $content = "<?php\n\n// Add these entries to your demo_entities seeder\n\n";
        $content .= "use App\Models\DemoEntity;\n\n";
        $content .= "DB::table('demo_entities')->insert([\n";

        $articles = array_key_exists('articles', $this->seederData) ? $this->seederData['articles'] : [];
        foreach ($articles as $article) {
            $articleId = is_scalar($article['id']) ? (string) $article['id'] : '';
            $createdAt = is_scalar($article['created_at']) ? (string) $article['created_at'] : '';
            $updatedAt = is_scalar($article['updated_at']) ? (string) $article['updated_at'] : '';
            $content .= "    [\n";
            $content .= "        'id' => '".Uuid::uuid7()->toString()."',\n";
            $content .= "        'entity_type' => 'App\\\\Integrations\\\\InternalCommunication\\\\Models\\\\Article',\n";
            $content .= "        'entity_id' => '{$articleId}',\n";
            $content .= "        'created_at' => '{$createdAt}',\n";
            $content .= "        'updated_at' => '{$updatedAt}',\n";
            $content .= "    ],\n";
        }

        return $content."]);\n";
    }

    /**
     * Generate detailed report
     */
    protected function generateReport(string $environment, string $basePath): string
    {
        $report = "# Article Duplication Report\n\n";
        $report .= 'Generated: '.now()->format('Y-m-d H:i:s')."\n";
        $report .= "Environment: {$environment}\n\n";

        $report .= "## Statistics\n\n";
        $report .= "- Articles duplicated: {$this->statistics['articles_duplicated']}\n";
        $report .= "- Translations duplicated: {$this->statistics['translations_duplicated']}\n";
        $report .= "- Versions duplicated: {$this->statistics['versions_duplicated']}\n";
        $report .= "- Tags duplicated: {$this->statistics['tags_duplicated']}\n";
        $report .= "- LLM Requests duplicated: {$this->statistics['llm_requests_duplicated']}\n";
        $report .= "- Demo entities created: {$this->statistics['demo_entities_created']}\n\n";

        $report .= "## Files Generated\n\n";
        if (! empty($this->seederData['articles'])) {
            $report .= "- `{$basePath}/articles_seeder.php`\n";
        }
        if (! empty($this->seederData['translations'])) {
            $report .= "- `{$basePath}/translations_seeder.php`\n";
        }
        if (! empty($this->seederData['article_tags'])) {
            $report .= "- `{$basePath}/article_tags_seeder.php`\n";
        }
        if (! empty($this->seederData['versions'])) {
            $report .= "- `{$basePath}/versions_seeder.php`\n";
        }
        if (! empty($this->seederData['llm_requests'])) {
            $report .= "- `{$basePath}/llm_requests_seeder.php`\n";
        }
        $report .= "- `{$basePath}/demo_entities_seeder.php`\n\n";

        $report .= "## Manual Steps Required\n\n";
        $report .= "1. Review the generated seeder files\n";
        $report .= "2. Copy relevant entries to your environment seeders:\n";
        if ($environment === 'staging') {
            $report .= "   - `database/seeders/StagingIntCommunicationRhArticlesTableSeeder.php`\n";
            $report .= "   - `database/seeders/StagingIntCommunicationRhArticleTranslationsTableSeeder.php`\n";
            $report .= "   - `database/seeders/StagingDemoEntitiesTableSeeder.php`\n";
        } else {
            $report .= "   - `database/seeders/DevIntCommunicationRhArticlesTableSeeder.php`\n";
            $report .= "   - `database/seeders/DevIntCommunicationRhArticleTranslationsTableSeeder.php`\n";
            $report .= "   - `database/seeders/DevDemoEntitiesTableSeeder.php`\n";
        }
        $report .= "3. Run `php artisan db:seed` with appropriate classes\n\n";

        $report .= "## Article ID Mapping\n\n";
        $report .= "| Original ID | New ID |\n";
        $report .= "|------------|--------|\n";
        foreach ($this->articleIdMapping as $original => $new) {
            $report .= "| {$original} | {$new} |\n";
        }

        return $report;
    }
}
