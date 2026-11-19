<?php

namespace Ernestdefoe\Gatehouse;

use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository;

/**
 * A member's standing in the queue: null for everybody Gatehouse never held,
 * otherwise pending, approved or declined.
 *
 * The permission lock asks on every request a signed-in member makes, so the
 * answer is cached per member. It changes only through Gatehouse itself (a
 * hold, a decision, a deleted member), and each of those calls changed().
 */
class Standing
{
    private const TTL = 86400;

    public function __construct(private Repository $cache, private PendingCount $pending)
    {
    }

    public function of(User $user): ?string
    {
        if ($user->isGuest()) {
            return null;
        }

        // An empty string for "never held": the cache treats a stored null as a miss.
        $status = $this->cache->remember(
            $this->key($user->id),
            self::TTL,
            fn () => (string) Applicant::query()->whereKey($user->id)->value('status')
        );

        return $status === '' ? null : $status;
    }

    public function locksOut(User $user): bool
    {
        return Applicant::locksOutStatus($this->of($user));
    }

    /** A member's place in the queue changed: forget their standing and the waiting count. */
    public function changed(int $userId): void
    {
        $this->cache->forget($this->key($userId));
        $this->pending->forget();
    }

    private function key(int $userId): string
    {
        return 'ernestdefoe-gatehouse.standing.'.$userId;
    }
}
