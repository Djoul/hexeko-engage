<?php

declare(strict_types=1);

namespace App\DTOs\Financer;

class FinancerMetricsDTO
{
    public function __construct(
        public readonly string $financer_id,
        public readonly ?string $period = null,
        public readonly ?string $interval = null,
        public readonly ?int $total = null,
        public readonly ?float $rate = null,
        public readonly ?int $total_users = null,
        public readonly ?int $activated_users = null,
        public readonly ?int $median_minutes = null,
        public readonly ?int $total_sessions = null,
        public readonly ?int $total_interactions = null,
        /** @var ?array<string, mixed> */
        public readonly ?array $metrics = null,
        /** @var ?array<int, array{date: string, count: int}> */
        public readonly ?array $data = null,
        /** @var ?array<int, array{date: string, value: float}> */
        public readonly ?array $trend = null,
        /** @var ?array{views: int, unique_users: int} */
        public readonly ?array $articles = null,
        /** @var ?array{clicks: int, unique_users: int} */
        public readonly ?array $tools = null,
        /** @var ?array<int, array{name: string, unique_users: int, total_uses: int, trend: array<int, array{date: string, value: float}>}> */
        public readonly ?array $modules = null,
        /** @var ?array{cached_at: string, ttl_seconds: int} */
        public readonly ?array $cache_info = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            financer_id: is_scalar($data['financer_id']) ? (string) $data['financer_id'] : '',
            period: array_key_exists('period', $data) && $data['period'] !== null && is_scalar($data['period']) ? (string) $data['period'] : null,
            interval: array_key_exists('interval', $data) && $data['interval'] !== null && is_scalar($data['interval']) ? (string) $data['interval'] : null,
            total: array_key_exists('total', $data) && $data['total'] !== null && is_numeric($data['total']) ? (int) $data['total'] : null,
            rate: array_key_exists('rate', $data) && $data['rate'] !== null && is_numeric($data['rate']) ? (float) $data['rate'] : null,
            total_users: array_key_exists('total_users', $data) && $data['total_users'] !== null && is_numeric($data['total_users']) ? (int) $data['total_users'] : null,
            activated_users: array_key_exists('activated_users', $data) && $data['activated_users'] !== null && is_numeric($data['activated_users']) ? (int) $data['activated_users'] : null,
            median_minutes: array_key_exists('median_minutes', $data) && $data['median_minutes'] !== null && is_numeric($data['median_minutes']) ? (int) $data['median_minutes'] : null,
            total_sessions: array_key_exists('total_sessions', $data) && $data['total_sessions'] !== null && is_numeric($data['total_sessions']) ? (int) $data['total_sessions'] : null,
            total_interactions: array_key_exists('total_interactions', $data) && $data['total_interactions'] !== null && is_numeric($data['total_interactions']) ? (int) $data['total_interactions'] : null,
            metrics: array_key_exists('metrics', $data) && is_array($data['metrics']) ? self::castMetrics($data['metrics']) : null,
            data: array_key_exists('data', $data) && is_array($data['data']) ? self::castData($data['data']) : null,
            trend: array_key_exists('trend', $data) && is_array($data['trend']) ? self::castTrend($data['trend']) : null,
            articles: array_key_exists('articles', $data) && is_array($data['articles']) ? self::castArticles($data['articles']) : null,
            tools: array_key_exists('tools', $data) && is_array($data['tools']) ? self::castTools($data['tools']) : null,
            modules: array_key_exists('modules', $data) && is_array($data['modules']) ? self::castModules($data['modules']) : null,
            cache_info: array_key_exists('cache_info', $data) && is_array($data['cache_info']) ? self::castCacheInfo($data['cache_info']) : null,
        );
    }

    /**
     * @param  array<mixed, mixed>  $metrics
     * @return array<string, mixed>
     */
    private static function castMetrics(array $metrics): array
    {
        return $metrics;
    }

    /**
     * @param  array<mixed, mixed>  $data
     * @return array<int, array{date: string, count: int}>
     */
    private static function castData(array $data): array
    {
        return $data;
    }

    /**
     * @param  array<mixed, mixed>  $trend
     * @return array<int, array{date: string, value: float}>
     */
    private static function castTrend(array $trend): array
    {
        return $trend;
    }

    /**
     * @param  array<mixed, mixed>  $articles
     * @return array{views: int, unique_users: int}
     */
    private static function castArticles(array $articles): array
    {
        return $articles;
    }

    /**
     * @param  array<mixed, mixed>  $tools
     * @return array{clicks: int, unique_users: int}
     */
    private static function castTools(array $tools): array
    {
        return $tools;
    }

    /**
     * @param  array<mixed, mixed>  $modules
     * @return array<int, array{name: string, unique_users: int, total_uses: int, trend: array<int, array{date: string, value: float}>}>
     */
    private static function castModules(array $modules): array
    {
        return $modules;
    }

    /**
     * @param  array<mixed, mixed>  $cacheInfo
     * @return array{cached_at: string, ttl_seconds: int}
     */
    private static function castCacheInfo(array $cacheInfo): array
    {
        return $cacheInfo;
    }
}
