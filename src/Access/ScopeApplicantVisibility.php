<?php

namespace Ernestdefoe\Gatehouse\Access;

use Ernestdefoe\Gatehouse\Deciders;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/** Applicants are seen only by those who may approve and decline them. */
class ScopeApplicantVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if (! $actor->hasPermission(Deciders::PERMISSION)) {
            // An empty key list: Laravel compiles it to a condition that matches no row.
            $query->whereIn('user_id', []);
        }
    }
}
