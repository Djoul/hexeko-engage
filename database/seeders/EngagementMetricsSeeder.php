<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MetricPeriod;
use App\Integrations\HRTools\Models\Link;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Models\Audit;
use App\Models\EngagementLog;
use App\Models\EngagementMetric;
use App\Models\Financer;
use App\Models\Module;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class EngagementMetricsSeeder extends Seeder
{
    private array $auditBatch = [];

    private array $engagementLogBatch = [];

    private array $engagementMetricBatch = [];

    private array $articleInteractionBatch = [];

    private array $financerUserBatch = [];

    private const BATCH_SIZE = 5000;

    // Cache for frequently accessed data
    private ?Collection $cachedArticles = null;

    private ?Collection $cachedLinks = null;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable Eloquent events for better performance
        User::unsetEventDispatcher();
        EngagementLog::unsetEventDispatcher();
        EngagementMetric::unsetEventDispatcher();
        Audit::unsetEventDispatcher();

        // Start transaction for better performance
        DB::beginTransaction();

        try {
            $this->command->info('Seeding engagement metrics...');
            $totalStartTime = microtime(true);

            // Get all financers or create one if none exist
            $financers = Financer::all();
            if ($financers->isEmpty()) {
                $financers = collect([
                    Financer::factory()->create(['name' => 'Demo Financer']),
                ]);
            }

            // Reference date for metrics
            $referenceDate = Carbon::now();

            foreach ($financers as $financer) {
                // Clear cache for each financer
                $this->cachedArticles = null;
                $this->cachedLinks = null;

                $financerStartTime = microtime(true);
                $this->command->info("Generating metrics for financer: {$financer->name}");

                // Create some users for the financer if none exist
                $totalUsers = rand(50, 200);
                // Add 10-20% more invited users to simulate users who haven't registered yet
                $totalInvited = (int) ($totalUsers * rand(110, 120) / 100);
                $this->command->info("Creating {$totalInvited} invited users and {$totalUsers} registered users for financer...");

                // Prepare batch data for invited users
                $users = [];
                $emails = [];

                // Pre-generate fake data for better performance
                $firstNames = [];
                $lastNames = [];
                $ipAddresses = [];
                $userAgents = [];

                for ($j = 0; $j < $totalInvited; $j++) {
                    $firstNames[] = fake()->firstName();
                    $lastNames[] = fake()->lastName();
                    $ipAddresses[] = fake()->ipv4();
                    $userAgents[] = fake()->userAgent();
                }

                for ($i = 0; $i < $totalInvited; $i++) {
                    $invitedDaysAgo = rand(0, 365);
                    $invitedAt = now()->subDays($invitedDaysAgo);
                    $invitedUserId = rand(1000, 999999);
                    $email = "user{$i}_{$financer->id}_".time().'@example.com'; // Generate predictable unique emails
                    $emails[$i] = $email;

                    // Batch audit records
                    $this->auditBatch[] = [
                        'user_type' => null,
                        'user_id' => null,
                        'event' => 'created',
                        'auditable_type' => 'App\Models\InvitedUser',
                        'auditable_id' => $invitedUserId,
                        'financer_id' => $financer->id,
                        'old_values' => json_encode([]),
                        'new_values' => json_encode([
                            'financer_id' => $financer->id,
                            'first_name' => $firstNames[$i],
                            'last_name' => $lastNames[$i],
                            'email' => $email,
                        ]),
                        'url' => null,
                        'ip_address' => $ipAddresses[$i],
                        'user_agent' => $userAgents[$i],
                        'tags' => null,
                        'created_at' => $invitedAt,
                        'updated_at' => $invitedAt,
                    ];

                    $this->flushBatchIfNeeded('audit');

                    // Register only some of the invited users (to match totalUsers)
                    if ($i < $totalUsers) {
                        $userCreatedDaysAgo = rand(0, $invitedDaysAgo);
                        $userCreatedAt = now()->subDays($userCreatedDaysAgo);

                        $users[] = [
                            'email' => $email,
                            'created_at' => $userCreatedAt,
                            'invitedDaysAgo' => $invitedDaysAgo,
                            'userCreatedDaysAgo' => $userCreatedDaysAgo,
                        ];
                    }
                }

                // Flush audit batch
                $this->flushBatch('audit');

                // Create users in batch using raw insert
                $createdUsers = [];
                $userBatch = [];

                // Get a team for users or create one if none exists
                $team = Team::first();
                if (! $team) {
                    $team = Team::factory()->create();
                }

                foreach ($users as $index => $userData) {
                    $userId = Uuid::uuid4()->toString();
                    $now = now();

                    $userBatch[] = [
                        'id' => $userId,
                        'team_id' => $team->id,
                        'email' => $userData['email'],
                        'cognito_id' => Uuid::uuid4()->toString(),
                        'temp_password' => null,
                        'first_name' => $firstNames[$index] ?? fake()->firstName(),
                        'last_name' => $lastNames[$index] ?? fake()->lastName(),
                        'description' => null,
                        'force_change_email' => false,
                        'birthdate' => null,
                        'terms_confirmed' => true,
                        'enabled' => true,
                        'locale' => 'en-US',
                        'currency' => 'EUR',
                        'timezone' => 'Europe/Brussels',
                        'stripe_id' => null,
                        'sirh_id' => json_encode(['platform' => 'aws', 'id' => Uuid::uuid4()->toString()]),
                        'last_login' => null,
                        'opt_in' => true,
                        'phone' => null,
                        'remember_token' => null,
                        'created_at' => $userData['created_at'],
                        'updated_at' => $userData['created_at'],
                        'deleted_at' => null,
                    ];

                    $pivotCreatedDaysAgo = rand(0, $userData['userCreatedDaysAgo']);
                    $pivotCreatedAt = now()->subDays($pivotCreatedDaysAgo);

                    $this->financerUserBatch[] = [
                        'id' => Uuid::uuid4()->toString(),
                        'financer_id' => $financer->id,
                        'user_id' => $userId,
                        'active' => true,
                        'from' => $pivotCreatedAt->toDateTimeString(),
                        'created_at' => $pivotCreatedAt->toDateTimeString(),
                        'updated_at' => $pivotCreatedAt->toDateTimeString(),
                    ];

                    // Create a simple user object for later use
                    $user = new User;
                    $user->id = $userId;
                    $user->email = $userData['email'];
                    $createdUsers[] = $user;
                }

                // Insert all users in one batch
                foreach (array_chunk($userBatch, self::BATCH_SIZE) as $batch) {
                    DB::table('users')->insert($batch);
                }

                // Insert financer_user relationships in batch
                foreach (array_chunk($this->financerUserBatch, self::BATCH_SIZE) as $batch) {
                    DB::table('financer_user')->insert($batch);
                }
                $this->financerUserBatch = [];

                $userCreationTime = microtime(true) - $financerStartTime;
                $this->command->info('  User creation took: '.round($userCreationTime, 2).'s');

                // Generate session events for all users
                $sessionStartTime = microtime(true);
                foreach ($createdUsers as $user) {
                    $this->generateSessionEvents($user, $financer);
                }

                // Flush all remaining engagement logs
                $this->flushBatch('engagementLog');

                $sessionTime = microtime(true) - $sessionStartTime;
                $this->command->info('  Session generation took: '.round($sessionTime, 2).'s');

                // Generate metrics for different periods only
                $metricsStartTime = microtime(true);
                $this->generatePeriodMetrics($financer, $referenceDate, $totalUsers, $totalInvited);

                $metricsTime = microtime(true) - $metricsStartTime;
                $this->command->info('  Metrics generation took: '.round($metricsTime, 2).'s');

                $financerTotalTime = microtime(true) - $financerStartTime;
                $this->command->info("  Total time for {$financer->name}: ".round($financerTotalTime, 2).'s');
            }

            $totalTime = microtime(true) - $totalStartTime;
            $this->command->info('Engagement metrics seeding completed! Total time: '.round($totalTime, 2).'s');

            // Commit transaction
            DB::commit();
        } catch (Exception $e) {
            // Rollback on error
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Flush batch if it reaches the batch size limit
     */
    private function flushBatchIfNeeded(string $type): void
    {
        switch ($type) {
            case 'audit':
                if (count($this->auditBatch) >= self::BATCH_SIZE) {
                    $this->flushBatch('audit');
                }
                break;
            case 'engagementLog':
                if (count($this->engagementLogBatch) >= self::BATCH_SIZE) {
                    $this->flushBatch('engagementLog');
                }
                break;
            case 'engagementMetric':
                if (count($this->engagementMetricBatch) >= self::BATCH_SIZE) {
                    $this->flushBatch('engagementMetric');
                }
                break;
            case 'articleInteraction':
                if (count($this->articleInteractionBatch) >= self::BATCH_SIZE) {
                    $this->flushBatch('articleInteraction');
                }
                break;
        }
    }

    /**
     * Flush batch data to database
     */
    private function flushBatch(string $type): void
    {
        switch ($type) {
            case 'audit':
                if ($this->auditBatch !== []) {
                    DB::table('audits')->insert($this->auditBatch);
                    $this->auditBatch = [];
                }
                break;
            case 'engagementLog':
                if ($this->engagementLogBatch !== []) {
                    DB::table('engagement_logs')->insert($this->engagementLogBatch);
                    $this->engagementLogBatch = [];
                }
                break;
            case 'engagementMetric':
                if ($this->engagementMetricBatch !== []) {
                    DB::table('engagement_metrics')->insert($this->engagementMetricBatch);
                    $this->engagementMetricBatch = [];
                }
                break;
            case 'articleInteraction':
                if ($this->articleInteractionBatch !== []) {
                    // Use insertOrIgnore to handle duplicate key violations
                    DB::table('int_communication_rh_article_interactions')->insertOrIgnore($this->articleInteractionBatch);
                    $this->articleInteractionBatch = [];
                }
                break;
        }
    }

    /**
     * Generate session events for a user
     *
     * Creates realistic session data with:
     * - SessionStarted and SessionFinished events with matching session_ids
     * - Varied session durations (short, medium, long)
     * - Some edge cases for testing (very short, very long, missing session_id)
     * - Both created_at and logged_at timestamps properly set
     * - More activity on weekdays vs weekends
     */
    private function generateSessionEvents(User $user, Financer $financer): void
    {
        // Generate sessions for the last 30 days (to have more data for article interactions)
        $startDate = now()->subDays(30);
        $endDate = now();

        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            // Generate 0-3 sessions per day, with higher probability for weekdays
            $isWeekend = in_array($currentDate->dayOfWeek, [0, 6]); // Sunday = 0, Saturday = 6
            $maxSessions = $isWeekend ? 2 : 3;
            $sessionsToday = rand(0, $maxSessions);

            for ($i = 0; $i < $sessionsToday; $i++) {
                // Random session start time during the day (more realistic hours)
                $hourRanges = $isWeekend
                    ? [[9, 11], [14, 16], [19, 21]] // Weekend hours
                    : [[8, 10], [11, 13], [14, 17], [19, 21]]; // Weekday hours

                $hourRange = $hourRanges[array_rand($hourRanges)];
                $sessionStart = $currentDate->copy()
                    ->setHour(rand($hourRange[0], $hourRange[1]))
                    ->setMinute(rand(0, 59))
                    ->setSecond(rand(0, 59));

                // Session duration with more realistic distribution
                // 3% very short sessions (<5 min - will be filtered out)
                // 65% short sessions (5-20 min)
                // 25% medium sessions (20-60 min)
                // 5% long sessions (60-120 min)
                // 2% abnormally long sessions (>8 hours - will be filtered out)
                $random = rand(1, 100);
                if ($random <= 3) {
                    // Very short sessions (will be filtered by calculator)
                    $sessionDurationSeconds = rand(1, 4) * 60 + rand(0, 59); // 1-4 minutes
                    $sessionDurationMinutes = $sessionDurationSeconds / 60;
                } elseif ($random <= 68) {
                    $sessionDurationMinutes = rand(5, 20);
                } elseif ($random <= 93) {
                    $sessionDurationMinutes = rand(20, 60);
                } elseif ($random <= 98) {
                    $sessionDurationMinutes = rand(60, 120);
                } else {
                    // Abnormally long sessions (will be filtered by calculator)
                    $sessionDurationMinutes = rand(481, 600); // 8+ hours
                }

                $sessionEnd = $sessionStart->copy()->addMinutes($sessionDurationMinutes);

                // Generate session ID (95% of sessions have ID, 5% don't for testing)
                $hasSessionId = rand(1, 100) <= 95;
                $sessionId = $hasSessionId ? Uuid::uuid4()->toString() : null;

                // Batch SessionStarted event
                $startMetadata = [
                    'financer_id' => $financer->id,
                    'user_agent' => $this->getRandomUserAgent(),
                    'ip_address' => $this->getRandomIpAddress(),
                ];

                if ($hasSessionId) {
                    $startMetadata['session_id'] = $sessionId;
                }

                $this->engagementLogBatch[] = [
                    'id' => Uuid::uuid4()->toString(),
                    'user_id' => $user->id,
                    'type' => 'SessionStarted',
                    'target' => null,
                    'logged_at' => $sessionStart,
                    'created_at' => $sessionStart,
                    'updated_at' => $sessionStart,
                    'metadata' => json_encode($startMetadata),
                ];

                // Batch SessionFinished event
                $endMetadata = [
                    'financer_id' => $financer->id,
                    'duration' => (int) ($sessionDurationMinutes * 60),
                ];

                if ($hasSessionId) {
                    $endMetadata['session_id'] = $sessionId;
                }

                $this->engagementLogBatch[] = [
                    'id' => Uuid::uuid4()->toString(),
                    'user_id' => $user->id,
                    'type' => 'SessionFinished',
                    'target' => null,
                    'logged_at' => $sessionEnd,
                    'created_at' => $sessionEnd,
                    'updated_at' => $sessionEnd,
                    'metadata' => json_encode($endMetadata),
                ];

                $this->flushBatchIfNeeded('engagementLog');

                // Only generate additional events for 30% of sessions to improve performance
                if (rand(1, 100) <= 30) {
                    // Generate ModuleAccessed events during this session
                    $this->generateModuleAccessedEvents($user->id, $sessionId, $sessionStart, $sessionEnd, $financer->id);

                    // Only generate article/link events for 50% of these sessions
                    if (rand(1, 100) <= 50) {
                        // Generate ArticleViewed events during this session
                        $this->generateArticleViewedEvents($user->id, $sessionId, $sessionStart, $sessionEnd, $financer->id);

                        // Generate LinkClicked events during this session
                        $this->generateLinkClickedEvents($user->id, $sessionId, $sessionStart, $sessionEnd, $financer->id);
                    }
                }
            }

            $currentDate->addDay();
        }
    }

    /**
     * Generate ModuleAccessed events for a session
     */
    private function generateModuleAccessedEvents(
        string $userId,
        ?string $sessionId,
        Carbon $sessionStart,
        Carbon $sessionEnd,
        string $financerId
    ): void {
        // Get active modules from database
        $modules = Module::where('active', true)->get();

        if ($modules->isEmpty()) {
            return;
        }

        // Define access probabilities for common module names
        $moduleProbabilities = [
            'dashboard' => 80,      // 80% chance
            'vouchers' => 60,       // 60% chance
            'benefits' => 50,       // 50% chance
            'savings' => 40,        // 40% chance
            'lifestyle' => 35,      // 35% chance
            'hr-tools' => 30,       // 30% chance
            'news' => 25,          // 25% chance
            'profile' => 20,       // 20% chance
        ];

        $sessionDuration = $sessionStart->diffInMinutes($sessionEnd);

        // Skip if session is too short (less than 5 minutes)
        if ($sessionDuration < 5) {
            return;
        }

        // Find dashboard module if exists
        $dashboardModule = $modules->first(function ($module): bool {
            $nameArray = $module->name;
            if (is_array($nameArray)) {
                $name = strtolower($nameArray['en-GB'] ?? $nameArray['en'] ?? $nameArray['fr-FR'] ?? $nameArray['fr'] ?? '');
            } else {
                $name = strtolower($module->name ?? '');
            }

            return str_contains($name, 'dashboard') || str_contains($name, 'tableau');
        });

        // Always access dashboard first if it exists
        if ($dashboardModule && rand(1, 100) <= 80) {
            $accessTime = $sessionStart->copy()->addSeconds(rand(5, 30));
            $this->engagementLogBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => $userId,
                'type' => 'ModuleAccessed',
                'target' => $dashboardModule->id,
                'logged_at' => $accessTime,
                'created_at' => $accessTime,
                'updated_at' => $accessTime,
                'metadata' => json_encode([
                    'session_id' => $sessionId,
                    'financer_id' => $financerId,
                ]),
            ];
            $this->flushBatchIfNeeded('engagementLog');
        }

        // Access other modules based on probability
        foreach ($modules as $module) {
            if ($module->id === $dashboardModule?->id) {
                continue; // Already handled
            }

            // Get module name for probability lookup
            $nameArray = $module->name;
            if (is_array($nameArray)) {
                $moduleName = strtolower($nameArray['en-GB'] ?? $nameArray['en'] ?? $nameArray['fr-FR'] ?? $nameArray['fr'] ?? '');
            } else {
                $moduleName = strtolower($module->name ?? '');
            }

            // Find matching probability or use default
            $probability = 30; // Default probability
            foreach ($moduleProbabilities as $key => $prob) {
                if (str_contains($moduleName, $key)) {
                    $probability = $prob;
                    break;
                }
            }

            if (rand(1, 100) <= $probability) {
                // Random time during the session
                $accessTime = $sessionStart->copy()->addMinutes(rand(1, (int) $sessionDuration));

                $this->engagementLogBatch[] = [
                    'id' => Uuid::uuid4()->toString(),
                    'user_id' => $userId,
                    'type' => 'ModuleAccessed',
                    'target' => $module->id,
                    'logged_at' => $accessTime,
                    'created_at' => $accessTime,
                    'updated_at' => $accessTime,
                    'metadata' => json_encode([
                        'session_id' => $sessionId,
                        'financer_id' => $financerId,
                    ]),
                ];
                $this->flushBatchIfNeeded('engagementLog');
            }
        }
    }

    /**
     * Generate ArticleViewed events for a session
     */
    private function generateArticleViewedEvents(
        string $userId,
        ?string $sessionId,
        Carbon $sessionStart,
        Carbon $sessionEnd,
        string $financerId
    ): void {
        $sessionDuration = $sessionStart->diffInMinutes($sessionEnd);

        // 60% chance to view articles in a session
        if (rand(1, 100) > 60) {
            return;
        }

        // Generate 1-3 article views per session
        $articleCount = rand(1, 3);

        // Use cached articles or load them once
        if (! $this->cachedArticles instanceof Collection || $this->cachedArticles->isEmpty()) {
            $this->cachedArticles = Article::with('translations')->limit(20)->get();
            if ($this->cachedArticles->isEmpty()) {
                // Create articles in batch if none exist
                $articleFactory = resolve(ArticleFactory::class);
                $articleData = [];
                for ($j = 0; $j < 20; $j++) {
                    $article = $articleFactory->make()->toArray();
                    $article['id'] = Uuid::uuid4()->toString();
                    $article['created_at'] = now();
                    $article['updated_at'] = now();
                    $articleData[] = $article;
                }
                DB::table('int_communication_rh_articles')->insert($articleData);
                $this->cachedArticles = Article::with('translations')->limit(20)->get();

                // Generate historical interactions for newly created articles
                $this->generateHistoricalInteractions($financerId);
            }
        }
        $articles = $this->cachedArticles;

        for ($i = 0; $i < $articleCount; $i++) {
            // Random time during the session
            $viewTime = $sessionStart->copy()->addMinutes(rand(1, max(1, (int) $sessionDuration)));

            // Pick a random article
            $article = $articles->random();

            $this->engagementLogBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => $userId,
                'type' => 'ArticleViewed',
                'target' => $article->id,
                'logged_at' => $viewTime,
                'created_at' => $viewTime,
                'updated_at' => $viewTime,
                'metadata' => json_encode([
                    'session_id' => $sessionId,
                    'financer_id' => $financerId,
                    'article_title' => 'Article '.$article->id,
                    'category' => ['news', 'benefits', 'wellness', 'finance'][rand(0, 3)],
                    'read_time' => rand(30, 300),
                ]),
            ];
            $this->flushBatchIfNeeded('engagementLog');

            // 40% chance to react to the article
            if (rand(1, 100) <= 40) {
                $this->generateArticleReaction($userId, $article->id);
            }
        }
    }

    /**
     * Generate historical interactions for articles over the past 12 months
     */
    private function generateHistoricalInteractions(string $financerId): void
    {
        $this->command->info('  Generating historical article interactions...');

        $articles = $this->cachedArticles;
        $financerUsers = DB::table('financer_user')
            ->where('financer_id', $financerId)
            ->where('active', true)
            ->pluck('user_id');

        if ($financerUsers->isEmpty() || $articles->isEmpty()) {
            return;
        }

        $interactionCount = 0;
        $reactions = ['like', 'love', 'celebrate', 'insightful', 'laugh', 'surprised', 'sad', 'dislike'];

        foreach ($articles as $article) {
            // Each article gets between 5-30% of users interacting with it
            $interactionRate = rand(5, 30) / 100;
            $usersToInteract = $financerUsers->random((int) ($financerUsers->count() * $interactionRate));

            foreach ($usersToInteract as $userId) {
                // Random date within last 12 months
                $daysAgo = $this->getExponentialRandomDays(365);
                $interactionDate = Carbon::now()->subDays($daysAgo)
                    ->setTime(rand(8, 18), rand(0, 59), rand(0, 59));

                $articleTranslationId = null;
                if ($article->translations->isNotEmpty()) {
                    $articleTranslationId = $article->translations->random()->id;
                }

                $this->articleInteractionBatch[] = [
                    'id' => Uuid::uuid4()->toString(),
                    'user_id' => $userId,
                    'article_id' => $article->id,
                    'article_translation_id' => $articleTranslationId,
                    'reaction' => $reactions[array_rand($reactions)],
                    'is_favorite' => rand(1, 100) <= 20,
                    'created_at' => $interactionDate,
                    'updated_at' => $interactionDate,
                ];

                $interactionCount++;
                $this->flushBatchIfNeeded('articleInteraction');
            }
        }

        // Flush remaining interactions
        $this->flushBatch('articleInteraction');

        $this->command->info("    Generated {$interactionCount} historical interactions");
    }

    /**
     * Generate ArticleInteraction (reaction) for an article
     */
    private function generateArticleReaction(string $userId, string $articleId): void
    {
        $reactions = ['like', 'love', 'celebrate', 'insightful', 'laugh', 'surprised', 'sad', 'dislike'];
        $reaction = $reactions[array_rand($reactions)];

        // Create a unique key for this interaction
        $interactionKey = $userId.'_'.$articleId;

        // Track interactions to avoid duplicates in batch
        static $processedInteractions = [];

        // Skip if we already have this interaction in our batch
        if (isset($processedInteractions[$interactionKey])) {
            return;
        }

        // Mark as processed
        $processedInteractions[$interactionKey] = true;

        // Get article translations if available
        $article = Article::find($articleId);
        $articleTranslationId = null;
        if ($article && $article->translations->isNotEmpty()) {
            $articleTranslationId = $article->translations->random()->id;
        }

        // Generate random date within last 12 months with more recent dates being more likely
        $daysAgo = $this->getExponentialRandomDays(365);
        $interactionDate = Carbon::now()->subDays($daysAgo)
            ->setTime(rand(8, 18), rand(0, 59), rand(0, 59));

        // Simply add to batch without checking database
        // The database constraint will handle duplicates
        $this->articleInteractionBatch[] = [
            'id' => Uuid::uuid4()->toString(),
            'user_id' => $userId,
            'article_id' => $articleId,
            'article_translation_id' => $articleTranslationId,
            'reaction' => $reaction,
            'is_favorite' => rand(1, 100) <= 20,
            'created_at' => $interactionDate,
            'updated_at' => $interactionDate,
        ];
        $this->flushBatchIfNeeded('articleInteraction');
    }

    /**
     * Generate exponentially distributed random days (more recent dates are more likely)
     */
    private function getExponentialRandomDays(int $maxDays): int
    {
        // Generate a random float between 0 and 1
        $random = mt_rand() / mt_getrandmax();

        // Apply exponential distribution (lambda = 2 gives good recent bias)
        $lambda = 2;
        $exponential = -log(1 - $random) / $lambda;

        // Scale to max days and ensure within bounds
        return (int) min($exponential * $maxDays / 3, $maxDays);
    }

    /**
     * Generate LinkClicked events for a session
     */
    private function generateLinkClickedEvents(
        string $userId,
        ?string $sessionId,
        Carbon $sessionStart,
        Carbon $sessionEnd,
        string $financerId
    ): void {
        $sessionDuration = $sessionStart->diffInMinutes($sessionEnd);

        // 40% chance to click on shortcuts in a session
        if (rand(1, 100) > 40) {
            return;
        }

        // Use cached links for better performance
        if (! $this->cachedLinks instanceof Collection || $this->cachedLinks->isEmpty()) {
            $this->cachedLinks = Link::where('financer_id', $financerId)->get();
        }
        $links = $this->cachedLinks;

        if ($links->isEmpty()) {
            // Create default links in batch if none exist
            $defaultLinks = [
                ['name' => 'Benefits Portal', 'url' => 'https://example.com/benefits'],
                ['name' => 'Payslip Access', 'url' => 'https://example.com/payslip'],
                ['name' => 'HR Tools', 'url' => 'https://example.com/hr-tools'],
                ['name' => 'Savings Plan', 'url' => 'https://example.com/savings'],
                ['name' => 'Employee Profile', 'url' => 'https://example.com/profile'],
                ['name' => 'Help Center', 'url' => 'https://example.com/help'],
            ];

            $linkBatch = [];
            foreach ($defaultLinks as $index => $linkData) {
                $linkBatch[] = [
                    'id' => Uuid::uuid4()->toString(),
                    'financer_id' => $financerId,
                    'name' => json_encode(['en' => $linkData['name'], 'fr' => $linkData['name']]),
                    'url' => json_encode(['en' => $linkData['url'], 'fr' => $linkData['url']]),
                    'position' => $index + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('int_outils_rh_links')->insert($linkBatch);
            $this->cachedLinks = Link::where('financer_id', $financerId)->get();
            $links = $this->cachedLinks;
        }

        // Generate 1-3 link clicks per session
        $clickCount = rand(1, 3);

        for ($i = 0; $i < $clickCount; $i++) {
            // Random time during the session
            $clickTime = $sessionStart->copy()->addMinutes(rand(1, max(1, (int) $sessionDuration)));

            // Select a random link
            $link = $links->random();

            $this->engagementLogBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'user_id' => $userId,
                'type' => 'LinkClicked',
                'target' => $link->id,
                'logged_at' => $clickTime,
                'created_at' => $clickTime,
                'updated_at' => $clickTime,
                'metadata' => json_encode([
                    'session_id' => $sessionId,
                    'financer_id' => $financerId,
                    'link_name' => $link->name,
                    'url' => $link->url,
                    'referrer' => 'dashboard',
                    'user_agent' => $this->getRandomUserAgent(),
                ]),
            ];
            $this->flushBatchIfNeeded('engagementLog');
        }
    }

    /**
     * Generate metrics for different periods
     */
    private function generatePeriodMetrics(Financer $financer, Carbon $referenceDate, int $totalUsers, int $totalInvited): void
    {
        // Périodes à générer
        $periods = [
            MetricPeriod::SEVEN_DAYS,
            MetricPeriod::THIRTY_DAYS,
            MetricPeriod::THREE_MONTHS,
        ];

        foreach ($periods as $period) {
            $dateRange = MetricPeriod::getDateRange($period, $referenceDate);

            // Active beneficiaries
            $activeRate = 0.7 + (rand(-10, 10) / 100);
            $activeBeneficiaries = (int) ($totalUsers * $activeRate);

            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_active_beneficiaries',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'total' => $activeBeneficiaries,
                    'new' => rand(5, 50),
                    'returning' => $activeBeneficiaries - rand(5, 50),
                    'growth_rate' => rand(-5, 15) / 100,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Activation rate
            $activationRate = round(($totalUsers / $totalInvited) * 100, 1);
            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_activation_rate',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'rate' => $activationRate,
                    'total_users' => $totalInvited,
                    'activated_users' => $totalUsers,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Session time
            $sessionTime = rand(10, 25);
            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_session_time',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'median_minutes' => $sessionTime,
                    'average_minutes' => $sessionTime + rand(-2, 3),
                    'total_sessions' => $activeBeneficiaries * rand(20, 100),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Module usage
            $modules = [
                'vouchers' => rand(30, 60),
                'hr_tools' => rand(20, 40),
                'internal_communication' => rand(40, 70),
                'benefits' => rand(15, 35),
                'surveys' => rand(10, 25),
            ];

            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_module_usage',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'modules' => array_map(function (int $percentage) use ($activeBeneficiaries): array {
                        return [
                            'unique_users' => (int) ($activeBeneficiaries * $percentage / 100),
                            'total_uses' => (int) ($activeBeneficiaries * $percentage / 100 * rand(10, 50)),
                            'percentage' => $percentage,
                        ];
                    }, $modules),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // HR Communications
            $articleViews = rand(500, 2000);
            $toolClicks = rand(200, 800);

            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_article_viewed',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'articles' => [
                        'views' => $articleViews,
                        'unique_users' => (int) ($articleViews * 0.7),
                        'likes' => (int) ($articleViews * 0.15),
                    ],
                    'tools' => [
                        'clicks' => $toolClicks,
                        'unique_users' => (int) ($toolClicks * 0.8),
                    ],
                    'total_interactions' => $articleViews + $toolClicks,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Voucher purchases
            $purchaseCount = rand(50, 300);
            $avgAmount = rand(20, 100);

            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_voucher_purchases',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'total_amount' => $purchaseCount * $avgAmount,
                    'count' => $purchaseCount,
                    'average_amount' => $avgAmount,
                    'unique_buyers' => (int) ($purchaseCount * 0.8),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Bounce rate
            $bounceRate = rand(5, 20);

            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_bounce_rate',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'rate' => $bounceRate,
                    'total_sessions' => $activeBeneficiaries * rand(20, 100),
                    'bounced_sessions' => (int) ($activeBeneficiaries * rand(20, 100) * $bounceRate / 100),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Top modules
            $topModules = [
                ['name' => 'vouchers', 'usage_rate' => rand(40, 70)],
                ['name' => 'internal_communication', 'usage_rate' => rand(35, 65)],
                ['name' => 'hr_tools', 'usage_rate' => rand(25, 50)],
                ['name' => 'benefits', 'usage_rate' => rand(20, 40)],
                ['name' => 'surveys', 'usage_rate' => rand(15, 30)],
            ];

            $this->engagementMetricBatch[] = [
                'id' => Uuid::uuid4()->toString(),
                'date_from' => $dateRange['from']->toDateString(),
                'date_to' => $dateRange['to']->toDateString(),
                'metric' => 'financer_top_modules',
                'financer_id' => $financer->id,
                'period' => $period,
                'data' => json_encode([
                    'modules' => $topModules,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->flushBatchIfNeeded('engagementMetric');
        }

        // Flush remaining metrics
        $this->flushBatch('engagementMetric');
    }

    /**
     * Pre-generated user agents for performance
     */
    private array $userAgents = [];

    /**
     * Pre-generated IP addresses for performance
     */
    private array $ipAddresses = [];

    /**
     * Get a random user agent
     */
    private function getRandomUserAgent(): string
    {
        if ($this->userAgents === []) {
            // Pre-generate 100 user agents
            for ($i = 0; $i < 100; $i++) {
                $this->userAgents[] = fake()->userAgent();
            }
        }

        return $this->userAgents[array_rand($this->userAgents)];
    }

    /**
     * Get a random IP address
     */
    private function getRandomIpAddress(): string
    {
        if ($this->ipAddresses === []) {
            // Pre-generate 100 IP addresses
            for ($i = 0; $i < 100; $i++) {
                $this->ipAddresses[] = fake()->ipv4();
            }
        }

        return $this->ipAddresses[array_rand($this->ipAddresses)];
    }
}
