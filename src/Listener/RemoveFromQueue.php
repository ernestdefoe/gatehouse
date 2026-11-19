<?php

namespace Ernestdefoe\Gatehouse\Listener;

use Ernestdefoe\Gatehouse\Applicant;
use Ernestdefoe\Gatehouse\PendingCount;
use Flarum\User\Event\Deleted;

/**
 * A deleted member leaves the queue. The key cascades on MySQL and PostgreSQL,
 * but SQLite does not enforce foreign keys, so the row is removed here too.
 */
class RemoveFromQueue
{
    public function __construct(private PendingCount $pending)
    {
    }

    public function handle(Deleted $event): void
    {
        Applicant::query()->where('user_id', $event->user->id)->delete();
        $this->pending->forget();
    }
}
