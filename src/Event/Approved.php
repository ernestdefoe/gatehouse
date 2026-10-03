<?php

namespace Ernestdefoe\Gatehouse\Event;

use Flarum\User\User;

/**
 * An admin let a waiting applicant in. For extensions that act on a member
 * becoming a member — a welcome message, for instance — which would otherwise
 * fire at sign-up, while the applicant still cannot do anything.
 */
class Approved
{
    public function __construct(
        public readonly User $user,
        public readonly User $actor,
    ) {
    }
}
