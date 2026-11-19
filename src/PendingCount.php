<?php

namespace Ernestdefoe\Gatehouse;

use Illuminate\Contracts\Cache\Repository;

/**
 * How many applicants are waiting. The forum payload carries it for everyone
 * who may decide, so it is cached rather than counted on each page load, and
 * forgotten whenever the queue changes: a hold, a decision, a deleted member.
 */
class PendingCount
{
    private const KEY = 'ernestdefoe-gatehouse.pending';

    public function __construct(private Repository $cache)
    {
    }

    public function get(): int
    {
        return (int) $this->cache->rememberForever(self::KEY, fn () => Applicant::query()->where('status', 'pending')->count());
    }

    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }
}
