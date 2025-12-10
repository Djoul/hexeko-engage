<?php

declare(strict_types=1);

namespace App\ValueObjects\Metrics;

use Carbon\Carbon;

final class CacheInfo
{
    public function __construct(
        public readonly string $cachedAt,
        public readonly int $ttlSeconds
    ) {}

    /**
     * @return array{cached_at: string, ttl_seconds: int}
     */
    public function toArray(): array
    {
        return [
            'cached_at' => $this->cachedAt,
            'ttl_seconds' => $this->ttlSeconds,
        ];
    }

    /**
     * @param  array{cached_at: string, ttl_seconds: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cachedAt: $data['cached_at'],
            ttlSeconds: $data['ttl_seconds']
        );
    }

    public static function createNow(int $ttlSeconds): self
    {
        return new self(
            cachedAt: Carbon::now()->toISOString() ?? Carbon::now()->toDateTimeString(),
            ttlSeconds: $ttlSeconds
        );
    }

    public function isExpired(): bool
    {
        $cachedTime = Carbon::parse($this->cachedAt);

        return $cachedTime->addSeconds($this->ttlSeconds)->isPast();
    }

    public function getExpirationTime(): Carbon
    {
        return Carbon::parse($this->cachedAt)->addSeconds($this->ttlSeconds);
    }

    public function getRemainingTtl(): int
    {
        $expiration = $this->getExpirationTime();

        if ($expiration->isPast()) {
            return 0;
        }

        return (int) Carbon::now()->diffInSeconds($expiration);
    }
}
