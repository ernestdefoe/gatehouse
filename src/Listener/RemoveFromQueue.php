<?php

namespace Ernestdefoe\Gatehouse\Listener;

use Ernestdefoe\Gatehouse\Applicant;
use Ernestdefoe\Gatehouse\Standing;
use Flarum\User\Event\Deleted;

/**
 * A deleted member leaves the queue. The foreign key already cascades; the row
 * is removed here too so nothing lingers on a database running with foreign
 * keys off, and the cached count and standing are forgotten.
 */
class RemoveFromQueue
{
    public function __construct(private Standing $standing)
    {
    }

    public function handle(Deleted $event): void
    {
        Applicant::query()->where('user_id', $event->user->id)->delete();
        $this->standing->changed($event->user->id);
    }
}
